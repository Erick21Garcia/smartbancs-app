<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'cuenta_origen_id' => ['required', 'uuid', 'exists:accounts,id'],
            'cuenta_destino_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'referencia_externa' => ['nullable', 'string', 'max:100'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['sometimes', 'string', 'size:3'],
        ];
    }
}