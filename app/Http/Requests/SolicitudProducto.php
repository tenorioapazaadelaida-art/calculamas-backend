<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudProducto extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo')), 'UTF-8'),
            'nombre' => $this->primeraMayuscula($this->input('nombre')),
            'subcategoria' => $this->primeraMayuscula($this->input('subcategoria')),
            'color' => $this->primeraMayuscula($this->input('color')),
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
        $n = $this->user()->negocio_id;
        $id = $this->route('id');

        return ['categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where(fn ($query) => $query->where('negocio_id', $n)->where('activo', true))], 'subcategoria' => ['nullable', 'string', 'max:120'], 'codigo' => ['required', 'string', 'max:60', Rule::unique('productos')
            ->where('negocio_id', $n)
            ->ignore($id)], 'codigo_barras' => ['nullable', 'string', 'max:80', Rule::unique('productos')
            ->where('negocio_id', $n)
            ->ignore($id)], 'nombre' => ['required', 'string', 'max:180'], 'descripcion' => ['nullable', 'string', 'max:2000'], 'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'], 'color' => ['nullable', 'string', 'max:40'], 'precio_venta' => ['required', 'numeric', 'min:0'], 'stock_minimo' => ['sometimes', 'numeric', 'min:0'], 'activo' => ['sometimes', 'boolean']];
    }
}
