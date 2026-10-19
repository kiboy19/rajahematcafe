<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value, ['owner', 'manager', 'kasir'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['dine_in', 'takeaway'])],
            'table_label' => ['nullable', 'required_if:channel,dine_in', 'string', 'max:20', Rule::exists('cafe_tables', 'label')->where('is_active', true)],
            'discount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
