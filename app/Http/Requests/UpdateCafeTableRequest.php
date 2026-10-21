<?php

namespace App\Http\Requests;

use App\Models\CafeTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCafeTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('table')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CafeTable $table */
        $table = $this->route('table');

        return [
            'label' => ['required', 'string', 'max:20', Rule::unique('cafe_tables', 'label')->ignore($table->id)],
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
