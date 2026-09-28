<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Negocio extends Model
{
    protected $table = 'negocios';

    protected $fillable = ['nombre', 'razon_social', 'nit', 'tipo_negocio', 'telefono', 'direccion', 'moneda', 'zona_horaria', 'impuestos_habilitados', 'regimen_tributario', 'iva_habilitado', 'it_habilitado', 'iue_habilitado', 'activo'];

    protected function casts(): array
    {
        return ['impuestos_habilitados' => 'boolean', 'iva_habilitado' => 'boolean', 'it_habilitado' => 'boolean', 'iue_habilitado' => 'boolean', 'activo' => 'boolean'];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'negocio_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class, 'negocio_id');
    }
}
