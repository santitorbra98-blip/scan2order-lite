<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MfaCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $minutes,
        public string $purpose
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de verificación Scan2Order',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mfa_code',
            with: [
                'code' => $this->code,
                'minutes' => $this->minutes,
                'purpose' => $this->purpose,
            ],
        );
    }
}
