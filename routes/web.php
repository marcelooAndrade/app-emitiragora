<?php

use App\Http\Controllers\EscolherEmitenteController;
use App\Http\Controllers\FaturaPublicaController;
use App\Http\Controllers\LogoTenantController;
use App\Http\Controllers\NotaServicoArquivoController;
use App\Http\Controllers\RaizController;
use App\Http\Controllers\TransporteArquivoController;
use App\Livewire\Certificados\Gerenciar;
use App\Livewire\Contador\Exportacao;
use App\Livewire\Estoque\Painel;
use App\Livewire\Financeiro\CentrosCusto;
use App\Livewire\Financeiro\ContasBancarias;
use App\Livewire\Financeiro\ContasPagar;
use App\Livewire\Financeiro\ContasReceber;
use App\Livewire\Financeiro\Dre;
use App\Livewire\Financeiro\FaturaDetalhe;
use App\Livewire\Financeiro\Faturas;
use App\Livewire\Nfse\Configuracao;
use App\Livewire\Nfse\Notas;
use App\Livewire\Notas\Emissao;
use App\Livewire\Painel\Inicio;
use App\Livewire\Pessoas\Cadastro;
use App\Livewire\Tenancy\Marca;
use App\Livewire\Transporte;
use App\Livewire\Tributacao\Regras;
use App\Livewire\Usuarios\Cadastro as UsuariosCadastro;
use Illuminate\Support\Facades\Route;

// A raiz muda de superfície conforme o host: apresentação no domínio nu
// do produto, aplicação em todo o resto.
Route::get('/', RaizController::class)->name('home');

// Pública de propósito: a tela de login precisa mostrar a logo de quem está
// entrando, e ela roda antes de haver usuário. Não é exposição nova, porque
// quem alcança o host já alcança a tela de login. O isolamento continua vindo
// do host resolvido, não de identificador na URL.
Route::get('logo', LogoTenantController::class)->name('logo');

// A fatura como o cliente a vê. Pública de propósito: o token é o único
// identificador, sorteado por fatura, e a marca vem da empresa dona dela.
Route::get('fatura/{token}', [FaturaPublicaController::class, 'show'])->name('fatura.publica');
Route::get('fatura/{token}/logo', [FaturaPublicaController::class, 'logo'])->name('fatura.publica.logo');

// Página de indicação para contadores. Pública, mesmo padrão da apresentação.
Route::view('contadores', 'contadores')->name('contadores');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Inicio::class)->name('dashboard');

    Route::get('contabilidade', Exportacao::class)->name('contabilidade');
    Route::get('notas', Emissao::class)->name('notas');
    Route::get('importacao', App\Livewire\Importacao\Painel::class)->name('importacao');
    Route::get('estoque', Painel::class)->name('estoque');
    // `Painel` já nomeia o do estoque neste arquivo, então este vai pelo
    // nome completo em vez de um alias que confundiria os dois.
    Route::get('financeiro', App\Livewire\Financeiro\Painel::class)->name('financeiro');
    Route::get('contas-a-pagar', ContasPagar::class)->name('contas-a-pagar');
    Route::get('contas-a-receber', ContasReceber::class)->name('contas-a-receber');
    Route::get('faturas', Faturas::class)->name('faturas');
    Route::get('faturas/{fatura}', FaturaDetalhe::class)->name('faturas.detalhe');
    Route::get('contas-bancarias', ContasBancarias::class)->name('contas-bancarias');
    Route::get('centros-de-custo', CentrosCusto::class)->name('centros-de-custo');
    Route::get('dre', Dre::class)->name('dre');
    Route::get('marca', Marca::class)->name('marca');
    Route::get('produtos', App\Livewire\Produtos\Cadastro::class)->name('produtos');
    Route::get('regras-fiscais', Regras::class)->name('regras-fiscais');
    Route::get('destinatarios', Cadastro::class)->name('destinatarios');
    Route::get('certificados', Gerenciar::class)->name('certificados');
    // `Cadastro` já nomeia o de pessoas neste arquivo, então este vai pelo
    // nome completo.
    Route::get('emitente', App\Livewire\Emitentes\Cadastro::class)->name('emitente');
    Route::get('usuarios', UsuariosCadastro::class)->name('usuarios');
    Route::get('nfse', Configuracao::class)->name('nfse');
    Route::get('notas-servico', Notas::class)->name('notas-servico');
    Route::get('notas-servico/{nota}/pdf', [NotaServicoArquivoController::class, 'pdf'])->name('notas-servico.pdf');
    Route::get('notas-servico/{nota}/xml', [NotaServicoArquivoController::class, 'xml'])->name('notas-servico.xml');

    // Transporte: viagem, CT-e e MDF-e (portado do app-transm).
    Route::get('viagens', Transporte\Viagens::class)->name('viagens');
    Route::get('viagens/{viagem}', Transporte\ViagemDetalhe::class)->whereNumber('viagem')->name('viagens.detalhe');
    Route::get('veiculos', Transporte\Veiculos::class)->name('veiculos');
    Route::get('motoristas', Transporte\Motoristas::class)->name('motoristas');
    Route::get('transporte', Transporte\Configuracao::class)->name('transporte.configuracao');
    Route::get('transporte/cte/{cte}/dacte', [TransporteArquivoController::class, 'dacte'])->name('transporte.dacte');
    Route::get('transporte/cte/{cte}/xml', [TransporteArquivoController::class, 'cteXml'])->name('transporte.cte.xml');
    Route::get('transporte/contrato/{contrato}', [TransporteArquivoController::class, 'contrato'])->name('transporte.contrato');
    Route::get('transporte/contrato/{contrato}/ciot', [TransporteArquivoController::class, 'ciotPdf'])->name('transporte.ciot.pdf');
    Route::get('transporte/mdfe/{mdfe}/damdfe', [TransporteArquivoController::class, 'damdfe'])->name('transporte.damdfe');
    Route::get('transporte/mdfe/{mdfe}/xml', [TransporteArquivoController::class, 'mdfeXml'])->name('transporte.mdfe.xml');

    Route::post('emitente/escolher', EscolherEmitenteController::class)->name('emitente.escolher');
});

require __DIR__.'/settings.php';
