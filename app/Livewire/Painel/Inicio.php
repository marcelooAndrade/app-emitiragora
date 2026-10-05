<?php

namespace App\Livewire\Painel;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Enums\Transporte\ViagemStatus;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\Mdfe;
use App\Models\Viagem;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel de entrada da transportadora.
 *
 * A ordem da tela é deliberada: primeiro o que exige ação, depois o que
 * aconteceu. Quem abre o sistema de manhã precisa saber qual CT-e travou e
 * qual MDF-e ficou aberto antes de saber quanto de frete saiu no mês.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Painel')]
class Inicio extends Component
{
    /**
     * MDF-e autorizado há mais que isso sem encerrar vira aviso. A SEFAZ
     * recusa MDF-e novo enquanto houver um esquecido aberto há mais de 30
     * dias; 7 dá tempo de encerrar bem antes disso.
     */
    public const DIAS_PARA_ENCERRAR_MDFE = 7;

    /**
     * Números do mês corrente.
     *
     * Propriedade e não computed: são contagens que a tela sempre mostra, e
     * deixá-las públicas permite asserção direta no teste.
     *
     * @var array{ctes:int, frete_centavos:int, cancelados:int, viagens_abertas:int}
     */
    public array $mes = [
        'ctes' => 0,
        'frete_centavos' => 0,
        'cancelados' => 0,
        'viagens_abertas' => 0,
    ];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('relatorio.ver');

        $this->mes = $this->apurarMes();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /**
     * @return array{ctes:int, frete_centavos:int, cancelados:int, viagens_abertas:int}
     */
    private function apurarMes(): array
    {
        $doMes = fn () => Cte::query()->whereBetween('autorizado_em', [now()->startOfMonth(), now()->endOfMonth()]);

        // Cancelado não conta como frete: existiu e foi desfeito. Somá-lo
        // infla o mês e é o tipo de número que ninguém confere depois.
        $autorizados = $doMes()->where('status', CteStatus::Autorizado->value);

        return [
            'ctes' => (clone $autorizados)->count(),
            'frete_centavos' => (int) (clone $autorizados)->sum('valor_total_centavos'),
            'cancelados' => $doMes()->where('status', CteStatus::Cancelado->value)->count(),
            'viagens_abertas' => Viagem::query()
                ->whereIn('status', [ViagemStatus::Rascunho->value, ViagemStatus::Pendente->value])
                ->count(),
        ];
    }

    /**
     * CT-e e MDF-e que pararam no meio do caminho.
     *
     * Em processamento é o mais urgente: a SEFAZ pode já ter autorizado sem
     * que a resposta tenha voltado, então transmitir de novo duplicaria.
     *
     * @return Collection<int, Cte|Mdfe>
     */
    #[Computed]
    public function pendentes(): Collection
    {
        $travados = [CteStatus::EmProcessamento->value, CteStatus::Rejeitado->value];

        return Cte::query()->with('viagem')->whereIn('status', $travados)->latest('updated_at')->limit(10)->get()
            ->concat(Mdfe::query()->with('viagem')->whereIn('status', $travados)->latest('updated_at')->limit(10)->get())
            ->sortByDesc('updated_at')
            ->values();
    }

    /**
     * MDF-e em viagem há mais de uma semana. Quase sempre é viagem que já
     * terminou e ninguém encerrou.
     *
     * @return Collection<int, Mdfe>
     */
    #[Computed]
    public function mdfesParaEncerrar(): Collection
    {
        return Mdfe::query()
            ->with('viagem')
            ->where('status', MdfeStatus::Autorizado->value)
            ->where('autorizado_em', '<', now()->subDays(self::DIAS_PARA_ENCERRAR_MDFE))
            ->orderBy('autorizado_em')
            ->get();
    }

    /**
     * @return Collection<int, Viagem>
     */
    #[Computed]
    public function ultimas(): Collection
    {
        return Viagem::query()
            ->with(['motorista', 'veiculo'])
            ->orderByDesc('data_carregamento')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function certificado(): ?EmitenteCertificado
    {
        return EmitenteCertificado::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)
            ->orderByDesc('valido_ate')
            ->first();
    }

    /**
     * Dias até o certificado vencer. Negativo quando já venceu.
     */
    #[Computed]
    public function diasDeCertificado(): ?int
    {
        return $this->certificado === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->certificado->valido_ate->startOfDay(), false);
    }

    public function render()
    {
        return view('livewire.painel.inicio');
    }
}
