<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudProducto;
use App\Models\Producto;
use App\Models\TipoUtilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $productos = Producto::where(
            'negocio_id',
            $request->user()->negocio_id,
        )
            ->with('categoria:id,nombre')
            ->orderBy('nombre')
            ->get();

        $esAdministrador = $request->user()
            ->roles()
            ->where('identificador', 'administrador')
            ->exists();
        if ($esAdministrador) {
            return response()->json($productos);
        }

        $tipo = TipoUtilidad::where(
            'negocio_id',
            $request->user()->negocio_id,
        )
            ->where('activo', true)
            ->with('productos:id')
            ->orderByDesc('predeterminada')
            ->orderBy('id')
            ->first();

        $productos->each(function (Producto $producto) use ($tipo) {
            $especifica = $tipo?->productos->firstWhere('id', $producto->id);
            $porcentaje = (float) (
                ($especifica && $especifica->pivot->activo)
                    ? $especifica->pivot->porcentaje
                    : ($tipo?->porcentaje_general ?? 0)
            );

            $precioVenta = round(
                (float) $producto->costo_promedio * (1 + $porcentaje / 100),
                2,
            );

            $producto->setAttribute('precio_venta_calculado', $precioVenta);
            $producto->setAttribute('tipo_utilidad_id', $tipo?->id);
            $producto->makeHidden(['costo_promedio']);
        });

        return response()->json($productos);
    }

    public function store(SolicitudProducto $request): JsonResponse
    {
        $datos = $request->validated();
        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto = Producto::create(
            $datos + ['negocio_id' => $request->user()->negocio_id],
        )->load('categoria:id,nombre,subcategoria');

        return response()->json($producto, 201);
    }

    public function update(SolicitudProducto $request, int $id): JsonResponse
    {
        $producto = $this->buscar($request, $id);
        $datos = $request->validated();
        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $request->file('imagen')->store('productos', 'public');
        }
        $producto->update($datos);

        return response()->json($producto->load('categoria:id,nombre'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->buscar($request, $id)->update(['activo' => false]);

        return response()->json(['message' => 'Producto desactivado correctamente.']);
    }

    private function buscar(Request $request, int $id): Producto
    {
        return Producto::where(
            'negocio_id',
            $request->user()->negocio_id,
        )->findOrFail($id);
    }
}
