<?php

namespace App\Services\Integrations;

use App\Models\CadastroIniciado;
use App\Models\Emitente;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Monta os eventos de conversão para a Conversions API do Meta: o Lead, quando
 * o e-mail é guardado, e o CompleteRegistration, quando a conta nasce.
 *
 * E-mail, telefone e nome vão hasheados (SHA-256), como a especificação de
 * "advanced matching" do Meta exige: são os dados que permitem casar o
 * evento com a pessoa sem mandar o dado em claro pela rede.
 */
class MontadorDeConversoesMeta
{
    /**
     * @param  array{fbp?: ?string, fbc?: ?string, ip?: ?string, user_agent?: ?string}  $sinaisDoNavegador
     * @return array<string, mixed>
     */
    public function paraCadastro(User $user, Emitente $emitente, string $eventId, string $eventSourceUrl, array $sinaisDoNavegador = []): array
    {
        return $this->evento(
            'CompleteRegistration', $eventId, $eventSourceUrl,
            $this->pessoa($user->email, $user->name, $emitente->telefone, $eventId, $sinaisDoNavegador),
        );
    }

    /**
     * O e-mail guardado no começo do cadastro (CadastroController), antes de
     * existir conta. O `event_id` sai do e-mail, e o navegador calcula o
     * mesmo: os dois lados mandam o Lead e o Meta conta um só.
     *
     * Os cookies do pixel que o site mandou valem quando a requisição não
     * traz os seus.
     *
     * @param  array{fbp?: ?string, fbc?: ?string, ip?: ?string, user_agent?: ?string}  $sinaisDoNavegador
     * @return array<string, mixed>
     */
    public function paraLead(CadastroIniciado $cadastro, string $eventSourceUrl, array $sinaisDoNavegador = []): array
    {
        $eventId = CadastroIniciado::idDoEventoLead($cadastro->email);
        $sinaisDoNavegador += array_filter(['fbp' => $cadastro->fbp, 'fbc' => $cadastro->fbc]);

        return $this->evento(
            'Lead', $eventId, $eventSourceUrl,
            $this->pessoa($cadastro->email, $cadastro->nome, $cadastro->telefone, $eventId, $sinaisDoNavegador),
        );
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function evento(string $nome, string $eventId, string $eventSourceUrl, array $userData): array
    {
        return [
            'event_name' => $nome,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'event_source_url' => $eventSourceUrl,
            'action_source' => 'website',
            'user_data' => $userData,
        ];
    }

    /**
     * @param  array{fbp?: ?string, fbc?: ?string, ip?: ?string, user_agent?: ?string}  $sinaisDoNavegador
     * @return array<string, mixed>
     */
    private function pessoa(string $email, string $nome, ?string $telefone, string $eventId, array $sinaisDoNavegador): array
    {
        $userData = [
            'em' => [$this->hash(mb_strtolower(trim($email)))],
            'external_id' => [$this->hash($eventId)],
        ];

        if (filled($telefone)) {
            $userData['ph'] = [$this->hash($this->normalizarTelefone($telefone))];
        }

        [$primeiroNome, $sobrenome] = $this->separarNome($nome);

        if ($primeiroNome !== '') {
            $userData['fn'] = [$this->hash($primeiroNome)];
        }

        if ($sobrenome !== '') {
            $userData['ln'] = [$this->hash($sobrenome)];
        }

        // fbp/fbc/ip/user_agent vêm crus, não hasheados: são sinais de rede e
        // de navegador, não dado pessoal do "advanced matching".
        foreach (['fbp' => 'fbp', 'fbc' => 'fbc', 'ip' => 'client_ip_address', 'user_agent' => 'client_user_agent'] as $origem => $campo) {
            if (filled($sinaisDoNavegador[$origem] ?? null)) {
                $userData[$campo] = $sinaisDoNavegador[$origem];
            }
        }

        return $userData;
    }

    private function hash(string $valor): string
    {
        return hash('sha256', $valor);
    }

    /**
     * Prefixa DDI 55 quando o telefone só tem DDD e número (10 ou 11
     * dígitos), do jeito que o Meta espera receber o telefone brasileiro.
     */
    private function normalizarTelefone(string $valor): string
    {
        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        return in_array(strlen($digitos), [10, 11], true) ? "55{$digitos}" : $digitos;
    }

    /** @return array{0: string, 1: string} */
    private function separarNome(string $nome): array
    {
        $semAcento = preg_replace('/[^a-z\s]/', ' ', Str::ascii(mb_strtolower(trim($nome)))) ?? '';
        $partes = array_values(array_filter(explode(' ', $semAcento)));

        return [
            $partes[0] ?? '',
            count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '',
        ];
    }
}
