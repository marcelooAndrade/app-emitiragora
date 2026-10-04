@props(['tenant'])

@php
    $emTeste = $tenant?->emTeste() ?? false;
    $somenteConsulta = $tenant?->somenteConsulta() ?? false;
    $pagamentoVencido = $tenant?->pagamentoVencido() ?? false;

    if ($emTeste || $somenteConsulta || $pagamentoVencido) {
        $mensagem = $tenant->pago_ate !== null
            ? 'Olá, quero acertar a mensalidade do EmitirAgora da empresa '.$tenant->nome
            : 'Olá, quero assinar o Plano '.$tenant->plano->rotulo().' do EmitirAgora para a empresa '.$tenant->nome;
        $whatsapp = 'https://wa.me/'.config('planos.whatsapp').'?text='.rawurlencode($mensagem);
        $dias = $tenant->diasDeTesteRestantes();
    }
@endphp

{{-- Teste grátis, mensalidade vencida e conta só para consulta. Some por
     completo para quem está em dia ou para as empresas que existiam antes da
     cobrança. --}}
@if ($emTeste)
    <div role="status" {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-primary-50 px-4 py-1.5 text-center text-graphite-800']) }}>
        <span>
            Teste grátis do Plano {{ $tenant->plano->rotulo() }}:
            <strong class="font-semibold">
                @if ($dias === 0)
                    hoje é o último dia
                @elseif ($dias === 1)
                    falta 1 dia
                @else
                    faltam {{ $dias }} dias
                @endif
            </strong>.
        </span>
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline underline-offset-2">Assinar pelo WhatsApp</a>
    </div>
@elseif ($pagamentoVencido)
    <div role="status" {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-ember-400 px-4 py-1.5 text-center text-graphite-900']) }}>
        <span>
            A mensalidade venceu em {{ $tenant->pago_ate->format('d/m/Y') }}.
            <strong class="font-semibold">Até {{ \Illuminate\Support\Carbon::parse($tenant->ultimoDiaDeTolerancia())->format('d/m/Y') }}</strong> o sistema segue normal; depois fica só para consulta.
        </span>
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="font-semibold underline underline-offset-2">Falar pelo WhatsApp</a>
    </div>
@elseif ($somenteConsulta)
    <div role="alert" {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-graphite-900 px-4 py-2 text-center text-white']) }}>
        <span>
            @if ($tenant->pago_ate !== null)
                A mensalidade está em aberto. A conta continua aberta só para consulta: para voltar a emitir, acerte o pagamento.
            @else
                Seu teste grátis acabou. A conta continua aberta só para consulta: para voltar a emitir, assine o plano.
            @endif
        </span>
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="rounded-full bg-primary-600 px-3 py-1 text-xs font-semibold text-white hover:bg-primary-700">Assinar pelo WhatsApp</a>
    </div>
@endif
