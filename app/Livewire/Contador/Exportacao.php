<?php

namespace App\Livewire\Contador;

use App\Enums\Transporte\CteStatus;
use App\Models\Emitente;
use App\Services\Export\PacoteContadorService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.fiscal')]
#[Title('Pacote da contabilidade')]
class Exportacao extends Component
{
    public string $de = '';

    public string $ate = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404);
        $this->authorize('contador.exportar');

        $this->de = now()->subMonth()->startOfMonth()->toDateString();
        $this->ate = now()->subMonth()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return array<string, int> */
    #[Computed]
    public function previa(): array
    {
        [$de, $ate] = $this->periodo();
        $pacote = app(PacoteContadorService::class);

        $ctes = $pacote->ctesDoPeriodo($this->emitente, $de, $ate);
        $autorizados = $ctes->where('status', CteStatus::Autorizado);

        return [
            'autorizados' => $autorizados->count(),
            'cancelados' => $ctes->where('status', CteStatus::Cancelado)->count(),
            'mdfes' => $pacote->mdfesDoPeriodo($this->emitente, $de, $ate)->count(),
            'frete_centavos' => (int) $autorizados->sum('valor_total_centavos'),
        ];
    }

    public function baixar(PacoteContadorService $servico)
    {
        $this->authorize('contador.exportar');

        [$de, $ate] = $this->periodo();

        try {
            $caminho = $servico->gerar($this->emitente, $de, $ate);
        } catch (Throwable $e) {
            $this->addError('periodo', $e->getMessage());

            return null;
        }

        $nome = 'contabilidade-'.$this->emitente->cnpj.'-'.$de->format('Y-m').'.zip';

        return response()->download($caminho, $nome)->deleteFileAfterSend();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodo(): array
    {
        return [
            Carbon::parse($this->de ?: now()->startOfMonth())->startOfDay(),
            Carbon::parse($this->ate ?: now())->endOfDay(),
        ];
    }

    public function render()
    {
        return view('livewire.contador.exportacao');
    }
}
