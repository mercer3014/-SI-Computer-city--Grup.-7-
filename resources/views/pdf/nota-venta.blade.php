<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de venta {{ $venta->numero_venta }}</title>
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
        .anulada { color: #b42318; font-weight: bold; }
    </style>
</head>
<body>
    <div class="marca">Computer City — Comercial Chiriguano, Santa Cruz</div>
    <h1>Nota de venta {{ $venta->numero_venta }}</h1>
    <p class="muted">
        {{ $venta->fecha?->timezone($zona)->format('d/m/Y H:i') }}
        · Vendedor: {{ $venta->vendedor?->name ?: $venta->vendedor?->email }}
        @if ($venta->estaAnulada())
            <span class="anulada"> · ANULADA</span>
        @endif
    </p>

    <div class="box">
        <strong>Cliente</strong><br>
        {{ $venta->cliente?->nombre }}
        @if ($venta->cliente?->razon_social)
            ({{ $venta->cliente->razon_social }})
        @endif
        <br>
        {{ $venta->cliente?->tipo_documento }} {{ $venta->cliente?->numero_documento }}
        @if ($venta->cliente?->telefono)
            · {{ $venta->cliente->telefono }}
        @endif
        @if ($venta->cliente?->email)
            · {{ $venta->cliente->email }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Serie</th>
                <th>Garantía</th>
                <th class="num">Cant.</th>
                <th class="num">P. unit.</th>
                <th class="num">Desc.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $linea)
                <tr>
                    <td>
                        {{ $linea->nombre_producto_snapshot }}
                        @if ($linea->variante_snapshot)
                            <br><span class="muted">{{ $linea->variante_snapshot }}</span>
                        @endif
                    </td>
                    <td>{{ $linea->numero_serie ?: '—' }}</td>
                    <td>{{ $linea->meses_garantia }} meses<br><span class="muted">hasta {{ $linea->fecha_fin_garantia?->format('d/m/Y') }}</span></td>
                    <td class="num">{{ $linea->cantidad }}</td>
                    <td class="num">Bs {{ number_format($linea->precio_unitario, 2, ',', '.') }}</td>
                    <td class="num">Bs {{ number_format((float) $linea->descuento, 2, ',', '.') }}</td>
                    <td class="num">Bs {{ number_format($linea->subtotal(), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="num">
        Descuento global: Bs {{ number_format((float) $venta->descuento_global, 2, ',', '.') }}<br>
        <span class="total">Total: Bs {{ number_format($total, 2, ',', '.') }}</span>
    </p>

    <div class="box">
        <strong>Pagos</strong>
        <ul>
            @foreach ($venta->pagos as $pago)
                <li>
                    {{ $pago->metodo?->nombre }}: Bs {{ number_format($pago->monto, 2, ',', '.') }}
                    @if ($pago->referencia)
                        (ref. {{ $pago->referencia }})
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @if ($venta->observacion)
        <p class="muted">Obs.: {{ $venta->observacion }}</p>
    @endif

    @if ($venta->estaAnulada())
        <p class="anulada">Anulada el {{ $venta->fecha_anulacion?->timezone($zona)->format('d/m/Y H:i') }}. Motivo: {{ $venta->motivo_anulacion }}</p>
    @endif
</body>
</html>
