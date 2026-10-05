<?php

namespace App\Http\Controllers;

use App\Models\ContratoFrete;
use App\Models\Cte;
use App\Models\Mdfe;
use App\Services\Transporte\DocumentosAuxiliares;
use App\Services\Transporte\TransporteException;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DACTE, DAMDFE, XML autorizados e o contrato do frete. O escopo global
 * garante o tenant, e matriz e filial dividem o tenant, então
 * o documento ainda precisa ser do emitente em foco.
 */
class TransporteArquivoController extends Controller
{
    public function dacte(Cte $cte, DocumentosAuxiliares $documentos, EmitenteAtual $emitenteAtual): Response
    {
        $this->conferir($cte->emitente_id, $emitenteAtual);
        try {
            $pdf = $documentos->dacte($cte);
        } catch (TransporteException $e) {
            abort(404, $e->getMessage());
        }

        return $this->pdf($pdf, "dacte-{$cte->numero}.pdf");
    }

    public function contrato(ContratoFrete $contrato, DocumentosAuxiliares $documentos, EmitenteAtual $emitenteAtual): Response
    {
        $this->conferir($contrato->emitente_id, $emitenteAtual);

        return $this->pdf($documentos->contrato($contrato), "contrato-frete-{$contrato->numero}.pdf");
    }

    public function ciotPdf(ContratoFrete $contrato, EmitenteAtual $emitenteAtual): Response
    {
        $this->conferir($contrato->emitente_id, $emitenteAtual);
        abort_if(blank($contrato->ciot_pdf_path) || ! Storage::disk('fiscal')->exists($contrato->ciot_pdf_path), 404);

        return $this->pdf((string) Storage::disk('fiscal')->get($contrato->ciot_pdf_path), "ciot-{$contrato->ciot}.pdf");
    }

    public function cteXml(Cte $cte, EmitenteAtual $emitenteAtual): StreamedResponse
    {
        $this->conferir($cte->emitente_id, $emitenteAtual);

        return $this->xml($cte->xml_autorizado_path, "cte-{$cte->chave}.xml");
    }

    public function damdfe(Mdfe $mdfe, DocumentosAuxiliares $documentos, EmitenteAtual $emitenteAtual): Response
    {
        $this->conferir($mdfe->emitente_id, $emitenteAtual);
        try {
            $pdf = $documentos->damdfe($mdfe);
        } catch (TransporteException $e) {
            abort(404, $e->getMessage());
        }

        return $this->pdf($pdf, "damdfe-{$mdfe->numero}.pdf");
    }

    public function mdfeXml(Mdfe $mdfe, EmitenteAtual $emitenteAtual): StreamedResponse
    {
        $this->conferir($mdfe->emitente_id, $emitenteAtual);

        return $this->xml($mdfe->xml_autorizado_path, "mdfe-{$mdfe->chave}.xml");
    }

    private function conferir(int $emitenteId, EmitenteAtual $emitenteAtual): void
    {
        Gate::authorize('transporte.ver');
        abort_unless($emitenteId === (int) $emitenteAtual->resolver()?->getKey(), 404);
    }

    private function pdf(string $conteudo, string $nome): Response
    {
        return response($conteudo, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function xml(?string $caminho, string $nome): StreamedResponse
    {
        abort_if(blank($caminho) || ! Storage::disk('fiscal')->exists($caminho), 404);

        return Storage::disk('fiscal')->download($caminho, $nome, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
