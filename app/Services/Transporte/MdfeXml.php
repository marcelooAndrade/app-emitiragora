<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\Mdfe;
use App\Models\Veiculo;
use App\Models\ViagemNota;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use NFePHP\Common\Keys;
use NFePHP\Common\UFList;
use NFePHP\MDFe\Make;

/**
 * Monta o XML do MDF-e 3.00 (modal rodoviário).
 *
 * Portado do `MdfeXmlBuilder` do app-transm. A diferença de fundo: lá todo
 * frete era de motorista terceiro, então CIOT e pagamento (infPag) eram
 * obrigatórios. Aqui eles só entram quando a viagem tem CIOT; frota própria
 * emite MDF-e sem nada disso, que é o caso comum.
 */
class MdfeXml
{
    /** @return array{xml: string, chave: string, emitido_em: Carbon} */
    public function montar(Mdfe $mdfe): array
    {
        $mdfe->loadMissing(['emitente', 'viagem.ctes.notas', 'viagem.motorista', 'viagem.veiculo', 'viagem.reboque', 'viagem.reboque2']);
        $emitente = $mdfe->emitente;
        $config = $emitente->configuracaoTransporte();
        $viagem = $mdfe->viagem;
        $ctes = $viagem->ctes->filter(fn (Cte $c): bool => $c->status === CteStatus::Autorizado)->values();
        $veiculo = $viagem->veiculo;
        $motorista = $viagem->motorista;

        $emitidoEm = now();
        $codigoNumerico = str_pad((string) random_int(1, 99_999_999), 8, '0', STR_PAD_LEFT);
        $codigoUf = (string) UFList::getCodeByUF($emitente->uf);
        $chave = Keys::build($codigoUf, $emitidoEm->format('y'), $emitidoEm->format('m'), $emitente->cnpj, '58', (string) $mdfe->serie, (string) $mdfe->numero, '1', $codigoNumerico);

        $make = new Make;
        $make->setOnlyAscii(true);
        $make->tagide((object) [
            'cUF' => $codigoUf,
            'tpAmb' => (string) $mdfe->ambiente->tpAmb(),
            'tpEmit' => $config->tipo_emitente_mdfe,
            'tpTransp' => $this->tipoTransportador($veiculo),
            'mod' => '58',
            'serie' => $mdfe->serie,
            'nMDF' => $mdfe->numero,
            'cMDF' => $codigoNumerico,
            'cDV' => substr($chave, -1),
            'modal' => '1',
            'dhEmi' => $emitidoEm->toIso8601String(),
            'tpEmis' => '1',
            'procEmi' => '0',
            'verProc' => mb_substr(config('app.name').' 1.0', 0, 20),
            'UFIni' => $mdfe->uf_inicio,
            'UFFim' => $mdfe->uf_fim,
            'dhIniViagem' => null,
            'indCanalVerde' => null,
            'indCarregaPosterior' => null,
        ]);
        $make->taginfMunCarrega((object) [
            'cMunCarrega' => $mdfe->municipio_carregamento_codigo,
            'xMunCarrega' => $mdfe->municipio_carregamento,
        ]);
        foreach ((array) $mdfe->percurso_ufs as $uf) {
            $make->taginfPercurso((object) ['UFPer' => $uf]);
        }
        $make->tagemit((object) [
            'CNPJ' => $emitente->cnpj,
            'CPF' => null,
            'IE' => $emitente->inscricao_estadual,
            'xNome' => $emitente->razao_social,
            'xFant' => $emitente->nome_fantasia,
        ]);
        $make->tagenderEmit((object) [
            'xLgr' => $emitente->logradouro,
            'nro' => $emitente->numero ?: 'S/N',
            'xCpl' => $emitente->complemento,
            'xBairro' => $emitente->bairro,
            'cMun' => $emitente->codigo_municipio,
            'xMun' => $emitente->municipio,
            'CEP' => $emitente->cep,
            'UF' => $emitente->uf,
            'fone' => $this->telefone($emitente->telefone),
            'email' => $emitente->email,
        ]);

        $make->taginfANTT((object) ['RNTRC' => str_pad((string) $config->rntrc, 8, '0', STR_PAD_LEFT)]);
        if (filled($mdfe->ciot)) {
            $make->taginfCIOT((object) ['CIOT' => $mdfe->ciot, 'CPF' => null, 'CNPJ' => $emitente->cnpj]);
        }
        foreach ($this->contratantes($ctes) as $contratante) {
            $make->taginfContratante($contratante);
        }

        $seguro = (array) $mdfe->seguro;
        if (filled($seguro['seguradora_nome'] ?? null)) {
            $make->tagseg((object) [
                'respSeg' => $seguro['responsavel'] ?? '1',
                'CNPJ' => ($seguro['responsavel'] ?? '1') === '1' ? $emitente->cnpj : null,
                'CPF' => null,
                'infSeg' => (object) [
                    'xSeg' => mb_substr((string) $seguro['seguradora_nome'], 0, 30),
                    'CNPJ' => $seguro['seguradora_cnpj'] ?? null,
                ],
                'nApol' => $seguro['apolice'] ?? null,
                'nAver' => array_values(array_filter((array) ($seguro['averbacoes'] ?? []))),
            ]);
        }

        $make->tagveicTracao((object) [
            'cInt' => (string) $veiculo->getKey(),
            'placa' => strtoupper((string) $veiculo->placa),
            'RENAVAM' => $veiculo->renavam,
            'tara' => $veiculo->tara_kg,
            'capKG' => $veiculo->capacidade_kg,
            'capM3' => $veiculo->capacidade_m3,
            'prop' => $this->proprietario($veiculo, $emitente),
            'tpRod' => $veiculo->tipo_rodado,
            'tpCar' => $veiculo->tipo_carroceria,
            'UF' => $veiculo->uf,
            'condutor' => [(object) ['xNome' => $motorista->nome, 'CPF' => $motorista->cpf]],
        ]);
        foreach ([$viagem->reboque, $viagem->reboque2] as $reboque) {
            if ($reboque === null) {
                continue;
            }
            $make->tagveicReboque((object) [
                'cInt' => (string) $reboque->getKey(),
                'placa' => strtoupper((string) $reboque->placa),
                'RENAVAM' => $reboque->renavam,
                'tara' => $reboque->tara_kg,
                'capKG' => $reboque->capacidade_kg ?? 0,
                'capM3' => $reboque->capacidade_m3,
                'prop' => $this->proprietario($reboque, $emitente),
                'tpCar' => $reboque->tipo_carroceria,
                'UF' => $reboque->uf,
            ]);
        }

        $destinos = $ctes->groupBy('municipio_fim_codigo')->values();
        foreach ($destinos as $indice => $ctesDoDestino) {
            $make->taginfMunDescarga((object) [
                'cMunDescarga' => $ctesDoDestino->first()->municipio_fim_codigo,
                'xMunDescarga' => $ctesDoDestino->first()->municipio_fim,
                'nItem' => $indice,
            ]);
            foreach ($ctesDoDestino as $cte) {
                $make->taginfCTe((object) ['chCTe' => $cte->chave, 'nItem' => $indice]);
            }
        }

        $predominante = $this->produtoPredominante($ctes);
        $prodPred = [
            'tpCarga' => '05',
            'xProd' => mb_substr($predominante['descricao'], 0, 120),
            'cEAN' => null,
            'NCM' => $predominante['ncm'],
            'infLotacao' => null,
        ];
        // Lotação (um documento só): o MDF-e precisa dos CEPs de carga e de
        // descarga. Com mais de um CT-e o grupo não é exigido.
        if ($ctes->count() === 1) {
            $cte = $ctes->first();
            $prodPred['infLotacao'] = (object) [
                'infLocalCarrega' => (object) ['CEP' => $cte->remetente['cep'] ?? $emitente->cep, 'latitude' => null, 'longitude' => null],
                'infLocalDescarrega' => (object) ['CEP' => $cte->destinatario['cep'] ?? null, 'latitude' => null, 'longitude' => null],
            ];
        }
        $make->tagprodPred((object) $prodPred);

        $make->tagtot((object) [
            'qCTe' => $ctes->count(),
            'qNFe' => null,
            'qMDFe' => null,
            'vCarga' => number_format($ctes->sum('valor_carga_centavos') / 100, 2, '.', ''),
            'cUnid' => '01',
            'qCarga' => number_format((float) $ctes->sum(fn (Cte $c): float => (float) $c->peso_kg), 4, '.', ''),
        ]);

        $responsavel = config('fiscal.responsavel_tecnico');
        if (filled($responsavel['cnpj'] ?? null)) {
            $make->taginfRespTec((object) [
                'CNPJ' => preg_replace('/\D/', '', (string) $responsavel['cnpj']),
                'xContato' => $responsavel['contato'] ?? null,
                'email' => $responsavel['email'] ?? null,
                'fone' => $this->telefone($responsavel['telefone'] ?? null),
                'CSRT' => null,
                'idCSRT' => null,
            ]);
        }

        try {
            $xml = $this->semIeVaziaDoProprietario($make->getXML());
        } catch (\RuntimeException) {
            throw new TransporteException('O MDF-e não fechou: '.collect($make->getErrors())->join(' '));
        }

        return ['xml' => $xml, 'chave' => $make->getChave() ?: $chave, 'emitido_em' => $emitidoEm];
    }

    /**
     * Quem contratou o frete: os tomadores dos CT-e, sem repetir.
     *
     * @param  Collection<int, Cte>  $ctes
     * @return array<int, object>
     */
    private function contratantes(Collection $ctes): array
    {
        return $ctes->map(fn (Cte $c): array => $c->tomador())
            ->unique(fn (array $t): string => (string) ($t['documento'] ?? ''))
            ->map(function (array $t): object {
                $documento = preg_replace('/\D/', '', (string) ($t['documento'] ?? ''));

                return (object) [
                    'xNome' => mb_substr((string) ($t['nome'] ?? ''), 0, 60),
                    'CPF' => strlen($documento) === 11 ? $documento : null,
                    'CNPJ' => strlen($documento) === 14 ? $documento : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Produto de maior valor entre as NF-e, com NCM, que o MDF-e exige no
     * produto predominante (regra que veio do Transm).
     *
     * @param  Collection<int, Cte>  $ctes
     * @return array{descricao: string, ncm: ?string}
     */
    private function produtoPredominante(Collection $ctes): array
    {
        $nota = $ctes->flatMap(fn (Cte $c) => $c->notas)
            ->sortByDesc(fn (ViagemNota $n): int => (int) $n->valor_centavos)
            ->first(fn (ViagemNota $n): bool => filled($n->ncm));

        return [
            'descricao' => (string) ($nota?->produto ?: $ctes->first()?->produto_predominante ?: 'CARGA GERAL'),
            'ncm' => $nota?->ncm,
        ];
    }

    /** Transportador: só se informa quando o veículo é de terceiro. */
    private function tipoTransportador(?Veiculo $veiculo): ?string
    {
        if ($veiculo === null || ! $veiculo->deTerceiro()) {
            return null;
        }

        return strlen((string) $veiculo->proprietario_documento) === 11 ? '2' : '1';
    }

    private function proprietario(Veiculo $veiculo, Emitente $emitente): ?object
    {
        if (! $veiculo->deTerceiro() || $veiculo->proprietario_documento === $emitente->cnpj) {
            return null;
        }
        $documento = (string) $veiculo->proprietario_documento;
        $ie = trim((string) $veiculo->proprietario_ie);

        return (object) [
            'CPF' => strlen($documento) === 11 ? $documento : null,
            'CNPJ' => strlen($documento) === 14 ? $documento : null,
            'RNTRC' => str_pad((string) $veiculo->proprietario_rntrc, 8, '0', STR_PAD_LEFT),
            'xNome' => $veiculo->proprietario_nome,
            'IE' => $ie !== '' ? $ie : null,
            'UF' => $veiculo->proprietario_uf ?: $emitente->uf,
            'tpProp' => $veiculo->proprietario_tp,
        ];
    }

    /**
     * Proprietário sem IE: a sped-mdfe ainda escreve IE e UF vazios, e o XSD
     * recusa. Veio do Transm (removeEmptyOwnerStateRegistrationGroups).
     */
    private function semIeVaziaDoProprietario(string $xml): string
    {
        $documento = new \DOMDocument('1.0', 'UTF-8');
        $documento->loadXML($xml, LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $donos = $xpath->query('//*[local-name()="veicTracao" or local-name()="veicReboque"]/*[local-name()="prop"][not(normalize-space(*[local-name()="IE"]))]');
        foreach ($donos as $dono) {
            foreach (['IE', 'UF'] as $campo) {
                foreach ($xpath->query('./*[local-name()="'.$campo.'"]', $dono) as $no) {
                    $dono->removeChild($no);
                }
            }
        }

        return $documento->saveXML() ?: $xml;
    }

    private function telefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);
        if (str_starts_with($digitos, '55') && strlen($digitos) > 12) {
            $digitos = substr($digitos, 2);
        }

        return strlen($digitos) >= 7 && strlen($digitos) <= 12 ? $digitos : null;
    }
}
