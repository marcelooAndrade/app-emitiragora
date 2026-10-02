<?php

namespace App\Services\Transporte;

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Enums\Transporte\CteStatus;
use App\Models\Cte;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Viagem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fatura a viagem: uma fatura por tomador, com os CT-e autorizados dele.
 *
 * É o "faturamento" do Transm, que lá gerava conta a receber e boleto do C6
 * a partir dos CT-e. Aqui vira `Fatura` do financeiro do EmitirAgora, com a
 * cobrança Pix que ele já gera, e o cliente nasce sozinho a partir do CT-e
 * quando ainda não existe.
 */
class FaturamentoViagem
{
    /** @return Collection<int, Fatura> */
    public function faturar(Viagem $viagem, ?string $vencimento = null, ?User $user = null): Collection
    {
        return DB::transaction(function () use ($viagem, $vencimento, $user): Collection {
            $viagem->load(['ctes', 'emitente']);
            $pendentes = $viagem->ctes->filter(fn (Cte $c): bool => $c->status === CteStatus::Autorizado && $c->fatura_id === null);
            if ($pendentes->isEmpty()) {
                throw new TransporteException('Não há CT-e autorizado sem fatura nesta viagem.');
            }

            $config = $viagem->emitente->configuracaoTransporte();
            $vence = $vencimento ? Carbon::parse($vencimento) : today()->addDays($config->prazo_fatura_dias);
            $faturas = collect();

            foreach ($pendentes->groupBy(fn (Cte $c): string => (string) ($c->tomador()['documento'] ?? '')) as $ctes) {
                $tomador = $this->cliente($viagem, $ctes->first()->tomador());
                $total = (int) $ctes->sum('valor_total_centavos');
                $numeros = $ctes->map(fn (Cte $c): string => (string) $c->numero)->join(', ');

                $fatura = Fatura::create([
                    'emitente_id' => $viagem->emitente_id,
                    'pessoa_id' => $tomador->getKey(),
                    'titulo' => mb_substr("Frete da viagem {$viagem->numeroFormatado()} · CT-e {$numeros}", 0, 160),
                    'observacoes' => 'Prestação de serviço de transporte. CT-e: '.$ctes->map->numeroFormatado()->join(', ').'.',
                ]);
                FaturaParcela::create([
                    'fatura_id' => $fatura->getKey(),
                    'numero' => 1,
                    'descricao' => mb_substr("Frete CT-e {$numeros}", 0, 160),
                    'valor_centavos' => $total,
                    'vencimento' => $vence->toDateString(),
                ])->setRelation('fatura', $fatura)->gerarCobrancaPix();

                Cte::query()->whereIn('id', $ctes->pluck('id'))->update([
                    'fatura_id' => $fatura->getKey(),
                    'tomador_pessoa_id' => $tomador->getKey(),
                ]);
                $viagem->registrar('viagem_faturada', "Fatura #{$fatura->id} para {$tomador->razao_social}: CT-e {$numeros}.", ['fatura_id' => $fatura->getKey()], $user?->getKey());
                $faturas->push($fatura);
            }

            return $faturas;
        });
    }

    /** O tomador como cliente do financeiro: acha pelo documento ou cadastra. */
    private function cliente(Viagem $viagem, array $p): Pessoa
    {
        $documento = preg_replace('/\D/', '', (string) ($p['documento'] ?? ''));
        $existente = Pessoa::query()
            ->where('emitente_id', $viagem->emitente_id)
            ->where('documento', $documento)
            ->first();
        if ($existente !== null) {
            if (! $existente->e_cliente) {
                $existente->update(['e_cliente' => true]);
            }

            return $existente;
        }

        $ie = trim((string) ($p['ie'] ?? ''));

        return Pessoa::create([
            'emitente_id' => $viagem->emitente_id,
            'tipo_pessoa' => strlen($documento) === 11 ? TipoPessoa::Fisica : TipoPessoa::Juridica,
            'documento' => $documento,
            'razao_social' => mb_substr((string) ($p['nome'] ?? $documento), 0, 255),
            'nome_fantasia' => $p['fantasia'] ?? null,
            'ind_ie_dest' => $ie !== '' ? IndIEDest::Contribuinte : (($p['ind_ie'] ?? null) === '2' ? IndIEDest::Isento : IndIEDest::NaoContribuinte),
            'inscricao_estadual' => $ie !== '' ? $ie : null,
            'consumidor_final' => $ie === '',
            'logradouro' => (string) ($p['logradouro'] ?? ''),
            'numero' => (string) ($p['numero'] ?: 'S/N'),
            'complemento' => $p['complemento'] ?? null,
            'bairro' => (string) ($p['bairro'] ?? ''),
            'codigo_municipio' => (string) ($p['municipio_codigo'] ?? ''),
            'municipio' => (string) ($p['municipio'] ?? ''),
            'uf' => (string) ($p['uf'] ?? ''),
            'cep' => (string) ($p['cep'] ?? ''),
            'telefone' => $p['telefone'] ?? null,
            'email' => $p['email'] ?? null,
            'e_cliente' => true,
        ]);
    }
}
