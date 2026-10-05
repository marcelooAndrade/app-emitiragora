<?php

namespace App\Mail;

use App\Models\CadastroIniciado;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** O link que confirma o e-mail e abre o resto do cadastro. */
class ConviteDeCadastro extends Mailable
{
    public function __construct(
        public readonly string $nome,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Crie sua senha no EmitirAgora');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.convite-de-cadastro', with: [
            'primeiroNome' => strtok($this->nome, ' '),
            'dias' => CadastroIniciado::DIAS_DO_CONVITE,
        ]);
    }
}
