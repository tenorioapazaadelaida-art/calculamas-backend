<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $esAdministrador = $request->user()->roles()
            ->where('identificador', 'administrador')
            ->exists();

        abort_unless($esAdministrador, 403, 'Solo el administrador puede consultar la auditoría.');

        return response()->json(Auditoria::where('negocio_id', $request->user()->negocio_id)
            ->with('usuario:id,nombre')
            ->latest('created_at')
            ->latest('id')
            ->limit(300)
            ->get());
    }
}
