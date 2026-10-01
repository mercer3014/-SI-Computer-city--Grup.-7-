<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu acceso a Computer City</title>
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
                            <p style="margin:0 0 12px;">Hola {{ $nombre }},</p>
                            <p style="margin:0 0 16px;">Un administrador creó tu cuenta en el sistema de Computer City. Usá estos datos para tu primer ingreso:</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf4eb;border:1px solid #e8dccb;border-radius:8px;margin-bottom:16px;">
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#6f6256;">Correo</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:bold;">{{ $email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#6f6256;">Clave temporal</td>
                                    <td style="padding:12px 16px;font-size:16px;font-weight:bold;font-family:'Courier New',monospace;letter-spacing:1px;">{{ $clave }}</td>
                                </tr>
                            </table>
                            <p style="margin:0 0 20px;">Al ingresar, el sistema te va a pedir que elijas una clave nueva. No compartas esta clave con nadie.</p>
                            <p style="margin:0 0 8px;">
                                <a href="{{ $urlLogin }}" style="display:inline-block;background:#d97706;color:#fffdf8;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold;">Ingresar al sistema</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
