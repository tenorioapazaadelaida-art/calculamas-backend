<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleCompra extends Model
{
    protected $table = 'detalles_compra';

    protected $fillable = ['compra_id', 'producto_id', 'cantidad', 'costo_unitario', 'costo_inventario_unitario', 'subtotal', 'subtotal_inventario', 'presentacion', 'cantidad_presentacion', 'unidades_por_paquete', 'costo_presentacion'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function detallesDevolucion(): HasMany
    {
        return $this->hasMany(DetalleDevolucionCompra::class);
    }
}
