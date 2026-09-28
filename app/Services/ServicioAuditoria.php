<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

class ServicioAuditoria
{
    private const OCULTOS = ['password', 'remember_token', 'token'];

    public function registrar(
        string $accion,
        string $entidad,
        ?int $entidadId,
        ?int $negocioId,
        array $anteriores = [],
        array $nuevos = [],
    ): void {
        $auditoria = Auditoria::create([
            'negocio_id' => $negocioId,
            'usuario_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'datos_anteriores' => $this->limpiar($anteriores) ?: null,
            'datos_nuevos' => $this->limpiar($nuevos) ?: null,
            'direccion_ip' => request()?->ip(),
            'agente_usuario' => request()?->userAgent(),
        ]);
        app(ServicioNotificacion::class)->desdeAuditoria($auditoria);
    }

    public function registrarModelo(string $accion, Model $modelo, array $anteriores, array $nuevos): void
    {
        $this->registrar(
            $accion,
            $modelo->getTable(),
            $modelo->getKey(),
            $modelo->getAttribute('negocio_id') ?? auth()->user()?->negocio_id,
            $anteriores,
            $nuevos,
        );
    }

    private function limpiar(array $datos): array
    {
        return collect($datos)->except(self::OCULTOS)->all();
    }
}
