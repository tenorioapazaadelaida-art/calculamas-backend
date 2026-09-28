<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Models\DevolucionCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class InventarioController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(Producto::where('negocio_id', $r->user()->negocio_id)
            ->select('id', 'codigo', 'nombre', 'stock_actual', 'stock_minimo', 'costo_promedio', 'precio_venta', 'activo')
            ->orderBy('nombre')->get());
    }

    public function kardex(Request $r, int $producto): JsonResponse
    {
        $existe = Producto::where('negocio_id', $r->user()->negocio_id)->whereKey($producto)
            ->exists();
        abort_unless($existe, 404);

        return response()->json($this->construirKardexVigente(
            $r->user()->negocio_id,
            $producto
        ));
    }

    public function kardexGeneral(Request $r): JsonResponse
    {
        return response()->json($this->construirKardexVigente(
            $r->user()->negocio_id
        ));
    }

    public function kardexPdf(Request $r): Response
    {
        $movimientos = $this->construirKardexVigente(
            $r->user()->negocio_id
        );

        $ultimosPorProducto = $movimientos->groupBy('producto_id')
            ->map->last();
        $totales = [
            'entrada_cantidad' => $movimientos->sum('entrada_cantidad'),
            'salida_cantidad' => $movimientos->sum('salida_cantidad'),
            'saldo_cantidad' => $ultimosPorProducto->sum('saldo_cantidad'),
            'entrada_total' => $movimientos->sum('entrada_total'),
            'salida_total' => $movimientos->sum('salida_total'),
            'saldo_total' => $ultimosPorProducto->sum('saldo_total'),
        ];

        $negocio = $r->user()->negocio;
        $pdf = Pdf::loadView('pdf.kardex-general', compact('movimientos', 'negocio', 'totales'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('kardex-general-'.now('America/La_Paz')->format('Y-m-d').'.pdf');
    }

    private function construirKardexVigente(
        int $negocioId,
        ?int $productoId = null
    ): Collection {
        $compras = Compra::where('negocio_id', $negocioId)
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
            ->with(['detalles' => function ($consulta) use ($productoId) {
                if ($productoId !== null) {
                    $consulta->where('producto_id', $productoId);
                }
                $consulta->with('producto:id,codigo,nombre');
            }])
            ->get();

        $ventas = Venta::where('negocio_id', $negocioId)
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
            ->with(['detalles' => function ($consulta) use ($productoId) {
                if ($productoId !== null) {
                    $consulta->where('producto_id', $productoId);
                }
                $consulta->with('producto:id,codigo,nombre');
            }])
            ->get();

        $devoluciones = DevolucionVenta::where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')
            ->with(['detalles' => function ($consulta) use ($productoId) {
                if ($productoId !== null) {
                    $consulta->where('producto_id', $productoId);
                }
                $consulta->where('reintegrar_stock', true)->with('producto:id,codigo,nombre');
            }])->get();

        $devolucionesCompra = DevolucionCompra::where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')
            ->with(['detalles' => function ($consulta) use ($productoId) {
                if ($productoId !== null) {
                    $consulta->where('producto_id', $productoId);
                }
                $consulta->with('producto:id,codigo,nombre');
            }])->get();

        $eventos = collect();

        foreach ($compras as $compra) {
            foreach ($compra->detalles as $detalle) {
                $eventos->push([
                    'orden' => $compra->fecha->format('Y-m-d').' '.$compra->created_at->format('H:i:s.u'),
                    'tipo' => 'entrada_compra',
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'usuario_id' => $compra->usuario_id,
                    'fecha' => $compra->fecha,
                    'fecha_operacion' => $compra->fecha->format('Y-m-d'),
                    'hora_registro' => $compra->created_at
                        ->copy()->timezone('America/La_Paz')->format('H:i:s'),
                    'producto' => $detalle->producto,
                    'cantidad' => (float) $detalle->cantidad,
                    'costo' => (float) ($detalle->costo_inventario_unitario
                        ?? $detalle->costo_unitario),
                ]);
            }
        }

        foreach ($ventas as $venta) {
            foreach ($venta->detalles as $detalle) {
                $eventos->push([
                    'orden' => $venta->fecha->format('Y-m-d').' '.$venta->created_at->format('H:i:s.u'),
                    'tipo' => 'salida_venta',
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'usuario_id' => $venta->usuario_id,
                    'fecha' => $venta->fecha,
                    'fecha_operacion' => $venta->fecha->format('Y-m-d'),
                    'hora_registro' => $venta->created_at
                        ->copy()->timezone('America/La_Paz')->format('H:i:s'),
                    'producto' => $detalle->producto,
                    'cantidad' => (float) $detalle->cantidad,
                    'costo' => (float) $detalle->costo_unitario,
                ]);
            }
        }

        foreach ($devoluciones as $devolucion) {
            foreach ($devolucion->detalles as $detalle) {
                $eventos->push([
                    'orden' => $devolucion->fecha->format('Y-m-d').' '.$devolucion->created_at->format('H:i:s.u'),
                    'tipo' => 'entrada_devolucion_venta',
                    'referencia_tipo' => 'devolucion_venta',
                    'referencia_id' => $devolucion->id,
                    'usuario_id' => $devolucion->usuario_id,
                    'fecha' => $devolucion->fecha,
                    'fecha_operacion' => $devolucion->fecha->format('Y-m-d'),
                    'hora_registro' => $devolucion->created_at->copy()->timezone('America/La_Paz')->format('H:i:s'),
                    'producto' => $detalle->producto,
                    'cantidad' => (float) $detalle->cantidad,
                    'costo' => (float) $detalle->costo_unitario,
                ]);
            }
        }

        foreach ($devolucionesCompra as $devolucion) {
            foreach ($devolucion->detalles as $detalle) {
                $eventos->push([
                    'orden' => $devolucion->fecha->format('Y-m-d').' '.$devolucion->created_at->format('H:i:s.u'),
                    'tipo' => 'salida_devolucion_compra',
                    'referencia_tipo' => 'devolucion_compra',
                    'referencia_id' => $devolucion->id,
                    'usuario_id' => $devolucion->usuario_id,
                    'fecha' => $devolucion->fecha,
                    'fecha_operacion' => $devolucion->fecha->format('Y-m-d'),
                    'hora_registro' => $devolucion->created_at->copy()->timezone('America/La_Paz')->format('H:i:s'),
                    'producto' => $detalle->producto,
                    'cantidad' => (float) $detalle->cantidad,
                    'costo' => (float) $detalle->costo_inventario_unitario,
                ]);
            }
        }

        $saldos = [];

        return $eventos->sortBy('orden')->values()->map(function (
            array $evento,
            int $indice
        ) use (&$saldos, $negocioId) {
            $productoId = $evento['producto']->id;
            $saldo = $saldos[$productoId] ?? ['cantidad' => 0.0, 'promedio' => 0.0];
            $movimiento = new MovimientoInventario;
            $movimiento->id = $indice + 1;
            $movimiento->negocio_id = $negocioId;
            $movimiento->producto_id = $productoId;
            $movimiento->usuario_id = $evento['usuario_id'];
            $movimiento->fecha = $evento['fecha'];
            $movimiento->fecha_operacion = $evento['fecha_operacion'];
            $movimiento->hora_registro = $evento['hora_registro'];
            $movimiento->tipo = $evento['tipo'];
            $movimiento->referencia_tipo = $evento['referencia_tipo'];
            $movimiento->referencia_id = $evento['referencia_id'];

            if (in_array($evento['tipo'], ['entrada_compra', 'entrada_devolucion_venta'], true)) {
                $nuevoStock = $saldo['cantidad'] + $evento['cantidad'];
                $nuevoValor = ($saldo['cantidad'] * $saldo['promedio'])
                    + ($evento['cantidad'] * $evento['costo']);
                $nuevoPromedio = $nuevoStock > 0
                    ? $nuevoValor / $nuevoStock
                    : 0;
                $movimiento->entrada_cantidad = $evento['cantidad'];
                $movimiento->entrada_costo_unitario = $evento['costo'];
                $movimiento->entrada_total = round(
                    $evento['cantidad'] * $evento['costo'],
                    2
                );
            } else {
                $nuevoStock = $saldo['cantidad'] - $evento['cantidad'];
                $nuevoPromedio = $saldo['promedio'];
                $movimiento->salida_cantidad = $evento['cantidad'];
                $movimiento->salida_costo_unitario = $saldo['promedio'];
                $movimiento->salida_total = round(
                    $evento['cantidad'] * $saldo['promedio'],
                    2
                );
            }

            $movimiento->saldo_cantidad = $nuevoStock;
            $movimiento->saldo_costo_promedio = $nuevoPromedio;
            $movimiento->saldo_total = round($nuevoStock * $nuevoPromedio, 2);
            $movimiento->setRelation('producto', $evento['producto']);
            $saldos[$productoId] = [
                'cantidad' => $nuevoStock,
                'promedio' => $nuevoPromedio,
            ];

            return $movimiento;
        });
    }
}
