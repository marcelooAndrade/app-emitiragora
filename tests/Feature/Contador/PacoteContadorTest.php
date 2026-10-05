<?php

use App\Enums\Perfil;
use App\Enums\Transporte\CteStatus;
use App\Livewire\Contador\Exportacao;
use App\Models\User;
use App\Services\Export\PacoteContadorService;
use App\Services\Transporte\EmissaoViagem;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function conteudoDoZip(string $caminho): array
{
    $zip = new ZipArchive;
    $zip->open($caminho);
    $nomes = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nomes[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $nomes;
}

function lerDoZip(string $caminho, string $nome): string
{
    $zip = new ZipArchive;
    $zip->open($caminho);
    $conteudo = (string) $zip->getFromName($nome);
    $zip->close();

    return $conteudo;
}

beforeEach(function () {
    $this->viagem = viagemPronta();
    $this->emitente = $this->viagem->emitente;

    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);
    app(EmissaoViagem::class)->emitir($this->viagem);

    $this->cte = $this->viagem->ctes()->sole();
    $this->mdfe = $this->viagem->mdfe()->sole();
});

function pacoteDoMes($emitente): string
{
    return app(PacoteContadorService::class)->gerar($emitente, now()->startOfMonth(), now()->endOfMonth());
}

it('inclui o xml dos CT-e e do MDF-e autorizados no periodo', function () {
    $nomes = conteudoDoZip(pacoteDoMes($this->emitente));

    expect($nomes)->toContain("cte/{$this->cte->chave}.xml")
        ->toContain("mdfe/{$this->mdfe->chave}.xml");
});

it('separa os CT-e cancelados em pasta propria', function () {
    $this->cte->forceFill(['status' => CteStatus::Cancelado, 'protocolo_cancelamento' => '135260000888001'])->save();

    $caminho = pacoteDoMes($this->emitente);

    expect(conteudoDoZip($caminho))->toContain("cte-cancelados/{$this->cte->chave}.xml")
        ->not->toContain("cte/{$this->cte->chave}.xml")
        ->and(lerDoZip($caminho, 'resumo.csv'))->toContain('135260000888001');
});

it('resume os CT-e numa planilha, com o frete em reais', function () {
    $caminho = pacoteDoMes($this->emitente);

    $resumo = lerDoZip($caminho, 'resumo.csv');

    expect(conteudoDoZip($caminho))->toContain('resumo.csv')->toContain('LEIA-ME.txt')
        ->and($resumo)->toContain($this->cte->chave)
        ->and($resumo)->toContain('75,00')
        ->and(lerDoZip($caminho, 'LEIA-ME.txt'))->toContain('CT-e no período : 1');
});

it('ignora documento autorizado fora do periodo', function () {
    DB::table('ctes')->update(['autorizado_em' => now()->subMonthsNoOverflow(2)]);
    DB::table('mdfes')->update(['autorizado_em' => now()->subMonthsNoOverflow(2)]);

    $nomes = conteudoDoZip(pacoteDoMes($this->emitente));

    expect(collect($nomes)->filter(fn ($n) => str_starts_with($n, 'cte/') || str_starts_with($n, 'mdfe/')))->toBeEmpty();
});

it('recusa periodo invertido', function () {
    app(PacoteContadorService::class)->gerar($this->emitente, now(), now()->subDay());
})->throws(RuntimeException::class, 'invertido');

it('a tela mostra a previa do periodo antes de baixar', function () {
    $this->seed(PerfilSeeder::class);
    $user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $user->assignRole(Perfil::Contador->value);

    Livewire::actingAs($user)->test(Exportacao::class)
        ->set('de', now()->startOfMonth()->toDateString())
        ->set('ate', now()->endOfMonth()->toDateString())
        ->assertSee('CT-e autorizados')
        ->assertSee('MDF-e')
        ->assertSee('cte-cancelados/');
});
