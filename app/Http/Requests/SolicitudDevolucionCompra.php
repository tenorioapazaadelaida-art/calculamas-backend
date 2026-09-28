<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudDevolucionCompra extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'max:500'],
            'solucion' => ['required', Rule::in(['reembolso', 'credito_proveedor', 'descuento_deuda', 'reemplazo'])],
            'medio_reembolso' => ['nullable', Rule::in(['efectivo', 'qr', 'transferencia', 'tarjeta'])],
            'numero_nota_credito_debito' => ['nullable', 'string', 'max:80'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.detalle_compra_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
