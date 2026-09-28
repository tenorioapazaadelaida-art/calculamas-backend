<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudInicioSesion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_negocio' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
            'nombre_dispositivo' => ['nullable', 'string', 'max:100'],
        ];
    }
}
