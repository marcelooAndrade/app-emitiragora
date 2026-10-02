<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Models\TransporteSerie;
use App\Models\Viagem;
use App\Models\ViagemNota;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Criação da viagem e entrada das NF-e nela.
 */
class Viagens
{
    public function __construct(
        private readonly NumeracaoTransporte $numeracao,
        private readonly LeitorNfe $leitor,
        private readonly MontadorCtes $montador,
    ) {}

    public function criar(Emitente $emitente, array $dados = []): Viagem
    {
        return DB::transaction(function () use ($emitente, $dados): Viagem {
            $viagem = new Viagem([
                'data_carregamento' => $dados['data_carregamento'] ?? today()->toDateString(),
                ...collect($dados)->except(['data_carregamento'])->all(),
            ]);
            $viagem->forceFill([
                'emitente_id' => $emitente->getKey(),
                'numero' => $this->numeracao->proximo($emitente, TransporteSerie::VIAGEM, 1),
                'created_by' => auth()->id(),
            ])->save();
            $viagem->registrar('viagem_criada', 'Viagem '.$viagem->numeroFormatado().' criada.');

            return $viagem;
        });
    }

    /**
     * Lê o XML, guarda o arquivo e põe a nota na viagem. Depois remonta os
     * CT-e, para a tela já mostrar o agrupamento novo.
     */
    public function adicionarNota(Viagem $viagem, string $xml): ViagemNota
    {
        $viagem->loadMissing('ctes');
        if ($viagem->travada()) {
            throw new TransporteException('Esta viagem já tem CT-e na SEFAZ. Para outra carga, abra uma viagem nova.');
        }

        $nfe = $this->leitor->ler($xml);
        if ($viagem->notas()->where('chave', $nfe['chave'])->exists()) {
            throw new TransporteException("A NF-e {$nfe['numero']} já está nesta viagem.");
        }

        $caminho = "transporte/{$viagem->emitente_id}/nfe/{$nfe['chave']}.xml";
        Storage::disk('fiscal')->put($caminho, $xml);

        $nota = $viagem->notas()->create([
            'chave' => $nfe['chave'],
            'numero' => mb_substr($nfe['numero'], 0, 9),
            'serie' => mb_substr($nfe['serie'], 0, 3),
            'emitida_em' => $nfe['emitida_em'] ? Carbon::parse($nfe['emitida_em']) : null,
            'remetente' => $nfe['remetente'],
            'destinatario' => $nfe['destinatario'],
            'uf_origem' => $nfe['remetente']['uf'],
            'municipio_origem_codigo' => $nfe['remetente']['municipio_codigo'],
            'uf_destino' => $nfe['destinatario']['uf'],
            'municipio_destino_codigo' => $nfe['destinatario']['municipio_codigo'],
            'valor_centavos' => $nfe['valor_centavos'],
            'peso_kg' => $nfe['peso_kg'],
            'produto' => $nfe['produto_predominante'] ? mb_substr($nfe['produto_predominante'], 0, 120) : null,
            'ncm' => $nfe['ncm_predominante'],
            'mod_frete' => $nfe['mod_frete'],
            'xml_path' => $caminho,
        ]);

        $viagem->registrar('nota_incluida', "NF-e {$nota->numero} de {$nota->remetente['nome']} incluída.");
        $this->montador->montar($viagem);

        return $nota;
    }

    public function removerNota(Viagem $viagem, ViagemNota $nota): void
    {
        abort_unless($nota->viagem_id === $viagem->getKey(), 404);
        $viagem->loadMissing('ctes');
        if ($viagem->travada()) {
            throw new TransporteException('Esta viagem já tem CT-e na SEFAZ e as notas não mudam mais.');
        }
        $nota->delete();
        $viagem->registrar('nota_removida', "NF-e {$nota->numero} retirada da viagem.");
        $this->montador->montar($viagem);
    }

    /** Peso corrigido à mão (nota sem peso declarado), e o frete acompanha. */
    public function definirPeso(Viagem $viagem, ViagemNota $nota, float $pesoKg): void
    {
        abort_unless($nota->viagem_id === $viagem->getKey(), 404);
        $viagem->loadMissing('ctes');
        if ($viagem->travada()) {
            throw new TransporteException('Esta viagem já tem CT-e na SEFAZ e as notas não mudam mais.');
        }
        $nota->update(['peso_kg' => max(0, round($pesoKg, 3))]);
        $this->montador->montar($viagem);
    }
}
