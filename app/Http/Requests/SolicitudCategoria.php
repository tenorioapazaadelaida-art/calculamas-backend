<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudCategoria extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $codigo = mb_strtoupper(trim((string) $this->input('codigo_corto')), 'UTF-8');
        $this->merge([
            'codigo_corto' => $codigo !== '' ? $codigo : null,
            'nombre' => $this->primeraMayuscula($this->input('nombre')),
            'subcategoria' => $this->primeraMayuscula($this->input('subcategoria')),
            'descripcion' => $this->primeraMayuscula($this->input('descripcion')),
        ]);
    }

    private function primeraMayuscula(mixed $valor): mixed
    {
        if (! is_string($valor) || trim($valor) === '') {
            return $valor;
        }
        $texto = trim($valor);

        return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($texto, 1, null, 'UTF-8');
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_corto' => ['nullable', 'string', 'max:20', Rule::unique('categorias')->where('negocio_id', $this
                ->user()->negocio_id)->ignore($this->route('id'))],
            'nombre' => ['required', 'string', 'max:120', Rule::unique('categorias')->where('negocio_id', $this
                ->user()->negocio_id)->ignore($this->route('id'))],
            'subcategoria' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'icono' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
