<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $fillable = ['negocio_id', 'usuario_actor_id', 'titulo', 'mensaje', 'modulo', 'accion', 'entidad', 'entidad_id', 'ruta'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'notificacion_usuario')
            ->withPivot('leida_en')->withTimestamps();
    }
}
