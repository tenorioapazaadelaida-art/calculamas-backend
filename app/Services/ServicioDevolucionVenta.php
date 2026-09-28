<?php

namespace App\Services;

use App\Models\DevolucionVenta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\ImpuestoGenerado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioDevolucionVenta
{
    public function __construct(private readonly ServicioInventario $inventario) {}

    public function registrar(Venta $venta, array $datos, int $usuarioId): DevolucionVenta
    {
        return DB::transaction(function () use ($venta, $datos, $usuarioId) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'No se puede devolver una venta anulada.']);
            }
            $fecha = Carbon::parse($datos['fecha'] ?? now('America/La_Paz'), 'America/La_Paz');
            if ($fecha->lt($venta->fecha->copy()->startOfDay())) {
                throw ValidationException::withMessages(['fecha' => 'La devolución no puede tener una fecha anterior a la venta.']);
            }
            if ($fecha->gt(now('America/La_Paz')->endOfDay())) {
                throw ValidationException::withMessages(['fecha' => 'La devolución no puede registrarse con una fecha futura.']);
            }
            if (ImpuestoGenerado::where('negocio_id', $venta->negocio_id)
                ->where('gestion', $fecha->year)->where('periodo', $fecha->month)
                ->where('estado', 'cerrado')->exists()) {
                throw ValidationException::withMessages(['fecha' => 'No se puede registrar la devolución en un periodo tributario cerrado.']);
            }
            if ($venta->con_factura && empty($datos['numero_nota_credito_debito'])) {
                throw ValidationException::withMessages([
                    'numero_nota_credito_debito' => 'Registra el número de la Nota de Crédito–Débito emitida para esta devolución.',
                ]);
            }

            $lineas = [];
            $total = 0.0;
            foreach ($datos['detalles'] as $solicitado) {
                $detalle = DetalleVenta::where('venta_id', $venta->id)
                    ->whereKey($solicitado['detalle_venta_id'])->lockForUpdate()->first();
                if (! $detalle) {
                    throw ValidationException::withMessages(['detalles' => 'Uno de los productos no pertenece a esta venta.']);
                }
                $devuelto = (float) $detalle->detallesDevolucion()
                    ->whereHas('devolucion', fn ($q) => $q->where('estado', 'confirmada'))->sum('cantidad');
                $disponible = (float) $detalle->cantidad - $devuelto;
                $cantidad = (float) $solicitado['cantidad'];
                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages(['detalles' => "Solo se pueden devolver {$disponible} unidad(es) de este producto."]);
                }
                $factorDescuento = (float) $venta->subtotal > 0
                    ? (float) $venta->total / (float) $venta->subtotal : 1;
                $subtotal = round($cantidad * (float) $detalle->precio_unitario * $factorDescuento, 2);
                $total += $subtotal;
                $lineas[] = [$detalle, $cantidad, $subtotal, (bool) $solicitado['reintegrar_stock']];
            }

            $devolucion = DevolucionVenta::create([
                'negocio_id' => $venta->negocio_id,
                'venta_id' => $venta->id,
                'usuario_id' => $usuarioId,
                'numero' => 'DEV-'.$venta->negocio_id.'-'.now('America/La_Paz')->format('YmdHisv'),
                'fecha' => $fecha,
                'motivo' => $datos['motivo'],
                'metodo_reembolso' => $datos['metodo_reembolso'],
                'con_nota_credito_debito' => (bool) $venta->con_factura,
                'numero_nota_credito_debito' => $venta->con_factura
                    ? $datos['numero_nota_credito_debito'] : null,
                'total' => round($total, 2),
                'ajuste_debito_fiscal_iva' => $venta->con_factura ? round($total * .13, 2) : 0,
            ]);

            foreach ($lineas as [$detalle, $cantidad, $subtotal, $reintegrar]) {
                $devolucion->detalles()->create([
                    'detalle_venta_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'costo_unitario' => $detalle->costo_unitario,
                    'subtotal' => $subtotal,
                    'costo_total' => round($cantidad * (float) $detalle->costo_unitario, 2),
                    'reintegrar_stock' => $reintegrar,
                ]);
                if ($reintegrar) {
                    $producto = Producto::where('negocio_id', $venta->negocio_id)
                        ->lockForUpdate()->findOrFail($detalle->producto_id);
                    $this->inventario->entradaDevolucionVenta(
                        $producto, $cantidad, (float) $detalle->costo_unitario,
                        $usuarioId, $devolucion->id, $venta->id,
                    );
                }
            }

            $venta->load('detalles.detallesDevolucion.devolucion');
            $totalVendido = (float) $venta->detalles->sum('cantidad');
            $totalDevuelto = (float) $venta->detalles->sum(fn ($detalle) => $detalle->detallesDevolucion
                ->filter(fn ($linea) => $linea->devolucion?->estado === 'confirmada')->sum('cantidad'));
            $venta->update(['estado' => $totalDevuelto >= $totalVendido ? 'devuelta_total' : 'devuelta_parcial']);

            return $devolucion->load('detalles.producto:id,codigo,nombre');
        });
    }
}
