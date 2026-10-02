<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenant;
use Database\Factories\EmitenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Emitente extends Model
{
    /** @use HasFactory<EmitenteFactory> */
    use Auditavel, DoTenant, HasFactory;

    /**
     * O ambiente nunca é preenchível em massa. Emitente nasce em homologação
     * e só vai para produção por ação própria, auditada. Regra 3 do projeto.
     */
    protected $attributes = [
        'ambiente' => 'homologacao',
        'ativo' => true,
    ];

    protected $guarded = ['id', 'ambiente'];

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'inscricao_estadual',
        'inscricao_municipal',
        'crt',
        'cnae',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'codigo_municipio',
        'municipio',
        'uf',
        'cep',
        'telefone',
        'email',
        'chave_pix',
        'logo_path',
        'serie_padrao',
        'aliquota_credito_simples',
        'info_complementares_padrao',
        'autxml_documento',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'ativo' => 'boolean',
            'serie_padrao' => 'integer',
            'aliquota_credito_simples' => 'decimal:2',
            'producao_ativada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<EmitenteCertificado, $this> */
    public function certificados(): HasMany
    {
        return $this->hasMany(EmitenteCertificado::class)->latest('id');
    }

    /** @return HasOne<EmitenteTransporte, $this> */
    public function transporte(): HasOne
    {
        return $this->hasOne(EmitenteTransporte::class);
    }

    /**
     * Configuração de transporte, criada com os padrões na primeira leitura:
     * o emitente que nunca abriu a tela de transporte ainda assim emite CT-e
     * com CFOP e série padrão, e só o RNTRC e o seguro ficam pendentes.
     */
    public function configuracaoTransporte(): EmitenteTransporte
    {
        return $this->transporte()->firstOrCreate([]);
    }

    /** @return HasOne<EmitenteNfse, $this> */
    public function nfse(): HasOne
    {
        return $this->hasOne(EmitenteNfse::class);
    }

    /** @return HasMany<ServicoNfse, $this> */
    public function servicosNfse(): HasMany
    {
        return $this->hasMany(ServicoNfse::class)->orderBy('nome');
    }
}
