<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'string|max:255',
            'description' => 'nullable|string|max:2000',
            'active'      => 'boolean',
            'order'       => 'integer|min:0',
        ];
    }
}
