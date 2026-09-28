<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'negocio_id', 'usuario_id', 'accion', 'entidad', 'entidad_id',
        'datos_anteriores', 'datos_nuevos', 'direccion_ip', 'agente_usuario',
    ];

    protected function casts(): array
    {
        return ['datos_anteriores' => 'array', 'datos_nuevos' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
