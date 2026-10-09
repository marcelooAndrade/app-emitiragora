<?php

namespace App\Services\Transporte\Ciot;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Transporte\TransporteException;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * CIOT para todos (Res. ANTT 6.078/2026, DF-026): toda viagem tem CIOT antes
 * do MDF-e, com caminhão próprio ou de terceiro.
 *
 * Decide qual empresa chamar, trava a viagem para dois cliques não gerarem
 * dois CIOT, consulta antes de declarar de novo quando um pedido ficou sem
 * resposta, e grava tudo em `ciots`. CIOT já existente volta sempre à
 * empresa que o gerou, com as credenciais dela, mesmo depois de o emitente
 * trocar de empresa.
 */
class ServicoCiot
{
    private const MDFE_NA_SEFAZ = [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::EmProcessamento];

    public function __construct(
        private readonly ProvedoresCiot $provedores,
        private readonly MontadorOperacaoCiot $montador,
    ) {}

    /**
     * O que impede o CIOT desta viagem agora, em frases para a lista de
     * pendências. Vazio quando o CIOT já está registrado.
     *
     * @return array<int, string>
     */
    public function pendencias(Viagem $viagem): array
    {
        $viagem->loadMissing(['emitente', 'ciotVigente', 'ctes', 'notas', 'veiculo', 'reboque', 'reboque2', 'contrato']);
        if ($viagem->ciotVigente?->registrado()) {
            return [];
        }
        if ($this->deOutraTransportadora($viagem)) {
            return ['O veículo é de outra transportadora, e o CIOT é dela (Res. ANTT 6.078/2026). Informe o CIOT que ela gerou em "Informar CIOT gerado fora".'];
        }

        $config = $viagem->emitente->configuracaoCiot();
        if ($config->provedor === 'manual') {
            return [CiotManual::PEDIDO];
        }
        if (! $this->provedores->existe($config->provedor)) {
            return ['A empresa de CIOT configurada não está mais disponível. Escolha outra em Configurações, CIOT.'];
        }
        if ($viagem->emitente->ambiente === Ambiente::Producao && ! $this->provedores->liberadoEmProducao($config->provedor)) {
            return ['A empresa de CIOT escolhida ('.$this->provedores->nome($config->provedor).') ainda não foi testada em homologação neste sistema, e a produção fica bloqueada para ela. Informe o CIOT em "Informar CIOT gerado fora" ou escolha outra empresa em Configurações, CIOT.'];
        }

        $daEmpresa = $this->provedores->gateway($config->provedor)
            ->pendencias($viagem, $config->credenciais($config->provedor, $viagem->emitente->ambiente));

        return $daEmpresa !== [] ? $daEmpresa : $this->montador->pendencias($viagem);
    }

    /**
     * Garante o CIOT da viagem pela empresa do emitente: devolve o registrado,
     * consulta o que estava processando, ou declara. Devolve o CIOT registrado
     * ou processando; recusa e pendência saem como `TransporteException`.
     */
    public function garantir(Viagem $viagem, ?User $user = null): Ciot
    {
        return Cache::lock('transporte:ciot:viagem:'.$viagem->getKey(), 120)->block(5, function () use ($viagem, $user): Ciot {
            $viagem->refresh()->load(['emitente', 'ctes', 'notas', 'veiculo', 'reboque', 'reboque2', 'contrato', 'motorista', 'mdfe', 'ciotVigente']);
            $ciot = $viagem->ciotVigente;
            if ($ciot?->registrado()) {
                return $ciot;
            }
            if (in_array($viagem->mdfe?->status, self::MDFE_NA_SEFAZ, true)) {
                throw new TransporteException('O MDF-e desta viagem já foi para a SEFAZ: o CIOT precisa sair antes dele.');
            }
            // O valor do frete e o contratante saem dos CT-e: CT-e ainda
            // mudando daria um CIOT com dado divergente da contratação.
            $validos = $viagem->ctes->reject(fn ($c): bool => $c->status === CteStatus::Cancelado);
            if ($validos->isEmpty() || $validos->contains(fn ($c): bool => $c->status !== CteStatus::Autorizado)) {
                throw new TransporteException('Os CT-e da viagem precisam estar autorizados antes do CIOT.');
            }

            // Processando fica com a empresa que recebeu o pedido. Rascunho
            // (uma tentativa que parou no meio) recomeça pela empresa atual.
            if ($ciot === null || $ciot->situacao === 'rascunho') {
                if ($pendencias = $this->pendencias($viagem)) {
                    throw new TransporteException(implode(' ', $pendencias));
                }
                $config = $viagem->emitente->configuracaoCiot();
                $ciot ??= new Ciot(['viagem_id' => $viagem->getKey(), 'emitente_id' => $viagem->emitente_id]);
                $ciot->forceFill([
                    'provedor' => $config->provedor,
                    'ambiente' => $viagem->emitente->ambiente,
                    'situacao' => 'rascunho',
                    'origem' => 'provedor',
                    'user_id' => $user?->getKey(),
                ])->save();
            }

            $gateway = $this->provedores->gateway($ciot->provedor);
            $operacao = $this->montador->montar($viagem);
            $credenciais = $this->credenciais($ciot, $viagem->emitente);
            try {
                $resposta = $ciot->situacao === 'processando'
                    ? $gateway->consultar($ciot, $operacao, $credenciais)
                    : $gateway->declarar($ciot, $operacao, $credenciais);
            } catch (TransporteException $e) {
                if ($ciot->situacao === 'rascunho') {
                    $this->aplicar($ciot, $viagem, new RespostaCiot('recusado', mensagem: $e->getMessage()), $user);
                }

                throw $e;
            }

            $this->aplicar($ciot, $viagem, $resposta, $user);
            if ($resposta->situacao === 'recusado') {
                throw new TransporteException($this->mensagemDeRecusa($ciot, $operacao));
            }

            return $ciot->fresh();
        });
    }

    /**
     * CIOT gerado fora (programa da ANTT, portal da empresa de pagamento, ou a
     * outra transportadora quando o veículo é dela). Aceita "123456789012" ou
     * com o verificador, "123456789012/1234", com qualquer pontuação.
     */
    public function informar(Viagem $viagem, string $codigo, ?string $responsavel = null, ?User $user = null): Ciot
    {
        $digitos = (string) preg_replace('/\D/', '', $codigo);
        if (! in_array(strlen($digitos), [12, 16], true)) {
            throw new TransporteException('O CIOT tem 12 dígitos, ou 16 com o código verificador.');
        }
        $responsavel = (string) preg_replace('/\D/', '', (string) $responsavel);
        if ($responsavel !== '' && ! in_array(strlen($responsavel), [11, 14], true)) {
            throw new TransporteException('O CPF ou CNPJ de quem gerou o CIOT tem 11 ou 14 dígitos.');
        }

        return Cache::lock('transporte:ciot:viagem:'.$viagem->getKey(), 120)->block(5, function () use ($viagem, $digitos, $responsavel, $user): Ciot {
            $viagem->refresh()->load(['emitente', 'mdfe', 'ciotVigente', 'veiculo']);
            if (in_array($viagem->mdfe?->status, self::MDFE_NA_SEFAZ, true)) {
                throw new TransporteException('O MDF-e desta viagem já foi para a SEFAZ com o CIOT. Para trocar, cancele o MDF-e.');
            }
            $atual = $viagem->ciotVigente;
            if ($atual?->registrado()) {
                throw new TransporteException("A viagem já tem o CIOT {$atual->numeroCompleto()}. Cancele esse antes de informar outro.");
            }
            $atual?->forceFill(['situacao' => 'cancelado', 'cancelado_em' => now(), 'motivo_cancelamento' => 'Substituído por CIOT informado à mão.'])->save();

            $padrao = $this->deOutraTransportadora($viagem)
                ? (string) preg_replace('/\D/', '', (string) $viagem->veiculo->proprietario_documento)
                : (string) $viagem->emitente->cnpj;
            $ciot = (new Ciot)->forceFill([
                'emitente_id' => $viagem->emitente_id,
                'viagem_id' => $viagem->getKey(),
                'provedor' => 'manual',
                'ambiente' => $viagem->emitente->ambiente,
                'situacao' => 'registrado',
                'origem' => 'digitado',
                'numero' => substr($digitos, 0, 12),
                'verificador' => strlen($digitos) === 16 ? substr($digitos, 12) : null,
                'responsavel_documento' => $responsavel !== '' ? $responsavel : $padrao,
                'declarado_em' => now(),
                'user_id' => $user?->getKey(),
            ]);
            $ciot->save();
            $viagem->registrar('ciot_informado', "CIOT {$ciot->numeroCompleto()} informado à mão.", ['ciot_id' => $ciot->getKey()], $user?->getKey());

            return $ciot;
        });
    }

    /** A ANTT só aceita cancelar antes de a viagem acontecer. */
    public function cancelar(Ciot $ciot, string $motivo, ?User $user = null): Ciot
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 15 || mb_strlen($motivo) > 500) {
            throw new TransporteException('Explique o cancelamento do CIOT em 15 a 500 caracteres.');
        }
        $ciot->loadMissing(['viagem.mdfe', 'emitente']);
        if (! in_array($ciot->situacao, ['rascunho', 'processando', 'registrado'], true)) {
            throw new TransporteException('Este CIOT não está aberto para cancelar.');
        }
        if (in_array($ciot->viagem->mdfe?->status, self::MDFE_NA_SEFAZ, true)) {
            throw new TransporteException('O CIOT está no MDF-e desta viagem. Cancele o MDF-e antes.');
        }

        $resposta = $ciot->digitado() || $ciot->situacao !== 'registrado'
            ? new RespostaCiot('cancelado', mensagem: $ciot->digitado() ? 'Cancele também onde o CIOT foi gerado.' : null)
            : $this->provedores->gateway($ciot->provedor)->cancelar($ciot, $motivo, $this->credenciais($ciot, $ciot->emitente));
        $ciot->forceFill(['situacao' => 'cancelado', 'cancelado_em' => now(), 'motivo_cancelamento' => $motivo])->save();
        $ciot->viagem->registrar('ciot_cancelado', trim("CIOT {$ciot->numeroCompleto()} cancelado: {$motivo} ".(string) $resposta->mensagem), ['ciot_id' => $ciot->getKey()], $user?->getKey());

        return $ciot;
    }

    public function encerrar(Ciot $ciot, ?User $user = null): Ciot
    {
        if ($ciot->situacao === 'encerrado') {
            return $ciot;
        }
        if ($ciot->situacao !== 'registrado') {
            throw new TransporteException('Só CIOT registrado pode ser encerrado.');
        }
        $ciot->loadMissing(['viagem', 'emitente']);
        if (! $ciot->digitado()) {
            $this->provedores->gateway($ciot->provedor)->encerrar($ciot, $this->credenciais($ciot, $ciot->emitente));
        }
        $ciot->forceFill(['situacao' => 'encerrado', 'encerrado_em' => now()])->save();
        $ciot->viagem->registrar('ciot_encerrado', $ciot->digitado()
            ? "CIOT {$ciot->numeroCompleto()} encerrado aqui. Encerre também onde ele foi gerado."
            : "CIOT {$ciot->numeroCompleto()} encerrado.", ['ciot_id' => $ciot->getKey()], $user?->getKey());

        return $ciot;
    }

    /**
     * Fim da viagem: o MDF-e encerrado leva o CIOT junto. Se a empresa do
     * CIOT falhar, o MDF-e continua encerrado e a viagem mostra a falha para
     * tentar de novo.
     */
    public function encerrarDepoisDoMdfe(Viagem $viagem, ?User $user = null): void
    {
        $ciot = $viagem->ciotVigente()->first();
        if ($ciot === null || $ciot->situacao !== 'registrado') {
            return;
        }
        try {
            $this->encerrar($ciot, $user);
        } catch (Throwable $e) {
            report($e);
            $viagem->registrar('ciot_encerramento_falhou', "O MDF-e foi encerrado, mas o CIOT {$ciot->numeroCompleto()} não: {$e->getMessage()} Tente de novo na viagem.", ['ciot_id' => $ciot->getKey()], $user?->getKey());
        }
    }

    /**
     * Sem CT-e autorizado não há operação: o CIOT aberto cai junto. Falha da
     * empresa não desfaz o cancelamento do CT-e; fica registrada na viagem.
     */
    public function cancelarSeSemCte(Viagem $viagem, ?User $user = null): void
    {
        $viagem->loadMissing('ctes');
        if ($viagem->ctes->contains(fn ($c): bool => $c->status === CteStatus::Autorizado)) {
            return;
        }
        $ciot = $viagem->ciotVigente()->first();
        if ($ciot === null || ! in_array($ciot->situacao, ['rascunho', 'processando', 'registrado'], true)) {
            return;
        }
        try {
            $this->cancelar($ciot, 'CT-e da viagem cancelados.', $user);
        } catch (Throwable $e) {
            report($e);
            $viagem->registrar('ciot_cancelamento_falhou', "Os CT-e foram cancelados, mas o CIOT {$ciot->numeroCompleto()} não: {$e->getMessage()}", ['ciot_id' => $ciot->getKey()], $user?->getKey());
        }
    }

    /** Para o botão "Testar conexão" da página CIOT. */
    public function testarConexao(Emitente $emitente): string
    {
        $config = $emitente->configuracaoCiot();
        $resposta = $this->provedores->gateway($config->provedor)
            ->testarConexao($emitente, $config->credenciais($config->provedor, $emitente->ambiente));

        return (string) $resposta->mensagem;
    }

    private function aplicar(Ciot $ciot, Viagem $viagem, RespostaCiot $r, ?User $user): void
    {
        $anterior = $ciot->situacao;
        $ciot->forceFill(array_filter([
            'situacao' => $r->situacao,
            'numero' => $r->numero,
            'verificador' => $r->verificador,
            'protocolo' => $r->protocolo,
            'codigo_retorno' => $r->codigo,
            'mensagem' => $r->mensagem,
            'aviso_transportador' => $r->aviso,
            'resposta' => $r->resposta !== [] ? $r->resposta : null,
            'responsavel_documento' => $r->responsavel ?? ($r->situacao === 'registrado' ? $viagem->emitente->cnpj : null),
            'declarado_em' => $r->situacao === 'registrado' ? now() : null,
        ], fn (mixed $v): bool => $v !== null))->save();

        if ($r->pdf !== null && filled($ciot->numero)) {
            $caminho = "transporte/{$ciot->emitente_id}/ciot/{$ciot->numero}.pdf";
            Storage::disk('fiscal')->put($caminho, $r->pdf);
            $ciot->forceFill(['pdf_path' => $caminho])->save();
        }

        $nome = $this->provedores->nome($ciot->provedor);
        $dados = ['ciot_id' => $ciot->getKey()];
        match (true) {
            $r->situacao === 'registrado' => $viagem->registrar('ciot_registrado', "CIOT {$ciot->numeroCompleto()} gerado via {$nome}.", $dados, $user?->getKey()),
            $r->situacao === 'processando' && $anterior !== 'processando' => $viagem->registrar('ciot_processando', "{$nome} aceitou o pedido do CIOT e ainda não devolveu o número.", $dados, $user?->getKey()),
            $r->situacao === 'recusado' => $viagem->registrar('ciot_recusado', trim('CIOT recusado: '.($r->codigo ? "{$r->codigo} " : '').$r->mensagem), $dados, $user?->getKey()),
            default => null,
        };
    }

    /** As duas recusas que mais pegam a transportadora pequena, ditas do jeito dela. */
    private function mensagemDeRecusa(Ciot $ciot, OperacaoCiot $operacao): string
    {
        return match ((string) $ciot->codigo_retorno) {
            '291' => 'A ANTT recusou o CIOT: o frete de R$ '.Dinheiro::formatar($operacao->valorFreteCentavos).' ficou abaixo do piso mínimo para esta operação. Revise o valor do frete.',
            '292' => "A ANTT recusou o CIOT: a distância de {$operacao->distanciaKm} km não bate com a origem e o destino. Revise a distância em Dados do CIOT.",
            default => trim('CIOT recusado: '.($ciot->codigo_retorno ? "{$ciot->codigo_retorno} " : '').$ciot->mensagem),
        };
    }

    /** @return array<string, mixed> */
    private function credenciais(Ciot $ciot, Emitente $emitente): array
    {
        return $emitente->configuracaoCiot()->credenciais($ciot->provedor, $ciot->ambiente);
    }

    /** Veículo de outra transportadora (não TAC): o CIOT é dela. */
    private function deOutraTransportadora(Viagem $viagem): bool
    {
        return (bool) $viagem->veiculo?->deTerceiro() && $viagem->veiculo->proprietario_tp === '2';
    }
}
