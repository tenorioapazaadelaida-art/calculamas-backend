<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudAsignacionPermisos extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permisos' => ['present', 'array'], 'permisos.*' => ['string', Rule::exists('permisos', 'identificador')]];
    }
}
