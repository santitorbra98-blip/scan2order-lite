<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'phone'            => 'nullable|string|max:25',
            'email'            => 'required|string|email|max:255',
            'password'         => 'required|string|min:12|confirmed',
            'accept_terms'     => 'required|accepted',
            'accept_privacy'   => 'required|accepted',
            'accept_marketing' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'El nombre es obligatorio.',
            'email.required'          => 'El email es obligatorio.',
            'email.email'             => 'El email no tiene un formato válido.',
            'password.required'       => 'La contraseña es obligatoria.',
            'password.min'            => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed'      => 'La confirmación de contraseña no coincide.',
            'accept_terms.required'   => 'Debes aceptar los términos de servicio.',
            'accept_terms.accepted'   => 'Debes aceptar los términos de servicio.',
            'accept_privacy.required' => 'Debes aceptar la política de privacidad.',
            'accept_privacy.accepted' => 'Debes aceptar la política de privacidad.',
        ];
    }
}
