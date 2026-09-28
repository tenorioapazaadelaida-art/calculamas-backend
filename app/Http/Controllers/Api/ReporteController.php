<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudPeriodoReporte;
use App\Services\ServicioReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function utilidad(SolicitudPeriodoReporte $request, ServicioReporte $servicio): JsonResponse
    {
        $periodo = $request->validated();

        return response()->json($servicio->utilidad(
            $request->user()->negocio_id,
            $periodo['gestion'] ?? now('America/La_Paz')->year,
            $periodo['mes'] ?? null,
        ));
    }

    public function dashboard(Request $request, ServicioReporte $servicio): JsonResponse
    {
        return response()->json($servicio->dashboard($request->user()->negocio_id));
    }
}
