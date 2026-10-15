<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotaVentaMail extends Mailable
{
    public function __construct(
        public Venta $venta,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nota de venta '.$this->venta->numero_venta.' — Computer City',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nota-venta');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, 'nota-'.$this->venta->numero_venta.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
