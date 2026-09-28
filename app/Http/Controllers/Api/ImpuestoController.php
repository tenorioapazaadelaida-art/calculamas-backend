<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudPeriodoImpuesto;
use App\Models\ConfiguracionImpuesto;
use App\Models\Negocio;
use App\Services\ServicioImpuesto;
use App\Services\ServicioReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpuestoController extends Controller
{
    public function configuracion(Request $request): JsonResponse
    {
        return response()->json(Negocio::whereKey($request->user()->negocio_id)
            ->firstOrFail(['regimen_tributario', 'iva_habilitado', 'it_habilitado', 'iue_habilitado']));
    }

    public function guardarConfiguracion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'regimen_tributario' => ['sometimes', 'string', 'in:general'],
            'iva_habilitado' => ['required', 'boolean'],
            'it_habilitado' => ['required', 'boolean'],
            'iue_habilitado' => ['required', 'boolean'],
        ]);
        $negocio = Negocio::findOrFail($request->user()->negocio_id);
        $regimen = $datos['regimen_tributario'] ?? 'general';
        unset($datos['regimen_tributario']);
        $negocio->update($datos + ['regimen_tributario' => $regimen, 'impuestos_habilitados' => collect($datos)
            ->contains(true)]);
        foreach ([['IVA', 'Impuesto al Valor Agregado', 0.13, 'mensual', $datos['iva_habilitado']], ['IT', 'Impuesto a las Transacciones', 0.03, 'mensual', $datos['it_habilitado']], ['IUE', 'Impuesto sobre las Utilidades de las Empresas', 0.25, 'anual', $datos['iue_habilitado']]] as [$codigo, $nombre, $alicuota, $periodicidad, $activo]) {
            ConfiguracionImpuesto::updateOrCreate(['negocio_id' => $negocio->id, 'codigo' => $codigo, 'vigente_desde' => now()
                ->startOfYear()
                ->toDateString()], ['nombre' => $nombre, 'alicuota' => $alicuota, 'periodicidad' => $periodicidad, 'incluido_en_precio' => false, 'activo' => $activo]);
        }

        return response()->json(['message' => 'Configuración tributaria guardada.', 'configuracion' => $negocio
            ->only(['regimen_tributario', 'iva_habilitado', 'it_habilitado', 'iue_habilitado'])]);
    }

    public function cerrar(Request $request, ServicioImpuesto $servicio): JsonResponse
    {
        $periodo = $request->validate(['gestion' => ['required', 'integer', 'min:2000', 'max:2100'], 'mes' => ['required', 'integer', 'between:1,12']]);

        return response()->json($servicio->cerrarPeriodo($request->user()
            ->negocio_id, $periodo['gestion'], $periodo['mes']));
    }

    public function iue(Request $request, ServicioImpuesto $servicio, ServicioReporte $reportes): JsonResponse
    {
        $datos = $request->validate(['gestion' => ['required', 'integer', 'min:2000', 'max:2100']]);

        return response()->json($servicio->iueEstimado($request->user()
            ->negocio_id, $datos['gestion'], $reportes));
    }

    public function resumen(SolicitudPeriodoImpuesto $request, ServicioImpuesto $servicio): JsonResponse
    {
        $periodo = $request->validated();

        return response()->json($servicio->resumenMensual(
            $request->user()->negocio_id,
            $periodo['gestion'],
            $periodo['mes'],
        ));
    }

    public function productos(SolicitudPeriodoImpuesto $request, ServicioImpuesto $servicio): JsonResponse
    {
        $periodo = $request->validated();

        return response()->json($servicio->porProducto(
            $request->user()->negocio_id,
            $periodo['gestion'],
            $periodo['mes'],
        ));
    }
}
