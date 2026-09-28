<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudAsignacionPermisos;
use App\Http\Requests\SolicitudRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Services\ServicioAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(Rol::where('negocio_id', $r->user()->negocio_id)
            ->with('permisos:id,nombre,identificador,modulo,accion')->orderBy('nombre')->get());
    }

    public function store(SolicitudRol $r): JsonResponse
    {
        return response()->json(Rol::create($r->validated() + ['negocio_id' => $r->user()
            ->negocio_id]), 201);
    }

    public function update(SolicitudRol $r, int $id): JsonResponse
    {
        $rol = $this->buscar($r, $id);
        $datos = $r->validated();
        if ($rol->sistema) {
            unset($datos['identificador']);
        }$rol->update($datos);

        return response()->json($rol);
    }

    public function asignarPermisos(SolicitudAsignacionPermisos $r, int $id): JsonResponse
    {
        $rol = $this->buscar($r, $id);
        $anteriores = $rol->permisos()->pluck('identificador')->sort()->values()->all();
        $rol->permisos()->sync(Permiso::whereIn('identificador', $r->validated('permisos'))
            ->pluck('id'));
        $nuevos = $rol->permisos()->pluck('identificador')->sort()->values()->all();
        app(ServicioAuditoria::class)->registrar(
            'asignar_permisos',
            'roles',
            $rol->id,
            $rol->negocio_id,
            ['permisos' => $anteriores],
            ['permisos' => $nuevos],
        );

        return response()->json($rol->load('permisos:id,nombre,identificador,modulo,accion'));
    }

    private function buscar(Request $r, int $id): Rol
    {
        return Rol::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
    }
}
