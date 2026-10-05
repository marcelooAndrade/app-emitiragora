<x-mail::message>
# Você já tem conta

Alguém pediu um cadastro novo no EmitirAgora com este e-mail, mas ele já tem uma conta. É só entrar:

<x-mail::button :url="$linkEntrar">
Entrar no EmitirAgora
</x-mail::button>

Esqueceu a senha? [Crie uma nova aqui]({{ $linkSenha }}).

Se não foi você, ignore este e-mail. Nada mudou na sua conta.
</x-mail::message>
