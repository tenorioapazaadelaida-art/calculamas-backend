<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudCompra extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['proveedor_nombre', 'observacion']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $n = $this->user()->negocio_id;

        return [
            'proveedor_nombre' => ['nullable', 'string', 'max:180'],
            'proveedor_nit' => ['nullable', 'string', 'max:30'],
            'fecha' => ['required', 'date'],
            'numero_factura' => ['nullable', 'string', 'max:80'],
            'cuf_autorizacion' => ['nullable', 'string', 'max:120'],
            'con_factura' => ['sometimes', 'boolean'],
            'descuento' => ['sometimes', 'numeric', 'min:0'],
            'importe_no_sujeto_iva' => ['sometimes', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')->where('negocio_id', $n)],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
            'detalles.*.presentacion' => ['sometimes', Rule::in(['unidad', 'paquete'])],
            'detalles.*.unidades_por_paquete' => ['required_if:detalles.*.presentacion,paquete', 'nullable', 'integer', 'min:2'],
        ];
    }
}
