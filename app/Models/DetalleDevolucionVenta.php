<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleDevolucionVenta extends Model
{
    protected $table = 'detalles_devolucion_venta';
    protected $fillable = ['devolucion_venta_id', 'detalle_venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'costo_unitario', 'subtotal', 'costo_total', 'reintegrar_stock'];
    protected function casts(): array { return ['reintegrar_stock' => 'boolean']; }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function detalleVenta(): BelongsTo { return $this->belongsTo(DetalleVenta::class); }
    public function devolucion(): BelongsTo { return $this->belongsTo(DevolucionVenta::class, 'devolucion_venta_id'); }
}
