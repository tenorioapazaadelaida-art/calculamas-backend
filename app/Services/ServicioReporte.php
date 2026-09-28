<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleVenta;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Models\DetalleDevolucionVenta;
use App\Models\DevolucionCompra;

class ServicioReporte
{
    public function utilidad(int $negocioId, int $gestion, ?int $mes = null): array
    {
        $ventas = Venta::query()
            ->where('negocio_id', $negocioId)
            ->whereYear('fecha', $gestion)
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total']);

        $devoluciones = DevolucionVenta::query()->where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')->whereYear('fecha', $gestion);

        $gastos = Gasto::query()
            ->where('negocio_id', $negocioId)
            ->whereYear('fecha', $gestion)
            ->where('estado', 'registrado');

        if ($mes !== null) {
            $ventas->whereMonth('fecha', $mes);
            $devoluciones->whereMonth('fecha', $mes);
            $gastos->whereMonth('fecha', $mes);
        }

        $ingresos = (float) (clone $ventas)->sum('total') - (float) (clone $devoluciones)->sum('total');
        $costoVentas = (float) DetalleVenta::whereIn('venta_id', (clone $ventas)->pluck('id'))
            ->sum('costo_total') - (float) DetalleDevolucionVenta::whereIn(
                'devolucion_venta_id', (clone $devoluciones)->pluck('id')
            )->where('reintegrar_stock', true)->sum('costo_total');
        $totalGastos = (float) $gastos->sum('monto');
        $totalDevoluciones = (float) (clone $devoluciones)->sum('total');
        $devolucionesCompras = DevolucionCompra::where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')->whereYear('fecha', $gestion);
        if ($mes !== null) {
            $devolucionesCompras->whereMonth('fecha', $mes);
        }
        $productosDanados = (float) DetalleDevolucionVenta::whereIn(
            'devolucion_venta_id', (clone $devoluciones)->pluck('id')
        )->where('reintegrar_stock', false)->sum('costo_total');
        $impuestos = (float) (clone $ventas)->sum('impuesto_transacciones');
        $utilidadAntesImpuestos = $ingresos - $costoVentas - $totalGastos;

        return [
            'gestion' => $gestion,
            'mes' => $mes,
            'ingresos' => $ingresos,
            'devoluciones_ventas' => $totalDevoluciones,
            'devoluciones_compras' => (float) $devolucionesCompras->sum('total'),
            'costo_ventas' => $costoVentas,
            'utilidad_bruta' => $ingresos - $costoVentas,
            'gastos' => $totalGastos,
            'costo_productos_no_reintegrados' => $productosDanados,
            'utilidad_antes_impuestos' => $utilidadAntesImpuestos,
            'impuestos_generados' => $impuestos,
            'utilidad_neta' => $utilidadAntesImpuestos - $impuestos,
        ];
    }

    public function dashboard(int $negocioId): array
    {
        $ahoraBolivia = now('America/La_Paz');
        $resultadoMes = $this->utilidad(
            $negocioId,
            $ahoraBolivia->year,
            $ahoraBolivia->month
        );

        return [
            'productos' => Producto::where('negocio_id', $negocioId)->where('activo', true)
                ->count(),
            'stock_total' => (float) Producto::where('negocio_id', $negocioId)
                ->where('activo', true)
                ->sum('stock_actual'),
            'compras' => Compra::where('negocio_id', $negocioId)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
                ->count(),
            'stock_bajo' => Producto::where('negocio_id', $negocioId)
                ->where('activo', true)
                ->whereColumn('stock_actual', '<=', 'stock_minimo')
                ->count(),
            'ventas_total' => (float) Venta::where('negocio_id', $negocioId)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
                ->sum('total') - (float) DevolucionVenta::where('negocio_id', $negocioId)
                ->where('estado', 'confirmada')->sum('total'),
            'ventas' => Venta::where('negocio_id', $negocioId)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
                ->count(),
            'ventas_mes' => Venta::where('negocio_id', $negocioId)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
                ->whereYear('fecha', $ahoraBolivia->year)
                ->whereMonth('fecha', $ahoraBolivia->month)
                ->sum('total') - (float) DevolucionVenta::where('negocio_id', $negocioId)
                ->where('estado', 'confirmada')->whereYear('fecha', $ahoraBolivia->year)
                ->whereMonth('fecha', $ahoraBolivia->month)->sum('total'),
            'utilidad_mes' => $resultadoMes['utilidad_neta'],
        ];
    }
}
