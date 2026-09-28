<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionImpuesto extends Model
{
    protected $table = 'configuraciones_impuestos';

    protected $fillable = ['negocio_id', 'codigo', 'nombre', 'alicuota', 'periodicidad', 'incluido_en_precio', 'activo', 'vigente_desde', 'vigente_hasta'];

    protected function casts(): array
    {
        return ['incluido_en_precio' => 'boolean', 'activo' => 'boolean', 'vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }
}
