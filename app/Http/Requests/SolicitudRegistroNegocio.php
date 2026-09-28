<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudRegistroNegocio extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['nombre_negocio', 'tipo_negocio', 'direccion', 'nombre']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_negocio' => ['required', 'string', 'max:150'],
            'tipo_negocio' => ['required', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'impuestos_habilitados' => ['sometimes', 'boolean'],
            'regimen_tributario' => ['nullable', Rule::requiredIf($this->boolean('impuestos_habilitados')), 'string', 'max:80'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', 'unique:usuarios,correo'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
