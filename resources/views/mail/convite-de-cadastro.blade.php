<x-mail::message>
# Olá, {{ $primeiroNome }}!

Falta só criar sua senha e informar os dados da empresa para começar o teste grátis de 14 dias do EmitirAgora.

<x-mail::button :url="$link">
Criar minha senha
</x-mail::button>

O link vale por {{ $dias }} dias. Se não foi você que pediu, é só ignorar este e-mail: nenhuma conta é criada sem ele.
</x-mail::message>
