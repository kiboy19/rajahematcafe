<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Simpan email huruf kecil dan nomor HP dalam bentuk kanonik 08xxx,
     * supaya "0812..." dan "+62812..." tidak menjadi dua akun berbeda.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }

        if ($this->filled('phone')) {
            $this->merge(['phone' => Customer::normalizePhone((string) $this->input('phone'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique(Customer::class, 'email')],
            'phone' => ['required', 'string', 'regex:/^08[1-9][0-9]{6,11}$/', Rule::unique(Customer::class, 'phone')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Format nomor WhatsApp/telepon Indonesia tidak valid (contoh: 08123456789).',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'phone.unique' => 'Nomor HP ini sudah terdaftar. Silakan masuk.',
        ];
    }
}
