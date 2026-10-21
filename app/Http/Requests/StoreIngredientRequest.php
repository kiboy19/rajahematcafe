<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ingredient::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'initial_stock' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }
}
