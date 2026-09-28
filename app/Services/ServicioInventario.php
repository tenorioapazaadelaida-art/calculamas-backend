<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Validation\ValidationException;

class ServicioInventario
{
    public function salidaDevolucionCompra(Producto $producto, float $cantidad, int $usuario, int $devolucionId, int $compraId): MovimientoInventario
    {
        if ((float) $producto->stock_actual < $cantidad) {
            throw ValidationException::withMessages(['productos' => "Stock insuficiente para devolver {$producto->nombre} al proveedor."]);
        }
        $costo = (float) $producto->costo_promedio;
        $nuevoStock = (float) $producto->stock_actual - $cantidad;
        $producto->update(['stock_actual' => $nuevoStock, 'costo_promedio' => $nuevoStock > 0 ? $costo : 0]);

        return MovimientoInventario::create([
            'negocio_id' => $producto->negocio_id, 'producto_id' => $producto->id,
            'usuario_id' => $usuario, 'fecha' => now('America/La_Paz'),
            'tipo' => 'salida_devolucion_compra', 'referencia_tipo' => 'devolucion_compra',
            'referencia_id' => $devolucionId, 'salida_cantidad' => $cantidad,
            'salida_costo_unitario' => $costo, 'salida_total' => round($cantidad * $costo, 2),
            'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $nuevoStock > 0 ? $costo : 0,
            'saldo_total' => round($nuevoStock * $costo, 2),
            'observacion' => "Devolución al proveedor de la compra {$compraId}",
        ]);
    }

    public function entradaDevolucionVenta(Producto $producto, float $cantidad, float $costo, int $usuario, int $devolucionId, int $ventaId): MovimientoInventario
    {
        $stock = (float) $producto->stock_actual;
        $valorActual = $stock * (float) $producto->costo_promedio;
        $nuevoStock = $stock + $cantidad;
        $nuevoValor = $valorActual + ($cantidad * $costo);
        $nuevoPromedio = $nuevoStock > 0 ? $nuevoValor / $nuevoStock : 0;
        $producto->update(['stock_actual' => $nuevoStock, 'costo_promedio' => $nuevoPromedio]);

        return MovimientoInventario::create([
            'negocio_id' => $producto->negocio_id, 'producto_id' => $producto->id,
            'usuario_id' => $usuario, 'fecha' => now('America/La_Paz'),
            'tipo' => 'entrada_devolucion_venta', 'referencia_tipo' => 'devolucion_venta',
            'referencia_id' => $devolucionId, 'entrada_cantidad' => $cantidad,
            'entrada_costo_unitario' => $costo, 'entrada_total' => round($cantidad * $costo, 2),
            'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $nuevoPromedio,
            'saldo_total' => round($nuevoValor, 2),
            'observacion' => "Devolución de la venta {$ventaId}",
        ]);
    }

    public function entrada(Producto $producto, float $cantidad, float $costo, int $usuario, string $referencia, int $referenciaId): MovimientoInventario
    {
        $stock = (float) $producto->stock_actual;
        $promedio = (float) $producto->costo_promedio;
        $nuevoStock = $stock + $cantidad;
        $nuevoPromedio = $nuevoStock > 0 ? (($stock * $promedio) + ($cantidad * $costo)) / $nuevoStock : 0;
        $producto->update(['stock_actual' => $nuevoStock, 'costo_promedio' => $nuevoPromedio]);

        return MovimientoInventario::create(['negocio_id' => $producto->negocio_id, 'producto_id' => $producto
            ->id, 'usuario_id' => $usuario, 'fecha' => now(), 'tipo' => 'entrada_compra', 'referencia_tipo' => $referencia, 'referencia_id' => $referenciaId, 'entrada_cantidad' => $cantidad, 'entrada_costo_unitario' => $costo, 'entrada_total' => round($cantidad * $costo, 2), 'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $nuevoPromedio, 'saldo_total' => round($nuevoStock * $nuevoPromedio, 2)]);
    }

    public function salida(Producto $producto, float $cantidad, int $usuario, string $referencia, int $referenciaId): MovimientoInventario
    {
        if ((float) $producto->stock_actual < $cantidad) {
            throw ValidationException::withMessages(['productos' => "Stock insuficiente para {$producto->nombre}."]);
        }$costo = (float) $producto->costo_promedio;
        $nuevoStock = (float) $producto->stock_actual - $cantidad;
        $producto->update(['stock_actual' => $nuevoStock]);

        return MovimientoInventario::create(['negocio_id' => $producto->negocio_id, 'producto_id' => $producto
            ->id, 'usuario_id' => $usuario, 'fecha' => now(), 'tipo' => 'salida_venta', 'referencia_tipo' => $referencia, 'referencia_id' => $referenciaId, 'salida_cantidad' => $cantidad, 'salida_costo_unitario' => $costo, 'salida_total' => round($cantidad * $costo, 2), 'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $costo, 'saldo_total' => round($nuevoStock * $costo, 2)]);
    }

    public function revertirEntradaCompra(Producto $producto, float $cantidad, float $costo, int $usuario, int $compraId): MovimientoInventario
    {
        $stock = (float) $producto->stock_actual;
        if ($stock < $cantidad) {
            throw ValidationException::withMessages(['compra' => "No se puede anular: el stock actual de {$producto->nombre} es menor que la cantidad comprada."]);
        }
        $valorActual = $stock * (float) $producto->costo_promedio;
        $nuevoStock = $stock - $cantidad;
        $nuevoValor = max(0, $valorActual - ($cantidad * $costo));
        $nuevoPromedio = $nuevoStock > 0 ? $nuevoValor / $nuevoStock : 0;
        $producto->update(['stock_actual' => $nuevoStock, 'costo_promedio' => $nuevoPromedio]);

        return MovimientoInventario::create(['negocio_id' => $producto->negocio_id, 'producto_id' => $producto
            ->id, 'usuario_id' => $usuario, 'fecha' => now(), 'tipo' => 'salida_anulacion_compra', 'referencia_tipo' => 'compra', 'referencia_id' => $compraId, 'salida_cantidad' => $cantidad, 'salida_costo_unitario' => $costo, 'salida_total' => round($cantidad * $costo, 2), 'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $nuevoPromedio, 'saldo_total' => round($nuevoValor, 2), 'observacion' => 'Reversión por anulación de compra']);
    }

    public function revertirSalidaVenta(Producto $producto, float $cantidad, float $costo, int $usuario, int $ventaId): MovimientoInventario
    {
        $stock = (float) $producto->stock_actual;
        $valorActual = $stock * (float) $producto->costo_promedio;
        $nuevoStock = $stock + $cantidad;
        $nuevoValor = $valorActual + ($cantidad * $costo);
        $nuevoPromedio = $nuevoStock > 0 ? $nuevoValor / $nuevoStock : 0;
        $producto->update(['stock_actual' => $nuevoStock, 'costo_promedio' => $nuevoPromedio]);

        return MovimientoInventario::create(['negocio_id' => $producto->negocio_id, 'producto_id' => $producto
            ->id, 'usuario_id' => $usuario, 'fecha' => now(), 'tipo' => 'entrada_anulacion_venta', 'referencia_tipo' => 'venta', 'referencia_id' => $ventaId, 'entrada_cantidad' => $cantidad, 'entrada_costo_unitario' => $costo, 'entrada_total' => round($cantidad * $costo, 2), 'saldo_cantidad' => $nuevoStock, 'saldo_costo_promedio' => $nuevoPromedio, 'saldo_total' => round($nuevoValor, 2), 'observacion' => 'Reversión de salida por edición o anulación de venta']);
    }
}
