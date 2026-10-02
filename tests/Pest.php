<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)

    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
 * Helpers compartilhados entre suítes.
 *
 * Ficam aqui, e não no arquivo onde nasceram, para que cada teste possa ser
 * rodado isoladamente. Pest carrega este arquivo sempre; um arquivo de teste
 * só é carregado quando ele mesmo entra na execução.
 */

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;
use App\Models\EmitenteNfse;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Motorista;
use App\Models\NaturezaOperacao;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\RegraIcmsTransporte;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazGateway;
use App\Services\Nfse\GatewayNfse;
use App\Services\Nfse\RespostaNfse;
use App\Services\Stock\StockService;
use App\Services\Transporte\GatewayCte;
use App\Services\Transporte\GatewayMdfe;
use App\Services\Transporte\Viagens;
use App\Support\TenantAtual;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function produtoDe(Emitente $emitente, array $extra = []): Produto
{
    return Produto::create(array_merge([
        'emitente_id' => $emitente->id,
        'codigo' => 'PC-'.fake()->unique()->numerify('###'),
        'descricao' => 'Peça microfundida',
        'ncm' => '73259910',
        'unidade_comercial' => 'PC',
        'unidade_tributavel' => 'PC',
        'fator_conversao' => 1,
        'origem' => '0',
    ], $extra));
}

function xmlAutorizado(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml'));
}

function emitenteCompleto(array $extra = []): Emitente
{
    return Emitente::factory()->create(array_merge([
        'razao_social' => 'RCM DO BRASIL LTDA',
        'nome_fantasia' => 'RCM do Brasil',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'logradouro' => 'Rua Joao Grigoleto', 'numero' => '83',
        'bairro' => 'Distrito Industrial II',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras',
        'uf' => 'SP', 'cep' => '13602200', 'telefone' => '1930960072',
    ], $extra));
}

function destinatarioCompleto(Emitente $e, array $extra = []): Pessoa
{
    return Pessoa::create(array_merge([
        'emitente_id' => $e->id, 'tipo_pessoa' => 'J',
        'documento' => '11444777000161',
        'razao_social' => 'METALURGICA PIRACICABA LTDA',
        'ind_ie_dest' => '1', 'inscricao_estadual' => '111222333444',
        'logradouro' => 'Avenida Industrial', 'numero' => '450',
        'bairro' => 'Distrito Industrial',
        'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba',
        'uf' => 'SP', 'cep' => '13400000',
        'e_cliente' => true,
    ], $extra));
}

function notaPronta(array $extraNota = [], array $extraRegra = []): Nota
{
    $emitente = emitenteCompleto();
    $dest = destinatarioCompleto($emitente);

    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Peças']);
    PerfilFiscalRegra::create(array_merge([
        'perfil_fiscal_id' => $perfil->id, 'ambito' => 'interna', 'vigente_de' => '2026-01-01',
        'cst_icms' => '00', 'aliquota_icms' => 18,
        'cst_pis' => '01', 'aliquota_pis' => 1.65,
        'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
    ], $extraRegra));

    $natureza = NaturezaOperacao::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'descricao' => 'Venda de producao propria',
        'cfop_interno' => '5101', 'cfop_interestadual' => '6101',
    ]);

    $produto = Produto::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'unidade_comercial' => 'PC', 'unidade_tributavel' => 'PC',
        'fator_conversao' => 1, 'origem' => '0', 'preco_venda' => 145.90,
        'peso_liquido' => 0.45, 'peso_bruto' => 0.48,
    ]);

    $nota = Nota::create(array_merge([
        'emitente_id' => $emitente->id,
        'pessoa_id' => $dest->id,
        'natureza_operacao_id' => $natureza->id,
        'natureza_operacao' => $natureza->descricao,
        'serie' => 1, 'numero' => 1480,
        'ambiente' => 'homologacao',
        'data_emissao' => now(),
        'id_dest' => '1', 'mod_frete' => '9',
        'valor_produtos' => 1459.00, 'valor_nota' => 1459.00,
        'base_icms' => 1459.00, 'valor_icms' => 262.62,
        'valor_pis' => 24.07, 'valor_cofins' => 110.88,
    ], $extraNota));

    // Produto com saldo, como numa fundição de verdade. Sem isso a
    // transmissão é barrada antes de chegar na SEFAZ, e com razão.
    app(StockService::class)->entrada($produto, 1000, 60.00, 'Saldo inicial');

    NotaItem::create([
        'nota_id' => $nota->id, 'produto_id' => $produto->id, 'numero' => 1,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'cfop' => '5101', 'unidade' => 'PC', 'unidade_tributavel' => 'PC',
        'origem' => '0', 'quantidade' => 10, 'quantidade_tributavel' => 10,
        'valor_unitario' => 145.90, 'valor_produto' => 1459.00,
        'cst_icms' => '00', 'mod_bc' => '3', 'base_icms' => 1459.00,
        'aliquota_icms' => 18, 'valor_icms' => 262.62,
        'cst_pis' => '01', 'valor_pis' => 24.07,
        'cst_cofins' => '01', 'valor_cofins' => 110.88,
    ]);

    return $nota->fresh(['itens', 'destinatario', 'emitente']);
}

/*
 * Gateway falso da SEFAZ, roteirizado.
 *
 * Fica aqui porque três suítes precisam dele: transmissão, eventos e a tela
 * de emissão. Guarda o que foi chamado, para as asserções de idempotência.
 */
function gatewayFake(array $roteiro): SefazGateway
{
    return new class($roteiro) implements SefazGateway
    {
        public array $chamadas = [];

        public function __construct(private array $roteiro) {}

        private function responder(string $metodo, ?RespostaSefaz $padrao = null): RespostaSefaz
        {
            $this->chamadas[] = $metodo;
            $r = $this->roteiro[$metodo] ?? $padrao ?? new RespostaSefaz('999', "Sem roteiro para {$metodo}");

            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }

        public function enviar(Emitente $e, string $xml): RespostaSefaz
        {
            return $this->responder('enviar');
        }

        public function consultarRecibo(Emitente $e, string $recibo): RespostaSefaz
        {
            return $this->responder('consultarRecibo');
        }

        public function consultarChave(Emitente $e, string $chave): RespostaSefaz
        {
            return $this->responder('consultarChave');
        }

        // Sem roteiro, a SEFAZ está no ar. Passa por `responder` para que o
        // roteiro possa mandar uma exceção, como faz com os outros métodos.
        public function statusServico(Emitente $e): RespostaSefaz
        {
            return $this->responder('statusServico', new RespostaSefaz('107', 'Servico em Operacao'));
        }

        public function cancelar(Emitente $e, string $chave, string $protocolo, string $justificativa): RespostaSefaz
        {
            return $this->responder('cancelar');
        }

        public function cartaCorrecao(Emitente $e, string $chave, string $correcao, int $sequencia): RespostaSefaz
        {
            return $this->responder('cartaCorrecao');
        }

        public function inutilizar(Emitente $e, int $ano, int $serie, int $inicial, int $final, string $justificativa): RespostaSefaz
        {
            return $this->responder('inutilizar');
        }
    };
}

function comGateway(array $roteiro): object
{
    $fake = gatewayFake($roteiro);
    app()->instance(SefazGateway::class, $fake);

    return $fake;
}

function autorizada(string $chave = '35260911222333000181550010000014801033717992'): RespostaSefaz
{
    return new RespostaSefaz('100', 'Autorizado o uso da NF-e', '135260000123456', null, '<nfeProc/>', $chave);
}

/*
 * Usuário do tenant de teste, com perfil e emitente vinculados.
 *
 * Três suítes precisam dele: a tela de marca, o upload de logo e o
 * isolamento entre tenants.
 */
function usuarioMarca(string $perfil): User
{
    $tenant = app(TenantAtual::class)->obter();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return $user;
}

/*
 * Uma parcela pronta para virar NFS-e: fatura ativa, com cliente completo
 * e um título de R$ 1.500,00 vencendo hoje.
 */
function parcelaParaNfse(Emitente $emitente, array $extraParcela = []): FaturaParcela
{
    // O mesmo cliente serve para várias parcelas de teste do mesmo emitente:
    // destinatarioCompleto() usa um documento fixo, e criar de novo colidiria
    // com a unicidade de emitente_id + documento.
    $tomador = Pessoa::where('emitente_id', $emitente->id)->where('documento', '11444777000161')->first()
        ?? destinatarioCompleto($emitente, ['telefone' => '1935551234', 'email' => 'fiscal@piracicaba.com.br']);

    $fatura = Fatura::create([
        'emitente_id' => $emitente->id,
        'pessoa_id' => $tomador->id,
        'titulo' => 'Consultoria de setembro',
    ]);

    return FaturaParcela::create(array_merge([
        'fatura_id' => $fatura->id,
        'numero' => 1,
        'descricao' => 'Consultoria de setembro, parcela 1 de 1',
        'valor_centavos' => 150000,
        'vencimento' => today(),
    ], $extraParcela));
}

/*
 * Emitente de Araras pronto para emitir NFS-e: inscrição municipal, NFS-e
 * habilitada em homologação com senha. `$config` sobrescreve a configuração,
 * `$emitente` sobrescreve o emitente.
 */
function emitenteComNfse(array $config = [], array $emitente = []): Emitente
{
    $e = emitenteCompleto(array_merge(['inscricao_municipal' => '44307'], $emitente));

    EmitenteNfse::create(array_merge([
        'emitente_id' => $e->id,
        'habilitado' => true,
        'senha_homologacao' => 'segredo-hml',
    ], $config));

    return $e->fresh();
}

/*
 * Gateway falso de NFS-e, roteirizado como o `gatewayFake` da SEFAZ.
 * `emitir` e `cancelar` devolvem RespostaNfse; `pdf` devolve string. Um
 * Throwable no roteiro é lançado.
 */
function gatewayNfseFake(array $roteiro): GatewayNfse
{
    return new class($roteiro) implements GatewayNfse
    {
        public array $chamadas = [];

        public function __construct(private array $roteiro) {}

        private function responder(string $metodo, array $args): mixed
        {
            $this->chamadas[] = ['metodo' => $metodo, 'args' => $args];
            $r = $this->roteiro[$metodo] ?? new RespostaNfse(false, motivo: "Sem roteiro para {$metodo}");

            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }

        public function emitir(Emitente $e, Ambiente $a, string $xml): RespostaNfse
        {
            return $this->responder('emitir', ['ambiente' => $a, 'xml' => $xml]);
        }

        public function cancelar(Emitente $e, Ambiente $a, string $numero, string $serie, string $motivo): RespostaNfse
        {
            return $this->responder('cancelar', ['ambiente' => $a, 'numero' => $numero, 'serie' => $serie, 'motivo' => $motivo]);
        }

        public function pdf(Emitente $e, Ambiente $a, string $numero, string $serie): string
        {
            return $this->responder('pdf', ['ambiente' => $a, 'numero' => $numero, 'serie' => $serie]);
        }
    };
}

function comGatewayNfse(array $roteiro): object
{
    $fake = gatewayNfseFake($roteiro);
    app()->instance(GatewayNfse::class, $fake);

    return $fake;
}

function nfseAutorizada(string $numero = '700'): RespostaNfse
{
    return new RespostaNfse(
        sucesso: true,
        numero: $numero,
        serie: 'NFE',
        codigoVerificacao: 'ABC123',
        bruto: "<notafiscal><numero_nf>{$numero}</numero_nf><serie>NFE</serie><codigo>ABC123</codigo></notafiscal>",
    );
}

/*
 | Transporte (CT-e e MDF-e).
 |
 | O roteiro aceita uma resposta por método ou uma lista, consumida na ordem
 | (para "rejeitado, depois autorizado"). `assinar` devolve o XML como veio:
 | quem quer o XML assinado de verdade usa o gateway real com o certificado
 | de teste, como em CteXmlTest.
 */
function gatewayTransporteFake(string $interface, array $roteiro): object
{
    $base = new class($roteiro)
    {
        public array $chamadas = [];

        public function __construct(public array $roteiro) {}

        public function responder(string $metodo, ?RespostaSefaz $padrao = null): RespostaSefaz
        {
            $this->chamadas[] = $metodo;
            $r = $this->roteiro[$metodo] ?? $padrao ?? new RespostaSefaz('999', "Sem roteiro para {$metodo}");
            if (is_array($r)) {
                $r = count($this->roteiro[$metodo]) > 1 ? array_shift($this->roteiro[$metodo]) : $this->roteiro[$metodo][0];
            }
            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }
    };

    if ($interface === GatewayCte::class) {
        return new class($base) implements GatewayCte
        {
            public function __construct(public object $base) {}

            public function assinar(Emitente $e, string $xml): string
            {
                return $xml;
            }

            public function enviar(Emitente $e, string $xmlAssinado): RespostaSefaz
            {
                return $this->base->responder('enviar');
            }

            public function consultar(Emitente $e, string $chave, ?string $xmlAssinado = null): RespostaSefaz
            {
                return $this->base->responder('consultar');
            }

            public function cancelar(Emitente $e, string $chave, string $protocolo, string $justificativa): RespostaSefaz
            {
                return $this->base->responder('cancelar');
            }

            public function cartaCorrecao(Emitente $e, string $chave, array $correcoes, int $sequencia): RespostaSefaz
            {
                return $this->base->responder('cartaCorrecao');
            }
        };
    }

    return new class($base) implements GatewayMdfe
    {
        public function __construct(public object $base) {}

        public function assinar(Emitente $e, string $xml): string
        {
            return $xml;
        }

        public function enviar(Emitente $e, string $xmlAssinado): RespostaSefaz
        {
            return $this->base->responder('enviar');
        }

        public function consultar(Emitente $e, string $chave, ?string $xmlAssinado = null): RespostaSefaz
        {
            return $this->base->responder('consultar');
        }

        public function encerrar(Emitente $e, string $chave, string $protocolo, string $uf, string $municipioCodigo, string $data): RespostaSefaz
        {
            return $this->base->responder('encerrar');
        }

        public function cancelar(Emitente $e, string $chave, string $protocolo, string $justificativa): RespostaSefaz
        {
            return $this->base->responder('cancelar');
        }
    };
}

function comGatewayCte(array $roteiro): object
{
    $fake = gatewayTransporteFake(GatewayCte::class, $roteiro);
    app()->instance(GatewayCte::class, $fake);

    return $fake->base;
}

function comGatewayMdfe(array $roteiro): object
{
    $fake = gatewayTransporteFake(GatewayMdfe::class, $roteiro);
    app()->instance(GatewayMdfe::class, $fake);

    return $fake->base;
}

function cteAutorizado(string $protocolo = '135260000999001'): RespostaSefaz
{
    return new RespostaSefaz('100', 'Autorizado o uso do CT-e', $protocolo, null, '<cteProc/>');
}

function mdfeAutorizado(string $protocolo = '958260000999001'): RespostaSefaz
{
    return new RespostaSefaz('100', 'Autorizado o uso do MDF-e', $protocolo, null, '<mdfeProc/>');
}

function eventoRegistrado(string $protocolo = '135260000888001'): RespostaSefaz
{
    return new RespostaSefaz('135', 'Evento registrado e vinculado', $protocolo);
}

function comCertificadoDeTeste(Emitente $emitente): void
{
    app(CertificateService::class)->enviar(
        $emitente,
        UploadedFile::fake()->createWithContent(
            'valido.pfx',
            (string) file_get_contents(base_path('tests/Fixtures/certificados/valido.pfx')),
        ),
        'teste123',
        User::factory()->create(),
    );
}

/**
 * Emitente pronto para transportar: RNTRC, seguro, regra de ICMS SP → SP a
 * 12% e certificado de teste (CNPJ 11222333000181, o mesmo do valido.pfx).
 */
function transportadora(array $extra = [], bool $certificado = true): Emitente
{
    $emitente = emitenteCompleto($extra);
    $emitente->configuracaoTransporte()->update([
        'rntrc' => '12345678',
        'seguradora_nome' => 'SEGURADORA TESTE',
        'seguradora_cnpj' => '11444777000161',
        'apolice' => 'AP-123456',
    ]);
    (new RegraIcmsTransporte(['nome' => 'SP interno', 'uf_origem' => 'SP', 'uf_destino' => 'SP', 'cst' => '00', 'aliquota' => 12]))
        ->forceFill(['emitente_id' => $emitente->id])->save();
    if ($certificado) {
        comCertificadoDeTeste($emitente);
    }

    return $emitente->fresh();
}

/**
 * Viagem com a NF-e da fixture (500 kg), motorista, cavalo e frete de
 * R$ 150,00 por tonelada: um CT-e de R$ 75,00.
 */
function viagemPronta(?Emitente $emitente = null, array $dadosViagem = []): Viagem
{
    Storage::fake('fiscal');
    $emitente ??= transportadora();
    $motorista = (new Motorista(['nome' => 'JOAO DA SILVA', 'cpf' => '52998224725']))->forceFill(['emitente_id' => $emitente->id]);
    $motorista->save();
    $veiculo = (new Veiculo([
        'tipo' => 'tracao', 'placa' => 'ABC1D23', 'renavam' => '12345678901', 'uf' => 'SP',
        'tara_kg' => 8000, 'capacidade_kg' => 30000, 'tipo_rodado' => '03', 'tipo_carroceria' => '02',
    ]))->forceFill(['emitente_id' => $emitente->id]);
    $veiculo->save();

    $viagens = app(Viagens::class);
    $viagem = $viagens->criar($emitente, [
        'motorista_id' => $motorista->id,
        'veiculo_id' => $veiculo->id,
        'frete_tonelada_centavos' => 15000,
        ...$dadosViagem,
    ]);
    $nota = $viagens->adicionarNota($viagem, (string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml')));
    $viagens->definirPeso($viagem, $nota, 500);

    return $viagem->fresh();
}
