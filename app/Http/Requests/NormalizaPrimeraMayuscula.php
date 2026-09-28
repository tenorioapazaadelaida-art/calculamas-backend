<?php

namespace App\Http\Requests;

trait NormalizaPrimeraMayuscula
{
    protected function normalizarPrimeraMayuscula(array $campos): void
    {
        $datos = [];
        foreach ($campos as $campo) {
            $valor = $this->input($campo);
            if (! is_string($valor) || trim($valor) === '') {
                continue;
            }
            $texto = trim($valor);
            $datos[$campo] = mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($texto, 1, null, 'UTF-8');
        }
        $this->merge($datos);
    }
}
