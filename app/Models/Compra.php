<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = ['negocio_id', 'proveedor_nombre', 'proveedor_nit', 'usuario_id', 'numero', 'numero_registro', 'numero_factura', 'cuf_autorizacion', 'fecha', 'subtotal', 'importe_no_sujeto_iva', 'descuento', 'base_credito_fiscal', 'total', 'credito_fiscal_iva', 'con_factura', 'estado', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'con_factura' => 'boolean'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(DevolucionCompra::class);
    }
}
