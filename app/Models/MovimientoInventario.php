<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = ['negocio_id', 'producto_id', 'usuario_id', 'fecha', 'tipo', 'referencia_tipo', 'referencia_id', 'entrada_cantidad', 'entrada_costo_unitario', 'entrada_total', 'salida_cantidad', 'salida_costo_unitario', 'salida_total', 'saldo_cantidad', 'saldo_costo_promedio', 'saldo_total', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
