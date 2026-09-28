<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudInicioSesion;
use App\Http\Requests\SolicitudRegistroNegocio;
use App\Models\Usuario;
use App\Services\RegistroNegocioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AutenticacionController extends Controller
{
    public function register(SolicitudRegistroNegocio $request, RegistroNegocioService $service): JsonResponse
    {
        $usuario = $service->registrar($request->validated());

        return response()->json($this->session($usuario, $request
            ->input('nombre_dispositivo', 'web')), 201);
    }

    public function login(SolicitudInicioSesion $request): JsonResponse
    {
        $usuario = Usuario::with('negocio', 'roles')
            ->where('correo', $request->string('correo'))
            ->whereHas('negocio', function ($consulta) use ($request) {
                $consulta->whereRaw('LOWER(nombre) = ?', [
                    Str::lower($request->string('nombre_negocio')->trim()->toString()),
                ]);
            })
            ->first();
        if (! $usuario || ! Hash::check($request->string('password'), $usuario->password)) {
            return response()->json(['message' => 'Las credenciales no son correctas.'], 422);
        }
        if (! $usuario->activo || ! $usuario->negocio?->activo) {
            return response()->json(['message' => 'La cuenta o el negocio están inactivos.'], 403);
        }

        return response()->json($this->session($usuario, $request
            ->input('nombre_dispositivo', 'web')));
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('negocio', 'roles');

        return response()->json(['usuario' => $usuario, 'permisos' => $usuario->permisos()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    private function session(Usuario $usuario, string $device): array
    {
        return ['token' => $usuario->createToken($device)
            ->plainTextToken, 'usuario' => $usuario, 'permisos' => $usuario->permisos()];
    }
}
