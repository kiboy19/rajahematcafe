<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'method' => ['required', Rule::in(['cash', 'qris', 'edc'])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tendered' => ['nullable', 'required_if:method,cash', 'integer', 'min:0', 'max:1000000000'],
            'reference' => ['nullable', 'string', 'max:100', 'required_unless:method,cash', 'unique:payments,reference'],
        ];
    }
}
