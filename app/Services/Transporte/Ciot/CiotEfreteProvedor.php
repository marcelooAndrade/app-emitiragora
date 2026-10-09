<?php

namespace App\Services\Transporte\Ciot;

use App\Models\Ciot;
use App\Models\ContratoFrete;
use App\Models\Emitente;
use App\Models\EmitenteTransporte;
use App\Models\Viagem;
use App\Services\Transporte\Efrete\CiotEfrete;
use App\Services\Transporte\Efrete\EfreteCliente;
use App\Services\Transporte\Efrete\PayloadEfrete;
use App\Services\Transporte\TransporteException;

/**
 * O e-Frete como empresa de CIOT. O código do e-Frete (`Efrete\*`) é todo
 * de contrato com terceiro TAC: cadastra motorista e proprietário, e o
 * identificador da operação é fixo por contrato. Então aqui ele serve só
 * a viagem com TAC, como sempre serviu; frota própria pede o CIOT digitado
 * enquanto o e-Frete for a empresa escolhida.
 *
 * As credenciais vêm de `emitente_ciot` e entram numa cópia em memória do
 * `EmitenteTransporte` (nunca salva), que é o que o cliente do e-Frete lê.
 */
class CiotEfreteProvedor implements GatewayCiot
{
    public function __construct(
        private readonly CiotEfrete $efrete,
        private readonly EfreteCliente $cliente,
    ) {}

    public function declarar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
    {
        return $this->efrete->gerar($this->contrato($operacao->viagem), $this->config($operacao->viagem->emitente, $credenciais));
    }

    public function consultar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
    {
        return $this->efrete->gerar($this->contrato($operacao->viagem), $this->config($operacao->viagem->emitente, $credenciais), consultar: true);
    }

    /** O cancelamento pelo e-Frete não foi portado do Transm: fica no portal. */
    public function cancelar(Ciot $ciot, string $motivo, array $credenciais): RespostaCiot
    {
        return new RespostaCiot('cancelado', mensagem: 'Marcado como cancelado aqui. Cancele também a operação no portal do e-Frete.');
    }

    public function encerrar(Ciot $ciot, array $credenciais): RespostaCiot
    {
        $viagem = $ciot->viagem;

        return $this->efrete->encerrar((string) $ciot->numero, $this->contrato($viagem), $this->config($viagem->emitente, $credenciais));
    }

    public function testarConexao(Emitente $emitente, array $credenciais): RespostaCiot
    {
        $config = $this->config($emitente, $credenciais);
        $this->cliente->travaHomologacao($config);
        $this->cliente->login($config);

        return new RespostaCiot('ok', mensagem: 'O e-Frete aceitou o login com estas credenciais.');
    }

    public function campos(): array
    {
        return [
            'usuario' => ['rotulo' => 'Usuário do e-Frete'],
            'senha' => ['rotulo' => 'Senha do e-Frete', 'segredo' => true],
            'integrador' => ['rotulo' => 'Hash do integrador', 'segredo' => true, 'ajuda' => 'Fornecido pela e-Frete.'],
            'embalagem' => ['rotulo' => 'Embalagem padrão', 'tipo' => 'select', 'opcoes' => PayloadEfrete::EMBALAGENS],
            'massa_antt' => ['rotulo' => 'Usar a massa de teste da ANTT (só homologação)', 'tipo' => 'checkbox'],
        ];
    }

    public function pendencias(Viagem $viagem, array $credenciais): array
    {
        $veiculo = $viagem->veiculo;
        if ($veiculo === null || ! $veiculo->deTerceiro() || ! in_array($veiculo->proprietario_tp, ['0', '1'], true)) {
            return ['O e-Frete, neste sistema, gera CIOT só de veículo de terceiro (TAC). Para esta viagem, informe o CIOT em "Informar CIOT gerado fora".'];
        }
        if (blank($credenciais['usuario'] ?? null) || blank($credenciais['senha'] ?? null) || blank($credenciais['integrador'] ?? null)) {
            return ['Informe usuário, senha e hash do integrador do e-Frete em Configurações, CIOT.'];
        }

        return [];
    }

    private function contrato(Viagem $viagem): ContratoFrete
    {
        $contrato = $viagem->contrato;
        if ($contrato === null || $contrato->status !== 'ativo') {
            throw new TransporteException('Preencha o contrato do frete com o terceiro antes do CIOT.');
        }

        return $contrato;
    }

    /** Cópia em memória com as credenciais: o cliente do e-Frete lê daqui. */
    private function config(Emitente $emitente, array $credenciais): EmitenteTransporte
    {
        $config = $emitente->configuracaoTransporte()->replicate();
        $config->setRelation('emitente', $emitente);
        $config->forceFill([
            'efrete_usuario' => $credenciais['usuario'] ?? null,
            'efrete_senha' => $credenciais['senha'] ?? null,
            'efrete_integrador' => $credenciais['integrador'] ?? null,
            'efrete_massa_antt' => (bool) ($credenciais['massa_antt'] ?? false),
            'efrete_embalagem' => $credenciais['embalagem'] ?? 'Pallet',
        ]);

        return $config;
    }
}
