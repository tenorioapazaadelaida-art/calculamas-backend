<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleVenta extends Model
{
    protected $table = 'detalles_venta';

    protected $fillable = ['venta_id', 'producto_id', 'tipo_utilidad_id', 'tipo_utilidad_nombre', 'porcentaje_utilidad', 'cantidad', 'precio_unitario', 'costo_unitario', 'subtotal', 'costo_total'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function detallesDevolucion(): HasMany
    {
        return $this->hasMany(DetalleDevolucionVenta::class);
    }
}
