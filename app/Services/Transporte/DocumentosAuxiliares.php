<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\Mdfe;
use Illuminate\Support\Facades\Storage;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\MDFe\Damdfe;
use Throwable;

/**
 * DACTE e DAMDFE a partir do XML autorizado guardado. Veio do
 * `FiscalAuxiliaryDocumentPdfService` do Transm.
 */
class DocumentosAuxiliares
{
    public function dacte(Cte $cte): string
    {
        if (! in_array($cte->status, [CteStatus::Autorizado, CteStatus::Cancelado], true) || blank($cte->xml_autorizado_path)) {
            throw new TransporteException('O DACTE sai depois que o CT-e é autorizado pela SEFAZ.');
        }

        return $this->gerar(fn (): string => (new Dacte(Storage::disk('fiscal')->get($cte->xml_autorizado_path)))->render(), 'DACTE');
    }

    public function damdfe(Mdfe $mdfe): string
    {
        if (! in_array($mdfe->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::Cancelado], true) || blank($mdfe->xml_autorizado_path)) {
            throw new TransporteException('O DAMDFE sai depois que o MDF-e é autorizado pela SEFAZ.');
        }

        return $this->gerar(fn (): string => (new Damdfe(Storage::disk('fiscal')->get($mdfe->xml_autorizado_path)))->render(), 'DAMDFE');
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
