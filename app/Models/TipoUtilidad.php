<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoUtilidad extends Model
{
    protected $table = 'tipos_utilidad';

    protected $fillable = ['negocio_id', 'nombre', 'porcentaje_general', 'predeterminada', 'activo', 'usuario_registro_id'];

    protected function casts(): array
    {
        return ['porcentaje_general' => 'decimal:2', 'predeterminada' => 'boolean', 'activo' => 'boolean'];
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_tipo_utilidad')
            ->withPivot(['porcentaje', 'activo'])->withTimestamps();
    }
}
