<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de devolución al proveedor</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #26383b; font-size: 12px; }
        h1 { color: #5f979d; margin-bottom: 4px; }
        .meta { color: #667; margin-bottom: 20px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #cfdadc; padding: 8px; text-align: left; }
        th { background: #eaf3f4; }
        .right { text-align: right; }
        .total { font-size: 17px; color: #477c82; margin-top: 18px; text-align: right; }
        .note { margin-top: 18px; padding: 10px; background: #f3f6f6; }
    </style>
</head>
<body>
    <h1>{{ $negocio->nombre }} · Devolución al proveedor</h1>
    <div class="meta">
        Devolución: {{ $registro->numero }}<br>
        Compra original: N.º {{ $registro->compra->numero_registro }}<br>
        Proveedor: {{ $registro->compra->proveedor_nombre ?: 'No especificado' }}<br>
        Fecha: {{ $registro->fecha->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}<br>
        Solución: {{ ucfirst(str_replace('_', ' ', $registro->solucion)) }}
        @if($registro->medio_reembolso)<br>Medio de reembolso: {{ ucfirst($registro->medio_reembolso) }}@endif
    </div>
    <strong>Motivo:</strong> {{ $registro->motivo }}
    <table>
        <thead><tr><th>Producto</th><th>Cantidad</th><th class="right">Costo reconocido</th><th class="right">Importe</th></tr></thead>
        <tbody>
        @foreach($registro->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto?->nombre }}</td>
                <td>{{ (float) $detalle->cantidad }}</td>
                <td class="right">Bs {{ number_format((float) $detalle->costo_compra_unitario, 2, ',', '.') }}</td>
                <td class="right">Bs {{ number_format((float) $detalle->subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="total">Total reconocido por el proveedor: Bs {{ number_format((float) $registro->total, 2, ',', '.') }}</div>
    @if($registro->con_nota_credito_debito)
        <div class="note">
            <strong>Documento de ajuste:</strong> {{ $registro->numero_nota_credito_debito }}<br>
            <strong>Factura original:</strong> {{ $registro->compra->numero_factura }}<br>
            <strong>Reversión del crédito fiscal IVA:</strong> Bs {{ number_format((float) $registro->ajuste_credito_fiscal_iva, 2, ',', '.') }}
        </div>
    @endif
    <p class="note">Documento interno de respaldo. El documento tributario del proveedor debe registrarse conforme al SIAT cuando corresponda.</p>
</body>
</html>
