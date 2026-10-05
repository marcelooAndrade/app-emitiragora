<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Enums\Transporte\ViagemStatus;
use Illuminate\Support\Facades\Blade;

it('exibe a faixa de ambiente em homologacao', function () {
    $html = Blade::render('<x-ui.env-banner :ambiente="$a" />', ['a' => Ambiente::Homologacao]);

    expect($html)->toContain('homologa');
});

it('nao exibe faixa alguma em producao', function () {
    $html = Blade::render('<x-ui.env-banner :ambiente="$a" />', ['a' => Ambiente::Producao]);

    expect(trim($html))->toBe('');
});

it('usa tonalidade para status recuperavel e solido para terminal', function () {
    $rejeitado = Blade::render('<x-ui.badge-status :status="$s" />', ['s' => CteStatus::Rejeitado]);
    $cancelado = Blade::render('<x-ui.badge-status :status="$s" />', ['s' => CteStatus::Cancelado]);

    // Rejeitado é recuperável: fundo claro, texto escuro.
    expect($rejeitado)->toContain('bg-danger-100');
    // Cancelado é terminal: preenchimento sólido.
    expect($cancelado)->toContain('bg-graphite-800');
});

it('cobre todo status de CT-e, MDF-e e viagem sem cair no padrao', function () {
    foreach ([...CteStatus::cases(), ...MdfeStatus::cases(), ...ViagemStatus::cases()] as $status) {
        expect($status->classesBadge())->not->toBe('')
            ->and($status->rotulo())->not->toBe('');
    }
});

it('renderiza botao primario em grafite, nunca no vermelho da marca', function () {
    $html = Blade::render('<x-ui.button>Transmitir</x-ui.button>');

    expect($html)->toContain('bg-graphite-900')
        ->and($html)->not->toContain('bg-primary-600');
});

it('renderiza botao destrutivo na cor de perigo', function () {
    $html = Blade::render('<x-ui.button variant="destructive">Cancelar</x-ui.button>');

    expect($html)->toContain('bg-danger-600');
});
