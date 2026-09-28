<?php

namespace App\Services;

use App\Models\ImpuestoGenerado;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\TipoUtilidad;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioVenta
{
    public function __construct(
        private readonly ServicioInventario $inventario,
        private readonly ServicioImpuesto $impuestos,
    ) {}

    public function registrar(array $datos, int $negocioId, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($datos, $negocioId, $usuarioId) {
            $this->validarPeriodoAbierto($negocioId, $datos['fecha'] ?? now('America/La_Paz'));
            $conFactura = (bool) ($datos['con_factura'] ?? false);
            [$lineas, $subtotal] = $this->prepararLineas($datos['detalles'], $negocioId, $conFactura);
            $descuento = (float) ($datos['descuento'] ?? 0);
            $total = $subtotal - $descuento;
            $negocio = Negocio::findOrFail($negocioId);

            $venta = Venta::create([
                'negocio_id' => $negocioId,
                'usuario_id' => $usuarioId,
                'numero' => $this->generarNumero($negocioId),
                'fecha' => $datos['fecha'] ?? now('America/La_Paz'),
                'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                'cliente_nit' => $datos['cliente_nit'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'debito_fiscal_iva' => $this->impuestos->debitoFiscal($total, $conFactura),
                'impuesto_transacciones' => $this->impuestos
                    ->impuestoTransacciones($total, $negocio->it_habilitado),
                'con_factura' => $conFactura,
                'numero_factura' => $conFactura ? ($datos['numero_factura'] ?? null) : null,
                'metodo_pago' => $datos['metodo_pago'] ?? 'efectivo',
                'observacion' => $datos['observacion'] ?? null,
            ]);

            foreach ($lineas as [$producto, $detalle, $precio]) {
                $movimiento = $this->inventario->salida(
                    $producto,
                    (float) $detalle['cantidad'],
                    $usuarioId,
                    'venta',
                    $venta->id,
                );

                $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'tipo_utilidad_id' => $detalle['tipo_utilidad_id'] ?? null,
                    'tipo_utilidad_nombre' => $detalle['tipo_utilidad_nombre'] ?? null,
                    'porcentaje_utilidad' => $detalle['porcentaje_utilidad'] ?? null,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $precio,
                    'costo_unitario' => $movimiento->salida_costo_unitario,
                    'subtotal' => round($detalle['cantidad'] * $precio, 2),
                    'costo_total' => $movimiento->salida_total,
                ]);
            }

            return $venta->load('detalles');
        });
    }

    public function actualizar(Venta $venta, array $datos, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($venta, $datos, $usuarioId) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'No se puede editar una venta anulada.']);
            }
            if ($venta->devoluciones()->where('estado', 'confirmada')->exists()) {
                throw ValidationException::withMessages(['venta' => 'No se puede editar una venta que ya tiene devoluciones.']);
            }
            $this->validarPeriodoAbierto($venta->negocio_id, $venta->fecha);
            $this->validarPeriodoAbierto($venta->negocio_id, $datos['fecha'] ?? $venta->fecha);
            $venta->load('detalles');
            foreach ($venta->detalles as $detalle) {
                $producto = Producto::where('negocio_id', $venta->negocio_id)->lockForUpdate()
                    ->findOrFail($detalle->producto_id);
                $this->inventario->revertirSalidaVenta($producto, (float) $detalle
                    ->cantidad, (float) $detalle->costo_unitario, $usuarioId, $venta->id);
            }
            $conFactura = (bool) ($datos['con_factura'] ?? false);
            [$lineas, $subtotal] = $this->prepararLineas($datos['detalles'], $venta
                ->negocio_id, $conFactura);
            $descuento = (float) ($datos['descuento'] ?? 0);
            if ($descuento > $subtotal) {
                throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
            }
            $total = $subtotal - $descuento;
            $negocio = Negocio::findOrFail($venta->negocio_id);
            $venta->update(['fecha' => $datos['fecha'] ?? $venta
                ->fecha, 'cliente_nombre' => $datos['cliente_nombre'] ?? null, 'cliente_nit' => $datos['cliente_nit'] ?? null, 'subtotal' => $subtotal, 'descuento' => $descuento, 'total' => $total, 'debito_fiscal_iva' => $this
                ->impuestos
                ->debitoFiscal($total, $conFactura), 'impuesto_transacciones' => $this
                ->impuestos->impuestoTransacciones($total, $negocio
                ->it_habilitado), 'con_factura' => $conFactura, 'numero_factura' => $conFactura ? ($datos['numero_factura'] ?? null) : null, 'metodo_pago' => $datos['metodo_pago'] ?? 'efectivo', 'observacion' => $datos['observacion'] ?? null]);
            $venta->detalles()->delete();
            foreach ($lineas as [$producto, $detalle, $precio]) {
                $movimiento = $this->inventario
                    ->salida($producto, (float) $detalle['cantidad'], $usuarioId, 'venta', $venta
                        ->id);
                $venta->detalles()->create(['producto_id' => $producto
                    ->id, 'tipo_utilidad_id' => $detalle['tipo_utilidad_id'] ?? null, 'tipo_utilidad_nombre' => $detalle['tipo_utilidad_nombre'] ?? null, 'porcentaje_utilidad' => $detalle['porcentaje_utilidad'] ?? null, 'cantidad' => $detalle['cantidad'], 'precio_unitario' => $precio, 'costo_unitario' => $movimiento
                    ->salida_costo_unitario, 'subtotal' => round($detalle['cantidad'] * $precio, 2), 'costo_total' => $movimiento
                    ->salida_total]);
            }

            return $venta->fresh()->load('detalles.producto:id,codigo,nombre');
        });
    }

    public function anular(Venta $venta, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($venta, $usuarioId) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'La venta ya está anulada.']);
            }
            if ($venta->devoluciones()->where('estado', 'confirmada')->exists()) {
                throw ValidationException::withMessages(['venta' => 'No se puede anular una venta que ya tiene devoluciones.']);
            }
            $this->validarPeriodoAbierto($venta->negocio_id, $venta->fecha);
            $venta->load('detalles');
            foreach ($venta->detalles as $detalle) {
                $producto = Producto::where('negocio_id', $venta->negocio_id)->lockForUpdate()
                    ->findOrFail($detalle->producto_id);
                $this->inventario->revertirSalidaVenta($producto, (float) $detalle
                    ->cantidad, (float) $detalle->costo_unitario, $usuarioId, $venta->id);
            }
            $venta->update(['estado' => 'anulada']);

            return $venta->fresh()->load('detalles.producto:id,codigo,nombre');
        });
    }

    private function prepararLineas(array $detalles, int $negocioId, bool $conFactura): array
    {
        $lineas = [];
        $subtotal = 0.0;

        foreach ($detalles as $detalle) {
            $producto = Producto::where('negocio_id', $negocioId)
                ->lockForUpdate()
                ->findOrFail($detalle['producto_id']);
            if (! $producto->activo || (float) $producto->stock_actual <= 0) {
                throw ValidationException::withMessages([
                    'detalles' => "El producto {$producto->nombre} no está disponible para la venta.",
                ]);
            }
            if (! empty($detalle['tipo_utilidad_id'])) {
                $tipo = TipoUtilidad::where('negocio_id', $negocioId)
                    ->where('activo', true)
                    ->with(['productos' => fn ($consulta) => $consulta->whereKey($producto->id)])
                    ->find($detalle['tipo_utilidad_id']);
                if (! $tipo) {
                    throw ValidationException::withMessages(['detalles' => "El tipo de utilidad seleccionado para {$producto->nombre} no está activo."]);
                }
                $especifica = $tipo->productos->first();
                $detalle['tipo_utilidad_nombre'] = $tipo->nombre;
                $detalle['porcentaje_utilidad'] = (float) (($especifica && $especifica->pivot
                    ->activo) ? $especifica->pivot->porcentaje : $tipo->porcentaje_general);
                $precioBase = round((float) $producto->costo_promedio * (1 + $detalle['porcentaje_utilidad'] / 100), 2);
                $detalle['precio_unitario'] = $conFactura ? round($precioBase / 0.87, 2) : $precioBase;
            }
            $precio = (float) ($detalle['precio_unitario'] ?? $producto->precio_venta);
            $subtotal += round($detalle['cantidad'] * $precio, 2);
            $lineas[] = [$producto, $detalle, $precio];
        }

        return [$lineas, $subtotal];
    }

    private function generarNumero(int $negocioId): string
    {
        return 'V-'.$negocioId.'-'.now('America/La_Paz')->format('YmdHisv');
    }

    private function validarPeriodoAbierto(int $negocioId, mixed $fecha): void
    {
        $fechaVenta = Carbon::parse($fecha);
        if (ImpuestoGenerado::where('negocio_id', $negocioId)->where('gestion', $fechaVenta
            ->year)->where('periodo', $fechaVenta->month)->where('estado', 'cerrado')
            ->exists()) {
            throw ValidationException::withMessages(['fecha' => 'No se puede registrar una venta en un periodo tributario cerrado.']);
        }
    }
}
