<?php

namespace App\Services\Transporte;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\ContratoFrete;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\Mdfe;
use App\Support\TemaMarca;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\MDFe\Damdfe;
use Throwable;

/**
 * DACTE e DAMDFE a partir do XML autorizado guardado, e o contrato do frete
 * com o terceiro. Veio do `FiscalAuxiliaryDocumentPdfService` do Transm.
 */
class DocumentosAuxiliares
{
    public function dacte(Cte $cte): string
    {
        if (! in_array($cte->status, [CteStatus::Autorizado, CteStatus::Cancelado], true) || blank($cte->xml_autorizado_path)) {
            throw new TransporteException('O DACTE sai depois que o CT-e é autorizado pela SEFAZ.');
        }

        return $this->gerar(fn (): string => (new Dacte(Storage::disk('fiscal')->get($cte->xml_autorizado_path)))->render($this->logo($cte->emitente)), 'DACTE');
    }

    public function damdfe(Mdfe $mdfe): string
    {
        if (! in_array($mdfe->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::Cancelado], true) || blank($mdfe->xml_autorizado_path)) {
            throw new TransporteException('O DAMDFE sai depois que o MDF-e é autorizado pela SEFAZ.');
        }

        return $this->gerar(fn (): string => (new Damdfe(Storage::disk('fiscal')->get($mdfe->xml_autorizado_path)))->render($this->logo($mdfe->emitente)), 'DAMDFE');
    }

    /**
     * Logo que o emitente subiu em Marca para os documentos impressos. Sem
     * logo, o DACTE e o DAMDFE saem só com os dados do emitente.
     */
    private function logo(?Emitente $emitente): string
    {
        $path = $emitente?->logo_path;

        if (blank($path) || ! Storage::disk('fiscal')->exists($path)) {
            return '';
        }

        return 'data://text/plain;base64,'.base64_encode((string) Storage::disk('fiscal')->get($path));
    }

    /** Contrato e recibo do adiantamento, nas cores da marca do cliente. */
    public function contrato(ContratoFrete $contrato): string
    {
        $contrato->loadMissing(['emitente.tenant', 'viagem.ctes', 'viagem.mdfe']);
        $emitente = $contrato->emitente;
        $tema = $emitente->tenant?->tema ?? [];

        return Pdf::loadView('transporte.pdf.contrato-frete', [
            'contrato' => $contrato,
            'viagem' => $contrato->viagem,
            'ctes' => $contrato->viagem->ctes->filter(fn (Cte $c): bool => $c->status === CteStatus::Autorizado)->values(),
            'emitente' => $emitente,
            'config' => $emitente->configuracaoTransporte(),
            'homologacao' => $emitente->ambiente === Ambiente::Homologacao,
            'primaria' => $tema['primaria'] ?? TemaMarca::PRIMARIA_PADRAO,
            'neutra' => $tema['neutra'] ?? TemaMarca::NEUTRA_PADRAO,
        ])->setPaper('a4')->output();
    }

    private function gerar(callable $render, string $documento): string
    {
        try {
            return $render();
        } catch (Throwable $e) {
            report($e);

            throw new TransporteException("Não foi possível gerar o {$documento} com o XML guardado. Baixe o XML e confira.");
        }
    }
}
