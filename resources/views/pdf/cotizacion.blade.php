<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->codigo_cotizacion }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1410; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6f6256; }
        .box { border: 1px solid #e8dccb; padding: 10px 12px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e8dccb; padding: 6px 4px; text-align: left; }
        th { font-size: 10px; text-transform: uppercase; color: #6f6256; }
        .num { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .marca { background: #d97706; color: #fffdf8; padding: 8px 12px; }
        .leyenda { margin-top: 16px; padding: 8px 12px; border: 1px dashed #d97706; font-weight: bold; }
        .estado { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>
    <div class="marca">
        @if ($logo)
            <img src="{{ $logo }}" alt="" style="height: 28px; vertical-align: middle; margin-right: 8px;">
        @endif
        {{ $tienda['nombre'] }}@if ($tienda['ubicacion']) — {{ $tienda['ubicacion'] }}@endif
        @if ($tienda['telefono'])<br><span style="font-size: 10px;">Tel. {{ $tienda['telefono'] }}@if ($tienda['whatsapp']) · WhatsApp {{ $tienda['whatsapp'] }}@endif</span>@endif
    </div>

    <h1>Cotización {{ $cotizacion->codigo_cotizacion }}</h1>
    <p class="muted">
        Emitida el {{ $cotizacion->fecha_emision?->timezone($zona)->format('d/m/Y H:i') }}
        · Válida hasta el {{ $cotizacion->fecha_vencimiento?->format('d/m/Y') }}
        · Estado: <span class="estado">{{ $cotizacion->estado }}</span>
    </p>

    <div class="box">
        <strong>Cliente</strong><br>
        {{ $cotizacion->cliente?->nombre }}
        <br>
        @if ($cotizacion->cliente?->numero_documento)
            {{ $cotizacion->cliente->tipo_documento }} {{ $cotizacion->cliente->numero_documento }}
        @endif
        @if ($cotizacion->cliente?->telefono)
            · {{ $cotizacion->cliente->telefono }}
        @endif
        @if ($cotizacion->cliente?->email)
            · {{ $cotizacion->cliente->email }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Cant.</th>
                <th class="num">P. unit.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cotizacion->detalles as $linea)
                <tr>
                    <td>{{ $linea->descripcion }}</td>
                    <td class="num">{{ $linea->cantidad }}</td>
                    <td class="num">Bs {{ number_format($linea->precio_unitario, 2, ',', '.') }}</td>
                    <td class="num">Bs {{ number_format($linea->subtotal(), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="num">
        <span class="total">Total: Bs {{ number_format($total, 2, ',', '.') }}</span>
    </p>

    @if ($cotizacion->observacion)
        <p class="muted">Obs.: {{ $cotizacion->observacion }}</p>
    @endif

    <p class="leyenda">Precios válidos hasta {{ $cotizacion->fecha_vencimiento?->format('d/m/Y') }}. No reserva stock.</p>
</body>
</html>
