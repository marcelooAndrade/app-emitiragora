<?php

namespace App\Services\Export;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\Emitente;
use App\Models\Mdfe;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Monta o pacote que a contabilidade precisa para escriturar o período da
 * transportadora: os CT-e (a receita de frete) e os MDF-e.
 *
 * A organização por pasta é proposital: o contador abre o ZIP e já sabe o
 * que é frete autorizado, o que foi cancelado e o que é manifesto, sem abrir
 * XML por XML para descobrir.
 */
class PacoteContadorService
{
    public function gerar(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): string
    {
        if ($de->greaterThan($ate)) {
            throw new RuntimeException('O período está invertido: a data inicial é maior que a final.');
        }

        $de = $de->copy()->startOfDay();
        $ate = $ate->copy()->endOfDay();

        $caminho = tempnam(sys_get_temp_dir(), 'contador_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo do pacote.');
        }

        try {
            $ctes = $this->ctesDoPeriodo($emitente, $de, $ate);
            $mdfes = $this->mdfesDoPeriodo($emitente, $de, $ate);

            foreach ($ctes as $cte) {
                $pasta = $cte->status === CteStatus::Cancelado ? 'cte-cancelados' : 'cte';
                $this->adicionarXml($zip, $cte->xml_autorizado_path, "{$pasta}/{$cte->chave}.xml");
            }

            foreach ($mdfes as $mdfe) {
                $this->adicionarXml($zip, $mdfe->xml_autorizado_path, "mdfe/{$mdfe->chave}.xml");
            }

            $zip->addFromString('resumo.csv', $this->resumo($ctes));
            $zip->addFromString('LEIA-ME.txt', $this->leiaMe($emitente, $de, $ate, $ctes->count(), $mdfes->count()));
        } finally {
            $zip->close();
        }

        return $caminho;
    }

    /**
     * CT-e autorizados ou cancelados no período, pela data de autorização:
     * é ela que vale para a escrituração, não a data do rascunho.
     *
     * @return Collection<int, Cte>
     */
    public function ctesDoPeriodo(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): Collection
    {
        return Cte::query()
            ->where('emitente_id', $emitente->getKey())
            ->whereIn('status', [CteStatus::Autorizado->value, CteStatus::Cancelado->value])
            ->whereBetween('autorizado_em', [$de, $ate])
            ->orderBy('serie')
            ->orderBy('numero')
            ->get();
    }

    /** @return Collection<int, Mdfe> */
    public function mdfesDoPeriodo(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): Collection
    {
        return Mdfe::query()
            ->where('emitente_id', $emitente->getKey())
            ->whereIn('status', [MdfeStatus::Autorizado->value, MdfeStatus::Encerrado->value, MdfeStatus::Cancelado->value])
            ->whereBetween('autorizado_em', [$de, $ate])
            ->orderBy('serie')
            ->orderBy('numero')
            ->get();
    }

    private function adicionarXml(ZipArchive $zip, ?string $caminho, string $nome): void
    {
        if (blank($caminho) || ! Storage::disk('fiscal')->exists($caminho)) {
            return;
        }

        $zip->addFromString($nome, (string) Storage::disk('fiscal')->get($caminho));
    }

    /** @param  Collection<int, Cte>  $ctes */
    private function resumo(Collection $ctes): string
    {
        $linhas = ['numero;serie;chave;autorizacao;tomador;cnpj_cpf_tomador;situacao;protocolo_cancelamento;valor_frete;valor_icms;valor_total'];

        foreach ($ctes as $c) {
            $tomador = $c->tomador();

            $linhas[] = implode(';', [
                $c->numero,
                $c->serie,
                $c->chave,
                $c->autorizado_em?->format('d/m/Y'),
                str_replace(';', ',', (string) ($tomador['nome'] ?? '')),
                (string) ($tomador['documento'] ?? ''),
                $c->status->rotulo(),
                (string) $c->protocolo_cancelamento,
                $this->reais($c->valor_frete_centavos),
                $this->reais($c->icms_valor_centavos),
                $this->reais($c->valor_total_centavos),
            ]);
        }

        // BOM para o Excel abrir com acento correto.
        return "\u{FEFF}".implode("\n", $linhas);
    }

    private function reais(?int $centavos): string
    {
        return number_format(((int) $centavos) / 100, 2, ',', '');
    }

    private function leiaMe(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate, int $ctes, int $mdfes): string
    {
        return <<<TXT
        Pacote da contabilidade
        =======================

        Emitente : {$emitente->razao_social}
        CNPJ     : {$emitente->cnpj}
        Período  : {$de->format('d/m/Y')} a {$ate->format('d/m/Y')}
        Gerado em: {$de->copy()->setTimeFrom(now())->format('d/m/Y H:i')}

        CT-e no período : {$ctes}
        MDF-e no período: {$mdfes}

        Pastas
        ------
        cte/             XML autorizado dos CT-e (receita de frete)
        cte-cancelados/  XML dos CT-e que foram cancelados depois
        mdfe/            XML autorizado dos MDF-e

        resumo.csv       Planilha com uma linha por CT-e, com o protocolo
                         de cancelamento quando houver

        Observação: o XML é o documento fiscal. O DACTE e o DAMDFE são
        apenas a representação impressa e não substituem o arquivo.
        TXT;
    }
}
