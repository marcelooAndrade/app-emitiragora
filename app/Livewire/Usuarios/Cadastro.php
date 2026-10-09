<?php

namespace App\Livewire\Usuarios;

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use App\Support\TenantAtual;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quem tem acesso a esta empresa, e com qual perfil em cada emitente dela.
 *
 * A unidade é o perfil (`Perfil` enum), não permissão avulsa: decisão da
 * Fase 2, mantida. Um e-mail é uma pessoa em qualquer empresa: cadastrar um
 * e-mail já existente em outro lugar não cria conta nova, só anexa aquele
 * login a esta empresa. Ver
 * docs/superpowers/specs/2026-09-14-gestao-usuarios-design.md.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Usuários')]
class Cadastro extends Component
{
    // Padrão de listagem de 09/10/2026: lista na largura toda, formulário numa janela.
    public bool $formularioAberto = false;

    public string $busca = '';

    /** @var array<string, string> */
    public array $form = ['name' => '', 'email' => '', 'senha' => ''];

    /** @var array<int, string> emitente_id => perfil (vazio = sem acesso) */
    public array $papeis = [];

    public ?int $editandoId = null;

    public bool $emailVerificado = false;

    public bool $contaExistente = false;

    public function mount(): void
    {
        $this->authorize('usuario.gerenciar');
        $this->prepararPapeisVazios();
    }

    /**
     * Os emitentes desta empresa. Toda a tela gira em torno desta lista:
     * é o que separa "usuário desta empresa" de "usuário de qualquer outra".
     *
     * @return Collection<int, Emitente>
     */
    #[Computed]
    public function emitentesDaEmpresa(): Collection
    {
        return Emitente::query()->orderBy('razao_social')->get();
    }

    /**
     * Usuários vinculados a pelo menos um emitente desta empresa.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        $idsDaEmpresa = $this->emitentesDaEmpresa->pluck('id');

        return User::query()
            ->whereHas('emitentes', fn ($q) => $q->whereIn('emitentes.id', $idsDaEmpresa))
            ->with(['emitentes' => fn ($q) => $q->whereIn('emitentes.id', $idsDaEmpresa)])
            ->orderBy('name')
            ->get();
    }

    /** O que a lista mostra: filtrado pela busca por nome ou e-mail. */
    #[Computed]
    public function listados(): Collection
    {
        $termo = mb_strtolower(trim($this->busca));
        if ($termo === '') {
            return $this->usuarios;
        }

        return $this->usuarios->filter(fn (User $u): bool => str_contains(mb_strtolower($u->name), $termo)
            || str_contains(mb_strtolower($u->email), $termo))->values();
    }

    public function fecharFormulario(): void
    {
        $this->resetErrorBag();
        $this->reset('form', 'editandoId', 'emailVerificado', 'contaExistente');
        $this->prepararPapeisVazios();
        $this->formularioAberto = false;
    }

    /**
     * A lixeira desta tela tira o acesso a esta empresa, em todos os
     * emitentes dela; não apaga a conta, que pode ser de outra empresa
     * também. Sem nenhuma outra empresa, a conta fica inativa, para não
     * sobrar um login que entra e não acha nada.
     */
    public function removerAcesso(int $id): void
    {
        $this->authorize('usuario.gerenciar');
        if ($id === auth()->id()) {
            session()->flash('erro', 'Você não pode remover o próprio acesso.');

            return;
        }

        $usuario = User::withoutGlobalScope('tenant')->findOrFail($id);
        $idsDaEmpresa = $this->emitentesDaEmpresa->pluck('id');

        $registrador = app(PermissionRegistrar::class);
        $timeOriginal = $registrador->getPermissionsTeamId();
        foreach ($idsDaEmpresa as $emitenteId) {
            $registrador->setPermissionsTeamId($emitenteId);
            $usuario->syncRoles([]);
        }
        $registrador->setPermissionsTeamId($timeOriginal);
        $usuario->emitentes()->detach($idsDaEmpresa->all());

        if (! $usuario->emitentes()->withoutGlobalScope('tenant')->exists()) {
            $usuario->forceFill(['ativo' => false])->save();
        }

        session()->flash('sucesso', "{$usuario->name} não tem mais acesso a esta empresa.");
        unset($this->usuarios, $this->listados);
    }

    private function prepararPapeisVazios(): void
    {
        $this->papeis = $this->emitentesDaEmpresa->mapWithKeys(fn (Emitente $e): array => [$e->id => ''])->all();
    }

    public function novoUsuario(): void
    {
        $this->resetErrorBag();
        $this->reset('form', 'editandoId', 'emailVerificado', 'contaExistente');
        $this->prepararPapeisVazios();
        $this->formularioAberto = true;
    }

    public function editar(int $id): void
    {
        $usuario = User::withoutGlobalScope('tenant')->findOrFail($id);

        $this->resetErrorBag();
        $this->reset('form', 'emailVerificado', 'contaExistente');
        $this->prepararPapeisVazios();
        $this->formularioAberto = true;

        $this->editandoId = $usuario->id;
        $this->form['name'] = $usuario->name;
        $this->form['email'] = $usuario->email;
        $this->emailVerificado = true;
        $this->contaExistente = true;

        $registrador = app(PermissionRegistrar::class);
        $timeOriginal = $registrador->getPermissionsTeamId();

        foreach ($this->emitentesDaEmpresa as $emitenteDaEmpresa) {
            if (! $usuario->podeAcessar($emitenteDaEmpresa)) {
                continue;
            }

            // `roles()->value('name')`, não a propriedade `roles`: a
            // propriedade cacheia no model na primeira leitura, e o laço
            // muda o time de permissão a cada volta. Sem isso, todo
            // emitente devolveria o papel do primeiro que o laço tocou.
            $registrador->setPermissionsTeamId($emitenteDaEmpresa->id);
            $this->papeis[$emitenteDaEmpresa->id] = (string) ($usuario->roles()->value('name') ?? '');
        }

        // O time de permissão é global no processo: sem restaurar o do
        // administrador logado, as checagens de permissão do resto desta
        // mesma requisição usariam o último emitente do laço acima.
        $registrador->setPermissionsTeamId($timeOriginal);
    }

    /**
     * Só desliga a conta inteira quando este login não tem nenhuma outra
     * empresa: senão, esta empresa estaria bloqueando o acesso a uma
     * empresa que não é dela. Quem quer só tirar o acesso a esta empresa
     * usa "Sem acesso" no perfil, em `salvar()`.
     */
    public function inativar(int $id): void
    {
        $this->authorize('usuario.gerenciar');

        if ($id === auth()->id()) {
            return;
        }

        $usuario = User::withoutGlobalScope('tenant')->findOrFail($id);

        $idsDaEmpresa = $this->emitentesDaEmpresa->pluck('id');
        $temOutraEmpresa = $usuario->emitentes()->withoutGlobalScope('tenant')
            ->whereNotIn('emitentes.id', $idsDaEmpresa)->exists();

        if ($temOutraEmpresa) {
            return;
        }

        $usuario->forceFill(['ativo' => false])->save();
        unset($this->usuarios, $this->listados);
    }

    public function reativar(int $id): void
    {
        $this->authorize('usuario.gerenciar');

        User::withoutGlobalScope('tenant')->findOrFail($id)->forceFill(['ativo' => true])->save();
        unset($this->usuarios, $this->listados);
    }

    public function verificarEmail(): void
    {
        $dados = $this->validate([
            'form.email' => ['required', 'email', 'max:254'],
        ], [], ['form.email' => 'e-mail'])['form'];

        $existente = User::withoutGlobalScope('tenant')->where('email', $dados['email'])->first();

        $this->emailVerificado = true;
        $this->contaExistente = $existente !== null;
        $this->editandoId = $existente?->id;

        if ($existente !== null) {
            $this->form['name'] = $existente->name;
        }
    }

    public function salvar(): void
    {
        $this->authorize('usuario.gerenciar');

        if ($this->contaExistente && (int) $this->editandoId === auth()->id()) {
            $ficaAdministradorEmAlgumEmitenteAqui = collect($this->papeis)->contains(Perfil::Administrador->value);

            if (! $ficaAdministradorEmAlgumEmitenteAqui) {
                $this->addError('form.email', 'Você não pode remover o próprio acesso de Administrador nesta empresa.');

                return;
            }
        }

        $dados = $this->validate([
            'form.email' => ['required', 'email', 'max:254'],
            'form.name' => $this->contaExistente ? ['nullable'] : ['required', 'string', 'min:2', 'max:160'],
            'form.senha' => $this->contaExistente ? ['nullable'] : ['required', 'string', 'min:8'],
        ], [], ['form.email' => 'e-mail', 'form.name' => 'nome', 'form.senha' => 'senha'])['form'];

        $usuario = $this->contaExistente
            ? User::withoutGlobalScope('tenant')->findOrFail($this->editandoId)
            : null;

        // A exigência de ao menos um emitente com perfil vale para quem
        // ainda não tinha acesso nenhum a esta empresa: conta nova, ou
        // conta existente sendo anexada aqui pela primeira vez. Quem já
        // tinha acesso pode reduzir a zero de propósito, para revogar.
        $jaTinhaAcessoNestaEmpresa = $usuario !== null
            && $usuario->emitentes()->withoutGlobalScope('tenant')
                ->whereIn('emitentes.id', $this->emitentesDaEmpresa->pluck('id'))->exists();

        if (! $jaTinhaAcessoNestaEmpresa && collect($this->papeis)->filter(fn (string $p): bool => $p !== '')->isEmpty()) {
            $this->addError('papeis', 'Escolha um perfil em pelo menos um emitente.');

            return;
        }

        $usuario ??= User::create([
            'tenant_id' => app(TenantAtual::class)->id(),
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => $dados['senha'],
            'email_verified_at' => now(),
        ]);

        $registrador = app(PermissionRegistrar::class);
        $timeOriginal = $registrador->getPermissionsTeamId();

        foreach ($this->papeis as $emitenteId => $perfil) {
            if ($perfil === '') {
                $usuario->emitentes()->detach($emitenteId);

                continue;
            }

            $usuario->emitentes()->syncWithoutDetaching([$emitenteId]);

            $registrador->setPermissionsTeamId($emitenteId);
            $usuario->syncRoles([$perfil]);
        }

        // Mesma razão do laço em editar(): sem restaurar, o resto desta
        // requisição checaria permissão pelo último emitente do laço.
        $registrador->setPermissionsTeamId($timeOriginal);

        session()->flash('sucesso', 'Usuário salvo.');
        $this->fecharFormulario();
        unset($this->usuarios, $this->listados);
    }

    public function render()
    {
        return view('livewire.usuarios.cadastro');
    }
}
