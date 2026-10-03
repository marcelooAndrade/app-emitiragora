<?php

namespace App\Console\Commands;

use App\Enums\Fiscal\NFeStatus;
use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\NaturezaOperacao;
use App\Models\Nota;
use App\Models\NotaEvento;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Financeiro\BaixaService;
use App\Services\Fiscal\CalcularNota;
use App\Services\Stock\StockService;
use App\Support\TenantAtual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Popula o sistema com dados de demonstração, para ver as telas funcionando
 * sem precisar cadastrar tudo à mão.
 *
 * Cria dois tenants com marcas diferentes, justamente para mostrar a troca
 * de identidade por URL.
 */
class PrepararDemonstracao extends Command
{
    protected $signature = 'venda:demo {--fresh : Recria o banco do zero}';

    protected $description = 'Popula o sistema com dados de demonstração';

    private const SENHA = 'demo1234';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->components->task('Recriando o banco', fn () => $this->callSilent('migrate:fresh') === 0);
        }

        $this->components->task('Perfis e permissões', fn () => $this->callSilent('db:seed', ['--class' => 'PerfilSeeder']) === 0);
        $this->components->task('Tabelas fiscais', fn () => $this->callSilent('db:seed', ['--class' => 'TabelasFiscaisSeeder']) === 0);
        $this->components->task('NCM de exemplo', fn () => $this->ncmsDeExemplo());

        $rcm = null;
        $this->components->task('RCM do Brasil (fundição)', function () use (&$rcm) {
            $rcm = $this->criarRcm();

            return true;
        });

        $this->components->task('Transportes Leme (marca verde)', fn () => $this->criarLeme());

        $this->newLine();
        $this->components->info('Pronto. Suba o servidor e acesse:');
        $this->newLine();

        $this->table(
            ['Acesso', 'URL', 'E-mail', 'Senha'],
            [
                ['Apresentação', 'http://localhost:8000', '', ''],
                ['RCM, administrador', 'http://rcm.localhost:8000/login', 'admin@rcm.test', self::SENHA],
                ['RCM, contador', 'http://rcm.localhost:8000/login', 'contador@rcm.test', self::SENHA],
                ['RCM, faturamento', 'http://rcm.localhost:8000/login', 'faturamento@rcm.test', self::SENHA],
                ['Leme, administrador', 'http://localhost:8000/login', 'admin@leme.test', self::SENHA],
            ],
        );

        $this->newLine();
        $this->components->warn('A RCM está no plano avançado: domínio próprio e marca já no login.');
        $this->components->warn('A Leme está no gratuito: entra por localhost, com a porta da Venda Redonda.');
        $this->components->warn('Rode com: php artisan serve --host=0.0.0.0');

        return self::SUCCESS;
    }

    private function ncmsDeExemplo(): bool
    {
        if (DB::table('ncms')->exists()) {
            return true;
        }

        DB::table('ncms')->insert([
            ['codigo' => '73259910', 'descricao' => 'Outras obras moldadas, de aço', 'valido_nfe' => true, 'vigente_de' => '2022-04-01'],
            ['codigo' => '73259990', 'descricao' => 'Outras obras moldadas de ferro ou aço', 'valido_nfe' => true, 'vigente_de' => '2022-04-01'],
            ['codigo' => '72222000', 'descricao' => 'Barras de aço inoxidável', 'valido_nfe' => true, 'vigente_de' => '2022-04-01'],
            ['codigo' => '34049019', 'descricao' => 'Ceras artificiais', 'valido_nfe' => true, 'vigente_de' => '2022-04-01'],
        ]);

        return true;
    }

    private function criarRcm(): Emitente
    {
        $tenant = Tenant::create([
            'nome' => 'RCM do Brasil', 'nome_curto' => 'RCM',
            // Plano avançado: domínio próprio e marca já na tela de login.
            'slug' => 'rcm', 'dominio' => 'rcm.localhost', 'plano' => PlanoTenant::Transporte,
            'tema' => ['primaria' => '#E8192C', 'neutra' => '#1A1A1A'],
        ]);

        app(TenantAtual::class)->definir($tenant);

        $emitente = Emitente::create([
            'tenant_id' => $tenant->id,
            'razao_social' => 'RCM do Brasil Ltda', 'nome_fantasia' => 'RCM do Brasil',
            'cnpj' => '11222333000181', 'inscricao_estadual' => '123456789012', 'crt' => '3',
            'logradouro' => 'Rua Joao Grigoleto', 'numero' => '83',
            'bairro' => 'Distrito Industrial II', 'codigo_municipio' => '3503307',
            'municipio' => 'Araras', 'uf' => 'SP', 'cep' => '13602200',
            'telefone' => '1930960072', 'email' => 'adm@rcmdobrasil.com.br',
            'chave_pix' => '11222333000181',
            'serie_padrao' => 1,
        ]);

        foreach ([
            ['Marcelo Andrade', 'admin@rcm.test', Perfil::Administrador],
            ['Contador da RCM', 'contador@rcm.test', Perfil::Contador],
            ['Operadora de faturamento', 'faturamento@rcm.test', Perfil::Faturamento],
            ['Almoxarife', 'estoque@rcm.test', Perfil::Estoque],
        ] as [$nome, $email, $perfil]) {
            $this->criarUsuario($tenant, $emitente, $nome, $email, $perfil);
        }

        $perfilFiscal = PerfilFiscal::create([
            'emitente_id' => $emitente->id,
            'nome' => 'Peças microfundidas em aço inox',
            'descricao' => 'Venda de produção própria dentro do estado.',
        ]);

        // Regra anterior, encerrada, para mostrar a vigência funcionando.
        PerfilFiscalRegra::create([
            'perfil_fiscal_id' => $perfilFiscal->id, 'ambito' => 'interna',
            'vigente_de' => '2026-01-01', 'vigente_ate' => '2026-08-02',
            'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_pis' => '01', 'aliquota_pis' => 1.65,
            'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
            'observacao_contador' => 'Regra anterior à obrigatoriedade do IBS/CBS.',
        ]);

        PerfilFiscalRegra::create([
            'perfil_fiscal_id' => $perfilFiscal->id, 'ambito' => 'interna',
            'vigente_de' => '2026-08-03',
            'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_ipi' => '50', 'aliquota_ipi' => 5,
            'cst_pis' => '01', 'aliquota_pis' => 1.65,
            'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
            'cst_ibscbs' => '000', 'cclasstrib' => '000001',
            'aliquota_ibs_uf' => 0.05, 'aliquota_ibs_mun' => 0.05, 'aliquota_cbs' => 0.9,
            'observacao_contador' => 'Inclui IBS, CBS e IS a partir da obrigatoriedade em produção, conforme NT 2025.002-RTC v1.40.',
        ]);

        $natureza = NaturezaOperacao::create([
            'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfilFiscal->id,
            'descricao' => 'Venda de produção própria',
            'cfop_interno' => '5101', 'cfop_interestadual' => '6101',
        ]);

        $destinatarios = [];
        foreach ([
            ['11444777000161', 'Metalurgica Piracicaba Ltda', 'MetalPira', '1', '111222333444', 'Piracicaba', '3538709', '13400000'],
            ['12ABC34501DE35', 'Distribuidora Rio Claro SA', 'DistRC', '2', null, 'Rio Claro', '3543907', '13500000'],
        ] as [$doc, $razao, $fantasia, $ind, $ie, $mun, $cmun, $cep]) {
            $destinatarios[] = Pessoa::create([
                'emitente_id' => $emitente->id, 'tipo_pessoa' => 'J',
                'documento' => $doc, 'razao_social' => $razao, 'nome_fantasia' => $fantasia,
                'ind_ie_dest' => $ind, 'inscricao_estadual' => $ie,
                'logradouro' => 'Avenida Industrial', 'numero' => '450', 'bairro' => 'Distrito Industrial',
                'codigo_municipio' => $cmun, 'municipio' => $mun, 'uf' => 'SP', 'cep' => $cep,
                'e_cliente' => true,
            ]);
        }

        // CNPJ próprio: a Metalúrgica já ocupa o 11444777000161 neste emitente,
        // e um documento só pode ter um cadastro, com vários papéis.
        Pessoa::create([
            'emitente_id' => $emitente->id, 'tipo_pessoa' => 'J',
            'documento' => '22334445000060', 'razao_social' => 'Transportes Leme Ltda',
            'ind_ie_dest' => '2', 'logradouro' => 'Rodovia SP-191', 'numero' => '1200',
            'bairro' => 'Centro', 'codigo_municipio' => '3526704', 'municipio' => 'Leme',
            'uf' => 'SP', 'cep' => '13610000',
            'e_transportadora' => true, 'placa' => 'ABC1D23', 'placa_uf' => 'SP', 'rntc' => '12345678',
        ]);

        $estoque = app(StockService::class);
        $produtos = [];

        foreach ([
            ['PC-001', 'Peça microfundida em aço inox 316L', '73259910', 'PC', 'PC', 1, 145.90, 0.48, 50, 500, 62.40],
            ['PC-002', 'Disco usinado em aço carbono', '73259990', 'PC', 'PC', 1, 89.50, 1.25, 100, 55, 38.90],
            ['PC-003', 'Conjunto técnico microfundido', '73259910', 'CX', 'PC', 12, 1240.00, 14.80, 10, 32, 540.00],
        ] as [$cod, $desc, $ncm, $uc, $ut, $fator, $preco, $peso, $minimo, $saldo, $custo]) {
            $p = Produto::create([
                'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfilFiscal->id,
                'codigo' => $cod, 'descricao' => $desc, 'ncm' => $ncm,
                'unidade_comercial' => $uc, 'unidade_tributavel' => $ut, 'fator_conversao' => $fator,
                'origem' => '0', 'preco_venda' => $preco, 'peso_bruto' => $peso,
                'peso_liquido' => round($peso * 0.95, 3), 'estoque_minimo' => $minimo,
            ]);
            $estoque->entrada($p, $saldo, $custo, 'NF 8821 · Metalurgica Piracicaba');
            $produtos[] = $p;
        }

        // Uma saída e um estorno, para o Kardex contar uma história.
        $estoque->saida($produtos[1], 30, 'NF-e 1.479');
        $estoque->estornar($produtos[1], 30, 'NF-e 1.479 cancelada');

        $calc = app(CalcularNota::class);

        // Rascunho, para você mexer.
        $rascunho = Nota::create([
            'emitente_id' => $emitente->id, 'pessoa_id' => $destinatarios[0]->id,
            'natureza_operacao_id' => $natureza->id, 'natureza_operacao' => $natureza->descricao,
            'serie' => 1, 'ambiente' => 'homologacao', 'data_emissao' => now(), 'mod_frete' => '3',
        ]);
        $calc->adicionarItem($rascunho, $produtos[0], 12);
        $calc->adicionarItem($rascunho, $produtos[1], 8);
        $calc->recalcular($rascunho->fresh(['itens', 'destinatario']));

        // Uma autorizada, para ver DANFE, eventos e a linha do tempo.
        // Marcada à mão: sem certificado real não há como transmitir de fato.
        $autorizada = Nota::create([
            'emitente_id' => $emitente->id, 'pessoa_id' => $destinatarios[1]->id,
            'natureza_operacao_id' => $natureza->id, 'natureza_operacao' => $natureza->descricao,
            'serie' => 1, 'ambiente' => 'homologacao', 'data_emissao' => now()->subDays(2),
            'mod_frete' => '1',
        ]);
        $calc->adicionarItem($autorizada, $produtos[2], 3);
        $calc->recalcular($autorizada->fresh(['itens', 'destinatario']));
        $autorizada->forceFill([
            'numero' => 1480,
            'chave_acesso' => '35260911222333000181550010000014801033717992',
            'status' => NFeStatus::Autorizada, 'c_stat' => '100',
            'x_motivo' => 'Autorizado o uso da NF-e',
            'protocolo' => '135260000123456', 'autorizada_em' => now()->subDays(2),
        ])->save();

        NotaEvento::create([
            'nota_id' => $autorizada->id, 'emitente_id' => $emitente->id,
            'tipo' => '110110', 'sequencia' => 1,
            'correcao' => 'Onde se le conjunto tecnico, leia-se conjunto tecnico microfundido em aco inox.',
            'protocolo' => '135260000777666', 'c_stat' => '135',
            'x_motivo' => 'Evento registrado e vinculado a NF-e', 'homologado_em' => now()->subDay(),
        ]);

        $this->criarFinanceiro($emitente, $destinatarios[1]);

        return $emitente;
    }

    /**
     * Financeiro de exemplo: um vencido, um a vencer, um pago e uma venda
     * parcelada. É o suficiente para a tela mostrar o que ela faz.
     */
    private function criarFinanceiro(Emitente $emitente, Pessoa $cliente): void
    {
        $conta = ContaFinanceira::create([
            'emitente_id' => $emitente->id,
            'nome' => 'Conta movimento',
            'banco' => 'Banco do Brasil',
            'saldo_inicial_centavos' => 4_200_000,
            'padrao' => true,
        ]);

        $despesas = CentroCusto::create([
            'emitente_id' => $emitente->id, 'codigo' => '021', 'nome' => 'Despesas',
            'natureza' => 'despesa', 'grupo' => true, 'essencial' => true,
        ]);

        $centros = [];
        foreach ([['021.001', 'Energia', true], ['021.002', 'Frete', true], ['021.003', 'Confraternização', false]] as [$codigo, $nome, $essencial]) {
            $centros[$nome] = CentroCusto::create([
                'emitente_id' => $emitente->id, 'pai_id' => $despesas->id,
                'codigo' => $codigo, 'nome' => $nome, 'natureza' => 'despesa',
                'essencial' => $essencial,
            ]);
        }

        foreach ([
            ['Energia de agosto', 'CPFL Paulista', 384_512, -6, $centros['Energia'], null],
            ['Frete da coleta 8821', 'Transportes Leme', 127_000, 3, $centros['Frete'], null],
            ['Energia de julho', 'CPFL Paulista', 351_990, -38, $centros['Energia'], $conta],
        ] as [$descricao, $fornecedor, $centavos, $dias, $centro, $contaBaixa]) {
            $titulo = ContaPagar::create([
                'emitente_id' => $emitente->id,
                'centro_custo_id' => $centro->id,
                'descricao' => $descricao,
                'fornecedor' => $fornecedor,
                'valor_centavos' => $centavos,
                'vencimento' => now()->addDays($dias),
            ]);

            if ($contaBaixa !== null) {
                app(BaixaService::class)->pagar($titulo, $contaBaixa, now()->addDays($dias));
            }
        }

        $fatura = Fatura::create([
            'emitente_id' => $emitente->id,
            'pessoa_id' => $cliente->id,
            'titulo' => 'Venda 1480',
        ]);

        foreach ([[1, 130_200, -4], [2, 130_200, 26], [3, 130_200, 56]] as [$numero, $centavos, $dias]) {
            FaturaParcela::create([
                'fatura_id' => $fatura->id, 'numero' => $numero,
                'descricao' => "Venda 1480, parcela {$numero} de 3",
                'valor_centavos' => $centavos,
                'vencimento' => now()->addDays($dias),
            ])->setRelation('fatura', $fatura)->gerarCobrancaPix();
        }
    }

    private function criarLeme(): bool
    {
        $tenant = Tenant::create([
            'nome' => 'Transportes Leme', 'nome_curto' => 'Transportes Leme',
            // Plano gratuito: sem domínio próprio, entra pela porta do produto.
            'slug' => 'leme',
            'tema' => ['primaria' => '#146B3A', 'neutra' => '#0E0E0E'],
        ]);

        app(TenantAtual::class)->definir($tenant);

        $emitente = Emitente::create([
            'tenant_id' => $tenant->id,
            'razao_social' => 'Transportes Leme Ltda', 'nome_fantasia' => 'Transportes Leme',
            'cnpj' => '22334445000060', 'inscricao_estadual' => '999888777', 'crt' => '3',
            'logradouro' => 'Rodovia SP-191', 'numero' => '1200', 'bairro' => 'Centro',
            'codigo_municipio' => '3526704', 'municipio' => 'Leme', 'uf' => 'SP',
            'cep' => '13610000', 'serie_padrao' => 1,
        ]);

        $this->criarUsuario($tenant, $emitente, 'Operador Leme', 'admin@leme.test', Perfil::Administrador);

        Pessoa::create([
            'emitente_id' => $emitente->id, 'tipo_pessoa' => 'J',
            'documento' => '11222333000181', 'razao_social' => 'RCM do Brasil Ltda',
            'ind_ie_dest' => '1', 'inscricao_estadual' => '123456789012',
            'logradouro' => 'Rua Joao Grigoleto', 'numero' => '83', 'bairro' => 'Distrito Industrial II',
            'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP', 'cep' => '13602200',
            'e_cliente' => true,
        ]);

        return true;
    }

    private function criarUsuario(Tenant $tenant, Emitente $emitente, string $nome, string $email, Perfil $perfil): void
    {
        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => $nome, 'email' => $email, 'password' => self::SENHA,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->emitentes()->attach($emitente);

        setPermissionsTeamId($emitente->id);
        $user->assignRole($perfil->value);
    }
}
