<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('is_available', true)],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:255'],
            'table_label' => ['nullable', 'string', 'max:20', Rule::exists('cafe_tables', 'label')->where('is_active', true)],
            'channel' => ['required', Rule::in(['dine_in', 'takeaway'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->input('quantity', 1),
            'channel' => $this->input('channel', 'dine_in'),
        ]);
    }
}
