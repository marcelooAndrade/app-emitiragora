{{--
    Contrato do frete com o motorista terceiro, mais o recibo do adiantamento.
    Portado do contrato-transporte do app-transm; sai pelo dompdf, que entende
    tabela e borda simples, por isso o layout é todo em <table>.
--}}
@php
    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $doc = function (?string $d): string {
        $d = preg_replace('/\D/', '', (string) $d);
        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => $d ?: '—',
        };
    };
    $tipos = ['0' => 'TAC agregado', '1' => 'TAC independente', '2' => 'Outros (empresa)'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de frete {{ $contrato->numero }}</title>
    <style>
        @page { margin: 26px 30px 40px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: {{ $neutra }}; font-size: 9.5px; margin: 0; line-height: 1.35; }
        .topo { border-bottom: 3px solid {{ $primaria }}; padding-bottom: 10px; margin-bottom: 12px; }
        .empresa { font-size: 15px; font-weight: bold; }
        .titulo { font-size: 12px; margin-top: 4px; }
        .apagado { color: #5b6b70; }
        .homologacao { margin-bottom: 10px; padding: 6px; border: 2px solid {{ $primaria }}; color: {{ $primaria }}; text-align: center; font-size: 11px; font-weight: bold; }
        h2 { font-size: 10px; color: {{ $primaria }}; margin: 14px 0 5px; }
        table.grade { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.grade td { border: 1px solid #d5dcde; padding: 5px 6px; vertical-align: top; }
        .rotulo { color: #5b6b70; font-size: 7.5px; margin-bottom: 2px; }
        .valor { font-weight: bold; font-size: 9.5px; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { background: #eef1f2; text-align: left; padding: 5px 6px; border: 1px solid #d5dcde; font-size: 7.5px; }
        table.dados td { padding: 5px 6px; border: 1px solid #d5dcde; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .destaque td { font-weight: bold; background: #f6f8f8; }
        .assinaturas { width: 100%; margin-top: 38px; border-collapse: separate; border-spacing: 24px 0; }
        .assinatura { border-top: 1px solid #5b6b70; text-align: center; padding-top: 4px; font-weight: bold; }
        .recibo { margin-top: 18px; border: 1px solid #d5dcde; padding: 8px 12px; page-break-inside: avoid; }
        .recibo p { margin: 6px 0; text-align: justify; line-height: 1.5; }
        .rodape { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; color: #8a979a; font-size: 7px; }
    </style>
</head>
<body>
@if($homologacao)
    <div class="homologacao">HOMOLOGAÇÃO: DOCUMENTO DE TESTE, SEM VALIDADE</div>
@endif

<div class="topo">
    <div class="empresa">{{ $emitente->razao_social }}</div>
    <div class="apagado">CNPJ {{ $doc($emitente->cnpj) }} · RNTRC {{ $config->rntrc ?: '—' }} · {{ $emitente->municipio }}/{{ $emitente->uf }}</div>
    <div class="titulo"><strong>Contrato de frete</strong> da viagem {{ $viagem->numeroFormatado() }}</div>
</div>

<table class="grade">
    <tr>
        <td width="22%"><div class="rotulo">Emissão</div><div class="valor">{{ ($contrato->emitido_em ?? now())->format('d/m/Y') }}</div></td>
        <td width="22%"><div class="rotulo">Carregamento</div><div class="valor">{{ $viagem->data_carregamento?->format('d/m/Y') ?: '—' }}</div></td>
        <td width="22%"><div class="rotulo">MDF-e</div><div class="valor">{{ $viagem->mdfe?->numero ? $viagem->mdfe->numeroFormatado() : 'Pendente' }}</div></td>
        <td width="34%"><div class="rotulo">CIOT</div><div class="valor">{{ $contrato->ciot ?: 'Não informado' }}</div></td>
    </tr>
</table>

<h2>Contratado (proprietário do veículo)</h2>
<table class="grade">
    <tr>
        <td width="40%"><div class="rotulo">Nome ou razão social</div><div class="valor">{{ $contrato->contratado_nome }}</div></td>
        <td width="22%"><div class="rotulo">CPF/CNPJ</div><div class="valor">{{ $doc($contrato->contratado_documento) }}</div></td>
        <td width="16%"><div class="rotulo">RNTRC</div><div class="valor">{{ $contrato->contratado_rntrc }}</div></td>
        <td width="22%"><div class="rotulo">Tipo</div><div class="valor">{{ $tipos[$contrato->contratado_tp] ?? '—' }}</div></td>
    </tr>
</table>

<h2>Motorista e veículo</h2>
<table class="grade">
    <tr>
        <td width="40%"><div class="rotulo">Motorista</div><div class="valor">{{ $contrato->motorista_nome }}</div></td>
        <td width="22%"><div class="rotulo">CPF</div><div class="valor">{{ $doc($contrato->motorista_cpf) }}</div></td>
        <td width="38%"><div class="rotulo">Placas</div><div class="valor">{{ $contrato->placas }}</div></td>
    </tr>
</table>

<h2>Documentos da viagem</h2>
<table class="dados">
    <thead>
        <tr><th>CT-e</th><th>Emissão</th><th>Remetente / origem</th><th>Destinatário / destino</th><th class="num">Peso (kg)</th></tr>
    </thead>
    <tbody>
        @forelse($ctes as $cte)
            <tr>
                <td>{{ $cte->numeroFormatado() }}</td>
                <td>{{ $cte->emitido_em?->format('d/m/Y') ?: '—' }}</td>
                <td>{{ $cte->remetente['nome'] ?? '—' }}<br><span class="apagado">{{ $cte->municipio_inicio }}/{{ $cte->uf_inicio }}</span></td>
                <td>{{ $cte->destinatario['nome'] ?? '—' }}<br><span class="apagado">{{ $cte->municipio_fim }}/{{ $cte->uf_fim }}</span></td>
                <td class="num">{{ number_format((float) $cte->peso_kg, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="apagado">Nenhum CT-e autorizado ainda.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Valores do contrato</h2>
<table class="dados">
    <thead>
        <tr><th>Frete do motorista</th><th>IR</th><th>Falta de mercadoria</th><th>Seguro motorista</th><th>Seguro carga</th><th>Adiantamento</th><th>Saldo a pagar</th></tr>
    </thead>
    <tbody>
        <tr class="destaque">
            <td class="num">{{ $reais($contrato->frete_centavos) }}</td>
            <td class="num">{{ $reais($contrato->imposto_renda_centavos) }}</td>
            <td class="num">{{ $reais($contrato->falta_mercadoria_centavos) }}</td>
            <td class="num">{{ $reais($contrato->seguro_motorista_centavos) }}</td>
            <td class="num">{{ $reais($contrato->seguro_carga_centavos) }}</td>
            <td class="num">{{ $reais($contrato->adiantamento_centavos) }}</td>
            <td class="num">{{ $reais($contrato->saldo_centavos) }}</td>
        </tr>
    </tbody>
</table>
<p class="apagado">
    Saldo com vencimento em {{ $contrato->vencimento_saldo->format('d/m/Y') }}, mediante comprovante de entrega.
    Pagamento por {{ $contrato->forma_pagamento === 'pix' ? 'Pix, chave '.$contrato->chave_pix : 'transferência: banco '.$contrato->banco_codigo.', agência '.$contrato->agencia.($contrato->conta ? ', conta '.$contrato->conta : '') }}.
</p>

<table class="assinaturas">
    <tr>
        <td class="assinatura">{{ $emitente->razao_social }}</td>
        <td class="assinatura">{{ $contrato->contratado_nome }}</td>
    </tr>
</table>

{{-- Recibo do adiantamento: o motorista assina no ato do pagamento, por isso
     sai na mesma impressão, como no Transm. --}}
<div class="recibo">
    <h2 style="margin-top: 2px;">Recibo do adiantamento</h2>
    <p>
        <strong>Recebi de {{ $emitente->razao_social }} a importância de {{ $reais($contrato->adiantamento_centavos) }}
        ({{ \App\Support\ValorPorExtenso::reais($contrato->adiantamento_centavos) }}),</strong>
        referente ao adiantamento do frete da viagem {{ $viagem->numeroFormatado() }}.
    </p>
    <p>
        Os serviços têm valores previamente combinados, e o saldo de {{ $reais($contrato->saldo_centavos) }}
        será recebido mediante apresentação dos comprovantes de entrega ao contratante. Passo o presente recibo
        em duas vias de igual teor, dando quitação do valor acima.
    </p>
    <p style="text-align: right;">{{ $emitente->municipio }}, {{ ($contrato->emitido_em ?? now())->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}</p>
    <table class="assinaturas" style="margin-top: 26px;">
        <tr>
            <td class="assinatura">{{ $contrato->motorista_nome }}<div class="apagado" style="font-weight: normal;">CPF {{ $doc($contrato->motorista_cpf) }}</div></td>
        </tr>
    </table>
</div>

<div class="rodape">Gerado pelo EmitirAgora em {{ now()->format('d/m/Y H:i') }} · contrato {{ $contrato->numero }}</div>
</body>
</html>
