<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudCategoria;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Categoria::where('negocio_id', $request->user()->negocio_id)
            ->withCount('productos')->orderBy('nombre')->get());
    }

    public function store(SolicitudCategoria $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(Categoria::create($data + ['negocio_id' => $request->user()
            ->negocio_id]), 201);
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $categoria = Categoria::where('negocio_id', $request->user()->negocio_id)->findOrFail($id);
        if (! $datos['activo'] && $categoria->productos()
            ->where('negocio_id', $request->user()->negocio_id)->exists()) {
            return response()->json([
                'message' => 'No se puede desactivar esta categoría porque tiene productos registrados.',
            ], 422);
        }
        $categoria->update(['activo' => $datos['activo']]);

        return response()->json($categoria);
    }

    public function update(SolicitudCategoria $request, int $id): JsonResponse
    {
        $categoria = Categoria::where('negocio_id', $request->user()->negocio_id)->findOrFail($id);
        $categoria->update($request->validated());

        return response()->json($categoria->fresh());
    }
}
