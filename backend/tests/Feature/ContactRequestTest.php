<?php

namespace Tests\Feature;

use App\Mail\ContactRequestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_request_queues_an_email_to_the_contact_address(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contact', [
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'phone' => '666 123 456',
            'restaurant_name' => 'Restaurante Demo',
            'message' => 'Necesito una cuenta para gestionar la carta digital y el QR.',
            'website' => '',
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('message', 'Gracias. Hemos recibido tu solicitud y te responderemos por email.');

        Mail::assertQueued(ContactRequestMail::class, function (ContactRequestMail $mail) {
            return $mail->hasTo(config('legal.contact_email'))
                && $mail->name === 'Cliente Demo'
                && $mail->email === 'cliente@example.com';
        });
    }

    public function test_contact_request_with_honeypot_does_not_queue_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'phone' => null,
            'restaurant_name' => null,
            'message' => 'Necesito una cuenta para gestionar la carta digital y el QR.',
            'website' => 'https://spam.example.com',
        ]);

        $response->assertAccepted();
        Mail::assertNothingQueued();
    }

    public function test_register_endpoint_is_disabled(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
        ]);

        $response->assertNotFound();
    }
}