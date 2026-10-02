<?php

namespace App\Services\Transporte;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Lê o XML autorizado de uma NF-e no formato que o CT-e e o MDF-e precisam.
 *
 * A extração veio do `NfeXmlReader` do app-transm (participantes completos,
 * produtos e conferência do protocolo). As defesas vieram do `NFeXmlParser`
 * deste projeto: limite de tamanho e recusa de DOCTYPE, que abre porta para
 * XXE. Classe pura: não toca em banco.
 */
class LeitorNfe
{
    private const LIMITE_BYTES = 20 * 1024 * 1024;

    /** @return array<string, mixed> */
    public function ler(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '' || strlen($xml) > self::LIMITE_BYTES) {
            throw new TransporteException('O arquivo está vazio ou passa de 20 MB.');
        }
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new TransporteException('O XML possui uma estrutura insegura e foi recusado.');
        }

        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try {
            $carregou = $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        if (! $carregou) {
            throw new TransporteException('O arquivo não é um XML válido.');
        }

        $xpath = new DOMXPath($documento);
        if ($this->primeiro($xpath, '//*[local-name()="infCte"]') !== null) {
            throw new TransporteException('Este arquivo é um CT-e. Envie o XML da NF-e que vai no caminhão.');
        }

        $infNfe = $this->primeiro($xpath, '//*[local-name()="infNFe"]');
        $ide = $this->primeiro($xpath, '//*[local-name()="infNFe"]/*[local-name()="ide"]');
        $emit = $this->primeiro($xpath, '//*[local-name()="infNFe"]/*[local-name()="emit"]');
        $dest = $this->primeiro($xpath, '//*[local-name()="infNFe"]/*[local-name()="dest"]');
        $total = $this->primeiro($xpath, '//*[local-name()="infNFe"]/*[local-name()="total"]/*[local-name()="ICMSTot"]');
        $protocolo = $this->primeiro($xpath, '//*[local-name()="protNFe"]/*[local-name()="infProt"]');

        if (! $infNfe || ! $ide || ! $emit) {
            throw new TransporteException('O arquivo não parece uma NF-e.');
        }
        if (! $dest) {
            throw new TransporteException('A NF-e não tem destinatário, e o CT-e precisa dele.');
        }

        $chave = preg_replace('/\D/', '', $infNfe->getAttribute('Id'));
        $cStat = $this->valor($xpath, $protocolo, 'cStat');
        $nProt = $this->valor($xpath, $protocolo, 'nProt');
        $chaveProtocolo = preg_replace('/\D/', '', (string) $this->valor($xpath, $protocolo, 'chNFe'));
        if (! $protocolo || ! in_array($cStat, ['100', '150'], true) || blank($nProt) || $chaveProtocolo !== $chave) {
            throw new TransporteException(
                'A NF-e '.$this->numeroDaChave($chave).' não traz o protocolo de autorização. Use o XML autorizado (nfeProc) que o fornecedor envia.'
            );
        }

        $tpAmb = $this->valor($xpath, $ide, 'tpAmb');
        if ($tpAmb !== $this->valor($xpath, $protocolo, 'tpAmb')) {
            throw new TransporteException('A NF-e tem ambientes diferentes na nota e no protocolo.');
        }

        $remetente = $this->participante($xpath, $emit, 'enderEmit');
        $destinatario = $this->participante($xpath, $dest, 'enderDest');
        $faltando = $this->camposFaltando($remetente, 'remetente') + $this->camposFaltando($destinatario, 'destinatário');
        if (strlen($chave) !== 44 || $faltando !== []) {
            throw new TransporteException(
                'A NF-e '.$this->numeroDaChave($chave).' está incompleta para o CT-e: falta '.implode(', ', $faltando).'.'
            );
        }

        $produtos = $this->produtos($xpath);
        $predominante = collect($produtos)
            ->groupBy(fn (array $p): string => (string) $p['ncm'])
            ->map(fn ($grupo, string $ncm): array => [
                'ncm' => $ncm !== '' ? $ncm : null,
                'descricao' => $grupo->first()['descricao'],
                'valor' => $grupo->sum('valor_centavos'),
            ])
            ->sortByDesc('valor')
            ->first();

        return [
            'chave' => $chave,
            'numero' => (string) $this->valor($xpath, $ide, 'nNF'),
            'serie' => (string) ($this->valor($xpath, $ide, 'serie') ?: '1'),
            'emitida_em' => $this->valor($xpath, $ide, 'dhEmi') ?: $this->valor($xpath, $ide, 'dEmi'),
            'ambiente' => $tpAmb === '1' ? 'producao' : 'homologacao',
            'protocolo' => $nProt,
            'remetente' => $remetente,
            'destinatario' => $destinatario,
            'valor_centavos' => $this->centavos($this->valor($xpath, $total, 'vNF') ?: '0'),
            'peso_kg' => $this->peso($xpath, $produtos),
            'mod_frete' => $this->valor($xpath, $this->primeiro($xpath, '//*[local-name()="infNFe"]/*[local-name()="transp"]'), 'modFrete'),
            'produtos' => $produtos,
            'produto_predominante' => $predominante['descricao'] ?? null,
            'ncm_predominante' => $predominante['ncm'] ?? null,
        ];
    }

    /** @return array<int, array{ncm: ?string, descricao: ?string, valor_centavos: int, unidade: ?string, quantidade: float}> */
    private function produtos(DOMXPath $xpath): array
    {
        $produtos = [];
        foreach ($xpath->query('//*[local-name()="infNFe"]/*[local-name()="det"]/*[local-name()="prod"]') as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }
            $ncm = preg_replace('/\D/', '', (string) $this->valor($xpath, $no, 'NCM'));
            $produtos[] = [
                'ncm' => $ncm !== '' && $ncm !== '00000000' ? $ncm : null,
                'descricao' => $this->valor($xpath, $no, 'xProd'),
                'valor_centavos' => $this->centavos($this->valor($xpath, $no, 'vProd') ?: '0'),
                'unidade' => $this->valor($xpath, $no, 'uCom'),
                'quantidade' => (float) ($this->valor($xpath, $no, 'qCom') ?: 0),
            ];
        }

        return $produtos;
    }

    /**
     * Peso bruto dos volumes. Nota sem volume declarado, mas vendida em
     * quilo, usa a quantidade dos itens; sem nenhum dos dois, fica zero e a
     * tela pede o peso, porque o frete por tonelada depende dele.
     */
    private function peso(DOMXPath $xpath, array $produtos): float
    {
        $bruto = 0.0;
        $liquido = 0.0;
        foreach ($xpath->query('//*[local-name()="infNFe"]/*[local-name()="transp"]/*[local-name()="vol"]') as $vol) {
            if ($vol instanceof DOMElement) {
                $bruto += (float) ($this->valor($xpath, $vol, 'pesoB') ?: 0);
                $liquido += (float) ($this->valor($xpath, $vol, 'pesoL') ?: 0);
            }
        }
        if ($bruto > 0) {
            return round($bruto, 3);
        }
        if ($liquido > 0) {
            return round($liquido, 3);
        }

        $emQuilo = collect($produtos)->every(
            fn (array $p): bool => in_array(mb_strtoupper(trim((string) $p['unidade'])), ['KG', 'KGS', 'QUILO', 'KILO', 'QUILOGRAMA'], true)
        );

        return $emQuilo && $produtos !== [] ? round(collect($produtos)->sum('quantidade'), 3) : 0.0;
    }

    /** @return array<string, ?string> */
    private function participante(DOMXPath $xpath, DOMElement $no, string $tagEndereco): array
    {
        $endereco = $this->primeiro($xpath, './*[local-name()="'.$tagEndereco.'"]', $no);

        return [
            'documento' => $this->valor($xpath, $no, 'CNPJ') ?: $this->valor($xpath, $no, 'CPF'),
            'ie' => $this->valor($xpath, $no, 'IE'),
            'ind_ie' => $this->valor($xpath, $no, 'indIEDest'),
            'nome' => $this->valor($xpath, $no, 'xNome'),
            'fantasia' => $this->valor($xpath, $no, 'xFant'),
            'telefone' => $this->valor($xpath, $endereco, 'fone'),
            'email' => $this->valor($xpath, $no, 'email'),
            'logradouro' => $this->valor($xpath, $endereco, 'xLgr'),
            'numero' => $this->valor($xpath, $endereco, 'nro'),
            'complemento' => $this->valor($xpath, $endereco, 'xCpl'),
            'bairro' => $this->valor($xpath, $endereco, 'xBairro'),
            'municipio_codigo' => $this->valor($xpath, $endereco, 'cMun'),
            'municipio' => $this->valor($xpath, $endereco, 'xMun'),
            'uf' => $this->valor($xpath, $endereco, 'UF'),
            'cep' => $this->valor($xpath, $endereco, 'CEP'),
        ];
    }

    /** @return array<int, string> */
    private function camposFaltando(array $participante, string $papel): array
    {
        $exigidos = [
            'documento' => 'CNPJ/CPF', 'nome' => 'nome', 'logradouro' => 'endereço',
            'bairro' => 'bairro', 'municipio_codigo' => 'município', 'uf' => 'UF',
        ];
        $faltando = [];
        foreach ($exigidos as $campo => $rotulo) {
            if (blank($participante[$campo] ?? null)) {
                $faltando["{$papel}.{$campo}"] = "{$rotulo} do {$papel}";
            }
        }

        return $faltando;
    }

    private function numeroDaChave(string $chave): string
    {
        return strlen($chave) === 44 ? (string) (int) substr($chave, 25, 9) : 'informada';
    }

    private function centavos(string $valor): int
    {
        [$inteiro, $decimal] = array_pad(explode('.', trim($valor), 2), 2, '0');

        return ((int) $inteiro * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function primeiro(DOMXPath $xpath, string $consulta, ?DOMElement $contexto = null): ?DOMElement
    {
        $no = $xpath->query($consulta, $contexto)->item(0);

        return $no instanceof DOMElement ? $no : null;
    }

    private function valor(DOMXPath $xpath, ?DOMElement $contexto, string $tag): ?string
    {
        if (! $contexto) {
            return null;
        }
        $valor = trim((string) $xpath->query('./*[local-name()="'.$tag.'"]', $contexto)->item(0)?->nodeValue);

        return $valor !== '' ? $valor : null;
    }
}
