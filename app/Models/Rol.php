<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['negocio_id', 'nombre', 'identificador', 'sistema', 'activo'];

    protected function casts(): array
    {
        return ['sistema' => 'boolean', 'activo' => 'boolean'];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_rol', 'rol_id', 'permiso_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'rol_usuario', 'rol_id', 'usuario_id');
    }
}
