<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudVenta;
use App\Http\Requests\SolicitudDevolucionVenta;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Services\ServicioDevolucionVenta;
use App\Services\ServicioVenta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VentaController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(Venta::where('negocio_id', $r->user()->negocio_id)
            ->with(['detalles.producto:id,codigo,nombre', 'devoluciones.detalles.producto:id,codigo,nombre'])
            ->latest('fecha')->latest('id')->get());
    }

    public function store(SolicitudVenta $r, ServicioVenta $s): JsonResponse
    {
        return response()->json($s->registrar($r->validated(), $r->user()->negocio_id, $r
            ->user()->id), 201);
    }

    public function show(Request $r, int $id): JsonResponse
    {
        return response()->json($this->buscar($r, $id)->load([
            'detalles.producto:id,codigo,nombre',
            'devoluciones.detalles.producto:id,codigo,nombre',
        ]));
    }

    public function devolver(SolicitudDevolucionVenta $r, int $id, ServicioDevolucionVenta $servicio): JsonResponse
    {
        return response()->json($servicio->registrar(
            $this->buscar($r, $id), $r->validated(), $r->user()->id
        ), 201);
    }

    public function devoluciones(Request $r, int $id): JsonResponse
    {
        $venta = $this->buscar($r, $id);
        return response()->json(DevolucionVenta::where('negocio_id', $r->user()->negocio_id)
            ->where('venta_id', $venta->id)->with('detalles.producto:id,codigo,nombre')
            ->latest('fecha')->latest('id')->get());
    }

    public function comprobanteDevolucionPdf(Request $r, int $id, int $devolucion): Response
    {
        $venta = $this->buscar($r, $id);
        $registro = DevolucionVenta::where('negocio_id', $r->user()->negocio_id)
            ->where('venta_id', $venta->id)->with(['venta', 'detalles.producto:id,codigo,nombre'])
            ->findOrFail($devolucion);
        $negocio = $r->user()->negocio;
        return Pdf::loadView('pdf.comprobante-devolucion-venta', compact('registro', 'negocio'))
            ->setPaper('a4')->download("comprobante-devolucion-{$registro->numero}.pdf");
    }

    public function comprobantePdf(Request $r, int $id): Response
    {
        $venta = $this->buscar($r, $id)->load('detalles.producto:id,codigo,nombre');
        $negocio = $r->user()->negocio;
        $pdf = Pdf::loadView('pdf.comprobante-venta', compact('venta', 'negocio'))
            ->setPaper('a4');

        return $pdf->download("comprobante-venta-{$venta->id}.pdf");
    }

    public function update(SolicitudVenta $r, int $id, ServicioVenta $s): JsonResponse
    {
        return response()->json($s->actualizar($this->buscar($r, $id), $r->validated(), $r
            ->user()->id));
    }

    public function destroy(Request $r, int $id, ServicioVenta $s): JsonResponse
    {
        return response()->json($s->anular($this->buscar($r, $id), $r->user()->id));
    }

    private function buscar(Request $r, int $id): Venta
    {
        return Venta::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
    }
}
