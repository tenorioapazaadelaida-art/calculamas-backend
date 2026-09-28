<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudGasto extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['concepto', 'proveedor', 'observacion']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $n = $this->user()->negocio_id;

        return ['categoria_gasto_id' => ['required', 'integer', Rule::exists('categorias_gastos', 'id')->where('negocio_id', $n)], 'fecha' => ['required', 'date'], 'concepto' => ['required', 'string', 'max:200'], 'periodicidad' => ['required', Rule::in(['unico', 'diario', 'mensual', 'anual'])], 'periodo_referencia' => ['required_unless:periodicidad,unico', 'nullable', 'date'], 'proveedor' => ['nullable', 'string', 'max:180'], 'numero_documento' => ['nullable', 'string', 'max:80'], 'monto' => ['required', 'numeric', 'gt:0'], 'con_factura' => ['sometimes', 'boolean'], 'deducible_iue' => ['sometimes', 'boolean'], 'observacion' => ['nullable', 'string', 'max:2000']];
    }
}
