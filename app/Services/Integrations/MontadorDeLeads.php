<?php

namespace App\Services\Integrations;

use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Monta o que o admin pessoal precisa saber sobre um tenant.
 *
 * O telefone vem do emitente, e não do usuário: o cadastro pede um telefone da
 * empresa, que é com quem se fala.
 *
 * `Emitente` e `User` escopam suas próprias consultas pelo tenant do
 * container (`TenantAtual`), e não pelo tenant deste método. Por isso toda
 * leitura de `Emitente` e `User` aqui passa por `withoutGlobalScope('tenant')`,
 * com o `tenant_id` do parâmetro amarrado explicitamente na consulta: o
 * método fica correto mesmo que seja chamado antes do container terminar de
 * resolver o tenant recém-criado.
 */
class MontadorDeLeads
{
    /** @return array<string, mixed> */
    public function paraTenant(Tenant $tenant): array
    {
        $emitente = Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->first();

        $usuarios = User::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->get();

        $primeiro = $usuarios->first();

        // O último acesso do tenant é o mais recente entre as pessoas dele.
        $ultimoAcesso = $usuarios->max('ultimo_acesso_em');

        return [
            'tenant_id' => $tenant->getKey(),
            'cnpj' => (string) $emitente?->cnpj,
            'empresa' => $tenant->nome,
            'nome' => (string) $primeiro?->name,
            'email' => (string) $primeiro?->email,
            'telefone' => $emitente?->telefone,
            'plano' => $tenant->plano->value,
            // Respostas do modal do site; nulo pra quem se cadastrou direto pelo app.
            'perfil_cadastro' => $tenant->perfil_cadastro,
            'cadastrado_em' => $tenant->created_at?->toIso8601String(),
            'ultimo_acesso_em' => $ultimoAcesso ? Carbon::parse($ultimoAcesso)->toIso8601String() : null,
        ];
    }
}
