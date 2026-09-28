<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Notificacion;
use App\Models\Usuario;

class ServicioNotificacion
{
    private const MODULOS = [
        'compras' => ['compras', '/compras', 'Compra'],
        'ventas' => ['ventas', '/ventas', 'Venta'],
        'devoluciones_venta' => ['ventas', '/ventas', 'Devolución'],
        'devoluciones_compra' => ['compras', '/compras', 'Devolución al proveedor'],
        'gastos' => ['gastos', '/gastos', 'Gasto'],
        'categorias_gastos' => ['categorias_gastos', '/gastos', 'Categoría de gasto'],
        'productos' => ['productos', '/productos', 'Producto'],
        'categorias' => ['categorias', '/categorias', 'Categoría'],
        'usuarios' => ['usuarios', '/usuarios', 'Usuario'],
        'roles' => ['roles', '/usuarios', 'Rol o permiso'],
        'tipos_utilidad' => ['utilidades', '/ventas', 'Configuración de utilidad'],
        'configuraciones_impuestos' => ['impuestos', '/impuestos', 'Configuración tributaria'],
        'impuestos_generados' => ['impuestos', '/impuestos', 'Periodo tributario'],
    ];

    public function desdeAuditoria(Auditoria $auditoria): void
    {
        if (! $auditoria->negocio_id || ! $auditoria->usuario_id
            || ! isset(self::MODULOS[$auditoria->entidad])) {
            return;
        }
        [$modulo, $ruta, $entidadVisible] = self::MODULOS[$auditoria->entidad];
        $usuarioActor = Usuario::find($auditoria->usuario_id);
        if (! $usuarioActor || (int) $usuarioActor->negocio_id !== (int) $auditoria->negocio_id) {
            return;
        }
        $accion = $this->accionVisible($auditoria->accion);
        $datos = array_merge($auditoria->datos_anteriores ?? [], $auditoria->datos_nuevos ?? []);
        $referencia = $datos['numero'] ?? $datos['nombre'] ?? $datos['concepto'] ?? null;
        $actor = $usuarioActor->nombre;
        $mensaje = "{$actor} {$accion} ".mb_strtolower($entidadVisible);
        if ($referencia) {
            $mensaje .= ' “'.mb_strimwidth((string) $referencia, 0, 80, '…').'”';
        }
        $mensaje .= '.';

        $notificacion = Notificacion::create([
            'negocio_id' => $auditoria->negocio_id,
            'usuario_actor_id' => $auditoria->usuario_id,
            'titulo' => "{$entidadVisible}: ".ucfirst($accion),
            'mensaje' => $mensaje,
            'modulo' => $modulo,
            'accion' => $auditoria->accion,
            'entidad' => $auditoria->entidad,
            'entidad_id' => $auditoria->entidad_id,
            'ruta' => $ruta,
        ]);

        $destinatarios = Usuario::where('negocio_id', $auditoria->negocio_id)
            ->where('activo', true)->get()->filter(fn (Usuario $usuario) =>
                $usuario->id === $auditoria->usuario_id
                || $usuario->tienePermiso("{$modulo}.ver")
            )->pluck('id');
        $notificacion->usuarios()->sync($destinatarios);
        if ($destinatarios->isEmpty()) {
            $notificacion->delete();
        }
    }

    private function accionVisible(string $accion): string
    {
        return match ($accion) {
            'crear' => 'registró', 'editar' => 'actualizó', 'anular' => 'anuló',
            'desactivar' => 'desactivó', 'activar' => 'activó',
            'asignar_permisos' => 'cambió', default => str_replace('_', ' ', $accion),
        };
    }
}
