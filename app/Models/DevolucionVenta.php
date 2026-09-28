<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionVenta extends Model
{
    protected $table = 'devoluciones_venta';

    protected $fillable = ['negocio_id', 'venta_id', 'usuario_id', 'numero', 'fecha', 'motivo', 'metodo_reembolso', 'con_nota_credito_debito', 'numero_nota_credito_debito', 'total', 'ajuste_debito_fiscal_iva', 'estado'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'con_nota_credito_debito' => 'boolean'];
    }

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function detalles(): HasMany { return $this->hasMany(DetalleDevolucionVenta::class); }
}
