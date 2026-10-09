<?php

namespace App\Livewire\Pessoas;

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Models\ContaPagar;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\Pessoa;
use App\Services\Integrations\ReceitaWsService;
use App\Services\Integrations\ViaCepService;
use App\Services\Pessoas\ValidarPessoa;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Clientes, fornecedores e transportadoras, no padrão de listagem de
 * 09/10/2026: a lista na largura toda, e cadastrar ou editar numa janela
 * por cima dela.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Clientes')]
class Cadastro extends Component
{
    use WithPagination;

    public bool $formularioAberto = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editandoId = null;

    public string $busca = '';

    public string $papel = 'todos';

    /** Situação cadastral quando diferente de ATIVA. Avisa, não bloqueia. */
    public ?string $avisoSituacao = null;

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('pessoa.ver');
        $this->limparFormulario();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function pessoas()
    {
        return Pessoa::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->when($this->papel === 'clientes', fn ($q) => $q->clientes())
            ->when($this->papel === 'fornecedores', fn ($q) => $q->fornecedores())
            ->when($this->papel === 'transportadoras', fn ($q) => $q->transportadoras())
            ->when($this->busca !== '', function ($q) {
                $termo = '%'.$this->busca.'%';
                $q->where(fn ($s) => $s->where('razao_social', 'like', $termo)
                    ->orWhere('nome_fantasia', 'like', $termo)
                    ->orWhere('documento', 'like', $termo));
            })
            ->orderByDesc('ativo')
            ->orderBy('razao_social')
            ->paginate(15);
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedPapel(): void
    {
        $this->resetPage();
    }

    public function novo(): void
    {
        $this->authorize('pessoa.gerenciar');
        $this->limparFormulario();
        $this->formularioAberto = true;
    }

    public function fecharFormulario(): void
    {
        $this->limparFormulario();
        $this->formularioAberto = false;
    }

    /**
     * Cadastro que nunca entrou em documento some de vez. O que já é tomador
     * de CT-e, cliente de fatura ou fornecedor de conta a pagar só é
     * inativado: apagar deixaria esses documentos sem nome.
     */
    public function excluir(int $id): void
    {
        $this->authorize('pessoa.gerenciar');
        $pessoa = Pessoa::query()->where('emitente_id', $this->emitente->getKey())->findOrFail($id);
        $nome = $pessoa->nome_fantasia ?: $pessoa->razao_social;
        $usado = Cte::where('tomador_pessoa_id', $pessoa->id)->exists()
            || Fatura::where('pessoa_id', $pessoa->id)->exists()
            || ContaPagar::where('pessoa_id', $pessoa->id)->exists();
        if ($usado) {
            $pessoa->update(['ativo' => false]);
            session()->flash('sucesso', "{$nome} já está em CT-e, fatura ou conta a pagar: foi inativado.");
        } else {
            $pessoa->delete();
            session()->flash('sucesso', "{$nome} excluído.");
        }
        unset($this->pessoas);
    }

    public function reativar(int $id): void
    {
        $this->authorize('pessoa.gerenciar');
        Pessoa::query()->where('emitente_id', $this->emitente->getKey())->findOrFail($id)->update(['ativo' => true]);
        unset($this->pessoas);
    }

    public function limparFormulario(): void
    {
        $this->reset('editandoId', 'avisoSituacao');
        $this->resetErrorBag();

        $this->form = [
            'tipo_pessoa' => TipoPessoa::Juridica->value,
            'documento' => '',
            'razao_social' => '',
            'nome_fantasia' => '',
            'ind_ie_dest' => IndIEDest::Contribuinte->value,
            'inscricao_estadual' => '',
            'inscricao_municipal' => '',
            'suframa' => '',
            'consumidor_final' => false,
            'logradouro' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'codigo_municipio' => '',
            'municipio' => '',
            'uf' => '',
            'cep' => '',
            'telefone' => '',
            'email' => '',
            'observacoes' => '',
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_transportadora' => false,
            'placa' => '',
            'placa_uf' => '',
            'rntc' => '',
            'ativo' => true,
        ];
    }

    public function editar(int $id): void
    {
        $this->authorize('pessoa.ver');

        $pessoa = Pessoa::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->findOrFail($id);

        $this->editandoId = $pessoa->id;
        $this->avisoSituacao = null;
        $this->resetErrorBag();
        $this->form = array_merge($this->form, $pessoa->only(array_keys($this->form)));
        $this->form['tipo_pessoa'] = $pessoa->tipo_pessoa->value;
        $this->form['ind_ie_dest'] = $pessoa->ind_ie_dest->value;
        $this->formularioAberto = true;
    }

    public function buscarCnpj(ReceitaWsService $receita): void
    {
        $this->avisoSituacao = null;

        try {
            $dados = $receita->consultar((string) $this->form['documento']);
        } catch (RuntimeException $e) {
            $this->addError('documento', $e->getMessage());

            return;
        }

        $this->form['razao_social'] = $dados->razaoSocial;
        $this->form['nome_fantasia'] = $dados->nomeFantasia ?? '';
        $this->form['logradouro'] = $dados->logradouro ?? '';
        $this->form['numero'] = $dados->numero ?? '';
        $this->form['complemento'] = $dados->complemento ?? '';
        $this->form['bairro'] = $dados->bairro ?? '';
        $this->form['municipio'] = $dados->municipio ?? '';
        $this->form['uf'] = $dados->uf ?? '';
        $this->form['cep'] = $dados->cep ?? '';
        $this->form['telefone'] = $dados->telefone ?? '';
        $this->form['email'] = $dados->email ?? '';

        if (! $dados->ativa) {
            $this->avisoSituacao = $dados->situacao;
        }

        // A consulta de CNPJ já trouxe o endereço inteiro. O que falta é o
        // código IBGE, que a ReceitaWS não devolve: ele sai da tabela oficial
        // pelo município e UF, e o ViaCEP é só reforço pelo CEP. Por isso o
        // ViaCEP aqui é silencioso: um CEP que ele não conhece, como o de
        // cidade pequena, não pode virar erro vermelho numa consulta que deu
        // certo. Quem viu isso: 47.528.089/0001-27, de Urânia, cujo CEP a
        // ViaCEP não tem. O código IBGE é resolvido de todo jeito, pela tabela.
        $this->enriquecerEnderecoPeloCep(app(ViaCepService::class), silencioso: true);
    }

    public function buscarCep(ViaCepService $viaCep): void
    {
        // O usuário clicou no botão de CEP de propósito, então aqui a falha
        // é dita: se o CEP não existe, ele precisa saber para corrigir.
        $this->enriquecerEnderecoPeloCep($viaCep, silencioso: false);
    }

    private function enriquecerEnderecoPeloCep(ViaCepService $viaCep, bool $silencioso): void
    {
        try {
            $dados = $viaCep->consultar((string) $this->form['cep']);
        } catch (RuntimeException $e) {
            if (! $silencioso) {
                $this->addError('cep', $e->getMessage());
            }

            // Mesmo sem o ViaCEP, o código IBGE ainda pode sair da tabela
            // oficial pelo município e UF que a consulta de CNPJ já preencheu.
            // Antes o método saía aqui e deixava o código vazio à toa.
            $this->completarCodigoIbge();

            return;
        }

        $this->form['logradouro'] = $dados->logradouro ?: $this->form['logradouro'];
        $this->form['bairro'] = $dados->bairro ?: $this->form['bairro'];
        $this->form['municipio'] = $dados->municipio ?: $this->form['municipio'];
        $this->form['uf'] = $dados->uf ?: $this->form['uf'];

        // Preserva o que já estava, em vez de apagar: o ViaCEP nem sempre
        // devolve o IBGE, e antes um CEP sem IBGE zerava um código correto
        // que a pessoa tinha acabado de digitar.
        $this->form['codigo_municipio'] = $dados->codigoIbge ?: $this->form['codigo_municipio'];

        $this->completarCodigoIbge();
    }

    /**
     * Resolve o código IBGE pela tabela oficial, a partir de município e UF.
     *
     * A tabela vem de `fiscal:importar-municipios` e é a própria lista do
     * IBGE, então ela manda sempre que souber responder: é o que garante o
     * par consistente que a decisão de 10/09 protege, inclusive no caso em
     * que a consulta de CNPJ troca o município e deixaria para trás o código
     * da cidade anterior.
     *
     * Quando a tabela não sabe, seja porque está vazia ou porque o nome não
     * casa, o que estiver no campo permanece. É aí que digitar na mão vale.
     *
     * A comparação ignora acento e caixa. A ReceitaWS devolve o município em
     * maiúsculas e sem acento ("URANIA"), enquanto a tabela guarda o nome
     * oficial ("Urânia"). Em MySQL a collation padrão casaria os dois, mas
     * em SQLite, que é o banco de teste e o local, a comparação é sensível a
     * acento e o par não fechava, deixando o código IBGE vazio para cidade
     * com acento no nome. Normalizar em PHP resolve nos dois bancos, sem DDL
     * específico, e mantém as migrations portáveis como o projeto exige.
     */
    private function completarCodigoIbge(): void
    {
        $municipio = trim((string) $this->form['municipio']);
        $uf = strtoupper(trim((string) $this->form['uf']));

        if ($municipio === '' || $uf === '') {
            return;
        }

        $alvo = $this->normalizarNomeMunicipio($municipio);

        $codigo = DB::table('municipios')
            ->where('uf', $uf)
            ->get(['nome', 'codigo_ibge'])
            ->first(fn ($m): bool => $this->normalizarNomeMunicipio($m->nome) === $alvo)
            ?->codigo_ibge;

        if ($codigo !== null) {
            $this->form['codigo_municipio'] = (string) $codigo;
        }
    }

    private function normalizarNomeMunicipio(string $nome): string
    {
        return Str::of($nome)->ascii()->lower()->trim()->value();
    }

    public function salvar(ValidarPessoa $validador): void
    {
        $this->authorize('pessoa.gerenciar');

        // Quem digitou município e UF na mão, sem passar pelo CEP, também
        // ganha o código pela tabela oficial antes da validação cobrar.
        $this->completarCodigoIbge();

        $dados = $this->form;
        $dados['inscricao_estadual'] = blank($dados['inscricao_estadual']) ? null : $dados['inscricao_estadual'];
        $dados['observacoes'] = blank($dados['observacoes']) ? null : trim((string) $dados['observacoes']);

        $validador->validar($dados);

        $dados['emitente_id'] = $this->emitente->getKey();

        if ($this->editandoId !== null) {
            Pessoa::query()
                ->where('emitente_id', $this->emitente->getKey())
                ->findOrFail($this->editandoId)
                ->update($dados);
        } else {
            Pessoa::create($dados);
        }

        unset($this->pessoas);
        $this->fecharFormulario();
        session()->flash('sucesso', 'Cadastro salvo.');
    }

    public function render()
    {
        return view('livewire.pessoas.cadastro');
    }
}
