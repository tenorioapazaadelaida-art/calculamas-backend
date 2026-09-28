<?php

namespace App\Observers;

use App\Services\ServicioAuditoria;
use Illuminate\Database\Eloquent\Model;

class AuditoriaObserver
{
    public function created(Model $modelo): void
    {
        app(ServicioAuditoria::class)->registrarModelo('crear', $modelo, [], $modelo->getAttributes());
    }

    public function updated(Model $modelo): void
    {
        $cambios = $modelo->getChanges();
        unset($cambios['updated_at']);
        if ($modelo instanceof \App\Models\Producto) {
            unset($cambios['stock_actual'], $cambios['costo_promedio']);
        }
        if ($cambios === []) {
            return;
        }
        $anteriores = collect(array_keys($cambios))->mapWithKeys(
            fn (string $campo) => [$campo => $modelo->getOriginal($campo)],
        )->all();
        $accion = match (true) {
            array_key_exists('estado', $cambios) && in_array($cambios['estado'], ['anulada', 'anulado'], true) => 'anular',
            array_key_exists('activo', $cambios) && $cambios['activo'] === false => 'desactivar',
            array_key_exists('activo', $cambios) && $cambios['activo'] === true => 'activar',
            default => 'editar',
        };
        app(ServicioAuditoria::class)->registrarModelo($accion, $modelo, $anteriores, $cambios);
    }
}
