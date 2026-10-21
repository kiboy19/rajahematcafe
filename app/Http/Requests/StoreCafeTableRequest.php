<?php

namespace App\Http\Requests;

use App\Models\CafeTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCafeTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CafeTable::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:20', Rule::unique('cafe_tables', 'label')],
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
