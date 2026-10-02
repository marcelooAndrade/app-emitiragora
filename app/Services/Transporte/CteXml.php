<?php

namespace App\Services\Transporte;

use App\Models\Cte;
use App\Models\Emitente;
use App\Models\EmitenteTransporte;
use App\Models\Veiculo;
use Illuminate\Support\Carbon;
use NFePHP\Common\Keys;
use NFePHP\Common\UFList;
use NFePHP\CTe\MakeCTe;
use stdClass;

/**
 * Monta o XML do CT-e 4.00 (modal rodoviário).
 *
 * Portado do `CteXmlBuilder` do app-transm, com três correções:
 *
 * 1. O remetente é quem emitiu a NF-e, não a própria transportadora. No
 *    Transm a empresa vendia e transportava a própria carga, e o remetente
 *    era cravado nela; para uma transportadora comum isso sai errado.
 * 2. O ambiente vem do emitente. No Transm o `tpAmb` era fixo em 2.
 * 3. O indicador de IE do tomador é calculado também quando o tomador é o
 *    remetente; no Transm só era para o destinatário.
 */
class CteXml
{
    /**
     * @return array{xml: string, chave: string, emitido_em: Carbon}
     */
    public function montar(Cte $cte): array
    {
        $cte->loadMissing(['notas', 'viagem.motorista', 'viagem.veiculo', 'viagem.reboque', 'viagem.reboque2', 'emitente']);
        $emitente = $cte->emitente;
        $config = $emitente->configuracaoTransporte();
        $emitidoEm = now();
        $codigoNumerico = str_pad((string) random_int(1, 99_999_999), 8, '0', STR_PAD_LEFT);
        $codigoUf = (string) UFList::getCodeByUF($emitente->uf);
        $chave = Keys::build(
            $codigoUf,
            $emitidoEm->format('y'),
            $emitidoEm->format('m'),
            $emitente->cnpj,
            '57',
            (string) $cte->serie,
            (string) $cte->numero,
            '1',
            $codigoNumerico,
        );

        $remetente = (array) $cte->remetente;
        $destinatario = (array) $cte->destinatario;
        $tomador = $cte->tomador();

        $make = new MakeCTe;
        $make->setOnlyAscii(true);
        $make->taginfCTe((object) ['Id' => $chave, 'versao' => '4.00']);
        $make->tagide((object) [
            'cUF' => $codigoUf,
            'cCT' => $codigoNumerico,
            'CFOP' => $cte->cfop,
            'natOp' => mb_substr($config->natureza_operacao, 0, 60),
            'serie' => $cte->serie,
            'nCT' => $cte->numero,
            'dhEmi' => $emitidoEm->toIso8601String(),
            'tpImp' => '1',
            'tpEmis' => '1',
            'cDV' => substr($chave, -1),
            'tpAmb' => (string) $cte->ambiente->tpAmb(),
            'tpCTe' => '0',
            'procEmi' => '0',
            'verProc' => mb_substr(config('app.name').' 1.0', 0, 20),
            'indGlobalizado' => null,
            'cMunEnv' => $emitente->codigo_municipio,
            'xMunEnv' => $emitente->municipio,
            'UFEnv' => $emitente->uf,
            'modal' => '01',
            'tpServ' => '0',
            'cMunIni' => $cte->municipio_inicio_codigo,
            'xMunIni' => $cte->municipio_inicio,
            'UFIni' => $cte->uf_inicio,
            'cMunFim' => $cte->municipio_fim_codigo,
            'xMunFim' => $cte->municipio_fim,
            'UFFim' => $cte->uf_fim,
            'retira' => '1',
            'xDetRetira' => null,
            'indIEToma' => $this->indicadorIe($tomador),
            'dhCont' => null,
            'xJust' => null,
        ]);
        $make->tagtoma3((object) ['toma' => $cte->tomador_tipo]);
        $make->tagemit((object) [
            'CNPJ' => $emitente->cnpj,
            'CPF' => null,
            'IE' => $emitente->inscricao_estadual,
            'IEST' => null,
            'xNome' => $emitente->razao_social,
            'xFant' => $emitente->nome_fantasia,
            'CRT' => $emitente->crt,
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
        ]);
        $make->tagrem($this->participante($remetente));
        $make->tagenderReme($this->endereco($remetente));
        $make->tagdest($this->participante($destinatario));
        $make->tagenderDest($this->endereco($destinatario));
        $make->tagvPrest((object) [
            'vTPrest' => $this->reais($cte->valor_total_centavos),
            'vRec' => $this->reais($cte->valor_total_centavos),
        ]);
        foreach ($this->componentes($cte) as $nome => $centavos) {
            $make->tagComp((object) ['xNome' => $nome, 'vComp' => $this->reais($centavos)]);
        }

        $make->tagicms((object) [
            'cst' => $cte->icms_cst,
            'vBC' => $this->reais($cte->icms_base_centavos),
            'pICMS' => number_format((float) $cte->icms_aliquota, 2, '.', ''),
            'vICMS' => $this->reais($cte->icms_valor_centavos),
            'pRedBC' => $cte->regraIcms && (float) $cte->regraIcms->reducao_base > 0
                ? number_format((float) $cte->regraIcms->reducao_base, 2, '.', '')
                : null,
            'vCred' => $cte->icms_credito_centavos > 0 ? $this->reais($cte->icms_credito_centavos) : null,
            'vTotTrib' => $this->reais($cte->icms_valor_centavos),
            'infAdFisco' => null,
        ]);

        $observacoes = collect([$cte->observacoes, $this->dadosOperacionais($cte, $config)])->filter()->join(' | ');
        if ($observacoes !== '') {
            $make->tagcompl((object) [
                'xCaracAd' => null,
                'xCaracSer' => null,
                'xEmi' => null,
                'origCalc' => null,
                'destCalc' => null,
                'xObs' => mb_substr($observacoes, 0, 2000),
            ]);
        }

        $make->taginfCTeNorm();
        $make->taginfCarga((object) [
            'vCarga' => $this->reais($cte->valor_carga_centavos),
            'proPred' => mb_substr((string) ($cte->produto_predominante ?: 'CARGA GERAL'), 0, 60),
            'xOutCat' => null,
            'vCargaAverb' => $this->reais($cte->valor_carga_centavos),
        ]);
        $make->taginfQ((object) [
            'cUnid' => '01',
            'tpMed' => 'PESO BRUTO',
            'qCarga' => number_format((float) $cte->peso_kg, 4, '.', ''),
        ]);
        foreach ($cte->notas as $nota) {
            $make->taginfNFe((object) [
                'chave' => $nota->chave,
                'PIN' => null,
                'dPrev' => null,
                'infUnidCarga' => null,
                'infUnidTransp' => null,
            ]);
        }
        $make->taginfModal((object) ['versaoModal' => '4.00']);
        $make->tagrodo((object) ['RNTRC' => str_pad(preg_replace('/\D/', '', (string) $config->rntrc), 8, '0', STR_PAD_LEFT)]);

        $responsavel = config('fiscal.responsavel_tecnico');
        if (filled($responsavel['cnpj'] ?? null)) {
            $make->taginfRespTec((object) [
                'CNPJ' => preg_replace('/\D/', '', (string) $responsavel['cnpj']),
                'xContato' => $responsavel['contato'] ?? null,
                'email' => $responsavel['email'] ?? null,
                'fone' => $this->telefone($responsavel['telefone'] ?? null),
                'idCSRT' => null,
                'CSRT' => null,
            ]);
        }

        try {
            $xml = $make->getXML();
        } catch (\RuntimeException) {
            throw new TransporteException('O CT-e '.$cte->numeroFormatado().' não fechou: '.collect($make->getErrors())->join(' '));
        }

        return ['xml' => $xml, 'chave' => $make->getChave() ?: $chave, 'emitido_em' => $emitidoEm];
    }

    /** @return array<string, int> */
    private function componentes(Cte $cte): array
    {
        return array_filter([
            'FRETE VALOR' => (int) $cte->valor_frete_centavos,
            'PEDAGIO' => (int) $cte->valor_pedagio_centavos,
        ], fn (int $centavos): bool => $centavos > 0);
    }

    /**
     * Motorista, placas e RNTRC no campo de observação, como o Transm fazia:
     * é o que o fiscal de posto procura primeiro.
     */
    private function dadosOperacionais(Cte $cte, EmitenteTransporte $config): ?string
    {
        $viagem = $cte->viagem;
        $placas = collect([$viagem?->veiculo, $viagem?->reboque, $viagem?->reboque2])
            ->filter()
            ->map(fn (Veiculo $v): string => $v->placaFormatada())
            ->unique()
            ->all();
        $partes = collect([
            $viagem?->motorista ? 'MOTORISTA: '.$viagem->motorista->nome : null,
            $placas !== [] ? 'PLACAS: '.implode(' / ', $placas) : null,
            filled($config->rntrc) ? 'RNTRC: '.$config->rntrc : null,
        ])->filter();

        return $partes->isNotEmpty() ? 'DADOS OPERACIONAIS - '.$partes->join('; ') : null;
    }

    private function participante(array $p): stdClass
    {
        $documento = preg_replace('/\D/', '', (string) ($p['documento'] ?? ''));

        return (object) [
            'CNPJ' => strlen($documento) === 14 ? $documento : null,
            'CPF' => strlen($documento) === 11 ? $documento : null,
            'IE' => $this->ie($p),
            'xNome' => $p['nome'] ?? null,
            'xFant' => $p['fantasia'] ?? null,
            'fone' => $this->telefone($p['telefone'] ?? null),
            'email' => $p['email'] ?? null,
            'ISUF' => null,
        ];
    }

    private function endereco(array $p): stdClass
    {
        return (object) [
            'xLgr' => $p['logradouro'] ?? null,
            'nro' => filled($p['numero'] ?? null) ? $p['numero'] : 'S/N',
            'xCpl' => $p['complemento'] ?? null,
            'xBairro' => $p['bairro'] ?? null,
            'cMun' => $p['municipio_codigo'] ?? null,
            'xMun' => $p['municipio'] ?? null,
            'CEP' => filled($p['cep'] ?? null) ? $p['cep'] : null,
            'UF' => $p['uf'] ?? null,
            'cPais' => '1058',
            'xPais' => 'BRASIL',
        ];
    }

    /**
     * Regra do manual do CT-e para a IE (veio comentada do Transm): o valor
     * real quando houver, "ISENTO" para isento e nada para não contribuinte.
     * Nunca em branco com indicador de isenção, que a SEFAZ rejeita.
     */
    private function ie(array $p): ?string
    {
        $ie = trim((string) ($p['ie'] ?? ''));
        if ($ie !== '') {
            return $ie;
        }

        return ($p['ind_ie'] ?? null) === '2' ? 'ISENTO' : null;
    }

    private function indicadorIe(array $tomador): string
    {
        return match ($this->ie($tomador)) {
            null => '9',
            'ISENTO' => '2',
            default => '1',
        };
    }

    private function telefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);
        if (str_starts_with($digitos, '55') && strlen($digitos) > 12) {
            $digitos = substr($digitos, 2);
        }

        return strlen($digitos) >= 7 && strlen($digitos) <= 12 ? $digitos : null;
    }

    private function reais(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
