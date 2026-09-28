<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de devolución</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #26383b; font-size: 12px; }
        h1 { color: #5f979d; margin-bottom: 4px; }
        .meta { color: #667; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #cfdadc; padding: 8px; text-align: left; }
        th { background: #eaf3f4; }
        .right { text-align: right; }
        .total { font-size: 17px; color: #477c82; margin-top: 18px; text-align: right; }
        .note { margin-top: 18px; padding: 10px; background: #f3f6f6; }
    </style>
</head>
<body>
    <h1>{{ $negocio->nombre }} · Comprobante de devolución</h1>
    <div class="meta">
        Devolución: {{ $registro->numero }}<br>
        Venta original: {{ $registro->venta->numero }}<br>
        Fecha: {{ $registro->fecha->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}<br>
        Reembolso: {{ ucfirst($registro->metodo_reembolso) }}
    </div>
    <strong>Motivo:</strong> {{ $registro->motivo }}
    <table>
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Destino</th><th class="right">Importe</th></tr></thead>
        <tbody>
        @foreach($registro->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto?->nombre }}</td>
                <td>{{ (float) $detalle->cantidad }}</td>
                <td>{{ $detalle->reintegrar_stock ? 'Reintegrado al inventario' : 'No reintegrado / dañado' }}</td>
                <td class="right">Bs {{ number_format((float) $detalle->subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="total">Total reembolsado: Bs {{ number_format((float) $registro->total, 2, ',', '.') }}</div>
    @if($registro->con_nota_credito_debito)
        <div class="note">
            <strong>Nota de Crédito–Débito:</strong> {{ $registro->numero_nota_credito_debito }}<br>
            <strong>Factura original:</strong> {{ $registro->venta->numero_factura }}<br>
            <strong>Ajuste del débito fiscal IVA:</strong> Bs {{ number_format((float) $registro->ajuste_debito_fiscal_iva, 2, ',', '.') }}
        </div>
    @endif
    <p class="note">Documento interno de respaldo. La Nota de Crédito–Débito fiscal debe ser emitida y autorizada mediante el SIAT cuando corresponda.</p>
</body>
</html>
