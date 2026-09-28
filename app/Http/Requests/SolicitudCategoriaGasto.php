<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudCategoriaGasto extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['nombre', 'descripcion']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:120', Rule::unique('categorias_gastos')->where('negocio_id', $this
            ->user()->negocio_id)->ignore($this
            ->route('id'))], 'descripcion' => ['nullable', 'string', 'max:1000'], 'deducible_iue' => ['sometimes', 'boolean'], 'activo' => ['sometimes', 'boolean']];
    }
}
