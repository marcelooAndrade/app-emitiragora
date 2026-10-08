<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarConversaoMeta;
use App\Mail\ContaJaExiste;
use App\Mail\ConviteDeCadastro;
use App\Models\CadastroIniciado;
use App\Models\User;
use App\Services\Integrations\MontadorDeConversoesMeta;
use App\Support\PerfilDeCadastro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Cadastro em duas metades, com o e-mail no meio.
 *
 * 1. `iniciar`: nome, e-mail e telefone (do modal do site ou do /register)
 *    ficam guardados e um link vai para o e-mail. Nenhuma conta nasce aqui.
 * 2. `concluir`: o link abre o resto do formulário (senha e empresa). Só
 *    quem abriu o e-mail chega nele, e é isso que valida o endereço.
 *
 * A conta em si continua sendo criada pelo CreateNewUser, via Fortify, que
 * exige o convite do link.
 */
class CadastroController
{
    /** Quantos e-mails o mesmo endereço recebe por hora, no máximo. */
    private const EMAILS_POR_HORA = 3;

    public function iniciar(Request $request): JsonResponse|RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'telefone' => [
                'required', 'string', 'max:20',
                function (string $atributo, mixed $valor, callable $falhar): void {
                    $digitos = preg_replace('/^55(?=\d{10,11}$)/', '', preg_replace('/\D/', '', (string) $valor));
                    if (! in_array(strlen($digitos), [10, 11], true)) {
                        $falhar('Informe o WhatsApp com DDD.');
                    }
                },
            ],
            'origem' => ['nullable', 'string'],
            'fbp' => ['nullable', 'string', 'max:255'],
            'fbc' => ['nullable', 'string', 'max:255'],
        ], [
            'nome.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'telefone.required' => 'Informe o WhatsApp com DDD.',
        ]);

        $email = mb_strtolower(trim($dados['email']));

        // A resposta é a mesma em todos os caminhos abaixo (e-mail novo, conta
        // existente, limite estourado): ninguém descobre por aqui se um
        // endereço tem conta, nem usa a página para encher a caixa de alguém.
        $dentroDoLimite = RateLimiter::attempt(
            'cadastro-email:'.sha1($email),
            self::EMAILS_POR_HORA,
            fn () => true,
            3600,
        );

        if ($dentroDoLimite) {
            try {
                $this->enviar($email, $dados, $request);
            } catch (\Throwable $e) {
                report($e);
                Log::warning('Falha ao enviar o e-mail de cadastro.', ['excecao' => $e::class, 'erro' => $e->getMessage()]);

                $mensagem = 'Não conseguimos enviar o e-mail agora. Tente de novo em instantes.';

                return $request->expectsJson()
                    ? response()->json(['message' => $mensagem], 503)
                    : back()->withInput()->withErrors(['email' => $mensagem]);
            }
        }

        return $request->expectsJson()
            ? response()->json(['email' => $email])
            : redirect()->route('cadastro.enviado')->with('cadastro_email', $email);
    }

    public function enviado(): View|RedirectResponse
    {
        $email = session('cadastro_email');

        return $email ? view('cadastro.enviado', ['email' => $email]) : redirect()->route('register');
    }

    public function concluir(string $convite): View
    {
        $cadastro = CadastroIniciado::peloConvite($convite);

        if (! $cadastro) {
            return view('cadastro.link-invalido');
        }

        return view('cadastro.concluir', ['cadastro' => $cadastro, 'convite' => $convite]);
    }

    /** @param  array<string, mixed>  $dados */
    private function enviar(string $email, array $dados, Request $request): void
    {
        if (User::query()->withoutGlobalScopes()->where('email', $email)->exists()) {
            Mail::to($email)->send(new ContaJaExiste(route('login'), route('password.request')));

            return;
        }

        $cadastro = CadastroIniciado::firstOrNew(['email' => $email]);

        $cadastro->fill([
            'nome' => trim($dados['nome']),
            'telefone' => trim($dados['telefone']),
            // Os nomes que o site manda (tipo, frota, volume) viram os campos
            // `perfil_*` que o filtro conhece; valor fora da lista some.
            'perfil' => PerfilDeCadastro::doCadastro(collect(array_keys(PerfilDeCadastro::OPCOES))
                ->mapWithKeys(fn (string $pergunta) => ["perfil_{$pergunta}" => $request->input($pergunta)])
                ->all()) ?? $cadastro->perfil,
            // A URL do anúncio vem longa com utm e fbclid; corta em vez de
            // recusar, porque é contexto, não o dado principal.
            'origem' => Str::limit((string) ($dados['origem'] ?? $request->headers->get('referer', '')), 1000, '') ?: $cadastro->origem,
            'fbp' => ($dados['fbp'] ?? null) ?: $cadastro->fbp,
            'fbc' => ($dados['fbc'] ?? null) ?: $cadastro->fbc,
        ]);

        $convite = $cadastro->novoConvite();

        Mail::to($email)->send(new ConviteDeCadastro($cadastro->nome, route('cadastro.concluir', $convite)));

        $this->avisarMeta($cadastro, $request);
    }

    /**
     * O e-mail guardado é o Lead do anúncio. Consequência, nunca condição:
     * falha aqui não pode impedir o e-mail que já saiu, mesma regra do
     * CreateNewUser.
     */
    private function avisarMeta(CadastroIniciado $cadastro, Request $request): void
    {
        try {
            EnviarConversaoMeta::dispatch(app(MontadorDeConversoesMeta::class)->paraLead(
                $cadastro,
                (string) ($cadastro->origem ?: $request->headers->get('referer') ?: $request->fullUrl()),
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
