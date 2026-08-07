<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by RestaurantPolicy in controller.
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'active'      => 'boolean',
            'order'       => 'integer|min:0',
        ];
    }
}
