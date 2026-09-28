<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 40px 52px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #26383b; font-size: 10.5px; }
        .header { width: 100%; border-bottom: 2px solid #63999f; padding-bottom: 14px; }
        .brand { color: #2f737a; font-size: 22px; font-weight: bold; letter-spacing: .2px; }
        .business { margin-top: 3px; font-size: 11px; font-weight: bold; }
        .document { text-align: right; }
        .document-title { color: #2f737a; font-size: 17px; font-weight: bold; text-transform: uppercase; }
        .document-number { margin-top: 5px; color: #607377; font-size: 10px; }
        .section-title { margin: 20px 0 8px; color: #2f737a; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .8px; }
        .information { width: 100%; border: 1px solid #d8e3e4; border-collapse: collapse; background: #f7fafb; }
        .information td { width: 50%; padding: 8px 10px; border-bottom: 1px solid #e5ecee; vertical-align: top; }
        .information tr:last-child td { border-bottom: 0; }
        .label { display: inline-block; min-width: 64px; color: #53676b; font-weight: bold; }
        .detail { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .detail th { padding: 9px 8px; background: #3d7f86; color: white; font-size: 9.5px; text-align: left; text-transform: uppercase; }
        .detail td { padding: 9px 8px; border-bottom: 1px solid #dce5e6; vertical-align: top; }
        .detail tbody tr:nth-child(even) { background: #f8fafb; }
        .number { text-align: right; white-space: nowrap; }
        .summary-wrap { width: 100%; margin-top: 16px; }
        .summary-space { width: 44%; }
        .summary-cell { width: 56%; }
        .summary { width: 100%; border-collapse: collapse; }
        .summary td { padding: 6px 8px; border-bottom: 1px solid #e2e9ea; }
        .summary .tax td { color: #8a621d; }
        .summary .grand-total td { padding-top: 9px; border-top: 2px solid #63999f; border-bottom: 0; color: #235d63; font-size: 14px; font-weight: bold; }
        .notice { margin-top: 24px; padding: 10px 12px; border-left: 3px solid #8db9bd; background: #f3f8f8; color: #5d7074; font-size: 9px; line-height: 1.5; }
        .footer { position: fixed; bottom: -34px; left: 0; right: 0; border-top: 1px solid #d8e3e4; padding-top: 7px; color: #718286; font-size: 8px; }
        .footer-right { float: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td class="summary-cell">
            <div class="brand">Calcula+</div>
            <div class="business">{{ $negocio->nombre }}</div>
        </td>
        <td class="document">
            <div class="document-title">Comprobante de venta</div>
            <div class="document-number">Registro N.&ordm; {{ $venta->id }}</div>
        </td>
    </tr>
</table>

<div class="section-title">Datos de la venta</div>
<table class="information">
    <tr>
        <td><span class="label">Fecha:</span> {{ $venta->fecha->format('d/m/Y H:i') }}</td>
        <td><span class="label">Estado:</span> {{ ucfirst($venta->estado) }}</td>
    </tr>
    <tr>
        <td><span class="label">Cliente:</span> {{ $venta->cliente_nombre ?: 'Consumidor final' }}</td>
        <td><span class="label">Pago:</span> {{ $venta->metodo_pago === 'qr' ? 'QR' : 'Efectivo' }}</td>
    </tr>
    <tr>
        <td><span class="label">Factura:</span> {{ $venta->con_factura ? ($venta->numero_factura ?: 'S&iacute;') : 'Sin factura' }}</td>
        <td></td>
    </tr>
</table>

<div class="section-title">Detalle de productos</div>
<table class="detail">
    <thead>
        <tr>
            <th style="width: 27%">Producto</th>
            <th style="width: 18%">C&oacute;digo</th>
            <th class="number" style="width: 14%">Cantidad</th>
            <th class="number" style="width: 21%">Precio de venta</th>
            <th class="number" style="width: 20%">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($venta->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto?->nombre ?? 'Producto' }}</td>
                <td>{{ $detalle->producto?->codigo ?? '-' }}</td>
                <td class="number">{{ number_format($detalle->cantidad, 2, ',', '.') }}</td>
                <td class="number">Bs {{ number_format($detalle->precio_unitario, 2, ',', '.') }}</td>
                <td class="number">Bs {{ number_format($detalle->subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="summary-wrap">
    <tr>
        <td class="summary-space"></td>
        <td>
            <table class="summary">
                <tr><td>Subtotal</td><td class="number">Bs {{ number_format($venta->subtotal, 2, ',', '.') }}</td></tr>
                <tr><td>Descuento</td><td class="number">Bs {{ number_format($venta->descuento, 2, ',', '.') }}</td></tr>
                @if($venta->con_factura)
                    <tr class="tax"><td>D&eacute;bito fiscal IVA</td><td class="number">Bs {{ number_format($venta->debito_fiscal_iva, 2, ',', '.') }}</td></tr>
                @endif
                <tr class="grand-total"><td>Total cobrado</td><td class="number">Bs {{ number_format($venta->total, 2, ',', '.') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="notice"><strong>Importante:</strong> Este documento es un comprobante interno de la operaci&oacute;n registrada en Calcula+. No sustituye una factura fiscal emitida conforme a la normativa vigente.</div>
<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }}
    <span class="footer-right">P&aacute;gina <span class="page-number"></span></span>
</div>
</body>
</html>
