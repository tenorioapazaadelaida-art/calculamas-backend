<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudPeriodoImpuesto extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['gestion' => ['required', 'integer', 'min:2000', 'max:2100'], 'mes' => ['required', 'integer', 'between:1,12']];
    }
}
