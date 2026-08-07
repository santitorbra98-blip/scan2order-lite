<?php

namespace Tests\Feature;

use App\Mail\ContactRequestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_request_sends_an_email_to_the_contact_address(): void
    {
        Mail::fake();

        config()->set('legal.contact_email', 'legal@example.com');

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

        Mail::assertSent(ContactRequestMail::class, function (ContactRequestMail $mail) {
            return $mail->hasTo('legal@example.com')
                && $mail->name === 'Cliente Demo'
                && $mail->email === 'cliente@example.com';
        });
    }

    public function test_contact_request_falls_back_to_mail_from_when_contact_email_is_placeholder(): void
    {
        Mail::fake();

        config()->set('legal.contact_email', 'legal@tu-dominio.com');
        config()->set('mail.from.address', 'owner@example.com');

        $response = $this->postJson('/api/contact', [
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'phone' => '666 123 456',
            'restaurant_name' => 'Restaurante Demo',
            'message' => 'Necesito una cuenta para gestionar la carta digital y el QR.',
            'website' => '',
        ]);

        $response->assertAccepted();

        Mail::assertSent(ContactRequestMail::class, function (ContactRequestMail $mail) {
            return $mail->hasTo('cliente@example.com');
        });
    }

    public function test_contact_request_falls_back_to_sender_when_no_destination_is_configured(): void
    {
        Mail::fake();

        config()->set('legal.contact_email', 'legal@tu-dominio.com');
        config()->set('mail.from.address', '');

        $response = $this->postJson('/api/contact', [
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'phone' => '666 123 456',
            'restaurant_name' => 'Restaurante Demo',
            'message' => 'Necesito una cuenta para gestionar la carta digital y el QR.',
            'website' => '',
        ]);

        $response->assertAccepted();

        Mail::assertSent(ContactRequestMail::class, function (ContactRequestMail $mail) {
            return $mail->hasTo('cliente@example.com');
        });
    }

    public function test_contact_request_with_honeypot_does_not_send_email(): void
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