<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = ['negocio_id', 'categoria_id', 'subcategoria', 'codigo', 'codigo_barras', 'nombre', 'descripcion', 'imagen', 'color', 'precio_venta', 'stock_actual', 'stock_minimo', 'costo_promedio', 'activo'];

    protected $appends = ['imagen_url'];

    protected function casts(): array
    {
        return ['precio_venta' => 'decimal:2', 'stock_actual' => 'decimal:4', 'stock_minimo' => 'decimal:4', 'costo_promedio' => 'decimal:4', 'activo' => 'boolean'];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset('storage/'.$this->imagen) : null;
    }
}
