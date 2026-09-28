<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\DevolucionCompra;
use App\Models\ImpuestoGenerado;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioDevolucionCompra
{
    public function __construct(private readonly ServicioInventario $inventario) {}

    public function registrar(Compra $compra, array $datos, int $usuarioId): DevolucionCompra
    {
        return DB::transaction(function () use ($compra, $datos, $usuarioId) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            if ($compra->estado === 'anulada') {
                throw ValidationException::withMessages(['compra' => 'No se puede devolver una compra anulada.']);
            }
            $fecha = Carbon::parse($datos['fecha'], 'America/La_Paz');
            if ($fecha->lt($compra->fecha->copy()->startOfDay())) {
                throw ValidationException::withMessages(['fecha' => 'La devolución no puede tener una fecha anterior a la compra.']);
            }
            if ($fecha->gt(now('America/La_Paz')->endOfDay())) {
                throw ValidationException::withMessages(['fecha' => 'La devolución no puede registrarse con una fecha futura.']);
            }
            if (ImpuestoGenerado::where('negocio_id', $compra->negocio_id)
                ->where('gestion', $fecha->year)->where('periodo', $fecha->month)
                ->where('estado', 'cerrado')->exists()) {
                throw ValidationException::withMessages(['fecha' => 'No se puede registrar la devolución en un periodo tributario cerrado.']);
            }
            if ($compra->con_factura && empty($datos['numero_nota_credito_debito'])) {
                throw ValidationException::withMessages(['numero_nota_credito_debito' => 'Registra el número del documento emitido por el proveedor para revertir el crédito fiscal.']);
            }
            if ($datos['solucion'] === 'reembolso' && empty($datos['medio_reembolso'])) {
                throw ValidationException::withMessages(['medio_reembolso' => 'Selecciona el medio por el que el proveedor devolverá el dinero.']);
            }

            $lineas = [];
            $total = 0.0;
            $factorNeto = (float) $compra->subtotal > 0 ? (float) $compra->total / (float) $compra->subtotal : 1;
            foreach ($datos['detalles'] as $solicitado) {
                $detalle = DetalleCompra::where('compra_id', $compra->id)
                    ->whereKey($solicitado['detalle_compra_id'])->lockForUpdate()->first();
                if (! $detalle) {
                    throw ValidationException::withMessages(['detalles' => 'Uno de los productos no pertenece a esta compra.']);
                }
                $devuelto = (float) $detalle->detallesDevolucion()
                    ->whereHas('devolucion', fn ($q) => $q->where('estado', 'confirmada'))->sum('cantidad');
                $disponible = (float) $detalle->cantidad - $devuelto;
                $cantidad = (float) $solicitado['cantidad'];
                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages(['detalles' => "Solo se pueden devolver {$disponible} unidad(es) de este producto."]);
                }
                $producto = Producto::where('negocio_id', $compra->negocio_id)
                    ->lockForUpdate()->findOrFail($detalle->producto_id);
                if ((float) $producto->stock_actual < $cantidad) {
                    throw ValidationException::withMessages(['detalles' => "El stock disponible de {$producto->nombre} es insuficiente para esta devolución."]);
                }
                $costoCompraNeto = (float) $detalle->costo_unitario * $factorNeto;
                $subtotal = round($cantidad * $costoCompraNeto, 2);
                $costoInventario = (float) $producto->costo_promedio;
                $total += $subtotal;
                $lineas[] = [$detalle, $producto, $cantidad, $costoCompraNeto, $costoInventario, $subtotal];
            }

            $total = round($total, 2);
            $ajusteIva = $compra->con_factura && (float) $compra->total > 0
                ? round((float) $compra->credito_fiscal_iva * ($total / (float) $compra->total), 2)
                : 0;
            $ajusteAnterior = (float) DevolucionCompra::where('compra_id', $compra->id)
                ->where('estado', 'confirmada')->sum('ajuste_credito_fiscal_iva');
            $ajusteIva = min($ajusteIva, max(0, (float) $compra->credito_fiscal_iva - $ajusteAnterior));

            $devolucion = DevolucionCompra::create([
                'negocio_id' => $compra->negocio_id,
                'compra_id' => $compra->id,
                'usuario_id' => $usuarioId,
                'numero' => 'DC-'.$compra->negocio_id.'-'.now('America/La_Paz')->format('YmdHisv'),
                'fecha' => $fecha,
                'motivo' => $datos['motivo'],
                'solucion' => $datos['solucion'],
                'medio_reembolso' => $datos['solucion'] === 'reembolso' ? $datos['medio_reembolso'] : null,
                'con_nota_credito_debito' => (bool) $compra->con_factura,
                'numero_nota_credito_debito' => $compra->con_factura ? $datos['numero_nota_credito_debito'] : null,
                'total' => $total,
                'ajuste_credito_fiscal_iva' => $ajusteIva,
            ]);

            foreach ($lineas as [$detalle, $producto, $cantidad, $costoCompra, $costoInventario, $subtotal]) {
                $devolucion->detalles()->create([
                    'detalle_compra_id' => $detalle->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'costo_compra_unitario' => $costoCompra,
                    'costo_inventario_unitario' => $costoInventario,
                    'subtotal' => $subtotal,
                    'costo_inventario_total' => round($cantidad * $costoInventario, 2),
                ]);
                $this->inventario->salidaDevolucionCompra($producto, $cantidad, $usuarioId, $devolucion->id, $compra->id);
            }

            $compra->load('detalles.detallesDevolucion.devolucion');
            $totalComprado = (float) $compra->detalles->sum('cantidad');
            $totalDevuelto = (float) $compra->detalles->sum(fn ($detalle) => $detalle->detallesDevolucion
                ->filter(fn ($linea) => $linea->devolucion?->estado === 'confirmada')->sum('cantidad'));
            $compra->update(['estado' => $totalDevuelto >= $totalComprado ? 'devuelta_total' : 'devuelta_parcial']);

            return $devolucion->load('detalles.producto:id,codigo,nombre');
        });
    }
}
