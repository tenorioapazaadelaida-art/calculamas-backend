<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['negocio_id', 'codigo_corto', 'nombre', 'subcategoria', 'descripcion', 'icono', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
