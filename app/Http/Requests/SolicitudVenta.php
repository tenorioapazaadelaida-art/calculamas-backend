<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudVenta extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['cliente_nombre', 'observacion']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $n = $this->user()->negocio_id;

        return ['fecha' => ['nullable', 'date'], 'cliente_nombre' => ['nullable', 'string', 'max:180'], 'cliente_nit' => ['nullable', 'string', 'max:30'], 'con_factura' => ['sometimes', 'boolean'], 'numero_factura' => ['nullable', 'required_if:con_factura,true', 'string', 'max:80'], 'descuento' => ['sometimes', 'numeric', 'min:0'], 'metodo_pago' => ['nullable', Rule::in(['efectivo', 'qr', 'transferencia', 'tarjeta', 'credito'])], 'observacion' => ['nullable', 'string', 'max:2000'], 'detalles' => ['required', 'array', 'min:1'], 'detalles.*.producto_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')->where('negocio_id', $n)], 'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'], 'detalles.*.precio_unitario' => ['nullable', 'numeric', 'gt:0'], 'detalles.*.tipo_utilidad_id' => ['nullable', 'integer', Rule::exists('tipos_utilidad', 'id')
            ->where('negocio_id', $n)], 'detalles.*.porcentaje_utilidad' => ['nullable', 'numeric', 'min:0', 'max:1000']];
    }
}
