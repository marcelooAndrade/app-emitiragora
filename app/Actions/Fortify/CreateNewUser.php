<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Jobs\EnviarConversaoMeta;
use App\Jobs\EnviarLeads;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Integrations\MontadorDeConversoesMeta;
use App\Services\Integrations\MontadorDeLeads;
use App\Support\Documento;
use App\Support\HostDoProduto;
use App\Support\PerfilDeCadastro;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cadastro pela porta do produto.
 *
 * Não cria só um usuário: cria a empresa inteira, porque neste sistema uma
 * conta solta não serve para nada. A permissão é escopada por emitente, então
 * sem emitente a pessoa entra e leva 403 em toda tela.
 *
 * São quatro coisas numa transação: tenant no plano gratuito, emitente,
 * usuário e papel de administrador. Se qualquer uma falhar, nenhuma fica.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            // Os limites de name, email e razao_social espelham o que o admin
            // pessoal aceita na entrada dele. Afrouxar aqui faria o cadastro
            // ser aceito neste lado e descartado em silêncio do lado de lá.
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'razao_social' => ['required', 'string', 'min:2', 'max:200'],
            'cnpj' => [
                'required', 'string',
                function (string $atributo, mixed $valor, callable $falhar): void {
                    if (! Documento::cnpjValido((string) $valor)) {
                        $falhar('O CNPJ informado não é válido.');
                    }
                },
                // Sem escopo de tenant: o CNPJ é único no país, não por cliente.
                Rule::unique('emitentes', 'cnpj')->where(
                    fn ($q) => $q->where('cnpj', Documento::normalizarCnpj((string) ($input['cnpj'] ?? '')))
                ),
            ],
            'inscricao_estadual' => ['required', 'string', 'max:20'],
            'crt' => ['required', 'string', Rule::in(['1', '2', '3'])],
            'telefone' => ['required', 'string', 'max:20'],
        ], [
            'razao_social.required' => 'Informe a razão social da empresa.',
            'cnpj.required' => 'Informe o CNPJ da empresa.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'inscricao_estadual.required' => 'Informe a inscrição estadual, ou ISENTO.',
            'crt.required' => 'Informe o regime tributário.',
            'telefone.required' => 'Informe um telefone para contato.',
        ])->validate();

        /** @var array{0: User, 1: Tenant} $criados */
        $criados = DB::transaction(function () use ($input): array {
            $tenant = Tenant::create([
                'nome' => $input['razao_social'],
                'slug' => $this->slugLivre($input['razao_social']),
                'plano' => PlanoTenant::Gratuito,
                'perfil_cadastro' => PerfilDeCadastro::doCadastro($input),
            ]);

            // O escopo de tenant é resolvido do contêiner, e a empresa acabou
            // de nascer. Sem isto, o emitente sairia com `tenant_id` nulo.
            app(TenantAtual::class)->definir($tenant);

            $emitente = Emitente::create([
                'tenant_id' => $tenant->getKey(),
                'razao_social' => $input['razao_social'],
                'cnpj' => Documento::normalizarCnpj($input['cnpj']),
                'inscricao_estadual' => $input['inscricao_estadual'],
                'crt' => $input['crt'],
                'telefone' => $input['telefone'],
            ]);

            $user = User::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->emitentes()->attach($emitente);

            // Papel é escopado por emitente: sem definir o time, o `assignRole`
            // grava no time errado e a pessoa entra sem permissão nenhuma.
            app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
            $user->assignRole(Perfil::Administrador->value);

            return [$user, $tenant];
        });

        [$user, $tenant] = $criados;

        // Fora da transação de propósito: rede não participa de commit, e uma
        // falha de envio não pode desfazer a criação da empresa. O job só entra
        // na fila depois que o banco confirmou.
        //
        // O try/catch existe porque avisar o admin pessoal é consequência do
        // cadastro, nunca condição dele, e essa garantia não pode depender de
        // qual driver de fila o ambiente usa. Com fila `sync` (a da suíte de
        // teste) o próprio dispatch() executa o job na hora, e uma falha ali
        // levantaria e derrubaria a resposta do cadastro com ela; com fila
        // `database` (a de produção) o dispatch() só enfileira e não lança,
        // então este catch não é acionado e não atrapalha as tentativas: o
        // job continua sendo repetido normalmente pela fila.
        //
        // O catch continua largo de propósito, e mesmo assim reportamos:
        // engolir aqui vale tanto para "o admin pessoal está fora do ar"
        // quanto para um bug de programação (um TypeError dentro do
        // MontadorDeLeads, uma classe errada num refactor futuro). O
        // report($e) manda a exceção para o rastreador de erros sem
        // interromper o cadastro, e a classe da exceção vai junto no log:
        // sem isso, ninguém consegue distinguir depois o que é falha normal
        // de rede do que é bug nosso escondido atrás da mesma mensagem.
        try {
            EnviarLeads::dispatch([
                app(MontadorDeLeads::class)->paraTenant($tenant),
            ])->afterCommit();
        } catch (\Throwable $e) {
            report($e);

            Log::warning('Falha ao despachar o envio de lead do cadastro.', [
                'tenant_id' => $tenant->getKey(),
                'excecao' => $e::class,
                'erro' => $e->getMessage(),
            ]);
        }

        // Mesmo motivo e mesma garantia do bloco acima: aviso ao Meta é
        // consequência do cadastro, nunca condição dele. O event_id é
        // estável (não um UUID novo a cada tentativa) para o Meta juntar
        // corretamente se o job precisar repetir, e é o mesmo id que a
        // sessão guarda abaixo para o navegador disparar o evento
        // equivalente na primeira tela do painel. Ver EnviarConversaoMeta
        // e partials/pixel-meta.
        try {
            $emitenteCriado = $user->emitentes()->first();

            if ($emitenteCriado) {
                $eventId = "cadastro-{$user->getKey()}";

                $evento = app(MontadorDeConversoesMeta::class)->paraCadastro(
                    $user,
                    $emitenteCriado,
                    $eventId,
                    request()->fullUrl(),
                    [
                        // `cookie()` com uma chave devolve string ou nulo; só
                        // devolve array quando chamado sem chave nenhuma. A
                        // checagem aqui é defensiva, para o tipo bater com o
                        // que o montador espera receber.
                        'fbp' => is_string($fbp = request()->cookie('_fbp')) ? $fbp : null,
                        'fbc' => is_string($fbc = request()->cookie('_fbc')) ? $fbc : null,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ],
                );

                EnviarConversaoMeta::dispatch($evento)->afterCommit();

                session()->flash('meta_pixel_evento', ['nome' => 'CompleteRegistration', 'id' => $eventId]);
            }
        } catch (\Throwable $e) {
            report($e);

            Log::warning('Falha ao despachar a conversão de cadastro ao Meta.', [
                'tenant_id' => $tenant->getKey(),
                'excecao' => $e::class,
                'erro' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    /**
     * Slug a partir do nome da empresa, sem colidir com o que é reservado.
     *
     * Reservado importa mesmo sem subdomínio de cliente: `app` capturaria o
     * host do próprio produto se o esquema de subdomínio voltar.
     */
    private function slugLivre(string $nome): string
    {
        $base = Str::limit(Str::slug($nome), 40, '') ?: 'empresa';
        $candidato = $base;
        $sufixo = 1;

        while (
            in_array($candidato, HostDoProduto::slugsReservados(), true)
            || Tenant::where('slug', $candidato)->exists()
        ) {
            $sufixo++;
            $candidato = "{$base}-{$sufixo}";
        }

        return $candidato;
    }
}
