<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Kardex general</title>
    <style>
        @page { margin: 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #34454c; font-size: 9px; }
        h1 { margin: 0; color: #31535d; font-size: 20px; }
        .encabezado { margin-bottom: 14px; }
        .subtitulo { color: #68787e; margin-top: 3px; }
        .generado { float: right; text-align: right; margin-top: -30px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #d9eef0; color: #31535d; border: 1px solid #9eb9be; padding: 6px 3px; }
        td { border: 1px solid #c8d5d8; padding: 5px 3px; vertical-align: top; }
        tr:nth-child(even) td { background: #f7fafb; }
        .numero { text-align: right; white-space: nowrap; }
        .entrada { color: #28765e; font-weight: bold; }
        .salida { color: #a04f58; font-weight: bold; }
        .producto { font-weight: bold; }
        .codigo { color: #728087; font-size: 8px; }
        .vacio { padding: 25px; text-align: center; color: #728087; }
        tfoot td { background: #d9eef0; color: #244f55; font-weight: bold; border-top: 2px solid #6d9ea3; }
    </style>
</head>
<body>
    <div class="encabezado">
        <h1>Kardex general</h1>
        <div class="subtitulo">{{ $negocio?->nombre ?? 'Calcula+' }} · Entradas, salidas y saldos de inventario</div>
        <div class="generado">Generado el {{ now()->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 7%">Fecha</th>
                <th style="width: 25%">Detalle</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Saldo</th>
                <th style="width: 9%">Costo promedio</th>
                <th>Débito</th>
                <th>Crédito</th>
                <th style="width: 9%">Saldo valor</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($movimientos as $movimiento)
            <tr>
                <td>{{ optional($movimiento->fecha)->timezone('America/La_Paz')->format('d/m/Y') }}</td>
                <td><span class="producto">{{ $movimiento->producto?->nombre }}</span> <span class="codigo">{{ $movimiento->producto?->codigo }}</span><br>{{ ucfirst(str_replace('_', ' ', $movimiento->tipo)) }}@if($movimiento->observacion)<br><span class="codigo">{{ $movimiento->observacion }}</span>@endif</td>
                <td class="numero entrada">{{ (float) $movimiento->entrada_cantidad > 0 ? number_format($movimiento->entrada_cantidad, 2, ',', '.') : '—' }}</td>
                <td class="numero salida">{{ (float) $movimiento->salida_cantidad > 0 ? number_format($movimiento->salida_cantidad, 2, ',', '.') : '—' }}</td>
                <td class="numero">{{ number_format($movimiento->saldo_cantidad, 2, ',', '.') }}</td>
                <td class="numero">Bs {{ number_format($movimiento->saldo_costo_promedio, 4, ',', '.') }}</td>
                <td class="numero entrada">{{ (float) $movimiento->entrada_total > 0 ? 'Bs '.number_format($movimiento->entrada_total, 2, ',', '.') : '—' }}</td>
                <td class="numero salida">{{ (float) $movimiento->salida_total > 0 ? 'Bs '.number_format($movimiento->salida_total, 2, ',', '.') : '—' }}</td>
                <td class="numero">Bs {{ number_format($movimiento->saldo_total, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="vacio">Todavía no existen movimientos de inventario.</td></tr>
        @endforelse
        </tbody>
        @if ($movimientos->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">TOTAL GENERAL</td>
                    <td class="numero">-</td>
                    <td class="numero">-</td>
                    <td class="numero">-</td>
                    <td class="numero">-</td>
                    <td class="numero entrada">Bs {{ number_format($totales['entrada_total'], 2, ',', '.') }}</td>
                    <td class="numero salida">Bs {{ number_format($totales['salida_total'], 2, ',', '.') }}</td>
                    <td class="numero">Bs {{ number_format($totales['saldo_total'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
