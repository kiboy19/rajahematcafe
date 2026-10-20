<?php

namespace App\Http\Requests;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVoucherRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'alpha_dash'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'value' => ['required', 'integer', 'min:1'],
            'max_discount' => ['nullable', 'required_if:type,percent', 'integer', 'min:1'],
            'min_subtotal' => ['nullable', 'integer', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'quota_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $code = mb_strtoupper(trim((string) $this->input('code')));
                if ($code !== '' && Voucher::query()->where('code', $code)->exists()) {
                    $validator->errors()->add('code', 'Kode voucher sudah terpakai.');
                }

                if ($this->input('type') === VoucherType::Percent->value && (int) $this->input('value') > 100) {
                    $validator->errors()->add('value', 'Persentase diskon tidak boleh melebihi 100%.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        $data = $this->validated();

        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['value'] = (int) $data['value'];
        $data['min_subtotal'] = (int) ($data['min_subtotal'] ?? 0);
        $data['quota'] = isset($data['quota']) ? (int) $data['quota'] : null;
        $data['quota_per_customer'] = (int) ($data['quota_per_customer'] ?? 1);
        $data['max_discount'] = $data['type'] === VoucherType::Percent->value ? (int) $data['max_discount'] : null;
        // Voucher tetap berlaku sampai akhir hari pada tanggal berakhirnya.
        $data['starts_at'] = isset($data['starts_at']) ? Carbon::parse($data['starts_at'])->startOfDay() : null;
        $data['expires_at'] = isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        $data['is_active'] = true;

        return $data;
    }
}
