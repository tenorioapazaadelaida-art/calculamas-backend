<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionCompra extends Model
{
    protected $table = 'devoluciones_compra';
    protected $fillable = ['negocio_id', 'compra_id', 'usuario_id', 'numero', 'fecha', 'motivo', 'solucion', 'medio_reembolso', 'con_nota_credito_debito', 'numero_nota_credito_debito', 'total', 'ajuste_credito_fiscal_iva', 'estado'];
    protected function casts(): array { return ['fecha' => 'datetime', 'con_nota_credito_debito' => 'boolean']; }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function detalles(): HasMany { return $this->hasMany(DetalleDevolucionCompra::class); }
}
