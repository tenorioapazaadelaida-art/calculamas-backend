<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Gasto;
use App\Models\ImpuestoGenerado;
use App\Models\Negocio;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Models\DevolucionCompra;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ServicioImpuesto
{
    public const ALICUOTA_IVA = 0.13;

    public const ALICUOTA_IT = 0.03;

    public const ALICUOTA_IUE = 0.25;

    public function creditoFiscal(float $monto, bool $conFactura): float
    {
        return $conFactura ? round($monto * self::ALICUOTA_IVA, 2) : 0.0;
    }

    public function debitoFiscal(float $monto, bool $conFactura): float
    {
        return $this->creditoFiscal($monto, $conFactura);
    }

    public function impuestoTransacciones(float $monto, bool $aplicaImpuestos): float
    {
        return $aplicaImpuestos ? round($monto * self::ALICUOTA_IT, 2) : 0.0;
    }

    public function resumenMensual(int $negocioId, int $gestion, int $mes): array
    {
        $negocio = Negocio::findOrFail($negocioId);
        $filtrar = fn ($consulta) => $consulta->where('negocio_id', $negocioId)
            ->whereYear('fecha', $gestion)->whereMonth('fecha', $mes);
        $debitoVentas = (float) $filtrar(Venta::query()->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total']))
            ->sum('debito_fiscal_iva');
        $ajusteDevoluciones = (float) $filtrar(DevolucionVenta::query()->where('estado', 'confirmada'))
            ->sum('ajuste_debito_fiscal_iva');
        $debito = max(0, $debitoVentas - $ajusteDevoluciones);
        $creditoComprasBruto = (float) $filtrar(Compra::query()
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total']))
            ->sum('credito_fiscal_iva');
        $ajusteDevolucionesCompra = (float) $filtrar(DevolucionCompra::query()
            ->where('estado', 'confirmada'))->sum('ajuste_credito_fiscal_iva');
        $creditoCompras = $creditoComprasBruto - $ajusteDevolucionesCompra;
        $creditoGastos = (float) $filtrar(Gasto::query())->sum('credito_fiscal_iva');
        $credito = $creditoCompras + $creditoGastos;
        $saldoAnterior = (float) ImpuestoGenerado::where('negocio_id', $negocioId)->where('codigo', 'IVA')
            ->where('estado', 'cerrado')->where(fn ($q) => $q->where('gestion', '<', $gestion)
            ->orWhere(fn ($q2) => $q2->where('gestion', $gestion)->where('periodo', '<', $mes)))
            ->latest('gestion')->latest('periodo')->value('saldo_favor');
        $ivaDeterminado = max(0, $debito - $credito - $saldoAnterior);
        $saldoFavor = max(0, $credito + $saldoAnterior - $debito);
        $ventasIt = (float) $filtrar(Venta::query()
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total']))->sum('impuesto_transacciones');
        $devolucionesIt = (float) $filtrar(DevolucionVenta::query()->where('estado', 'confirmada'))->sum('total') * self::ALICUOTA_IT;
        $it = $negocio->it_habilitado ? max(0, $ventasIt - $devolucionesIt) : 0;
        $periodos = ImpuestoGenerado::where('negocio_id', $negocioId)->where('gestion', $gestion)
            ->where('periodo', $mes)->get()->keyBy('codigo');

        return ['gestion' => $gestion, 'mes' => $mes, 'iva_debito_fiscal' => $debito, 'iva_credito_compras' => $creditoCompras, 'iva_credito_gastos' => $creditoGastos, 'iva_credito_fiscal' => $credito, 'iva_saldo_anterior' => $saldoAnterior, 'iva_determinado' => $ivaDeterminado, 'iva_saldo_favor' => $saldoFavor, 'it_determinado' => $it, 'periodo_cerrado' => $periodos->contains(fn ($p) => $p
            ->estado === 'cerrado'), 'configuracion' => $negocio
            ->only(['iva_habilitado', 'it_habilitado', 'iue_habilitado']), 'nota' => 'Cálculo de control interno; la declaración oficial corresponde al SIAT.'];
    }

    public function porProducto(int $negocioId, int $gestion, int $mes): array
    {
        $productos = [];
        $agregar = function ($producto) use (&$productos): array {
            return $productos[$producto->id] ?? [
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'ventas_con_factura' => 0,
                'compras_con_factura' => 0,
                'base_ventas' => 0.0,
                'debito_fiscal_iva' => 0.0,
                'base_compras' => 0.0,
                'credito_fiscal_iva' => 0.0,
                'it_generado' => 0.0,
            ];
        };

        $ventas = Venta::where('negocio_id', $negocioId)->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
            ->whereYear('fecha', $gestion)->whereMonth('fecha', $mes)
            ->with('detalles.producto:id,codigo,nombre')->get();

        foreach ($ventas as $venta) {
            foreach ($venta->detalles as $detalle) {
                if (! $detalle->producto) {
                    continue;
                }
                $fila = $agregar($detalle->producto);
                if ($venta->con_factura) {
                    $fila['ventas_con_factura']++;
                    $fila['base_ventas'] += (float) $detalle->subtotal;
                    $fila['debito_fiscal_iva'] += round((float) $detalle->subtotal * self::ALICUOTA_IVA, 2);
                }
                if ((float) $venta->total > 0 && (float) $venta->impuesto_transacciones > 0) {
                    $fila['it_generado'] += (float) $venta->impuesto_transacciones * ((float) $detalle
                        ->subtotal / (float) $venta->total);
                }
                $productos[$detalle->producto->id] = $fila;
            }
        }

        $devoluciones = DevolucionVenta::where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')->whereYear('fecha', $gestion)
            ->whereMonth('fecha', $mes)->with('detalles.producto:id,codigo,nombre')->get();
        foreach ($devoluciones as $devolucion) {
            foreach ($devolucion->detalles as $detalle) {
                if (! $detalle->producto) {
                    continue;
                }
                $fila = $agregar($detalle->producto);
                if ($devolucion->con_nota_credito_debito) {
                    $fila['base_ventas'] -= (float) $detalle->subtotal;
                    $fila['debito_fiscal_iva'] -= round((float) $detalle->subtotal * self::ALICUOTA_IVA, 2);
                }
                $fila['it_generado'] -= round((float) $detalle->subtotal * self::ALICUOTA_IT, 2);
                $productos[$detalle->producto->id] = $fila;
            }
        }

        $compras = Compra::where('negocio_id', $negocioId)
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta_total'])
            ->where('con_factura', true)
            ->whereYear('fecha', $gestion)->whereMonth('fecha', $mes)
            ->with('detalles.producto:id,codigo,nombre')->get();

        foreach ($compras as $compra) {
            foreach ($compra->detalles as $detalle) {
                if (! $detalle->producto) {
                    continue;
                }
                $fila = $agregar($detalle->producto);
                $proporcion = (float) $compra->total > 0 ? (float) $detalle
                    ->subtotal / (float) $compra->total : 0;
                $fila['compras_con_factura']++;
                $fila['base_compras'] += (float) $detalle->subtotal;
                $fila['credito_fiscal_iva'] += (float) $compra->credito_fiscal_iva * $proporcion;
                $productos[$detalle->producto->id] = $fila;
            }
        }

        $devolucionesCompra = DevolucionCompra::where('negocio_id', $negocioId)
            ->where('estado', 'confirmada')->whereYear('fecha', $gestion)
            ->whereMonth('fecha', $mes)->with('detalles.producto:id,codigo,nombre')->get();
        foreach ($devolucionesCompra as $devolucion) {
            foreach ($devolucion->detalles as $detalle) {
                if (! $detalle->producto) {
                    continue;
                }
                $fila = $agregar($detalle->producto);
                if ($devolucion->con_nota_credito_debito) {
                    $proporcion = (float) $devolucion->total > 0
                        ? (float) $detalle->subtotal / (float) $devolucion->total : 0;
                    $fila['base_compras'] -= (float) $detalle->subtotal;
                    $fila['credito_fiscal_iva'] -= (float) $devolucion->ajuste_credito_fiscal_iva * $proporcion;
                }
                $productos[$detalle->producto->id] = $fila;
            }
        }

        return collect($productos)->map(function (array $fila) {
            foreach (['base_ventas', 'debito_fiscal_iva', 'base_compras', 'credito_fiscal_iva', 'it_generado'] as $campo) {
                $fila[$campo] = round($fila[$campo], 2);
            }

            return $fila;
        })->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function cerrarPeriodo(int $negocioId, int $gestion, int $mes): array
    {
        $resumen = $this->resumenMensual($negocioId, $gestion, $mes);
        if ($resumen['periodo_cerrado']) {
            throw ValidationException::withMessages(['periodo' => 'El periodo ya está cerrado.']);
        }
        $negocio = Negocio::findOrFail($negocioId);
        $desde = Carbon::create($gestion, $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        if ($negocio->iva_habilitado || $resumen['iva_debito_fiscal'] > 0 || $resumen['iva_credito_fiscal'] > 0) {
            ImpuestoGenerado::updateOrCreate(['negocio_id' => $negocioId, 'codigo' => 'IVA', 'gestion' => $gestion, 'periodo' => $mes], ['desde' => $desde, 'hasta' => $hasta, 'base_imponible' => $resumen['iva_debito_fiscal'] / self::ALICUOTA_IVA, 'debito_fiscal' => $resumen['iva_debito_fiscal'], 'credito_fiscal' => $resumen['iva_credito_fiscal'], 'saldo_anterior' => $resumen['iva_saldo_anterior'], 'importe_determinado' => $resumen['iva_determinado'], 'saldo_favor' => $resumen['iva_saldo_favor'], 'estado' => 'cerrado', 'cerrado_en' => now()]);
        }
        if ($negocio->it_habilitado) {
            ImpuestoGenerado::updateOrCreate(['negocio_id' => $negocioId, 'codigo' => 'IT', 'gestion' => $gestion, 'periodo' => $mes], ['desde' => $desde, 'hasta' => $hasta, 'base_imponible' => $resumen['it_determinado'] / self::ALICUOTA_IT, 'debito_fiscal' => 0, 'credito_fiscal' => 0, 'saldo_anterior' => 0, 'importe_determinado' => $resumen['it_determinado'], 'saldo_favor' => 0, 'estado' => 'cerrado', 'cerrado_en' => now()]);
        }

        return $this->resumenMensual($negocioId, $gestion, $mes);
    }

    public function iueEstimado(int $negocioId, int $gestion, ServicioReporte $reportes): array
    {
        $negocio = Negocio::findOrFail($negocioId);
        $resultado = $reportes->utilidad($negocioId, $gestion);
        $base = $negocio->iue_habilitado ? max(0, (float) $resultado['utilidad_neta']) : 0;

        return ['gestion' => $gestion, 'habilitado' => $negocio->iue_habilitado, 'base_estimada' => $base, 'iue_estimado' => round($base * self::ALICUOTA_IUE, 2), 'nota' => 'Estimación sujeta a ajustes tributarios y revisión profesional antes de declarar.'];
    }
}
