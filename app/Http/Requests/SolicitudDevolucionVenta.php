<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudDevolucionVenta extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'max:500'],
            'metodo_reembolso' => ['required', Rule::in(['efectivo', 'qr', 'transferencia', 'tarjeta', 'credito'])],
            'numero_nota_credito_debito' => ['nullable', 'string', 'max:80'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.detalle_venta_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.reintegrar_stock' => ['required', 'boolean'],
        ];
    }
}
