<?php

namespace App\Services\Assinatura;

use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Tenant;
use App\Support\Documento;
use Carbon\CarbonImmutable;

/**
 * Liga a cobrança à assinatura: cada parcela paga de uma fatura de
 * mensalidade libera mais um mês para a empresa cliente, e o estorno devolve
 * esse mês.
 *
 * A fatura é lançada pela empresa que cobra o EmitirAgora, no financeiro
 * dela, como qualquer outra. O cliente é achado pelo CNPJ do destinatário da
 * fatura, que é o mesmo CNPJ do emitente com que ele se cadastrou. Nenhuma
 * tela atravessa empresas: só este serviço, e só para gravar o `pago_ate`.
 */
class RenovacaoDeAssinatura
{
    public function aoReceber(FaturaParcela $parcela): void
    {
        $cliente = $this->clienteDa($parcela->fatura);

        if ($cliente === null) {
            return;
        }

        // Pagar adiantado soma ao que já estava pago, e pagar durante o teste
        // não come os dias de teste que sobravam. Quem pagou atrasado volta a
        // contar de hoje.
        // Comparado como dia de calendário em Brasília ("AAAA-MM-DD"), não como
        // instante: o `pago_ate` é só data, e o teste termina às 23h59 daqui.
        $base = collect([
            now('America/Sao_Paulo')->toDateString(),
            $cliente->pago_ate?->toDateString(),
            $cliente->teste_ate?->setTimezone('America/Sao_Paulo')->toDateString(),
        ])->filter()->max();

        $cliente->update(['pago_ate' => CarbonImmutable::parse($base)->addMonthNoOverflow()->toDateString()]);
    }

    public function aoEstornar(FaturaParcela $parcela): void
    {
        $cliente = $this->clienteDa($parcela->fatura);

        if ($cliente?->pago_ate === null) {
            return;
        }

        $cliente->update(['pago_ate' => $cliente->pago_ate->subMonthNoOverflow()->toDateString()]);
    }

    /** A empresa cliente da fatura, se ela for uma mensalidade cobrada pela empresa que cobra o EmitirAgora. */
    public function clienteDa(Fatura $fatura): ?Tenant
    {
        $emissor = Emitente::withoutGlobalScopes()->find($fatura->emitente_id);

        if (! $fatura->mensalidade || $emissor === null || ! self::emiteCobranca($emissor)) {
            return null;
        }

        $documento = $fatura->destinatario()->withoutGlobalScopes()->value('documento');

        return $documento === null ? null : self::contaDoCnpj((string) $documento, (int) $emissor->tenant_id);
    }

    /** A empresa é a que cobra o EmitirAgora dos clientes? */
    public static function emiteCobranca(?Emitente $emitente): bool
    {
        $cnpj = Documento::normalizarCnpj((string) config('planos.cobranca.cnpjEmissor'));

        return $emitente !== null && $cnpj !== '' && $emitente->cnpj === $cnpj;
    }

    /** A conta do EmitirAgora que usa este CNPJ, fora a da própria empresa que cobra. */
    public static function contaDoCnpj(string $cnpj, int $tenantQueCobra): ?Tenant
    {
        $tenantId = Emitente::withoutGlobalScopes()
            ->where('cnpj', Documento::normalizarCnpj($cnpj))
            ->where('tenant_id', '!=', $tenantQueCobra)
            ->value('tenant_id');

        return $tenantId === null ? null : Tenant::find($tenantId);
    }
}
