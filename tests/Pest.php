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

use App\Models\CadastroIniciado;
use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\Motorista;
use App\Models\Pessoa;
use App\Models\RegraIcmsTransporte;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazGateway;
use App\Services\Transporte\Ciot\GatewayCiot;
use App\Services\Transporte\Ciot\OperacaoCiot;
use App\Services\Transporte\Ciot\RespostaCiot;
use App\Services\Transporte\Ciot\ServicoCiot;
use App\Services\Transporte\GatewayCte;
use App\Services\Transporte\GatewayMdfe;
use App\Services\Transporte\Viagens;
use App\Support\TenantAtual;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

/*
 * Gateway falso da SEFAZ, roteirizado.
 *
 * Só o status do serviço sobrou na interface (ver SefazGateway); o monitor e o
 * indicador da SEFAZ usam. Guarda o que foi chamado.
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

        // Sem roteiro, a SEFAZ está no ar. Passa por `responder` para que o
        // roteiro possa mandar uma exceção, como faz com os outros métodos.
        public function statusServico(Emitente $e): RespostaSefaz
        {
            return $this->responder('statusServico', new RespostaSefaz('107', 'Servico em Operacao'));
        }
    };
}

function comGateway(array $roteiro): object
{
    $fake = gatewayFake($roteiro);
    app()->instance(SefazGateway::class, $fake);

    return $fake;
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

/**
 * CIOT para todos (DF-026): o MDF-e não sai sem CIOT. Quem testa o MDF-e e
 * não o CIOT registra um CIOT digitado na viagem, como faria quem gera o
 * CIOT no programa da ANTT.
 */
function comCiotInformado(Viagem $viagem, string $numero = '123456789012'): Ciot
{
    return app(ServicoCiot::class)->informar($viagem->fresh(), $numero);
}

/**
 * Uma empresa de CIOT falsa, registrada em `config/ciot.php` como `fake` e
 * ligada ao emitente. O roteiro diz o que cada método devolve: uma
 * `RespostaCiot`, uma exceção, ou uma lista delas em ordem. Guarda as
 * chamadas, com a operação montada e as credenciais recebidas.
 */
function provedorCiotFake(array $roteiro = [], ?Emitente $emitente = null, string $chave = 'fake'): object
{
    $fake = new class($roteiro) implements GatewayCiot
    {
        /** @var array<int, array{metodo: string, ciot_id: ?int, operacao: ?OperacaoCiot, credenciais: array}> */
        public array $chamadas = [];

        public function __construct(public array $roteiro) {}

        public function declarar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
        {
            return $this->responder('declarar', $ciot, $operacao, $credenciais);
        }

        public function consultar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
        {
            return $this->responder('consultar', $ciot, $operacao, $credenciais);
        }

        public function cancelar(Ciot $ciot, string $motivo, array $credenciais): RespostaCiot
        {
            return $this->responder('cancelar', $ciot, null, $credenciais, new RespostaCiot('cancelado'));
        }

        public function encerrar(Ciot $ciot, array $credenciais): RespostaCiot
        {
            return $this->responder('encerrar', $ciot, null, $credenciais, new RespostaCiot('encerrado'));
        }

        public function testarConexao(Emitente $emitente, array $credenciais): RespostaCiot
        {
            return $this->responder('testarConexao', null, null, $credenciais, new RespostaCiot('ok', mensagem: 'Conexão aceita.'));
        }

        public function campos(): array
        {
            return ['token' => ['rotulo' => 'Token', 'segredo' => true], 'conta' => ['rotulo' => 'Conta']];
        }

        public function pendencias(Viagem $viagem, array $credenciais): array
        {
            return $this->roteiro['pendencias'] ?? [];
        }

        private function responder(string $metodo, ?Ciot $ciot, ?OperacaoCiot $operacao, array $credenciais, ?RespostaCiot $padrao = null): RespostaCiot
        {
            $this->chamadas[] = ['metodo' => $metodo, 'ciot_id' => $ciot?->getKey(), 'operacao' => $operacao, 'credenciais' => $credenciais];
            $r = $this->roteiro[$metodo] ?? $padrao ?? new RespostaCiot('registrado', numero: '123456789012', verificador: '1234');
            if (is_array($r)) {
                $r = count($this->roteiro[$metodo]) > 1 ? array_shift($this->roteiro[$metodo]) : $this->roteiro[$metodo][0];
            }
            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }

        /** @return array<int, string> */
        public function metodos(): array
        {
            return array_column($this->chamadas, 'metodo');
        }
    };

    config(["ciot.provedores.{$chave}" => ['classe' => "ciot.{$chave}", 'nome' => 'Empresa Teste', 'descricao' => 'Para teste.', 'producao' => true]]);
    app()->instance("ciot.{$chave}", $fake);
    $emitente?->configuracaoCiot()->update(['provedor' => $chave]);

    return $fake;
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

/**
 * Dados de cadastro com o convite do link do e-mail, sem o qual o
 * CreateNewUser não cria conta (ver CadastroController). O convite sai para
 * o e-mail dos próprios dados, como aconteceria de verdade.
 *
 * @param  array<string, mixed>  $dados
 * @return array<string, mixed>
 */
function comConvite(array $dados): array
{
    $cadastro = CadastroIniciado::firstOrCreate(
        ['email' => mb_strtolower($dados['email'] ?? 'marcelo@exemplo.com.br')],
        ['nome' => $dados['name'] ?? 'Marcelo Andrade', 'telefone' => $dados['telefone'] ?? '1930960072'],
    );

    return [...$dados, 'convite' => $cadastro->novoConvite()];
}
