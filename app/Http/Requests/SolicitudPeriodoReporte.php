<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudPeriodoReporte extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['gestion' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'mes' => ['nullable', 'integer', 'between:1,12']];
    }
}
