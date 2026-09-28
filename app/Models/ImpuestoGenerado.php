<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpuestoGenerado extends Model
{
    protected $table = 'impuestos_generados';

    protected $fillable = ['negocio_id', 'codigo', 'gestion', 'periodo', 'desde', 'hasta', 'base_imponible', 'debito_fiscal', 'credito_fiscal', 'saldo_anterior', 'importe_determinado', 'saldo_favor', 'estado', 'cerrado_en', 'numero_declaracion', 'fecha_pago'];

    protected function casts(): array
    {
        return ['desde' => 'date', 'hasta' => 'date', 'cerrado_en' => 'datetime', 'fecha_pago' => 'date'];
    }
}
