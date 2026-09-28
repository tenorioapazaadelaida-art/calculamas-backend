<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = ['negocio_id', 'nombre', 'correo', 'password', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'activo' => 'boolean'];
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'negocio_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id');
    }

    public function permisosDirectos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_usuario', 'usuario_id', 'permiso_id')
            ->withPivot('permitido');
    }

    public function notificaciones(): BelongsToMany
    {
        return $this->belongsToMany(Notificacion::class, 'notificacion_usuario')
            ->withPivot('leida_en')->withTimestamps();
    }

    public function permisos(): array
    {
        $esAdministrador = $this->roles()
            ->where('roles.activo', true)
            ->where('roles.identificador', 'administrador')
            ->exists();

        if ($esAdministrador) {
            return Permiso::query()
                ->orderBy('identificador')
                ->pluck('identificador')
                ->all();
        }

        $rol = $this->roles()->where('roles.activo', true)->with('permisos:id,identificador')
            ->get()->flatMap->permisos->pluck('identificador');
        $directos = $this->permisosDirectos()->get(['permisos.id', 'permisos.identificador']);

        return $rol->merge($directos->where('pivot.permitido', true)->pluck('identificador'))
            ->diff($directos->where('pivot.permitido', false)->pluck('identificador'))->unique()
            ->values()->all();
    }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos(), true);
    }
}
