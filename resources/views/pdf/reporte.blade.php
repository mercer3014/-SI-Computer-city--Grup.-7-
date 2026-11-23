<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $r->titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1410; }
        h1 { font-size: 18px; margin: 10px 0 2px; }
        .muted { color: #6f6256; }
        .marca { background: #d97706; color: #fffdf8; padding: 8px 12px; font-size: 13px; }
        .aviso { border: 1px solid #f5c26b; background: #fff7e6; color: #8a4b08; padding: 8px 12px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #e8dccb; padding: 6px 5px; text-align: left; }
        th { background: #fdf3e1; font-size: 10px; text-transform: uppercase; color: #6f6256; }
        .num { text-align: right; }
        .total td { font-weight: bold; background: #fdf3e1; border-top: 2px solid #d97706; }
    </style>
</head>
<body>
    <div class="marca">
        @if ($logo)
            <img src="{{ $logo }}" alt="" style="height: 24px; vertical-align: middle; margin-right: 8px;">
        @endif
        {{ $r->encabezado['tienda'] }}@if ($r->encabezado['ubicacion']) — {{ $r->encabezado['ubicacion'] }}@endif
    </div>

    <h1>{{ $r->titulo }}</h1>
    <p class="muted">
        Período: {{ $r->encabezado['periodo'] }}
        · Generado por {{ $r->encabezado['usuario'] }} el {{ $r->encabezado['generado'] }}
    </p>

    @foreach ($r->advertencias as $advertencia)
        <div class="aviso">Advertencia: {{ $advertencia }}</div>
    @endforeach

    <table>
        <thead>
            <tr>
                @foreach ($r->columnas as $columna)
                    <th class="{{ in_array($columna['tipo'], ['entero', 'decimal', 'moneda', 'porcentaje'], true) ? 'num' : '' }}">{{ $columna['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($r->filas as $fila)
                <tr>
                    @foreach ($r->columnas as $columna)
                        <td class="{{ in_array($columna['tipo'], ['entero', 'decimal', 'moneda', 'porcentaje'], true) ? 'num' : '' }}">{{ \App\Services\Reportes\Formato::valor($fila[$columna['clave']] ?? null, $columna['tipo']) }}</td>
                    @endforeach
                </tr>
            @endforeach
            @if ($r->totales !== [])
                <tr class="total">
                    @foreach ($r->columnas as $columna)
                        <td class="{{ in_array($columna['tipo'], ['entero', 'decimal', 'moneda', 'porcentaje'], true) ? 'num' : '' }}">{{ \App\Services\Reportes\Formato::valor($r->totales[$columna['clave']] ?? null, $columna['tipo']) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
