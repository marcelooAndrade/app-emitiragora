<?php

namespace App\Livewire\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Models\ViagemNota;
use App\Services\Transporte\AverbacaoAtm;
use App\Services\Transporte\Ciot\ServicoCiot;
use App\Services\Transporte\ContratosFrete;
use App\Services\Transporte\EmissaoViagem;
use App\Services\Transporte\EventosCte;
use App\Services\Transporte\EventosMdfe;
use App\Services\Transporte\FaturamentoViagem;
use App\Services\Transporte\MontadorCtes;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransmissorMdfe;
use App\Services\Transporte\TransporteException;
use App\Services\Transporte\Viagens;
use App\Support\Dinheiro;
use App\Support\Documento;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A viagem inteira numa tela: notas, viagem e documentos.
 *
 * No Transm o mesmo trabalho passava por três telas (ordem, processo fiscal,
 * faturamento) e seis formulários. Aqui a pessoa solta os XML das NF-e,
 * escolhe motorista e veículo, informa o frete e aperta Emitir; CT-e e MDF-e
 * saem das notas, sem preencher documento fiscal à mão.
 */
#[Layout('components.layouts.fiscal')]
class ViagemDetalhe extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $viagemId;

    /** @var array<int, mixed> */
    public array $arquivos = [];

    /** @var array<int, array{arquivo: string, erro: string}> */
    public array $falhas = [];

    /** @var array<int, string> */
    public array $pesos = [];

    public ?int $motoristaId = null;

    public ?int $veiculoId = null;

    public ?int $reboqueId = null;

    public ?int $reboque2Id = null;

    public string $dataCarregamento = '';

    public string $freteModo = 'tonelada';

    public string $freteValor = '';

    public string $pedagio = '';

    public string $observacoes = '';

    public string $percurso = '';

    public string $averbacoes = '';

    // Contrato com o terceiro: só aparece quando o cavalo não é da frota.
    public string $contratoFrete = '';

    public string $contratoAdiantamento = '';

    public string $contratoIr = '';

    public string $contratoFalta = '';

    public string $contratoSeguroMotorista = '';

    public string $contratoSeguroCarga = '';

    public string $contratoVencimento = '';

    public string $contratoForma = 'pix';

    public string $contratoPix = '';

    public string $contratoBanco = '';

    public string $contratoAgencia = '';

    public string $contratoConta = '';

    public bool $descontosAbertos = false;

    // Dados do CIOT (CIOT para todos, DF-026): gravados com a viagem.
    /** '' = o sistema sugere (lotação com um tomador, fracionada com mais). */
    public string $ciotTipoOperacao = '';

    public string $ciotDistancia = '';

    public string $ciotPrevisao = '';

    public string $ciotTipoCarga = '5';

    public bool $ciotAltoDesempenho = false;

    public bool $ciotRetornoVazio = false;

    // CIOT gerado fora: programa da ANTT, portal da empresa, outra transportadora.
    public bool $ciotInformarAberto = false;

    public string $ciotInformadoNumero = '';

    public string $ciotInformadoResponsavel = '';

    public bool $ciotCancelarAberto = false;

    public string $ciotMotivo = '';

    /** @var array{ok: array<int, string>, erros: array<int, string>}|null */
    public ?array $resultado = null;

    // Cadastro rápido, sem sair da viagem.
    public bool $novoMotoristaAberto = false;

    public string $novoMotoristaNome = '';

    public string $novoMotoristaCpf = '';

    public bool $novoVeiculoAberto = false;

    public string $novoVeiculoPlaca = '';

    public string $novoVeiculoTipo = 'tracao';

    public string $novoVeiculoUf = 'SP';

    public string $novoVeiculoTara = '';

    public string $novoVeiculoRodado = '03';

    public string $novoVeiculoCarroceria = '02';

    // Eventos.
    public ?int $cteCancelarId = null;

    public string $justificativa = '';

    public ?int $cteCorrigirId = null;

    public string $campoCorrecao = 'xObs';

    public string $valorCorrecao = '';

    public bool $cancelarMdfeAberto = false;

    public bool $encerrarAberto = false;

    public string $municipioEncerramento = '';

    public string $dataEncerramento = '';

    public string $vencimentoFatura = '';

    public function mount(int $viagem): void
    {
        $this->authorize('transporte.ver');
        $this->viagemId = $viagem;
        abort_if($this->viagem === null, 404);
        $this->preencher();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /**
     * Matriz e filial dividem o tenant: o escopo global não basta, a viagem
     * precisa ser do emitente em foco.
     */
    #[Computed]
    public function viagem(): ?Viagem
    {
        return Viagem::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->with(['notas', 'ctes.notas', 'ctes.eventos', 'ctes.fatura', 'mdfe', 'contrato', 'ciotVigente', 'motorista', 'veiculo', 'reboque', 'reboque2', 'eventos.user'])
            ->find($this->viagemId);
    }

    #[Computed]
    public function motoristas(): Collection
    {
        return Motorista::where('emitente_id', $this->emitente?->getKey())->where('ativo', true)->orderBy('nome')->get();
    }

    #[Computed]
    public function cavalos(): Collection
    {
        return Veiculo::where('emitente_id', $this->emitente?->getKey())->where('ativo', true)->where('tipo', 'tracao')->orderBy('placa')->get();
    }

    #[Computed]
    public function carretas(): Collection
    {
        return Veiculo::where('emitente_id', $this->emitente?->getKey())->where('ativo', true)->where('tipo', 'reboque')->orderBy('placa')->get();
    }

    /** O cavalo escolhido agora na tela, salvo ou não, é de terceiro? */
    #[Computed]
    public function veiculoTerceiro(): bool
    {
        return (bool) $this->cavalos->firstWhere('id', $this->veiculoId)?->deTerceiro();
    }

    /** O saldo enquanto a pessoa digita, para ela ver a conta fechando. */
    #[Computed]
    public function saldoContrato(): int
    {
        $frete = Dinheiro::emCentavos($this->contratoFrete);

        return $frete - $this->adiantamentoInformado($frete) - Dinheiro::emCentavos($this->contratoIr) - Dinheiro::emCentavos($this->contratoFalta)
            - Dinheiro::emCentavos($this->contratoSeguroMotorista) - Dinheiro::emCentavos($this->contratoSeguroCarga);
    }

    #[Computed]
    public function pendenciasMdfe(): array
    {
        return app(TransmissorMdfe::class)->pendencias($this->viagem);
    }

    /** Frete total que vai ser cobrado: a soma dos CT-e que ainda valem. */
    #[Computed]
    public function freteTotal(): int
    {
        return (int) $this->viagem->ctes->reject(fn (Cte $c): bool => $c->status === CteStatus::Cancelado)->sum('valor_total_centavos');
    }

    public function updatedArquivos(Viagens $viagens): void
    {
        $this->authorize('transporte.operar');
        $this->validate(['arquivos.*' => ['file', 'max:20480', 'extensions:xml']], attributes: ['arquivos.*' => 'arquivo']);

        $this->falhas = [];
        $incluidas = 0;
        foreach ($this->arquivos as $arquivo) {
            try {
                $viagens->adicionarNota($this->viagem, (string) file_get_contents($arquivo->getRealPath()));
                $incluidas++;
            } catch (TransporteException $e) {
                $this->falhas[] = ['arquivo' => $arquivo->getClientOriginalName(), 'erro' => $e->getMessage()];
            }
            unset($this->viagem);
        }
        $this->reset('arquivos');
        $this->preencherPesos();

        if ($incluidas > 0) {
            session()->flash('sucesso', $incluidas === 1 ? 'NF-e incluída.' : "{$incluidas} NF-e incluídas.");
        }
    }

    public function removerNota(int $notaId, Viagens $viagens): void
    {
        $this->authorize('transporte.operar');
        $nota = $this->viagem->notas->firstWhere('id', $notaId);
        abort_if($nota === null, 404);
        $this->executar(fn () => $viagens->removerNota($this->viagem, $nota));
        $this->preencherPesos();
    }

    public function salvarPeso(int $notaId, Viagens $viagens): void
    {
        $this->authorize('transporte.operar');
        $nota = $this->viagem->notas->firstWhere('id', $notaId);
        abort_if($nota === null, 404);
        $peso = (float) str_replace(['.', ','], ['', '.'], (string) ($this->pesos[$notaId] ?? '0'));
        $this->executar(fn () => $viagens->definirPeso($this->viagem, $nota, $peso));
        $this->preencherPesos();
    }

    public function salvarViagem(MontadorCtes $montador): void
    {
        $this->authorize('transporte.operar');
        $viagem = $this->viagem;
        $ids = fn (Collection $c): string => $c->pluck('id')->implode(',') ?: '0';

        $this->validate([
            'motoristaId' => ['nullable', 'integer', 'in:'.$ids($this->motoristas)],
            'veiculoId' => ['nullable', 'integer', 'in:'.$ids($this->cavalos)],
            'reboqueId' => ['nullable', 'integer', 'in:'.$ids($this->carretas)],
            'reboque2Id' => ['nullable', 'integer', 'in:'.$ids($this->carretas), 'different:reboqueId'],
            'dataCarregamento' => ['required', 'date'],
            'freteModo' => ['required', 'in:tonelada,fechado'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'ciotTipoOperacao' => ['nullable', 'in:1,2'],
            'ciotDistancia' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'ciotPrevisao' => ['nullable', 'date', 'after_or_equal:dataCarregamento'],
            'ciotTipoCarga' => ['required', 'integer', 'between:1,12'],
        ], ['reboque2Id.different' => 'Escolha carretas diferentes.', 'ciotPrevisao.after_or_equal' => 'A entrega não pode ser antes do carregamento.'], [
            'motoristaId' => 'motorista', 'veiculoId' => 'cavalo', 'reboqueId' => 'carreta', 'reboque2Id' => 'segunda carreta',
            'dataCarregamento' => 'data de carregamento', 'ciotDistancia' => 'distância', 'ciotPrevisao' => 'previsão de entrega',
            'ciotTipoCarga' => 'tipo de carga',
        ]);

        $mdfeEmitido = in_array($viagem->mdfe?->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado], true);
        if ($mdfeEmitido) {
            $this->addError('viagem', 'O MDF-e já foi emitido: motorista e veículo não mudam mais nesta viagem.');

            return;
        }

        $dados = [
            'motorista_id' => $this->motoristaId,
            'veiculo_id' => $this->veiculoId,
            'reboque_id' => $this->reboqueId,
            'reboque2_id' => $this->reboque2Id,
            'data_carregamento' => $this->dataCarregamento,
            'observacoes' => trim($this->observacoes) ?: null,
        ];
        // O CIOT foi declarado com estes dados: depois dele registrado, não
        // mudam mais (carga lotação não admite retificação, DCS regra B121).
        if (! $viagem->ciotVigente?->registrado()) {
            $dados += [
                'tipo_operacao' => $this->ciotTipoOperacao ?: null,
                'distancia_km' => $this->ciotDistancia !== '' ? (int) $this->ciotDistancia : null,
                'previsao_entrega' => $this->ciotPrevisao ?: null,
                'tipo_carga' => (int) $this->ciotTipoCarga,
                'alto_desempenho' => $this->ciotAltoDesempenho,
                'retorno_vazio' => $this->ciotRetornoVazio,
            ];
        }
        // O frete está dentro do CT-e: depois de autorizado ele não muda.
        if (! $viagem->travada()) {
            $valor = Dinheiro::emCentavos($this->freteValor);
            $dados += [
                'frete_modo' => $this->freteModo,
                'frete_tonelada_centavos' => $this->freteModo === 'tonelada' ? $valor : 0,
                'frete_fechado_centavos' => $this->freteModo === 'fechado' ? $valor : 0,
                'pedagio_centavos' => Dinheiro::emCentavos($this->pedagio),
            ];
        }
        $viagem->update($dados);
        if (! $viagem->travada()) {
            $montador->montar($viagem);
        }
        unset($this->viagem, $this->pendenciasMdfe, $this->freteTotal);

        // Um botão só: com veículo de terceiro, o contrato vai junto.
        $comContrato = $this->veiculoTerceiro && filled($this->contratoFrete) && ! $this->viagem->ciotVigente?->registrado();
        if ($comContrato && ! $this->gravarContrato()) {
            return;
        }
        session()->flash('sucesso', $comContrato ? 'Viagem e contrato do frete salvos.' : 'Viagem salva.');
    }

    public function salvarContrato(): void
    {
        $this->authorize('transporte.operar');
        if ($this->gravarContrato()) {
            session()->flash('sucesso', 'Contrato do frete salvo. Adiantamento e saldo já estão em Contas a pagar.');
        }
    }

    /** Grava a viagem e pede o CIOT à empresa configurada. */
    public function gerarCiot(ServicoCiot $ciot): void
    {
        $this->authorize('transporte.operar');
        $this->salvarViagem(app(MontadorCtes::class));
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $gerado = null;
        if ($this->executar(function () use ($ciot, &$gerado): void {
            $gerado = $ciot->garantir($this->viagem, Auth::user());
        }, 'ciot')) {
            session()->flash($gerado->registrado() ? 'sucesso' : 'aviso', $gerado->registrado()
                ? "CIOT {$gerado->numeroCompleto()} gerado. Vai no MDF-e."
                : 'A empresa aceitou o pedido do CIOT e ainda não devolveu o número. Clique em Consultar CIOT em instantes.');
        }
    }

    public function informarCiot(ServicoCiot $ciot): void
    {
        $this->authorize('transporte.operar');
        if ($this->executar(fn () => $ciot->informar($this->viagem, $this->ciotInformadoNumero, $this->ciotInformadoResponsavel, Auth::user()), 'ciot')) {
            $this->reset('ciotInformarAberto', 'ciotInformadoNumero', 'ciotInformadoResponsavel');
            session()->flash('sucesso', 'CIOT informado. Vai no MDF-e.');
        }
    }

    public function cancelarCiot(ServicoCiot $ciot): void
    {
        $this->authorize('transporte.cancelar');
        $atual = $this->viagem->ciotVigente;
        abort_if($atual === null, 404);
        if ($this->executar(fn () => $ciot->cancelar($atual, $this->ciotMotivo, Auth::user()), 'ciot')) {
            $this->reset('ciotCancelarAberto', 'ciotMotivo');
            session()->flash('sucesso', "CIOT {$atual->numeroCompleto()} cancelado. Corrija o que precisar e gere outro.");
        }
    }

    /** Para quando o encerramento junto com o MDF-e falhou na empresa. */
    public function encerrarCiot(ServicoCiot $ciot): void
    {
        $this->authorize('transporte.operar');
        $atual = $this->viagem->ciotVigente;
        abort_if($atual === null, 404);
        if ($this->executar(fn () => $ciot->encerrar($atual, Auth::user()), 'ciot')) {
            session()->flash('sucesso', "CIOT {$atual->numeroCompleto()} encerrado.");
        }
    }

    public function averbarCte(int $cteId, AverbacaoAtm $averbacao): void
    {
        $this->authorize('transporte.operar');
        $cte = $this->viagem->ctes->firstWhere('id', $cteId);
        abort_if($cte === null, 404);
        if ($this->executar(fn () => $averbacao->averbar($cte, Auth::user()))) {
            $this->averbacoes = implode(', ', (array) ($this->viagem->mdfe?->seguro['averbacoes'] ?? []));
            $cte->refresh();
            session()->flash($cte->averbacao_status === 'aprovada' ? 'sucesso' : 'aviso', $cte->averbacao_status === 'aprovada'
                ? "CT-e {$cte->numeroFormatado()} averbado: {$cte->averbacao_numero}."
                : "A AT&M recusou: {$cte->averbacao_mensagem}");
        }
    }

    public function definirTomador(int $cteId, string $tipo): void
    {
        $this->authorize('transporte.operar');
        $cte = $this->viagem->ctes->firstWhere('id', $cteId);
        abort_if($cte === null || ! in_array($tipo, array_map('strval', array_keys(Cte::TOMADORES)), true), 404);
        if (! $cte->status->transmissivel()) {
            $this->addError('documentos', 'Este CT-e já está na SEFAZ e o tomador não muda mais.');

            return;
        }
        $cte->update(['tomador_tipo' => $tipo]);
        unset($this->viagem);
    }

    public function emitir(EmissaoViagem $emissao): void
    {
        $this->authorize('transporte.operar');
        $this->salvarViagem(app(MontadorCtes::class));
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $this->salvarMdfeRascunho();
        $this->resultado = $emissao->emitir($this->viagem, Auth::user());
        unset($this->viagem, $this->pendenciasMdfe, $this->freteTotal);
        // As averbações que a AT&M devolveu durante a emissão aparecem no campo.
        $this->averbacoes = implode(', ', (array) ($this->viagem->mdfe?->seguro['averbacoes'] ?? []));
        session()->forget('sucesso');
    }

    public function salvarMdfeRascunho(): void
    {
        $this->authorize('transporte.operar');
        $mdfe = $this->viagem->mdfe;
        if ($mdfe === null && $this->viagem->ctes->contains(fn (Cte $c): bool => $c->status === CteStatus::Autorizado)) {
            try {
                $mdfe = app(TransmissorMdfe::class)->preparar($this->viagem);
            } catch (TransporteException) {
                return;
            }
        }
        if ($mdfe === null || ! $mdfe->status->transmissivel()) {
            return;
        }
        $ufs = collect(preg_split('/[\s,;]+/', mb_strtoupper($this->percurso)))->filter(fn ($uf): bool => preg_match('/^[A-Z]{2}$/', (string) $uf) === 1)->values()->all();
        $averbacoes = collect(preg_split('/[\s,;]+/', $this->averbacoes))->map(fn ($a): string => mb_substr(trim((string) $a), 0, 40))->filter()->values()->all();
        $seguro = $mdfe->seguro;
        if (is_array($seguro)) {
            $seguro['averbacoes'] = $averbacoes;
        }
        $mdfe->update(['percurso_ufs' => $ufs, 'seguro' => $seguro]);
        unset($this->viagem);
    }

    public function consultarCte(int $cteId, TransmissorCte $transmissor): void
    {
        $this->authorize('transporte.operar');
        $cte = $this->viagem->ctes->firstWhere('id', $cteId);
        abort_if($cte === null, 404);
        $this->executar(fn () => $transmissor->consultar($cte));
    }

    public function consultarMdfe(TransmissorMdfe $transmissor): void
    {
        $this->authorize('transporte.operar');
        abort_if($this->viagem->mdfe === null, 404);
        $this->executar(fn () => $transmissor->consultar($this->viagem->mdfe));
    }

    public function abrirCancelamento(int $cteId): void
    {
        $this->resetErrorBag();
        $this->reset('justificativa', 'cteCorrigirId');
        $this->cteCancelarId = $cteId;
    }

    public function cancelarCte(EventosCte $eventos): void
    {
        $this->authorize('transporte.cancelar');
        $cte = $this->viagem->ctes->firstWhere('id', $this->cteCancelarId);
        abort_if($cte === null, 404);
        if ($this->executar(fn () => $eventos->cancelar($cte, $this->justificativa, Auth::user()), 'justificativa')) {
            $this->reset('cteCancelarId', 'justificativa');
            session()->flash('sucesso', "CT-e {$cte->numeroFormatado()} cancelado.");
        }
    }

    public function abrirCorrecao(int $cteId): void
    {
        $this->resetErrorBag();
        $this->reset('valorCorrecao', 'cteCancelarId');
        $this->cteCorrigirId = $cteId;
    }

    public function corrigirCte(EventosCte $eventos): void
    {
        $this->authorize('transporte.operar');
        $cte = $this->viagem->ctes->firstWhere('id', $this->cteCorrigirId);
        abort_if($cte === null, 404);
        if ($this->executar(fn () => $eventos->cartaCorrecao($cte, $this->campoCorrecao, $this->valorCorrecao, Auth::user()), 'valorCorrecao')) {
            $this->reset('cteCorrigirId', 'valorCorrecao');
            session()->flash('sucesso', 'Carta de correção registrada.');
        }
    }

    public function cancelarMdfe(EventosMdfe $eventos): void
    {
        $this->authorize('transporte.cancelar');
        abort_if($this->viagem->mdfe === null, 404);
        if ($this->executar(fn () => $eventos->cancelar($this->viagem->mdfe, $this->justificativa, Auth::user()), 'justificativa')) {
            $this->reset('cancelarMdfeAberto', 'justificativa');
            session()->flash('sucesso', 'MDF-e cancelado.');
        }
    }

    public function encerrarMdfe(EventosMdfe $eventos): void
    {
        $this->authorize('transporte.operar');
        abort_if($this->viagem->mdfe === null, 404);
        if ($this->executar(fn () => $eventos->encerrar($this->viagem->mdfe, $this->municipioEncerramento ?: null, $this->dataEncerramento ?: null, Auth::user()), 'encerramento')) {
            $this->reset('encerrarAberto');
            session()->flash('sucesso', 'MDF-e encerrado. Viagem concluída.');
        }
    }

    public function faturar(FaturamentoViagem $faturamento): void
    {
        $this->authorize('transporte.operar');
        $faturas = null;
        if ($this->executar(function () use ($faturamento, &$faturas): void {
            $faturas = $faturamento->faturar($this->viagem, $this->vencimentoFatura ?: null, Auth::user());
        }, 'faturamento')) {
            session()->flash('sucesso', $faturas->count() === 1 ? 'Fatura lançada.' : "{$faturas->count()} faturas lançadas, uma por tomador.");
        }
    }

    public function salvarNovoMotorista(): void
    {
        $this->authorize('transporte.operar');
        $this->novoMotoristaCpf = preg_replace('/\D/', '', $this->novoMotoristaCpf);
        $this->validate([
            'novoMotoristaNome' => ['required', 'string', 'min:3', 'max:60'],
            'novoMotoristaCpf' => ['required', 'digits:11', fn ($a, $v, $falhar) => Documento::cpfValido((string) $v) ? null : $falhar('CPF inválido.')],
        ], attributes: ['novoMotoristaNome' => 'nome', 'novoMotoristaCpf' => 'CPF']);
        if (Motorista::where('emitente_id', $this->emitente->getKey())->where('cpf', $this->novoMotoristaCpf)->exists()) {
            $this->addError('novoMotoristaCpf', 'Já existe motorista com este CPF.');

            return;
        }
        $motorista = (new Motorista(['nome' => mb_strtoupper(trim($this->novoMotoristaNome)), 'cpf' => $this->novoMotoristaCpf]))
            ->forceFill(['emitente_id' => $this->emitente->getKey()]);
        $motorista->save();
        $this->motoristaId = $motorista->getKey();
        $this->reset('novoMotoristaAberto', 'novoMotoristaNome', 'novoMotoristaCpf');
        unset($this->motoristas);
    }

    public function salvarNovoVeiculo(): void
    {
        $this->authorize('transporte.operar');
        $this->novoVeiculoPlaca = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->novoVeiculoPlaca));
        $this->validate([
            'novoVeiculoPlaca' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'novoVeiculoTipo' => ['required', 'in:tracao,reboque'],
            'novoVeiculoUf' => ['required', 'size:2'],
            'novoVeiculoTara' => ['required', 'integer', 'min:1', 'max:99999'],
            'novoVeiculoRodado' => ['nullable', 'in:'.implode(',', array_keys(Veiculo::TIPOS_RODADO))],
            'novoVeiculoCarroceria' => ['required', 'in:'.implode(',', array_keys(Veiculo::TIPOS_CARROCERIA))],
        ], ['novoVeiculoPlaca.regex' => 'Placa no formato ABC1D23 ou ABC1234.'], [
            'novoVeiculoPlaca' => 'placa', 'novoVeiculoTara' => 'tara', 'novoVeiculoCarroceria' => 'carroceria',
        ]);
        if (Veiculo::where('emitente_id', $this->emitente->getKey())->where('placa', $this->novoVeiculoPlaca)->exists()) {
            $this->addError('novoVeiculoPlaca', 'Já existe veículo com esta placa.');

            return;
        }
        $veiculo = (new Veiculo([
            'tipo' => $this->novoVeiculoTipo,
            'placa' => $this->novoVeiculoPlaca,
            'uf' => strtoupper($this->novoVeiculoUf),
            'tara_kg' => (int) $this->novoVeiculoTara,
            'tipo_rodado' => $this->novoVeiculoTipo === 'tracao' ? $this->novoVeiculoRodado : null,
            'tipo_carroceria' => $this->novoVeiculoCarroceria,
        ]))->forceFill(['emitente_id' => $this->emitente->getKey()]);
        $veiculo->save();
        if ($veiculo->eTracao()) {
            $this->veiculoId = $veiculo->getKey();
        } elseif ($this->reboqueId === null) {
            $this->reboqueId = $veiculo->getKey();
        } else {
            $this->reboque2Id = $veiculo->getKey();
        }
        $this->reset('novoVeiculoAberto', 'novoVeiculoPlaca', 'novoVeiculoTara');
        unset($this->cavalos, $this->carretas);
    }

    public function render()
    {
        return view('livewire.transporte.viagem-detalhe')
            ->title('Viagem '.$this->viagem?->numeroFormatado());
    }

    /**
     * Roda a ação e mostra o erro do serviço no campo certo. As mensagens do
     * módulo já são escritas para quem opera.
     */
    private function executar(callable $acao, string $campo = 'documentos'): bool
    {
        // O Livewire guarda a bolsa de erros entre requisições: sem limpar,
        // o erro da tentativa anterior continua na tela depois do acerto.
        $this->resetErrorBag($campo);
        try {
            $acao();
        } catch (TransporteException $e) {
            $this->addError($campo, $e->getMessage());

            return false;
        } finally {
            unset($this->viagem, $this->pendenciasMdfe, $this->freteTotal, $this->saldoContrato);
        }

        return true;
    }

    private function gravarContrato(): bool
    {
        $frete = Dinheiro::emCentavos($this->contratoFrete);
        $dados = [
            'frete_centavos' => $frete,
            'adiantamento_centavos' => $this->adiantamentoInformado($frete),
            'imposto_renda_centavos' => Dinheiro::emCentavos($this->contratoIr),
            'falta_mercadoria_centavos' => Dinheiro::emCentavos($this->contratoFalta),
            'seguro_motorista_centavos' => Dinheiro::emCentavos($this->contratoSeguroMotorista),
            'seguro_carga_centavos' => Dinheiro::emCentavos($this->contratoSeguroCarga),
            'vencimento_saldo' => $this->contratoVencimento,
            'forma_pagamento' => $this->contratoForma,
            'chave_pix' => $this->contratoPix,
            'banco_codigo' => $this->contratoBanco,
            'agencia' => $this->contratoAgencia,
            'conta' => $this->contratoConta,
        ];

        $ok = $this->executar(fn () => app(ContratosFrete::class)->salvar($this->viagem, $dados, Auth::user()), 'contrato');
        if ($ok) {
            $this->preencherContrato();
        }

        return $ok;
    }

    /** Adiantamento em branco é o percentual padrão da empresa, como no Transm. */
    private function adiantamentoInformado(int $frete): int
    {
        if (trim($this->contratoAdiantamento) !== '') {
            return Dinheiro::emCentavos($this->contratoAdiantamento);
        }

        return $this->viagem ? app(ContratosFrete::class)->adiantamentoPadrao($this->viagem, $frete) : 0;
    }

    private function preencherContrato(): void
    {
        $s = app(ContratosFrete::class)->sugestao($this->viagem);
        $dinheiro = fn (int $c): string => $c > 0 ? Dinheiro::formatar($c) : '';
        $this->contratoFrete = $dinheiro((int) $s['frete_centavos']);
        $this->contratoAdiantamento = $this->viagem->contrato ? Dinheiro::formatar((int) $s['adiantamento_centavos']) : '';
        $this->contratoIr = $dinheiro((int) $s['imposto_renda_centavos']);
        $this->contratoFalta = $dinheiro((int) $s['falta_mercadoria_centavos']);
        $this->contratoSeguroMotorista = $dinheiro((int) $s['seguro_motorista_centavos']);
        $this->contratoSeguroCarga = $dinheiro((int) $s['seguro_carga_centavos']);
        $this->descontosAbertos = $this->contratoIr.$this->contratoFalta.$this->contratoSeguroMotorista.$this->contratoSeguroCarga !== '';
        $this->contratoVencimento = (string) $s['vencimento_saldo'];
        $this->contratoForma = (string) $s['forma_pagamento'];
        $this->contratoPix = (string) $s['chave_pix'];
        $this->contratoBanco = (string) $s['banco_codigo'];
        $this->contratoAgencia = (string) $s['agencia'];
        $this->contratoConta = (string) $s['conta'];
    }

    private function preencherCiot(): void
    {
        $viagem = $this->viagem;
        $this->ciotTipoOperacao = (string) $viagem->tipo_operacao;
        $this->ciotDistancia = $viagem->distancia_km ? (string) $viagem->distancia_km : '';
        $this->ciotPrevisao = $viagem->previsaoEntrega()->toDateString();
        $this->ciotTipoCarga = (string) ($viagem->tipo_carga ?: 5);
        $this->ciotAltoDesempenho = (bool) $viagem->alto_desempenho;
        $this->ciotRetornoVazio = (bool) $viagem->retorno_vazio;
    }

    private function preencher(): void
    {
        $viagem = $this->viagem;
        $this->motoristaId = $viagem->motorista_id;
        $this->veiculoId = $viagem->veiculo_id;
        $this->reboqueId = $viagem->reboque_id;
        $this->reboque2Id = $viagem->reboque2_id;
        $this->dataCarregamento = $viagem->data_carregamento?->toDateString() ?? today()->toDateString();
        $this->freteModo = $viagem->frete_modo;
        $valor = $viagem->frete_modo === 'fechado' ? $viagem->frete_fechado_centavos : $viagem->frete_tonelada_centavos;
        $this->freteValor = $valor > 0 ? Dinheiro::formatar($valor) : '';
        $this->pedagio = $viagem->pedagio_centavos > 0 ? Dinheiro::formatar($viagem->pedagio_centavos) : '';
        $this->observacoes = (string) $viagem->observacoes;
        $this->percurso = implode(', ', (array) $viagem->mdfe?->percurso_ufs);
        $this->averbacoes = implode(', ', (array) ($viagem->mdfe?->seguro['averbacoes'] ?? []));
        $this->dataEncerramento = today()->toDateString();
        $this->municipioEncerramento = (string) $viagem->ctes->last()?->municipio_fim_codigo;
        $this->preencherContrato();
        $this->preencherCiot();
        $this->preencherPesos();
    }

    private function preencherPesos(): void
    {
        $this->pesos = $this->viagem->notas
            ->mapWithKeys(fn (ViagemNota $n): array => [$n->id => number_format((float) $n->peso_kg, 3, ',', '.')])
            ->all();
    }
}
