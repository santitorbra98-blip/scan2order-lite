<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactRequestMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(ContactRequest $request)
    {
        $data = $request->validated();

        if (trim((string) ($data['website'] ?? '')) !== '') {
            return response()->json([
                'message' => 'Gracias. Hemos recibido tu solicitud y te responderemos por email.',
            ], 202);
        }

        $targetEmail = $this->resolveContactRecipient();
        if (!$targetEmail) {
            Log::error('Contact request rejected: no valid destination email configured.', [
                'legal_contact_email' => config('legal.contact_email'),
                'superadmin_email' => env('SUPERADMIN_EMAIL'),
                'mail_from_address' => config('mail.from.address'),
            ]);

            return response()->json([
                'message' => 'No pudimos procesar tu solicitud en este momento. Intentalo de nuevo en unos minutos.',
            ], 503);
        }

        try {
            Mail::to($targetEmail)->send(new ContactRequestMail(
                name: trim((string) $data['name']),
                email: mb_strtolower(trim((string) $data['email'])),
                phone: trim((string) ($data['phone'] ?? '')) ?: null,
                restaurantName: trim((string) ($data['restaurant_name'] ?? '')) ?: null,
                message: trim((string) $data['message']),
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
            ));
        } catch (\Throwable $e) {
            Log::error('Contact request email failed.', [
                'target_email' => $targetEmail,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No pudimos procesar tu solicitud en este momento. Intentalo de nuevo en unos minutos.',
            ], 503);
        }

        return response()->json([
            'message' => 'Gracias. Hemos recibido tu solicitud y te responderemos por email.',
        ], 202);
    }

    private function resolveContactRecipient(): ?string
    {
        $candidates = [
            (string) config('legal.contact_email', ''),
            (string) env('SUPERADMIN_EMAIL', ''),
            (string) config('mail.from.address', ''),
        ];

        foreach ($candidates as $candidate) {
            $email = mb_strtolower(trim($candidate));
            if (!$this->isUsableEmail($email)) {
                continue;
            }

            return $email;
        }

        return null;
    }

    private function isUsableEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $blocked = [
            'legal@tu-dominio.com',
            'legal@your-domain.com',
            'your-verified-sender@email.com',
        ];

        return !in_array($email, $blocked, true);
    }
}