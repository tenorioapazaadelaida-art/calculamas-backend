<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = ['negocio_id', 'usuario_id', 'numero', 'fecha', 'cliente_nombre', 'cliente_nit', 'subtotal', 'descuento', 'total', 'debito_fiscal_iva', 'impuesto_transacciones', 'con_factura', 'numero_factura', 'estado', 'metodo_pago', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'con_factura' => 'boolean'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(DevolucionVenta::class);
    }
}
