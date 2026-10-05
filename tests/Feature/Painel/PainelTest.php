<?php

use App\Enums\Perfil;
use App\Enums\Transporte\CteStatus;
use App\Livewire\Painel\Inicio;
use App\Models\Cte;
use App\Models\EmitenteCertificado;
use App\Models\User;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Transporte\EmissaoViagem;
use App\Services\Transporte\TransmissorCte;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->viagem = viagemPronta();
    $this->emitente = $this->viagem->emitente;

    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

/** Emite CT-e e MDF-e da viagem com a SEFAZ falsa autorizando tudo. */
function emitirViagem($viagem): void
{
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);
    app(EmissaoViagem::class)->emitir($viagem);
}

/** Outro CT-e da mesma viagem, copiado do autorizado, com chave própria. */
function outroCte(Cte $base, array $atributos): Cte
{
    $copia = $base->replicate();
    $copia->forceFill(['chave' => substr_replace($base->chave, '9', 34, 1), 'numero' => $base->numero + 1, ...$atributos])->save();

    return $copia;
}

it('soma o frete dos CT-e autorizados no mes e nao conta o cancelado', function () {
    emitirViagem($this->viagem);
    outroCte($this->viagem->ctes()->sole(), ['status' => CteStatus::Cancelado, 'valor_total_centavos' => 999_900]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSet('mes.ctes', 1)
        ->assertSet('mes.frete_centavos', 7500)
        ->assertSet('mes.cancelados', 1);
});

it('ignora CT-e autorizado em mes anterior', function () {
    emitirViagem($this->viagem);
    outroCte($this->viagem->ctes()->sole(), [
        'valor_total_centavos' => 777_700,
        'autorizado_em' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSet('mes.ctes', 1)
        ->assertSet('mes.frete_centavos', 7500);
});

it('conta a viagem que ainda nao tem CT-e como em aberto', function () {
    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSet('mes.viagens_abertas', 1);
});

it('mostra CT-e rejeitado como pendencia, com o motivo da SEFAZ', function () {
    comGatewayCte(['enviar' => new RespostaSefaz('539', 'Rejeicao: Duplicidade com diferenca na chave')]);
    app(TransmissorCte::class)->transmitir($this->viagem->ctes->sole());

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Documentos que pararam no caminho')
        ->assertSee('Rejeitado')
        ->assertSee('Rejeicao: Duplicidade com diferenca na chave');
});

it('avisa que CT-e em processamento nao deve ser transmitido de novo', function () {
    $this->viagem->ctes->sole()->forceFill(['status' => CteStatus::EmProcessamento])->save();

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Em processamento')
        // O alerta que importa: transmitir de novo um CT-e em processamento duplica.
        ->assertSee('duplicaria');
});

it('avisa MDF-e em viagem ha mais de uma semana', function () {
    emitirViagem($this->viagem);
    DB::table('mdfes')->update(['autorizado_em' => now()->subDays(Inicio::DIAS_PARA_ENCERRAR_MDFE + 1)]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('MDF-e em viagem há mais de')
        ->assertSee('encerre');
});

it('nao avisa MDF-e que saiu esta semana', function () {
    emitirViagem($this->viagem);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertDontSee('MDF-e em viagem há mais de');
});

it('lista as ultimas viagens', function () {
    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Últimas viagens')
        ->assertSee($this->viagem->numeroFormatado())
        ->assertSee('JOAO DA SILVA');
});

it('avisa certificado proximo do vencimento', function () {
    EmitenteCertificado::query()->update(['valido_ate' => now()->addDays(9)]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('O certificado vence em 9 dia(s)');
});

it('avisa quando nao ha certificado cadastrado', function () {
    EmitenteCertificado::query()->delete();

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Nenhum certificado');
});

it('exige a permissao de relatorio', function () {
    $sem = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $sem->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($sem)->get('/dashboard')->assertForbidden();
});

it('o painel responde na rota do dashboard', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Painel');
});
