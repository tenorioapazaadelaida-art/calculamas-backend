<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\ImpuestoGenerado;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioCompra
{
    public function __construct(
        private readonly ServicioInventario $inventario,
        private readonly ServicioImpuesto $impuestos,
    ) {}

    public function registrar(array $datos, int $negocioId, int $usuarioId): Compra
    {
        $datos['detalles'] = array_map($this->normalizarDetalle(...), $datos['detalles']);
        return DB::transaction(function () use ($datos, $negocioId, $usuarioId) {
            $this->validarPeriodoAbierto($negocioId, $datos['fecha']);
            $subtotal = collect($datos['detalles'])
                ->sum(fn (array $detalle) => $detalle['subtotal']);
            $descuento = (float) ($datos['descuento'] ?? 0);
            if ($descuento > $subtotal) {
                throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
            }
            $total = $subtotal - $descuento;
            $conFactura = (bool) ($datos['con_factura'] ?? false);
            $importeNoSujetoIva = $conFactura ? (float) ($datos['importe_no_sujeto_iva'] ?? 0) : 0;
            if ($importeNoSujetoIva > $total) {
                throw ValidationException::withMessages(['importe_no_sujeto_iva' => 'El importe sin derecho a crédito IVA no puede superar el total.']);
            }
            $baseCreditoFiscal = $conFactura ? max(0, $total - $importeNoSujetoIva) : 0;
            $creditoFiscal = $this->impuestos->creditoFiscal($baseCreditoFiscal, $conFactura);
            $costoTotalInventario = $total;
            $factorCostoInventario = $subtotal > 0 ? $costoTotalInventario / $subtotal : 0;

            $compra = Compra::create([
                'negocio_id' => $negocioId,
                'proveedor_nombre' => $datos['proveedor_nombre'] ?? null,
                'proveedor_nit' => $datos['proveedor_nit'] ?? null,
                'usuario_id' => $usuarioId,
                'numero' => $this->generarNumero($negocioId),
                'numero_registro' => ((int) Compra::where('negocio_id', $negocioId)->lockForUpdate()
                    ->max('numero_registro')) + 1,
                'numero_factura' => $datos['numero_factura'] ?? null,
                'cuf_autorizacion' => $datos['cuf_autorizacion'] ?? null,
                'fecha' => $datos['fecha'],
                'subtotal' => $subtotal,
                'importe_no_sujeto_iva' => $importeNoSujetoIva,
                'descuento' => $descuento,
                'base_credito_fiscal' => $baseCreditoFiscal,
                'total' => $total,
                'credito_fiscal_iva' => $creditoFiscal,
                'con_factura' => $conFactura,
                'observacion' => $datos['observacion'] ?? null,
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::where('negocio_id', $negocioId)
                    ->lockForUpdate()
                    ->findOrFail($detalle['producto_id']);

                $costoInventarioUnitario = round((float) $detalle['costo_unitario'] * $factorCostoInventario, 4);
                $subtotalInventario = round((float) $detalle['cantidad'] * $costoInventarioUnitario, 2);

                $compra->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $detalle['cantidad'],
                    'costo_unitario' => $detalle['costo_unitario'],
                    'presentacion' => $detalle['presentacion'],
                    'cantidad_presentacion' => $detalle['cantidad_presentacion'],
                    'unidades_por_paquete' => $detalle['unidades_por_paquete'],
                    'costo_presentacion' => $detalle['costo_presentacion'],
                    'costo_inventario_unitario' => $costoInventarioUnitario,
                    'subtotal' => $detalle['subtotal'],
                    'subtotal_inventario' => $subtotalInventario,
                ]);

                $this->inventario->entrada(
                    $producto,
                    (float) $detalle['cantidad'],
                    $costoInventarioUnitario,
                    $usuarioId,
                    'compra',
                    $compra->id,
                );
            }

            return $compra->load('detalles');
        });
    }

    private function generarNumero(int $negocioId): string
    {
        $siguiente = ((int) Compra::where('negocio_id', $negocioId)->lockForUpdate()
            ->max('id')) + 1;

        return 'C-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    public function anular(Compra $compra, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($compra, $usuarioId) {
            $compra->lockForUpdate();
            if ($compra->estado === 'anulada') {
                throw ValidationException::withMessages(['compra' => 'La compra ya está anulada.']);
            }
            if ($compra->devoluciones()->where('estado', 'confirmada')->exists()) {
                throw ValidationException::withMessages(['compra' => 'No se puede anular una compra que ya tiene devoluciones al proveedor.']);
            }
            $this->validarPeriodoAbierto($compra->negocio_id, $compra->fecha->toDateString());
            $compra->load('detalles');
            $this->validarSinVentasPosteriores($compra);
            foreach ($compra->detalles as $detalle) {
                $producto = Producto::where('negocio_id', $compra->negocio_id)->lockForUpdate()
                    ->findOrFail($detalle->producto_id);
                $this->inventario->revertirEntradaCompra($producto, (float) $detalle
                    ->cantidad, (float) ($detalle->costo_inventario_unitario ?? $detalle
                    ->costo_unitario), $usuarioId, $compra->id);
            }
            $compra->update(['estado' => 'anulada']);

            return $compra->fresh('detalles');
        });
    }

    public function actualizar(Compra $compra, array $datos, int $usuarioId): Compra
    {
        $datos['detalles'] = array_map($this->normalizarDetalle(...), $datos['detalles']);
        return DB::transaction(function () use ($compra, $datos, $usuarioId) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            if ($compra->estado === 'anulada') {
                throw ValidationException::withMessages(['compra' => 'No se puede editar una compra anulada.']);
            }
            if ($compra->devoluciones()->where('estado', 'confirmada')->exists()) {
                throw ValidationException::withMessages(['compra' => 'No se puede editar una compra que ya tiene devoluciones al proveedor.']);
            }
            $this->validarPeriodoAbierto($compra->negocio_id, $compra->fecha->toDateString());
            $this->validarPeriodoAbierto($compra->negocio_id, $datos['fecha']);
            $compra->load('detalles');
            $this->validarSinVentasPosteriores($compra);

            foreach ($compra->detalles as $detalle) {
                $producto = Producto::where('negocio_id', $compra->negocio_id)->lockForUpdate()
                    ->findOrFail($detalle->producto_id);
                $this->inventario->revertirEntradaCompra($producto, (float) $detalle
                    ->cantidad, (float) ($detalle->costo_inventario_unitario ?? $detalle
                    ->costo_unitario), $usuarioId, $compra->id);
            }

            $subtotal = collect($datos['detalles'])->sum(fn (array $detalle) => $detalle['subtotal']);
            $descuento = (float) ($datos['descuento'] ?? 0);
            if ($descuento > $subtotal) {
                throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
            }
            $total = $subtotal - $descuento;
            $conFactura = (bool) ($datos['con_factura'] ?? false);
            $importeNoSujetoIva = $conFactura ? (float) ($datos['importe_no_sujeto_iva'] ?? 0) : 0;
            if ($importeNoSujetoIva > $total) {
                throw ValidationException::withMessages(['importe_no_sujeto_iva' => 'El importe sin derecho a crédito IVA no puede superar el total.']);
            }
            $baseCreditoFiscal = $conFactura ? max(0, $total - $importeNoSujetoIva) : 0;
            $creditoFiscal = $this->impuestos->creditoFiscal($baseCreditoFiscal, $conFactura);
            $factorCostoInventario = $subtotal > 0 ? $total / $subtotal : 0;

            $compra->update(['proveedor_nombre' => $datos['proveedor_nombre'] ?? null, 'proveedor_nit' => $datos['proveedor_nit'] ?? null, 'numero_factura' => $datos['numero_factura'] ?? null, 'cuf_autorizacion' => $datos['cuf_autorizacion'] ?? null, 'fecha' => $datos['fecha'], 'subtotal' => $subtotal, 'importe_no_sujeto_iva' => $importeNoSujetoIva, 'descuento' => $descuento, 'base_credito_fiscal' => $baseCreditoFiscal, 'total' => $total, 'credito_fiscal_iva' => $creditoFiscal, 'con_factura' => $conFactura, 'observacion' => $datos['observacion'] ?? null]);
            $compra->detalles()->delete();

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::where('negocio_id', $compra->negocio_id)->lockForUpdate()
                    ->findOrFail($detalle['producto_id']);
                $costoInventarioUnitario = round((float) $detalle['costo_unitario'] * $factorCostoInventario, 4);
                $subtotalInventario = round((float) $detalle['cantidad'] * $costoInventarioUnitario, 2);
                $compra->detalles()->create(['producto_id' => $producto
                    ->id, 'cantidad' => $detalle['cantidad'], 'costo_unitario' => $detalle['costo_unitario'], 'presentacion' => $detalle['presentacion'], 'cantidad_presentacion' => $detalle['cantidad_presentacion'], 'unidades_por_paquete' => $detalle['unidades_por_paquete'], 'costo_presentacion' => $detalle['costo_presentacion'], 'costo_inventario_unitario' => $costoInventarioUnitario, 'subtotal' => $detalle['subtotal'], 'subtotal_inventario' => $subtotalInventario]);
                $this->inventario
                    ->entrada($producto, (float) $detalle['cantidad'], $costoInventarioUnitario, $usuarioId, 'compra', $compra
                        ->id);
            }

            return $compra->fresh()->load('detalles.producto:id,codigo,nombre,color');
        });
    }

    private function normalizarDetalle(array $detalle): array
    {
        $presentacion = $detalle['presentacion'] ?? 'unidad';
        $factor = $presentacion === 'paquete' ? (int) $detalle['unidades_por_paquete'] : 1;
        $cantidadPresentacion = (float) $detalle['cantidad'];
        $costoPresentacion = (float) $detalle['costo_unitario'];

        return [
            ...$detalle,
            'presentacion' => $presentacion,
            'cantidad_presentacion' => $cantidadPresentacion,
            'unidades_por_paquete' => $factor,
            'costo_presentacion' => $costoPresentacion,
            'cantidad' => $cantidadPresentacion * $factor,
            'costo_unitario' => $costoPresentacion / $factor,
            'subtotal' => round($cantidadPresentacion * $costoPresentacion, 2),
        ];
    }

    public function ventaPosterior(Compra $compra): ?array
    {
        $compra->loadMissing('detalles.producto');

        foreach ($compra->detalles as $detalle) {
            $entradaId = MovimientoInventario::where('negocio_id', $compra->negocio_id)
                ->where('producto_id', $detalle->producto_id)
                ->where('tipo', 'entrada_compra')
                ->where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->min('id');
            if (! $entradaId) {
                continue;
            }

            $venta = Venta::where('negocio_id', $compra->negocio_id)
                ->where('estado', '!=', 'anulada')
                ->whereHas('detalles', fn ($consulta) => $consulta->where('producto_id', $detalle->producto_id))
                ->whereIn('id', function ($consulta) use ($compra, $detalle, $entradaId) {
                    $consulta->select('referencia_id')->from('movimientos_inventario')
                        ->where('negocio_id', $compra->negocio_id)
                        ->where('producto_id', $detalle->producto_id)
                        ->where('tipo', 'salida_venta')
                        ->where('referencia_tipo', 'venta')
                        ->where('id', '>', $entradaId);
                })
                ->orderBy('id')
                ->first();

            if ($venta) {
                return [
                    'producto' => $detalle->producto?->nombre ?? 'Producto',
                    'venta' => $venta->numero ?: $venta->id,
                ];
            }
        }

        return null;
    }

    private function validarSinVentasPosteriores(Compra $compra): void
    {
        $ids = $compra->detalles->pluck('producto_id')->unique()->sort()->values();
        foreach ($ids as $productoId) {
            Producto::where('negocio_id', $compra->negocio_id)
                ->lockForUpdate()->findOrFail($productoId);
        }

        if ($venta = $this->ventaPosterior($compra)) {
            throw ValidationException::withMessages([
                'compra' => "No se puede editar ni anular esta compra: {$venta['producto']} tiene una venta posterior vigente ({$venta['venta']}).",
            ]);
        }
    }

    private function validarPeriodoAbierto(int $negocioId, string $fecha): void
    {
        $fechaCompra = Carbon::parse($fecha);
        if (ImpuestoGenerado::where('negocio_id', $negocioId)->where('gestion', $fechaCompra
            ->year)->where('periodo', $fechaCompra->month)->where('estado', 'cerrado')
            ->exists()) {
            throw ValidationException::withMessages(['fecha' => 'No se puede registrar una compra en un periodo tributario cerrado.']);
        }
    }
}
