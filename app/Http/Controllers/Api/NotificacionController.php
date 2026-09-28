<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $notificaciones = Notificacion::where('negocio_id', $usuario->negocio_id)
            ->whereHas('usuarios', fn ($q) => $q->where('usuarios.id', $usuario->id))
            ->with(['usuarios' => fn ($q) => $q->where('usuarios.id', $usuario->id)])
            ->latest()->limit(50)->get()->map(function (Notificacion $notificacion) {
                $notificacion->leida = $notificacion->usuarios->first()?->pivot?->leida_en !== null;
                unset($notificacion->usuarios);
                return $notificacion;
            });
        return response()->json($notificaciones);
    }

    public function leer(Request $request, int $id): JsonResponse
    {
        $notificacion = $this->buscar($request, $id);
        $notificacion->usuarios()->updateExistingPivot($request->user()->id, ['leida_en' => now('America/La_Paz')]);
        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $request->user()->notificaciones()->wherePivotNull('leida_en')->get()->each(
            fn (Notificacion $notificacion) => $notificacion->usuarios()
                ->updateExistingPivot($request->user()->id, ['leida_en' => now('America/La_Paz')])
        );
        return response()->json(['message' => 'Notificaciones marcadas como leídas.']);
    }

    private function buscar(Request $request, int $id): Notificacion
    {
        return Notificacion::where('negocio_id', $request->user()->negocio_id)
            ->whereHas('usuarios', fn ($q) => $q->where('usuarios.id', $request->user()->id))
            ->findOrFail($id);
    }
}
