<?php

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\ViagemStatus;
use App\Models\Cte;
use App\Models\RegraIcmsTransporte;
use App\Models\SefazLog;
use App\Models\Tenant;
use App\Models\Viagem;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransporteException;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->viagem = viagemPronta();
    $this->cte = $this->viagem->ctes->sole();
});

it('autoriza o CT-e, numera, calcula o ICMS e guarda o XML', function () {
    comGatewayCte(['enviar' => cteAutorizado('135260000123456')]);

    $cte = app(TransmissorCte::class)->transmitir($this->cte);

    expect($cte->status)->toBe(CteStatus::Autorizado)
        ->and($cte->numero)->toBe(1)
        ->and($cte->chave)->toHaveLength(44)
        ->and(substr($cte->chave, 20, 2))->toBe('57')
        ->and($cte->protocolo)->toBe('135260000123456')
        ->and($cte->valor_total_centavos)->toBe(7500)
        ->and($cte->icms_cst)->toBe('00')
        ->and($cte->icms_valor_centavos)->toBe(900)
        ->and($cte->cfop)->toBe('5353')
        ->and(Storage::disk('fiscal')->exists($cte->xml_autorizado_path))->toBeTrue()
        ->and($this->viagem->fresh()->status)->toBe(ViagemStatus::Pendente)
        ->and($this->viagem->fresh()->eventos->pluck('tipo'))->toContain('cte_autorizado');
});

it('rejeitado guarda o motivo e retransmite com o mesmo número', function () {
    comGatewayCte(['enviar' => [new RespostaSefaz('539', 'Rejeicao: Duplicidade com diferenca na chave'), cteAutorizado()]]);
    $transmissor = app(TransmissorCte::class);

    $rejeitado = $transmissor->transmitir($this->cte);
    expect($rejeitado->status)->toBe(CteStatus::Rejeitado)
        ->and($rejeitado->x_motivo)->toContain('539');

    $autorizado = $transmissor->transmitir($rejeitado);
    expect($autorizado->status)->toBe(CteStatus::Autorizado)
        ->and($autorizado->numero)->toBe($rejeitado->numero);
});

it('diante de falha de comunicação consulta pela chave antes de qualquer reenvio', function () {
    $fake = comGatewayCte(['enviar' => new RuntimeException('timeout'), 'consultar' => cteAutorizado()]);

    $cte = app(TransmissorCte::class)->transmitir($this->cte);

    expect($cte->status)->toBe(CteStatus::Autorizado)
        ->and($fake->chamadas)->toBe(['enviar', 'consultar'])
        ->and(SefazLog::where('operacao', 'cte-autorizacao')->whereNotNull('erro')->exists())->toBeTrue();
});

it('sem resposta nem na consulta fica em processamento, que é o estado honesto', function () {
    comGatewayCte(['enviar' => new RuntimeException('timeout'), 'consultar' => new RuntimeException('timeout')]);

    $cte = app(TransmissorCte::class)->transmitir($this->cte);

    expect($cte->status)->toBe(CteStatus::EmProcessamento)
        ->and($cte->x_motivo)->toContain('consulte de novo');
});

it('não transmite sem RNTRC da empresa', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    $this->viagem->emitente->configuracaoTransporte()->update(['rntrc' => null]);

    app(TransmissorCte::class)->transmitir($this->cte);
})->throws(TransporteException::class, 'RNTRC');

it('não transmite sem regra de ICMS para a rota quando não é Simples', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    RegraIcmsTransporte::query()->delete();

    app(TransmissorCte::class)->transmitir($this->cte);
})->throws(TransporteException::class, 'regra de ICMS para frete de SP para SP');

it('Simples Nacional sem regra sai com ICMSSN', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    RegraIcmsTransporte::query()->delete();
    $this->viagem->emitente->forceFill(['crt' => '1'])->save();

    $cte = app(TransmissorCte::class)->transmitir($this->cte->fresh());

    expect($cte->icms_cst)->toBe('SN')->and($cte->icms_valor_centavos)->toBe(0);
});

it('CT-e autorizado não aceita alteração de valor', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    $cte = app(TransmissorCte::class)->transmitir($this->cte);

    $cte->update(['valor_frete_centavos' => 1]);
})->throws(RuntimeException::class, 'CT-e autorizado não pode ser alterado');

it('não deixa outro cliente enxergar o CT-e', function () {
    $outroTenant = Tenant::create(['nome' => 'Outra', 'slug' => 'outra-'.uniqid()]);
    app(TenantAtual::class)->definir($outroTenant);

    expect(Cte::find($this->cte->id))->toBeNull()
        ->and(Viagem::find($this->viagem->id))->toBeNull();
});
