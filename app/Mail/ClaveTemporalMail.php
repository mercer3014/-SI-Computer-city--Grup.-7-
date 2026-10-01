<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClaveTemporalMail extends Mailable
{
    public function __construct(
        public string $nombre,
        public string $email,
        public string $clave,
        public string $urlLogin,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu acceso a Computer City');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.clave-temporal');
    }
}
