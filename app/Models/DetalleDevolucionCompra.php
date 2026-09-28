<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleDevolucionCompra extends Model
{
    protected $table = 'detalles_devolucion_compra';
    protected $fillable = ['devolucion_compra_id', 'detalle_compra_id', 'producto_id', 'cantidad', 'costo_compra_unitario', 'costo_inventario_unitario', 'subtotal', 'costo_inventario_total'];
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function detalleCompra(): BelongsTo { return $this->belongsTo(DetalleCompra::class); }
    public function devolucion(): BelongsTo { return $this->belongsTo(DevolucionCompra::class, 'devolucion_compra_id'); }
}
