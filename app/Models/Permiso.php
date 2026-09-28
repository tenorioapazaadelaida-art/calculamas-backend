<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = ['nombre', 'identificador', 'modulo', 'accion'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'permiso_rol', 'permiso_id', 'rol_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'permiso_usuario', 'permiso_id', 'usuario_id')
            ->withPivot('permitido');
    }
}
