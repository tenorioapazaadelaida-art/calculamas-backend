<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudUsuario;
use App\Models\Permiso;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(Usuario::where('negocio_id', $r->user()->negocio_id)
            ->with('roles:id,nombre,identificador')->orderBy('nombre')->get());
    }

    public function store(SolicitudUsuario $r): JsonResponse
    {
        $datos = $r->validated();
        $roles = $datos['roles'] ?? [];
        unset($datos['roles']);
        $usuario = DB::transaction(function () use ($r, $datos, $roles) {
            $u = Usuario::create($datos + ['negocio_id' => $r->user()->negocio_id]);
            $u->roles()->sync($roles);

            return $u;
        });

        return response()->json($usuario->load('roles:id,nombre,identificador'), 201);
    }

    public function update(SolicitudUsuario $r, int $id): JsonResponse
    {
        $usuario = Usuario::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
        $datos = $r->validated();
        $roles = array_key_exists('roles', $datos) ? $datos['roles'] : null;
        unset($datos['roles']);
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }DB::transaction(function () use ($usuario, $datos, $roles) {
            $usuario->update($datos);
            if ($roles !== null) {
                $usuario->roles()->sync($roles);
            }
        });

        return response()->json($usuario->load('roles:id,nombre,identificador'));
    }

    public function destroy(Request $r, int $id): JsonResponse
    {
        $usuario = Usuario::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);

        if ($usuario->is($r->user())) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        DB::transaction(function () use ($usuario) {
            $usuario->update(['activo' => false]);
            $usuario->tokens()->delete();
        });

        return response()->json(['message' => 'Usuario desactivado correctamente.']);
    }

    public function permisos(Request $r, int $id): JsonResponse
    {
        $usuario = Usuario::where('negocio_id', $r->user()->negocio_id)
            ->with('roles:id,nombre,identificador')
            ->findOrFail($id);

        return response()->json([
            'usuario' => $usuario,
            'permisos' => $usuario->permisos(),
        ]);
    }

    public function actualizarPermisos(Request $r, int $id): JsonResponse
    {
        $datos = $r->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', 'exists:permisos,identificador'],
        ]);
        $usuario = Usuario::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
        $anteriores = $usuario->permisos();
        $seleccionados = collect($datos['permisos']);
        $heredados = $usuario->roles()
            ->where('roles.activo', true)
            ->with('permisos:id,identificador')
            ->get()
            ->flatMap->permisos
            ->pluck('identificador')
            ->unique();

        $asignaciones = Permiso::all(['id', 'identificador'])->reduce(
            function (array $resultado, Permiso $permiso) use ($seleccionados, $heredados) {
                $deseado = $seleccionados->contains($permiso->identificador);
                $heredado = $heredados->contains($permiso->identificador);
                if ($deseado !== $heredado) {
                    $resultado[$permiso->id] = ['permitido' => $deseado];
                }

                return $resultado;
            },
            [],
        );

        $usuario->permisosDirectos()->sync($asignaciones);
        $nuevos = $usuario->permisos();
        app(ServicioAuditoria::class)->registrar(
            'asignar_permisos',
            'usuarios',
            $usuario->id,
            $usuario->negocio_id,
            ['permisos' => $anteriores],
            ['permisos' => $nuevos],
        );

        return response()->json([
            'usuario' => $usuario->load('roles:id,nombre,identificador'),
            'permisos' => $usuario->permisos(),
        ]);
    }

}
