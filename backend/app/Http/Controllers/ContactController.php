<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactRequestMail;
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

        Mail::to(config('legal.contact_email'))->queue(new ContactRequestMail(
            name: trim((string) $data['name']),
            email: mb_strtolower(trim((string) $data['email'])),
            phone: trim((string) ($data['phone'] ?? '')) ?: null,
            restaurantName: trim((string) ($data['restaurant_name'] ?? '')) ?: null,
            message: trim((string) $data['message']),
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
        ));

        return response()->json([
            'message' => 'Gracias. Hemos recibido tu solicitud y te responderemos por email.',
        ], 202);
    }
}