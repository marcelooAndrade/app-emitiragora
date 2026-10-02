<?php

namespace App\Services\Transporte;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Models\Cte;
use App\Models\EmitenteTransporte;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Averba o seguro da carga de um CT-e autorizado na AT&M.
 *
 * Portado do `AtmAverbacaoClient` do app-transm: autentica em /Auth (token
 * guardado por ~55 minutos), manda o XML autorizado em /CTe e lê o protocolo
 * e o número da averbação. Token vencido (HTTP 401 ou erro 915) é renovado
 * uma vez. "Já cadastrado" (001) com número de volta conta como averbado.
 *
 * O número da averbação vai sozinho para o MDF-e da viagem.
 */
class AverbacaoAtm
{
    /** Liga e desliga sem quebrar a emissão: quem chama decide se mostra o erro. */
    public function disponivel(Cte $cte): bool
    {
        $config = $cte->emitente->configuracaoTransporte();

        return $config->temAtm() && ($cte->ambiente === Ambiente::Homologacao || config('fiscal.atm.producao'));
    }

    public function averbar(Cte $cte, ?User $user = null): Cte
    {
        $cte->loadMissing(['emitente', 'viagem.mdfe']);
        if ($cte->status !== CteStatus::Autorizado) {
            throw new TransporteException('Só CT-e autorizado é averbado.');
        }
        $config = $cte->emitente->configuracaoTransporte();
        if (! $config->temAtm()) {
            throw new TransporteException('Informe usuário, senha e código da AT&M em Transporte, Configuração.');
        }
        if ($cte->ambiente !== Ambiente::Homologacao && ! config('fiscal.atm.producao')) {
            throw new TransporteException('A averbação automática pela AT&M está liberada só em homologação, como no Transm. Em produção, averbe no portal da seguradora e informe o número no MDF-e.');
        }
        $xml = $cte->xml_autorizado_path ? Storage::disk('fiscal')->get($cte->xml_autorizado_path) : null;
        if (blank($xml)) {
            throw new TransporteException('O XML autorizado deste CT-e não foi encontrado para averbar.');
        }

        $resposta = $this->enviar($config, $xml);
        if ($this->tokenVencido($resposta)) {
            Cache::forget($this->chaveToken($config));
            $resposta = $this->enviar($config, $xml);
        }
        $resultado = $this->ler($resposta);

        $cte->forceFill([
            'averbacao_status' => $resultado['status'],
            'averbacao_protocolo' => $resultado['protocolo'],
            'averbacao_numero' => $resultado['numero'],
            'averbacao_mensagem' => $resultado['mensagem'],
        ])->save();

        $viagem = $cte->viagem;
        if ($resultado['status'] === 'aprovada') {
            $this->levarParaMdfe($cte);
            $viagem->registrar('cte_averbado', "CT-e {$cte->numeroFormatado()} averbado na AT&M: {$resultado['numero']}.", ['cte_id' => $cte->getKey()], $user?->getKey());
        } else {
            $viagem->registrar('cte_averbacao_recusada', "AT&M recusou a averbação do CT-e {$cte->numeroFormatado()}: {$resultado['mensagem']}", ['cte_id' => $cte->getKey()], $user?->getKey());
        }

        return $cte;
    }

    /** O número entra nas averbações do MDF-e enquanto ele ainda não saiu. */
    private function levarParaMdfe(Cte $cte): void
    {
        $mdfe = $cte->viagem->mdfe;
        if ($mdfe === null || ! $mdfe->status->transmissivel() || blank($cte->averbacao_numero)) {
            return;
        }
        $seguro = (array) $mdfe->seguro;
        if ($seguro === []) {
            return;
        }
        $seguro['averbacoes'] = array_values(array_unique([...(array) ($seguro['averbacoes'] ?? []), $cte->averbacao_numero]));
        $mdfe->forceFill(['seguro' => $seguro])->save();
    }

    private function enviar(EmitenteTransporte $config, string $xml): Response
    {
        $token = $this->token($config);
        for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
            try {
                return $this->http()->withToken($token)->acceptJson()
                    ->withBody($xml, 'application/xml')
                    ->post($this->endpoint('CTe'));
            } catch (ConnectionException) {
                // Uma segunda tentativa, como no Transm.
            }
        }

        throw new TransporteException('A AT&M não respondeu à averbação do CT-e. Tente de novo em instantes.');
    }

    private function token(EmitenteTransporte $config): string
    {
        return Cache::remember(
            $this->chaveToken($config),
            now()->addSeconds((int) config('fiscal.atm.token_ttl_seconds', 3300)),
            fn (): string => $this->autenticar($config),
        );
    }

    private function autenticar(EmitenteTransporte $config): string
    {
        try {
            $resposta = $this->http()->acceptJson()->asJson()->post($this->endpoint('Auth'), [
                'usuario' => (string) $config->atm_usuario,
                'senha' => (string) $config->atm_senha,
                'codigoatm' => (string) $config->atm_codigo,
            ]);
        } catch (ConnectionException) {
            throw new TransporteException('A autenticação da AT&M não respondeu. Tente de novo em instantes.');
        }
        if (! $resposta->successful()) {
            throw new TransporteException($this->mensagemHttp($resposta, 'A AT&M recusou o login (confira usuário, senha e código)'));
        }

        $token = $this->extrairToken($resposta);
        if ($token === '') {
            throw new TransporteException('A AT&M aceitou o login, mas não devolveu token.');
        }

        return $token;
    }

    private function tokenVencido(Response $resposta): bool
    {
        return $resposta->status() === 401
            || collect($this->mensagens($resposta->json(), 'Erros', 'Erro'))->contains(fn (array $m): bool => $m['codigo'] === '915');
    }

    /** @return array{status: string, protocolo: ?string, numero: ?string, mensagem: string} */
    private function ler(Response $resposta): array
    {
        $corpo = $resposta->json();
        if (! is_array($corpo)) {
            throw new TransporteException('A AT&M devolveu uma resposta que não deu para ler (HTTP '.$resposta->status().').');
        }

        $erros = $this->mensagens($corpo, 'Erros', 'Erro');
        $infos = $this->mensagens($corpo, 'Infos', 'Info');
        $averbado = $this->lista($corpo, 'Averbado');
        $protocolo = $this->texto($averbado, 'Protocolo');
        $dadosSeguro = $this->registros($this->lista($averbado, 'DadosSeguro'))[0] ?? [];
        $numero = $this->texto($dadosSeguro, 'NumeroAverbacao');
        $duplicado = collect([...$erros, ...$infos])->contains(fn (array $m): bool => $m['codigo'] === '001');
        $aprovada = $averbado !== [] && ($protocolo !== '' || $numero !== '');

        $mensagem = $aprovada
            ? ($duplicado ? 'CT-e já estava averbado; a AT&M confirmou.' : 'Averbação aceita pela AT&M.')
            : ($this->juntar([...$erros, ...$infos]) ?: $this->mensagemHttp($resposta, 'A AT&M recusou a averbação'));

        return [
            'status' => $aprovada ? 'aprovada' : 'recusada',
            'protocolo' => $protocolo !== '' ? mb_substr($protocolo, 0, 100) : null,
            'numero' => $numero !== '' ? mb_substr($numero, 0, 40) : null,
            'mensagem' => mb_substr($mensagem, 0, 2000),
        ];
    }

    private function extrairToken(Response $resposta): string
    {
        $corpo = $resposta->json();
        if (is_string($corpo)) {
            return trim($corpo);
        }
        if (is_array($corpo)) {
            foreach (['token', 'access_token', 'authtoken', 'bearer'] as $chave) {
                if (($valor = $this->textoEmQualquerNivel($corpo, $chave)) !== '') {
                    return $valor;
                }
            }
        }
        $texto = trim(trim($resposta->body()), '"');

        return str_starts_with($texto, '{') || str_starts_with($texto, '[') ? '' : $texto;
    }

    /** @return array<int, array{codigo: string, descricao: string}> */
    private function mensagens(mixed $corpo, string $grupo, string $item): array
    {
        if (! is_array($corpo)) {
            return [];
        }

        return collect($this->registros($this->lista($this->lista($corpo, $grupo), $item)))
            ->map(fn (array $m): array => ['codigo' => $this->texto($m, 'Codigo'), 'descricao' => $this->texto($m, 'Descricao')])
            ->filter(fn (array $m): bool => $m['codigo'] !== '' || $m['descricao'] !== '')
            ->values()->all();
    }

    private function juntar(array $mensagens): string
    {
        return collect($mensagens)
            ->map(fn (array $m): string => trim(implode(' · ', array_filter([$m['codigo'], $m['descricao']]))))
            ->filter()->unique()->join(' | ');
    }

    private function mensagemHttp(Response $resposta, string $prefixo): string
    {
        $corpo = $resposta->json();
        $texto = $this->juntar([...$this->mensagens($corpo, 'Erros', 'Erro'), ...$this->mensagens($corpo, 'Infos', 'Info')]);

        return $texto !== '' ? "{$prefixo}: {$texto}" : "{$prefixo} (HTTP {$resposta->status()}).";
    }

    /** A AT&M devolve um registro solto ou uma lista deles para o mesmo campo. */
    private function registros(array $valor): array
    {
        if ($valor === []) {
            return [];
        }

        return array_is_list($valor) ? array_values(array_filter($valor, 'is_array')) : [$valor];
    }

    private function lista(array $valores, string $chave): array
    {
        foreach ($valores as $candidata => $valor) {
            if (is_string($candidata) && strcasecmp($candidata, $chave) === 0 && is_array($valor)) {
                return $valor;
            }
        }

        return [];
    }

    private function texto(array $valores, string $chave): string
    {
        foreach ($valores as $candidata => $valor) {
            if (is_string($candidata) && strcasecmp($candidata, $chave) === 0 && is_scalar($valor)) {
                return trim((string) $valor);
            }
        }

        return '';
    }

    private function textoEmQualquerNivel(array $valores, string $chave): string
    {
        foreach ($valores as $candidata => $valor) {
            if (is_string($candidata) && strcasecmp($candidata, $chave) === 0 && is_scalar($valor)) {
                return trim((string) $valor);
            }
            if (is_array($valor) && ($achado = $this->textoEmQualquerNivel($valor, $chave)) !== '') {
                return $achado;
            }
        }

        return '';
    }

    private function http(): PendingRequest
    {
        return Http::connectTimeout((int) config('fiscal.atm.connect_timeout', 10))
            ->timeout((int) config('fiscal.atm.timeout', 45))
            ->withOptions(['allow_redirects' => false]);
    }

    private function endpoint(string $recurso): string
    {
        return rtrim((string) config('fiscal.atm.url'), '/').'/'.$recurso;
    }

    private function chaveToken(EmitenteTransporte $config): string
    {
        return 'transporte:atm:token:'.hash('sha256', $config->emitente_id.'|'.$config->atm_usuario.'|'.$config->atm_codigo);
    }
}
