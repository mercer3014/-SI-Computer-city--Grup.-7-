<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de venta {{ $venta->numero_venta }}</title>
</head>
<body style="margin:0;background:#faf4eb;font-family:Arial,Helvetica,sans-serif;color:#1c1410;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#fffdf8;border:1px solid #e8dccb;border-radius:12px;">
                    <tr>
                        <td style="background:#d97706;color:#fffdf8;padding:20px 24px;border-radius:12px 12px 0 0;font-size:18px;font-weight:bold;">
                            Computer City
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;font-size:14px;line-height:22px;">
                            <p style="margin:0 0 12px;">Hola {{ $venta->cliente?->nombre }},</p>
                            <p style="margin:0 0 16px;">Adjuntamos la nota de venta <strong>{{ $venta->numero_venta }}</strong> por tu compra en Computer City.</p>
                            <p style="margin:0;color:#6f6256;font-size:13px;">Si el archivo no abre, pedí una copia en el local (Comercial Chiriguano, Santa Cruz).</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
