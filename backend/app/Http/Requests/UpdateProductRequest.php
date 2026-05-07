<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    private const ALLOWED_ALLERGENS = [
        'gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy',
        'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulfites',
        'lupins', 'mollusks',
    ];

    private const ALLOWED_DIET_TAGS = [
        'vegan', 'vegetarian', 'gluten_free', 'lactose_free',
        'keto', 'halal', 'spicy', 'low_calorie',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeJsonArray('allergens');
        $this->normalizeJsonArray('diet_tags');
    }

    public function rules(): array
    {
        return [
            'name'         => 'string|max:255',
            'description'  => 'nullable|string|max:2000',
            'price'        => 'numeric|min:0',
            'active'       => 'boolean',
            'show_image'   => 'boolean',
            'is_new'       => 'boolean',
            'allergens'    => 'nullable|array',
            'allergens.*'  => ['string', Rule::in(self::ALLOWED_ALLERGENS)],
            'diet_tags'    => 'nullable|array',
            'diet_tags.*'  => ['string', Rule::in(self::ALLOWED_DIET_TAGS)],
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_image' => 'boolean',
        ];
    }

    private function normalizeJsonArray(string $field): void
    {
        if (!$this->exists($field)) {
            return;
        }
        $value = $this->input($field);
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([$field => $decoded]);
                return;
            }
        }
        if ($value === null || $value === '') {
            $this->merge([$field => []]);
        }
    }
}
