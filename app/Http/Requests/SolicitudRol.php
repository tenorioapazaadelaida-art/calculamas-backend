<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudRol extends FormRequest
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
        $crear = $this->isMethod('post');

        return ['nombre' => [$crear ? 'required' : 'sometimes', 'string', 'max:80'], 'identificador' => [$crear ? 'required' : 'sometimes', 'alpha_dash', 'max:80', Rule::unique('roles', 'identificador')->where('negocio_id', $this
            ->user()->negocio_id)->ignore($this
            ->route('id'))], 'activo' => ['sometimes', 'boolean']];
    }
}
