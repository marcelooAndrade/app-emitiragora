<?php

namespace App\Livewire\Transporte;

use App\Enums\Transporte\ViagemStatus;
use App\Models\Emitente;
use App\Models\Viagem;
use App\Services\Transporte\Viagens as ServicoViagens;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.fiscal')]
#[Title('Viagens')]
class Viagens extends Component
{
    #[Url(as: 'situacao')]
    public string $status = '';

    #[Url(as: 'q')]
    public string $busca = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('transporte.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function viagens(): Collection
    {
        $busca = trim($this->busca);

        return Viagem::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($busca !== '', function ($q) use ($busca): void {
                $q->where(function ($q) use ($busca): void {
                    $q->where('numero', (int) $busca)
                        ->orWhereHas('veiculo', fn ($v) => $v->where('placa', 'like', '%'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $busca)).'%'))
                        ->orWhereHas('motorista', fn ($m) => $m->where('nome', 'like', '%'.$busca.'%'))
                        ->orWhereHas('notas', fn ($n) => $n->where('numero', $busca)->orWhere('chave', $busca));
                });
            })
            ->with(['motorista', 'veiculo', 'ctes', 'mdfe', 'notas'])
            ->orderByDesc('numero')
            ->limit(200)
            ->get();
    }

    /** Contagem por situação, para as abas. */
    #[Computed]
    public function contagem(): Collection
    {
        return Viagem::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    public function nova(ServicoViagens $viagens): void
    {
        $this->authorize('transporte.operar');
        $viagem = $viagens->criar($this->emitente);
        $this->redirectRoute('viagens.detalhe', ['viagem' => $viagem->getKey()], navigate: true);
    }

    public function render()
    {
        return view('livewire.transporte.viagens', ['situacoes' => ViagemStatus::cases()]);
    }
}
