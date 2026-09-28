<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudUsuario extends FormRequest
{
    use NormalizaPrimeraMayuscula;

    protected function prepareForValidation(): void
    {
        $this->normalizarPrimeraMayuscula(['nombre']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $crear = $this->isMethod('post');

        return ['nombre' => [$crear ? 'required' : 'sometimes', 'string', 'max:255'], 'correo' => [$crear ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($id)], 'password' => [$crear ? 'required' : 'nullable', 'string', 'min:8'], 'activo' => ['sometimes', 'boolean'], 'roles' => ['sometimes', 'array'], 'roles.*' => ['integer', Rule::exists('roles', 'id')
            ->where('negocio_id', $this->user()->negocio_id)]];
    }
}
