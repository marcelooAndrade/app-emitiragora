<?php

namespace App\Enums;

/**
 * Plano contratado pelo tenant.
 *
 * Os mesmos dois planos do site (site-emitiragora, página Planos): Transporte,
 * para quem opera frota, e Pequena Empresa, para quem só compra, vende e
 * emite nota. O código nunca pergunta "qual plano é": pergunta o que o plano
 * permite. Assim renomear o plano, que é decisão de negócio, não obriga a
 * caçar comparação espalhada. Preço e texto de vitrine moram em
 * `config/planos.php`, com o `slug` igual ao valor do caso.
 */
enum PlanoTenant: string
{
    case Transporte = 'transporte';
    case PequenaEmpresa = 'pequena_empresa';

    /**
     * Plano sugerido pelas respostas do cadastro.
     *
     * Só quem disse que não é transportadora vai para o Pequena Empresa.
     * Sem resposta (cadastro direto pelo app), o plano é o Transporte, que é
     * o foco do produto e o que mostra tudo.
     *
     * @param  array<string, string>|null  $perfil  ver PerfilDeCadastro
     */
    public static function paraPerfil(?array $perfil): self
    {
        $tipo = $perfil['tipo'] ?? null;

        return $tipo === null || $tipo === 'transportadora'
            ? self::Transporte
            : self::PequenaEmpresa;
    }

    /** CT-e, MDF-e, CIOT, averbação, contrato do motorista e viagens. */
    public function permiteTransporte(): bool
    {
        return $this === self::Transporte;
    }

    /**
     * Domínio próprio e marca na tela de login.
     *
     * São o mesmo benefício visto de dois lados: o cliente entra por um
     * endereço dele e encontra a marca dele já na porta. Era o que separava
     * o antigo plano gratuito do pago; com os dois planos pagos, os dois
     * permitem.
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
            self::PequenaEmpresa => 'Pequena Empresa',
        };
    }
}
