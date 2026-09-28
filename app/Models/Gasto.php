<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $table = 'gastos';

    protected $fillable = ['negocio_id', 'categoria_gasto_id', 'usuario_id', 'fecha', 'concepto', 'periodicidad', 'periodo_desde', 'periodo_hasta', 'proveedor', 'numero_documento', 'monto', 'credito_fiscal_iva', 'con_factura', 'deducible_iue', 'estado', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'periodo_desde' => 'date', 'periodo_hasta' => 'date', 'con_factura' => 'boolean', 'deducible_iue' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_gasto_id');
    }
}
