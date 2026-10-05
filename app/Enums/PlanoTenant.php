<?php

namespace App\Enums;

/**
 * Plano contratado pelo tenant.
 *
 * Desde 05/10/2026 o EmitirAgora é só de transportadora e tem um plano só,
 * o mesmo do site (site-emitiragora, página Planos). O enum continua para a
 * coluna `tenants.plano` ter um valor conhecido e para o código seguir
 * perguntando o que o plano permite, nunca qual plano é: se um segundo plano
 * voltar, é aqui que ele entra. Preço e texto de vitrine moram em
 * `config/planos.php`, com o `slug` igual ao valor do caso.
 */
enum PlanoTenant: string
{
    case Transporte = 'transporte';

    /**
     * Domínio próprio e marca na tela de login.
     *
     * São o mesmo benefício visto de dois lados: o cliente entra por um
     * endereço dele e encontra a marca dele já na porta. Era o que separava
     * o antigo plano gratuito do pago; o plano pago permite.
     */
    public function permiteMarcaPropria(): bool
    {
        return true;
    }

    public function precoCentavos(): int
    {
        return (int) collect(config('planos.planos'))->firstWhere('slug', $this->value)['precoCentavos'];
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Transporte => 'Transporte',
        };
    }
}
