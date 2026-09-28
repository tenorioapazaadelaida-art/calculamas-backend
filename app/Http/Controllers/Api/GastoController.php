<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudCategoriaGasto;
use App\Http\Requests\SolicitudGasto;
use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\ImpuestoGenerado;
use App\Models\Negocio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function categorias(Request $r): JsonResponse
    {
        return response()->json(CategoriaGasto::where('negocio_id', $r->user()->negocio_id)
            ->orderBy('nombre')->get());
    }

    public function guardarCategoria(SolicitudCategoriaGasto $r): JsonResponse
    {
        return response()->json(CategoriaGasto::create($r->validated() + ['negocio_id' => $r
            ->user()->negocio_id]), 201);
    }

    public function actualizarCategoria(SolicitudCategoriaGasto $r, int $id): JsonResponse
    {
        $categoria = $this->buscarCategoria($r, $id);
        $categoria->update($r->validated());

        return response()->json($categoria->fresh());
    }

    public function cambiarEstadoCategoria(Request $r, int $id): JsonResponse
    {
        $categoria = $this->buscarCategoria($r, $id);
        $categoria->update(['activo' => ! $categoria->activo]);

        return response()->json($categoria->fresh());
    }

    public function index(Request $r): JsonResponse
    {
        return response()->json(Gasto::where('negocio_id', $r->user()->negocio_id)
            ->with('categoria:id,nombre')
            ->latest('fecha')
            ->get());
    }

    public function store(SolicitudGasto $r): JsonResponse
    {
        $datos = $this->prepararDatos($r, $r->validated());

        return response()->json(Gasto::create($datos + [
            'negocio_id' => $r->user()->negocio_id,
            'usuario_id' => $r->user()->id,
        ])->load('categoria:id,nombre'), 201);
    }

    public function show(Request $r, int $id): JsonResponse
    {
        return response()->json($this->buscarGasto($r, $id)
            ->load('categoria:id,nombre'));
    }

    public function update(SolicitudGasto $r, int $id): JsonResponse
    {
        $gasto = $this->buscarGasto($r, $id);
        abort_if($gasto->estado === 'anulado', 422, 'No se puede editar un gasto anulado.');
        $this->validarPeriodoAbierto($r->user()->negocio_id, Carbon::parse($gasto->fecha));
        $gasto->update($this->prepararDatos($r, $r->validated()));

        return response()->json($gasto->fresh()->load('categoria:id,nombre'));
    }

    public function destroy(Request $r, int $id): JsonResponse
    {
        $gasto = $this->buscarGasto($r, $id);
        abort_if($gasto->estado === 'anulado', 422, 'El gasto ya se encuentra anulado.');
        $this->validarPeriodoAbierto($r->user()->negocio_id, Carbon::parse($gasto->fecha));
        $gasto->update(['estado' => 'anulado']);

        return response()->json($gasto->fresh()->load('categoria:id,nombre'));
    }

    private function prepararDatos(Request $r, array $datos): array
    {
        $fecha = Carbon::parse($datos['fecha']);
        $this->validarPeriodoAbierto($r->user()->negocio_id, $fecha);
        $referencia = Carbon::parse($datos['periodo_referencia'] ?? $datos['fecha']);
        [$periodoDesde, $periodoHasta] = match ($datos['periodicidad']) {
            'mensual' => [$referencia->copy()->startOfMonth(), $referencia->copy()->endOfMonth()],
            'anual' => [$referencia->copy()->startOfYear(), $referencia->copy()->endOfYear()],
            default => [$referencia->copy()->startOfDay(), $referencia->copy()->startOfDay()],
        };
        $aplicaIva = Negocio::findOrFail($r->user()->negocio_id)->iva_habilitado
            && ($datos['con_factura'] ?? false);
        unset($datos['periodo_referencia']);

        return $datos + [
            'periodo_desde' => $periodoDesde->toDateString(),
            'periodo_hasta' => $periodoHasta->toDateString(),
            'credito_fiscal_iva' => $aplicaIva ? round($datos['monto'] * .13, 2) : 0,
        ];
    }

    private function validarPeriodoAbierto(int $negocioId, Carbon $fecha): void
    {
        if (ImpuestoGenerado::where('negocio_id', $negocioId)
            ->where('gestion', $fecha->year)
            ->where('periodo', $fecha->month)
            ->where('estado', 'cerrado')
            ->exists()) {
            abort(422, 'No se puede modificar un gasto de un periodo tributario cerrado.');
        }
    }

    private function buscarGasto(Request $r, int $id): Gasto
    {
        return Gasto::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
    }

    private function buscarCategoria(Request $r, int $id): CategoriaGasto
    {
        return CategoriaGasto::where('negocio_id', $r->user()->negocio_id)
            ->findOrFail($id);
    }
}
