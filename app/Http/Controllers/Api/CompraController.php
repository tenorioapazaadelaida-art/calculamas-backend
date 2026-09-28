<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudCompra;
use App\Http\Requests\SolicitudDevolucionCompra;
use App\Models\Compra;
use App\Models\DevolucionCompra;
use App\Services\ServicioCompra;
use App\Services\ServicioDevolucionCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompraController extends Controller
{
    public function index(Request $r, ServicioCompra $servicio): JsonResponse
    {
        $compras = Compra::where('negocio_id', $r->user()->negocio_id)
            ->with(['detalles.producto:id,codigo,nombre,color,stock_actual', 'devoluciones.detalles.producto:id,codigo,nombre'])
            ->latest('fecha')->latest('id')
            ->get();
        $compras->each(function (Compra $compra) use ($servicio) {
            $compra->setAttribute('venta_posterior', $compra->estado === 'anulada'
                ? null : $servicio->ventaPosterior($compra));
        });

        return response()->json($compras);
    }

    public function store(SolicitudCompra $r, ServicioCompra $s): JsonResponse
    {
        return response()->json($s->registrar($r->validated(), $r->user()->negocio_id, $r
            ->user()->id), 201);
    }

    public function show(Request $r, int $id): JsonResponse
    {
        return response()->json($this->buscar($r, $id)
            ->load(['detalles.producto:id,codigo,nombre,color,stock_actual', 'devoluciones.detalles.producto:id,codigo,nombre']));
    }

    public function devolver(SolicitudDevolucionCompra $r, int $id, ServicioDevolucionCompra $servicio): JsonResponse
    {
        return response()->json($servicio->registrar(
            $this->buscar($r, $id), $r->validated(), $r->user()->id
        ), 201);
    }

    public function devoluciones(Request $r, int $id): JsonResponse
    {
        $compra = $this->buscar($r, $id);
        return response()->json(DevolucionCompra::where('negocio_id', $r->user()->negocio_id)
            ->where('compra_id', $compra->id)->with('detalles.producto:id,codigo,nombre')
            ->latest('fecha')->latest('id')->get());
    }

    public function comprobanteDevolucionPdf(Request $r, int $id, int $devolucion): Response
    {
        $compra = $this->buscar($r, $id);
        $registro = DevolucionCompra::where('negocio_id', $r->user()->negocio_id)
            ->where('compra_id', $compra->id)->with(['compra', 'detalles.producto:id,codigo,nombre'])
            ->findOrFail($devolucion);
        $negocio = $r->user()->negocio;
        return Pdf::loadView('pdf.comprobante-devolucion-compra', compact('registro', 'negocio'))
            ->setPaper('a4')->download("comprobante-devolucion-compra-{$registro->numero}.pdf");
    }

    public function comprobantePdf(Request $r, int $id): Response
    {
        $compra = $this->buscar($r, $id)->load('detalles.producto:id,codigo,nombre');
        $negocio = $r->user()->negocio;
        $pdf = Pdf::loadView('pdf.comprobante-compra', compact('compra', 'negocio'))
            ->setPaper('a4');

        return $pdf->download("comprobante-compra-{$compra->numero_registro}.pdf");
    }

    public function update(SolicitudCompra $r, int $id, ServicioCompra $s): JsonResponse
    {
        return response()->json($s->actualizar($this->buscar($r, $id), $r->validated(), $r
            ->user()->id));
    }

    public function destroy(Request $r, int $id, ServicioCompra $s): JsonResponse
    {
        return response()->json($s->anular($this->buscar($r, $id), $r->user()->id));
    }

    private function buscar(Request $r, int $id): Compra
    {
        return Compra::where('negocio_id', $r->user()->negocio_id)->findOrFail($id);
    }
}
