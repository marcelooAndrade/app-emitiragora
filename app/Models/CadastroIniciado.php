<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Cadastro começado, à espera do clique no link do e-mail. Ver a migration
 * de `cadastros_iniciados` e o CadastroController.
 *
 * Sem escopo de tenant: ninguém aqui tem empresa ainda.
 */
class CadastroIniciado extends Model
{
    /** Quanto tempo o link do e-mail vale. */
    public const DIAS_DO_CONVITE = 7;

    protected $table = 'cadastros_iniciados';

    protected $fillable = ['email', 'nome', 'telefone', 'perfil', 'origem', 'fbp', 'fbc'];

    protected $hidden = ['convite_hash'];

    protected function casts(): array
    {
        return [
            'perfil' => 'array',
            'convite_expira_em' => 'datetime',
            'convertido_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sorteia um convite novo e devolve o valor em claro, que só existe aqui
     * e no e-mail. Pedir de novo invalida o link anterior.
     */
    public function novoConvite(): string
    {
        $convite = Str::random(48);

        $this->forceFill([
            'convite_hash' => hash('sha256', $convite),
            'convite_expira_em' => now()->addDays(self::DIAS_DO_CONVITE),
        ])->save();

        return $convite;
    }

    /**
     * Id do Lead no Meta, igual nos dois lados: o ModalCadastro do site
     * calcula o mesmo SHA-256 do e-mail no navegador. Sai do e-mail, e não do
     * id da linha, porque o navegador não conhece o id e a resposta do
     * `iniciar` não pode variar (ver CadastroController).
     */
    public static function idDoEventoLead(string $email): string
    {
        return 'lead-'.substr(hash('sha256', mb_strtolower(trim($email))), 0, 32);
    }

    /** O cadastro de um convite ainda válido: não vencido e não usado. */
    public static function peloConvite(string $convite): ?self
    {
        if ($convite === '') {
            return null;
        }

        return static::query()
            ->where('convite_hash', hash('sha256', $convite))
            ->whereNull('convertido_em')
            ->where('convite_expira_em', '>', now())
            ->first();
    }

    /** A conta nasceu: o convite deixa de abrir qualquer coisa. */
    public function converter(User $user): void
    {
        $this->forceFill([
            'user_id' => $user->getKey(),
            'convertido_em' => now(),
            'convite_hash' => null,
        ])->save();
    }
}
