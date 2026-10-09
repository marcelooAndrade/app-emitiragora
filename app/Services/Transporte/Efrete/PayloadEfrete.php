<?php

namespace App\Services\Transporte\Efrete;

use App\Enums\Transporte\CteStatus;
use App\Models\ContratoFrete;
use App\Models\Cte;
use App\Models\EmitenteTransporte;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\ViagemNota;
use App\Services\Transporte\Rateio;
use App\Services\Transporte\TransporteException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monta o que vai para o e-Frete a partir do contrato e da viagem.
 *
 * Portado do `EfreteCiotPayloadFactory` do app-transm. Lá a operação tinha
 * um formulário próprio (o "CIOT" do processo fiscal); aqui quase tudo sai
 * do que a viagem já tem: valores do contrato, CT-e autorizados, NF-e,
 * motorista e veículos. Distância, tipo de carga e previsão de entrega vêm
 * da viagem (CIOT para todos, 09/10/2026); a embalagem é ajuste do e-Frete
 * em Configurações, CIOT. Operação "Padrao", que é a viagem de lotação.
 */
class PayloadEfrete
{
    public const EMBALAGENS = [
        'Pallet' => 'Pallet', 'Caixa' => 'Caixa', 'Saco' => 'Saco', 'Bigbag' => 'Big bag', 'Granel' => 'Granel',
        'Fardo' => 'Fardo', 'Unitario' => 'Unitário', 'Container' => 'Contêiner', 'Tanque' => 'Tanque',
    ];

    /** Classificação da carga da ANTT, a mesma tabela do tpCarga do MDF-e. */
    public const TIPOS_CARGA = [
        1 => 'Granel sólido', 2 => 'Granel líquido', 3 => 'Frigorificada', 4 => 'Conteinerizada', 5 => 'Carga geral',
        6 => 'Neogranel', 7 => 'Perigosa (granel sólido)', 8 => 'Perigosa (granel líquido)', 9 => 'Perigosa (frigorificada)',
        10 => 'Perigosa (conteinerizada)', 11 => 'Perigosa (carga geral)', 12 => 'Granel pressurizada',
    ];

    public function operacao(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        $c = $this->contexto($contrato, $config);
        $emitente = $contrato->emitente;
        $ctes = $c['ctes'];
        $pesos = $ctes->map(fn (Cte $cte): float => max(0.001, (float) $cte->peso_kg))->all();
        $fretes = Rateio::dividir($contrato->frete_centavos, $pesos);
        $adiantamentos = Rateio::dividir($contrato->adiantamento_centavos, $pesos);
        $ncm = $this->naturezaCarga($c['notas']);
        $viagens = $ctes->values()->map(fn (Cte $cte, int $i): array => $this->viagem(
            $contrato, $cte, $ncm, $fretes[$i] ?? 0, $adiantamentos[$i] ?? 0,
        ));
        $destinatario = (array) $ctes->first()->destinatario;

        return [
            'TipoViagem' => 'Padrao',
            ...$this->autenticacao($config, $token, 8),
            'MatrizCNPJ' => $emitente->cnpj,
            'IdOperacaoCliente' => $this->idOperacao($contrato),
            'DataInicioViagem' => $c['inicio']->format('Y-m-d\TH:i:s'),
            'DataFimViagem' => $c['fim']->format('Y-m-d\TH:i:s'),
            'CodigoNCMNaturezaCarga' => $ncm,
            'PesoCarga' => round((float) $c['notas']->sum(fn (ViagemNota $n): float => (float) $n->peso_kg), 3),
            'TipoEmbalagem' => $config->efrete_embalagem,
            'Viagens' => $viagens->count() === 1 ? $viagens->first() : $viagens->all(),
            'Impostos' => [
                'IRRF' => $this->reais($contrato->imposto_renda_centavos),
                'SestSenat' => 0.0,
                'INSS' => 0.0,
                'ISSQN' => 0.0,
                'OutrosImpostos' => 0.0,
            ],
            'Pagamentos' => $this->pagamentos($contrato, $config, $c),
            'Contratado' => ['CpfOuCnpj' => $this->contratado($contrato, $config), 'RNTRC' => $this->rntrcContratado($contrato, $config)],
            'Motorista' => [
                'CpfOuCnpj' => $c['motorista']->cpf,
                'CNH' => $this->digitos($c['motorista']->cnh),
                'Celular' => $this->celular($c['motorista']->telefone),
            ],
            'Destinatario' => array_filter([
                'NomeOuRazaoSocial' => $destinatario['nome'] ?? null,
                'CpfOuCnpj' => $this->digitos($destinatario['documento'] ?? null),
                'Endereco' => $this->endereco($destinatario['bairro'] ?? null, $destinatario['cep'] ?? null, $destinatario['municipio_codigo'] ?? null,
                    $destinatario['logradouro'] ?? null, $destinatario['numero'] ?? null, $destinatario['complemento'] ?? null),
                'EMail' => $this->email($destinatario['email'] ?? null),
                'ResponsavelPeloPagamento' => false,
            ], fn (mixed $v): bool => $v !== null && $v !== ''),
            'Contratante' => array_filter([
                'RNTRC' => $this->rntrc($config->rntrc),
                'NomeOuRazaoSocial' => $emitente->razao_social,
                'CpfOuCnpj' => $emitente->cnpj,
                'ResponsavelPeloPagamento' => true,
                'Endereco' => $this->endereco($emitente->bairro, $emitente->cep, $emitente->codigo_municipio, $emitente->logradouro, $emitente->numero, $emitente->complemento),
                'EMail' => $this->email($emitente->email),
            ], fn (mixed $v): bool => $v !== null && $v !== ''),
            'Veiculos' => collect($c['veiculos'])->map(fn (Veiculo $v): array => ['Placa' => $this->placa($v, $config)])->all(),
            'CodigoTipoCarga' => (int) $contrato->viagem->tipo_carga,
            'AltoDesempenho' => false,
            'ComposicaoVeicular' => count($c['veiculos']) > 1,
            'RetornoVazio' => false,
            'TipoPagamento' => 'TransferenciaBancaria',
            'EntregaDocumentacao' => 'Cliente',
            'QuantidadeSaques' => 0,
            'QuantidadeTransferencias' => 0,
            'ValorSaques' => 0.0,
            'ValorTransferencias' => 0.0,
        ];
    }

    public function motorista(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        $m = $this->contexto($contrato, $config)['motorista'];

        return [
            ...$this->autenticacao($config, $token, 2),
            'CNH' => $this->digitos($m->cnh),
            'CPF' => $m->cpf,
            'DataNascimento' => $m->nascimento->format('Y-m-d\T00:00:00'),
            'Endereco' => $this->endereco($m->bairro, $m->cep, $m->municipio_codigo, $m->logradouro, $m->numero, $m->complemento),
            'Nome' => $m->nome,
            'Telefones' => ['Celular' => $this->celular($m->telefone)],
        ];
    }

    /**
     * Um proprietário por veículo, na ordem de veiculos(): o e-Frete só
     * reconhece o vínculo placa-RNTRC quando o proprietário é regravado logo
     * antes de cada veículo, mesmo sendo o mesmo para os dois (Transm).
     */
    public function proprietarios(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        $c = $this->contexto($contrato, $config);

        return collect($c['veiculos'])->map(function (Veiculo $v) use ($c, $config, $token): array {
            $dono = $this->dono($v, $c['cavalo']);

            return [
                ...$this->autenticacao($config, $token, 4),
                'CNPJ' => $dono->proprietario_documento,
                'Endereco' => $this->endereco($dono->proprietario_bairro, $dono->proprietario_cep, $dono->proprietario_municipio_codigo,
                    $dono->proprietario_logradouro, $dono->proprietario_numero, $dono->proprietario_complemento),
                'RNTRC' => $this->rntrc($dono->proprietario_rntrc),
                'RazaoSocial' => $dono->proprietario_nome,
            ];
        })->all();
    }

    public function veiculos(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        $c = $this->contexto($contrato, $config);

        return collect($c['veiculos'])->map(fn (Veiculo $v): array => [
            ...$this->autenticacao($config, $token, 1),
            'Veiculo' => array_filter([
                'Chassi' => strtoupper((string) $v->chassi),
                'NumeroDeEixos' => (int) $v->eixos,
                'Placa' => $v->placa,
                // Todo veículo leva RNTRC > 0: o do próprio dono ou o do dono do cavalo.
                'RNTRC' => $this->rntrc($this->dono($v, $c['cavalo'])->proprietario_rntrc),
                'Renavam' => $this->digitos($v->renavam),
                'TipoCarroceria' => match ((string) $v->tipo_carroceria) {
                    '01' => 'Aberta', '02' => 'FechadaOuBau', '03' => 'Granelera', '04' => 'PortaContainer', '05' => 'Sider',
                    default => 'NaoAplicavel',
                },
                'TipoRodado' => $v->eTracao() ? 'Cavalo' : 'NaoAplicavel',
                'CapacidadeKg' => $v->capacidade_kg,
                'CapacidadeM3' => $v->capacidade_m3,
                'Tara' => $v->tara_kg,
            ], fn (mixed $valor): bool => $valor !== null && $valor !== ''),
        ])->all();
    }

    public function consultaTransportador(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        $c = $this->contexto($contrato, $config);

        return [
            'InteressadoCpfOuCnpj' => $contrato->emitente->cnpj,
            'TransportadorCpfOuCnpj' => $this->contratado($contrato, $config),
            'TransportadorRNTRC' => $this->rntrcContratado($contrato, $config),
            'DataPrevistaFimViagem' => $c['fim']->format('Y-m-d\TH:i:s'),
            'Veiculos' => collect($c['veiculos'])->map(fn (Veiculo $v): array => ['Placa' => $this->placa($v, $config)])->all(),
            ...$this->autenticacao($config, $token, 1),
        ];
    }

    public function busca(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        return [
            'MatrizCNPJ' => $contrato->emitente->cnpj,
            'IdOperacaoCliente' => $this->idOperacao($contrato),
            ...$this->autenticacao($config, $token, 1),
        ];
    }

    public function pdf(EmitenteTransporte $config, string $token, string $codigo): array
    {
        return ['CodigoIdentificacaoOperacao' => $codigo, ...$this->autenticacao($config, $token, 1)];
    }

    public function encerramento(ContratoFrete $contrato, EmitenteTransporte $config, string $token, string $ciot): array
    {
        $contrato->loadMissing('viagem.notas');

        return [
            'CodigoIdentificacaoOperacao' => $ciot,
            'PesoCarga' => round((float) $contrato->viagem->notas->sum(fn (ViagemNota $n): float => (float) $n->peso_kg), 3),
            ...$this->autenticacao($config, $token, 2),
        ];
    }

    /** Fixo por contrato: é o que impede CIOT duplicado ao tentar de novo. */
    public function idOperacao(ContratoFrete $contrato): string
    {
        return 'EA-'.$contrato->emitente_id.'-'.$contrato->getKey();
    }

    public function usaMassaAntt(EmitenteTransporte $config): bool
    {
        return (bool) $config->efrete_massa_antt;
    }

    /**
     * Confere tudo antes da primeira chamada, para a pessoa receber a lista
     * do que falta de uma vez, e não um erro do e-Frete por vez.
     *
     * @return array{motorista: Motorista, cavalo: Veiculo, veiculos: array<int, Veiculo>, ctes: Collection<int, Cte>, notas: Collection<int, ViagemNota>, inicio: CarbonInterface, fim: CarbonInterface}
     */
    public function contexto(ContratoFrete $contrato, EmitenteTransporte $config): array
    {
        $contrato->loadMissing(['emitente', 'viagem.ctes.notas', 'viagem.notas', 'viagem.motorista', 'viagem.veiculo', 'viagem.reboque', 'viagem.reboque2']);
        $viagem = $contrato->viagem;
        $emitente = $contrato->emitente;
        $motorista = $viagem->motorista;
        $cavalo = $viagem->veiculo;
        $veiculos = array_values(array_filter([$cavalo, $viagem->reboque, $viagem->reboque2]));
        $ctes = $viagem->ctes->filter(fn (Cte $c): bool => $c->status === CteStatus::Autorizado)->values();

        $falta = [];
        if ($ctes->isEmpty()) {
            $falta[] = 'CT-e autorizado na viagem';
        }
        foreach (['RNTRC da empresa' => $config->rntrc, 'CEP da empresa' => $emitente->cep, 'endereço da empresa' => $emitente->logradouro,
            'bairro da empresa' => $emitente->bairro, 'código IBGE da empresa' => $emitente->codigo_municipio] as $campo => $valor) {
            if (blank($valor)) {
                $falta[] = $campo;
            }
        }
        if ($motorista === null) {
            $falta[] = 'motorista';
        } elseif ($pendente = $motorista->pendenciasEfrete()) {
            $falta[] = 'no motorista '.$motorista->nome.': '.implode(', ', $pendente);
        }
        foreach ($veiculos as $v) {
            if (! $this->usaMassaAntt($config) && ($pendente = $v->pendenciasEfrete())) {
                $falta[] = 'no veículo '.$v->placaFormatada().': '.implode(', ', $pendente);
            }
        }
        if (! $viagem->distancia_km) {
            $falta[] = 'distância da viagem (km)';
        }
        if (! array_key_exists((string) $config->efrete_embalagem, self::EMBALAGENS)) {
            $falta[] = 'tipo de embalagem (em Configurações, CIOT)';
        }
        if (! array_key_exists((int) $viagem->tipo_carga, self::TIPOS_CARGA)) {
            $falta[] = 'tipo de carga';
        }
        if ($ctes->isNotEmpty() && strlen($this->naturezaCarga($viagem->notas)) !== 4) {
            $falta[] = 'NCM do produto nas NF-e';
        }
        if ($falta !== []) {
            throw new TransporteException('Para gerar o CIOT, complete: '.implode('; ', $falta).'.');
        }

        $inicio = $viagem->data_carregamento && $viagem->data_carregamento->isFuture() ? $viagem->data_carregamento->startOfDay() : now()->startOfMinute();
        $fim = $viagem->previsaoEntrega()->copy()->setTime(18, 0);
        $dias = $inicio->diffInDays($fim, false);
        if ($dias <= 0 || $dias > 90) {
            throw new TransporteException('O fim previsto da viagem precisa ser depois do início e em até 90 dias.');
        }

        return ['motorista' => $motorista, 'cavalo' => $cavalo, 'veiculos' => $veiculos, 'ctes' => $ctes, 'notas' => $viagem->notas, 'inicio' => $inicio, 'fim' => $fim];
    }

    private function viagem(ContratoFrete $contrato, Cte $cte, string $ncm, int $frete, int $adiantamento): array
    {
        $remetente = (array) $cte->remetente;
        $destinatario = (array) $cte->destinatario;
        $quitacao = max(0, $frete - $adiantamento);
        $notas = $cte->notas->values()->map(fn (ViagemNota $n): array => array_filter([
            'CnpjEmissor' => $this->digitos($n->remetente['documento'] ?? null),
            'Numero' => (string) $n->numero,
            'Serie' => (string) ($n->serie ?: '1'),
            'Data' => $n->emitida_em?->format('Y-m-d\TH:i:s'),
            'ValorTotal' => $this->reais($n->valor_centavos),
            'ValorDaMercadoriaPorUnidade' => (float) $n->peso_kg > 0 ? round($n->valor_centavos / 100 / (float) $n->peso_kg, 5) : 0.0,
            'CodigoNCMNaturezaCarga' => $ncm,
            'DescricaoDaMercadoria' => mb_substr((string) ($n->produto ?: 'CARGA GERAL'), 0, 60),
            'UnidadeDeMedidaDaMercadoria' => 'Kg',
            'TipoDeCalculo' => 'SemQuebra',
            'ValorDoFretePorUnidadeDeMercadoria' => null,
            'QuantidadeDaMercadoriaNoEmbarque' => round((float) $n->peso_kg, 3),
            'ToleranciaDePerdaDeMercadoria' => ['Tipo' => 'Nenhum', 'Valor' => 0.0],
        ], fn (mixed $v, string $k): bool => $k === 'ValorDoFretePorUnidadeDeMercadoria' || ($v !== null && $v !== ''), ARRAY_FILTER_USE_BOTH))->all();

        $viagem = [
            'DocumentoViagem' => 'CTe-'.$cte->numero,
            'CodigoMunicipioOrigem' => (int) $cte->municipio_inicio_codigo,
            'CodigoMunicipioDestino' => (int) $cte->municipio_fim_codigo,
        ];
        // CEP de origem e destino vão juntos ou não vão: a ANTT recusa só um lado.
        if (filled($remetente['cep'] ?? null) && filled($destinatario['cep'] ?? null)) {
            $viagem += ['CepOrigem' => $this->digitos($remetente['cep']), 'CepDestino' => $this->digitos($destinatario['cep'])];
        }

        return $viagem + [
            'DistanciaPercorrida' => (int) $contrato->viagem->distancia_km,
            'Valores' => [
                'TotalOperacao' => $this->reais($frete),
                'TotalViagem' => $this->reais($adiantamento + $quitacao),
                'TotalDeAdiantamento' => $this->reais($adiantamento),
                'TotalDeQuitacao' => $this->reais($quitacao),
                'Combustivel' => 0.0,
                'Pedagio' => 0.0,
                'OutrosCreditos' => 0.0,
                'Seguro' => 0.0,
                'OutrosDebitos' => 0.0,
            ],
            'TipoPagamento' => 'TransferenciaBancaria',
            'NotasFiscais' => ['NotaFiscal' => $notas],
        ];
    }

    private function pagamentos(ContratoFrete $contrato, EmitenteTransporte $config, array $c): array
    {
        $pagamentos = [];
        $quitacao = max(0, $contrato->frete_centavos - $contrato->adiantamento_centavos);
        foreach ([['Adiantamento', $contrato->adiantamento_centavos, $c['inicio']], ['Quitacao', $quitacao, $c['fim']]] as $i => [$categoria, $valor, $data]) {
            if ($valor <= 0) {
                continue;
            }
            $pagamento = [
                'IdPagamentoCliente' => $this->idOperacao($contrato).'-P'.($i + 1),
                'DataDeLiberacao' => $data->format('Y-m-d\TH:i:s'),
                'Valor' => $this->reais($valor),
                'TipoPagamento' => 'TransferenciaBancaria',
                'Categoria' => $categoria,
                'Documento' => 'VIAGEM-'.$contrato->numero,
                'TipoChavePix' => null,
                'IndicadorPagamento' => 'AVista',
                'NumeroParcela' => 0,
                'CpfCnpjCreditado' => $this->contratado($contrato, $config),
            ];
            if ($contrato->forma_pagamento === 'pix') {
                $chave = $this->chavePix($contrato, $config);
                $pagamento['TipoChavePix'] = $this->tipoPix($chave);
                $pagamento['ValorChavePix'] = $chave;
            } else {
                $pagamento['InformacoesBancarias'] = [
                    'InstituicaoBancaria' => $contrato->banco_codigo,
                    'Agencia' => $contrato->agencia,
                    'Conta' => $contrato->conta,
                    'TipoConta' => 'ContaCorrente',
                ];
            }
            $pagamentos[] = array_filter($pagamento, fn (mixed $v, string $k): bool => $k === 'TipoChavePix' || ($v !== null && $v !== ''), ARRAY_FILTER_USE_BOTH);
        }

        return $pagamentos;
    }

    /** Dono da carreta quando ela tem RNTRC próprio; senão, o dono do cavalo. */
    private function dono(Veiculo $veiculo, Veiculo $cavalo): Veiculo
    {
        return $veiculo->deTerceiro() && (int) $this->digitos($veiculo->proprietario_rntrc) > 0 ? $veiculo : $cavalo;
    }

    private function contratado(ContratoFrete $contrato, EmitenteTransporte $config): string
    {
        return $this->usaMassaAntt($config) ? (string) config('fiscal.efrete.massa_antt.contratado') : (string) $contrato->contratado_documento;
    }

    private function rntrcContratado(ContratoFrete $contrato, EmitenteTransporte $config): string
    {
        return $this->rntrc($this->usaMassaAntt($config) ? (string) config('fiscal.efrete.massa_antt.rntrc') : $contrato->contratado_rntrc);
    }

    private function placa(Veiculo $veiculo, EmitenteTransporte $config): string
    {
        if (! $this->usaMassaAntt($config)) {
            return (string) $veiculo->placa;
        }

        return (string) config($veiculo->eTracao() ? 'fiscal.efrete.massa_antt.placa_cavalo' : 'fiscal.efrete.massa_antt.placa_carreta');
    }

    /** Chave Pix CPF/CNPJ precisa ser o documento do contratado, também na massa de teste. */
    private function chavePix(ContratoFrete $contrato, EmitenteTransporte $config): string
    {
        $chave = (string) $contrato->chave_pix;

        return $this->usaMassaAntt($config) && $this->tipoPix($chave) === 'CpfOuCnpj' ? $this->contratado($contrato, $config) : $chave;
    }

    private function tipoPix(string $chave): string
    {
        $digitos = $this->digitos($chave);

        return match (true) {
            str_contains($chave, '@') => 'Email',
            in_array(strlen($digitos), [11, 14], true) && strlen($digitos) === strlen(preg_replace('/[\s.\-\/]/', '', $chave)) => 'CpfOuCnpj',
            strlen($digitos) >= 10 && strlen($digitos) <= 13 => 'TelefoneCelular',
            default => 'ChaveAleatoria',
        };
    }

    /** Os 4 primeiros dígitos do NCM da NF-e mais pesada. */
    private function naturezaCarga(Collection $notas): string
    {
        $nota = $notas->sortByDesc(fn (ViagemNota $n): float => (float) $n->peso_kg)->first(fn (ViagemNota $n): bool => strlen($this->digitos($n->ncm)) >= 4);

        return $nota ? substr($this->digitos($nota->ncm), 0, 4) : '';
    }

    /** Sem filtrar vazio: o encoder SOAP precisa de todas as propriedades do Endereco. */
    private function endereco(?string $bairro, ?string $cep, ?string $municipio, ?string $rua, ?string $numero, ?string $complemento): array
    {
        return [
            'Bairro' => (string) $bairro,
            'CEP' => $this->digitos($cep),
            'CodigoMunicipio' => (int) $municipio,
            'Rua' => (string) $rua,
            'Numero' => (string) ($numero ?: 'S/N'),
            'Complemento' => (string) $complemento,
        ];
    }

    private function autenticacao(EmitenteTransporte $config, string $token, int $versao): array
    {
        return ['Integrador' => $config->efrete_integrador, 'Token' => $token, 'Versao' => $versao];
    }

    private function celular(?string $telefone): array
    {
        $d = $this->digitos($telefone);

        return ['DDD' => (int) substr($d, 0, 2), 'Numero' => (int) substr($d, 2)];
    }

    private function email(?string $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function rntrc(?string $valor): string
    {
        return str_pad($this->digitos($valor), 9, '0', STR_PAD_LEFT);
    }

    private function reais(int $centavos): float
    {
        return $centavos / 100;
    }

    private function digitos(?string $valor): string
    {
        return (string) preg_replace('/\D/', '', (string) $valor);
    }
}
