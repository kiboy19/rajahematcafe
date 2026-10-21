<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value, ['owner', 'manager'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_name' => ['required', 'string', 'max:40'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_enabled' => ['boolean'],
            'service_name' => ['required', 'string', 'max:40'],
            'service_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_enabled' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
