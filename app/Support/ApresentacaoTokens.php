<?php

namespace App\Support;

/**
 * Tokens visuais do template da apresentação (Aura): ícones, cartão, botões.
 *
 * Compartilhado por View::composer com toda view que segue essa identidade
 * (`apresentacao`, `contadores`, `components.planos`), porque `@include` do
 * Blade não devolve variável para quem inclui: cada view compila para uma
 * função PHP própria, então um `@php` dentro do parcial não escapa dele.
 */
class ApresentacaoTokens
{
    /** @return array<string, mixed> */
    public static function compartilhados(): array
    {
        // Ícones decorativos, geométricos e genéricos, desenhados à mão para
        // não trazer dependência nova. Cada entrada é o miolo de um SVG
        // 24x24 de traço.
        $icones = [
            'recibo' => '<path d="M6 3h9l3 3v15l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
            'caixas' => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
            'dinheiro' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
            'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'escudo' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
            'upload' => '<path d="M12 15V4M8 8l4-4 4 4"/><path d="M4 15v3a2 2 0 002 2h12a2 2 0 002-2v-3"/>',
            'predio' => '<path d="M4 21V5a1 1 0 011-1h6a1 1 0 011 1v16"/><path d="M13 21V9l6 2v10"/><path d="M7 8h1M7 12h1M7 16h1M11 8h1M11 12h1M11 16h1"/>',
            'painel' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 15l3-4 3 2 4-6"/>',
            'pasta' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
            'check' => '<path d="M5 12l5 5L19 7"/>',
            'pessoas' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15 14.3c2.6.2 4.5 2.1 4.5 5.2"/>',
        ];

        // Chip de ícone do template: círculo claro com brilho interno.
        $chip = function (string $nome, string $tamanho = 'w-11 h-11') use ($icones) {
            return '<span class="inline-flex '.$tamanho.' shrink-0 items-center justify-center rounded-2xl border border-white bg-white/80 text-primary-700 shadow-[0_2px_8px_rgba(14,27,31,0.06),inset_0_1px_0_white]">'
                .'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" aria-hidden="true">'
                .$icones[$nome]
                .'</svg></span>';
        };

        return [
            'icones' => $icones,
            'chip' => $chip,
            'rotulo' => 'fonte-mono text-xs font-medium tracking-[-0.04em] text-primary-800 mb-4',
            'titulo' => 'text-4xl md:text-5xl lg:text-6xl font-normal tracking-tight text-graphite-900 leading-[1.05] max-w-5xl mx-auto',
            'subtitulo' => 'mt-6 text-base md:text-lg leading-8 text-graphite-600 font-light max-w-3xl mx-auto',
            'cartao' => 'rounded-[2rem] border border-white bg-white/68 p-6 shadow-[0_10px_28px_-18px_rgba(14,27,31,0.24),inset_0_1px_0_white] transition-all duration-300 hover:-translate-y-1 hover:bg-white/84',
            'vidro' => 'relative overflow-hidden rounded-[2.75rem] border border-white bg-white/60 shadow-[0_30px_80px_-45px_rgba(14,27,31,0.35),inset_0_1px_0_rgba(255,255,255,1)] backdrop-blur-xl',
            'escuro' => 'relative overflow-hidden rounded-[2.75rem] border border-white/10 bg-gradient-to-b from-graphite-800 to-graphite-900 text-white shadow-[0_40px_90px_-45px_rgba(14,27,31,0.78),inset_0_1px_0_rgba(255,255,255,0.14)]',
            'botaoCheio' => 'inline-flex whitespace-nowrap items-center justify-center rounded-full border border-primary-800 bg-gradient-to-b from-primary-600 to-primary-700 px-5 py-2.5 text-xs font-medium text-on-primary shadow-[0_5px_14px_rgba(228,87,46,0.28),inset_0_1px_0_rgba(255,255,255,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:from-primary-700 hover:to-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700',
            'botaoClaro' => 'inline-flex whitespace-nowrap items-center justify-center rounded-full border border-graphite-200 bg-white/78 px-5 py-2.5 text-xs font-normal text-graphite-700 shadow-[0_1px_2px_rgba(14,27,31,0.04),inset_0_1px_0_white] transition-all duration-300 hover:-translate-y-0.5 hover:bg-white hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700',
        ];
    }
}
