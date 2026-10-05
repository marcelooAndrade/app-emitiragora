<?php

namespace App\Livewire\Tenancy;

use App\Models\Emitente;
use App\Models\Tenant;
use App\Support\EmitenteAtual;
use App\Support\TemaMarca;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Configura a identidade visual do tenant.
 *
 * Todo utilitário do Tailwind aponta para `var(--color-*)`, então salvar aqui
 * repinta o sistema inteiro na próxima requisição, sem recompilar nada.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Marca')]
class Marca extends Component
{
    use WithFileUploads;

    /**
     * As duas logos aceitam o mesmo tipo de arquivo, e não podem divergir.
     * Sem SVG: o `sped-da` não desenha SVG no PDF do DACTE e do DAMDFE, e SVG servido da
     * mesma origem que o sistema é vetor de script.
     */
    private const REGRAS_LOGO = ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'];

    public $logoSistema = null;

    public $logoDocumento = null;

    public string $primaria = '';

    public string $neutra = '';

    public string $nomeCurto = '';

    /** Verdadeiro quando a cor informada precisou ser escurecida. */
    public bool $avisoContraste = false;

    public function mount(): void
    {
        abort_unless($this->tenant !== null, 404);
        $this->authorize('emitente.gerenciar');

        $tema = $this->tenant->tema ?? [];
        $this->primaria = $tema['primaria'] ?? TemaMarca::PRIMARIA_PADRAO;
        $this->neutra = $tema['neutra'] ?? TemaMarca::NEUTRA_PADRAO;
        $this->nomeCurto = $this->tenant->nome_curto ?? '';
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return app(TenantAtual::class)->obter();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return array<int, string> */
    #[Computed]
    public function previaPrimaria(): array
    {
        try {
            return TemaMarca::escalaDe($this->primaria);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /** @return array<int, string> */
    #[Computed]
    public function previaNeutra(): array
    {
        try {
            return TemaMarca::escalaNeutraDe($this->neutra);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Contraste da marca contra o texto que de fato se escreve sobre ela.
     *
     * Medir contra o branco acusava de defeituosa qualquer marca clara,
     * inclusive a do próprio produto, num cenário que o sistema não usa:
     * nenhum componente escreve em branco sobre a primária quando o escuro lê
     * melhor.
     */
    #[Computed]
    public function contrastePrimaria(): ?float
    {
        try {
            return TemaMarca::contrasteEntre($this->primaria, $this->textoSobrePrimaria);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    #[Computed]
    public function textoSobrePrimaria(): string
    {
        try {
            return TemaMarca::textoSobre($this->primaria, $this->neutra);
        } catch (InvalidArgumentException) {
            return '#ffffff';
        }
    }

    public function salvar(): void
    {
        $this->authorize('emitente.gerenciar');

        $this->validate([
            'primaria' => ['required', 'string'],
            'neutra' => ['required', 'string'],
            'nomeCurto' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            // Contraste insuficiente é corrigido, não recusado: dizer ao cliente
            // que a marca dele está errada não é opção.
            $ajustada = TemaMarca::ajustarParaContraste($this->primaria);
            $neutra = TemaMarca::escalaNeutraDe($this->neutra)[900];
        } catch (InvalidArgumentException $e) {
            $this->addError('primaria', $e->getMessage());

            return;
        }

        $this->avisoContraste = strcasecmp($ajustada, ltrim($this->primaria, '#') === $this->primaria ? '#'.$this->primaria : $this->primaria) !== 0;

        $this->tenant->update([
            'nome_curto' => $this->nomeCurto ?: null,
            'tema' => ['primaria' => $ajustada, 'neutra' => $neutra],
        ]);

        $this->primaria = $ajustada;
        $this->neutra = $neutra;
        unset($this->tenant, $this->previaPrimaria, $this->previaNeutra, $this->contrastePrimaria, $this->textoSobrePrimaria);

        session()->flash('sucesso', 'Marca salva. Recarregue para ver o sistema com as novas cores.');
    }

    public function salvarLogoSistema(): void
    {
        $this->authorize('emitente.gerenciar');

        $this->validate([
            'logoSistema' => self::REGRAS_LOGO,
        ]);

        $anterior = $this->tenant->logo_path;
        $path = $this->logoSistema->store('marca/tenant/'.$this->tenant->getKey(), 'fiscal');

        $this->tenant->update(['logo_path' => $path]);
        $this->apagar($anterior);

        $this->logoSistema = null;
        unset($this->tenant);
    }

    public function removerLogoSistema(): void
    {
        $this->authorize('emitente.gerenciar');

        $anterior = $this->tenant->logo_path;
        $this->tenant->update(['logo_path' => null]);
        $this->apagar($anterior);

        unset($this->tenant);
    }

    public function salvarLogoDocumento(): void
    {
        $this->authorize('emitente.gerenciar');

        $this->validate([
            'logoDocumento' => self::REGRAS_LOGO,
        ]);

        $emitente = $this->emitente;
        abort_if($emitente === null, 404);

        $anterior = $emitente->logo_path;
        $path = $this->logoDocumento->store('marca/emitente/'.$emitente->getKey(), 'fiscal');

        $emitente->update(['logo_path' => $path]);
        $this->apagar($anterior);

        $this->logoDocumento = null;
        unset($this->emitente);
    }

    public function removerLogoDocumento(): void
    {
        $this->authorize('emitente.gerenciar');

        $emitente = $this->emitente;
        abort_if($emitente === null, 404);

        $anterior = $emitente->logo_path;
        $emitente->update(['logo_path' => null]);
        $this->apagar($anterior);

        unset($this->emitente);
    }

    /** Logo não é documento fiscal: substituída ou removida, o arquivo vai junto. */
    private function apagar(?string $path): void
    {
        if (filled($path) && Storage::disk('fiscal')->exists($path)) {
            Storage::disk('fiscal')->delete($path);
        }
    }

    public function render()
    {
        return view('livewire.tenancy.marca');
    }
}
