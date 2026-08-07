<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $restaurantName,
        public string $requestMessage,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = 'Nueva solicitud de acceso a Scan2Order';

        if ($this->restaurantName) {
            $subject .= ' - ' . $this->restaurantName;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact_request',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'restaurantName' => $this->restaurantName,
                'requestMessage' => $this->requestMessage,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
            ],
        );
    }
}