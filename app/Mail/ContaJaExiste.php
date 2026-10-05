<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Pediram cadastro com um e-mail que já tem conta. Vai por e-mail, e não
 * como erro na tela, para a tela não contar a ninguém quem é cliente.
 */
class ContaJaExiste extends Mailable
{
    public function __construct(
        public readonly string $linkEntrar,
        public readonly string $linkSenha,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Você já tem conta no EmitirAgora');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.conta-ja-existe');
    }
}
