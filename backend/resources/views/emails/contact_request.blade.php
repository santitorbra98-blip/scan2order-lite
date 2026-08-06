<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva solicitud de acceso</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:680px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08);">
            <h1 style="margin:0 0 16px;font-size:24px;">Nueva solicitud de acceso</h1>
            <p style="margin:0 0 24px;color:#475569;">Un cliente ha solicitado crear una cuenta desde el formulario de contacto.</p>

            <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:8px 0;font-weight:700;width:180px;">Nombre</td>
                    <td style="padding:8px 0;">{{ $name }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:700;">Email</td>
                    <td style="padding:8px 0;">{{ $email }}</td>
                </tr>
                @if($phone)
                <tr>
                    <td style="padding:8px 0;font-weight:700;">Teléfono</td>
                    <td style="padding:8px 0;">{{ $phone }}</td>
                </tr>
                @endif
                @if($restaurantName)
                <tr>
                    <td style="padding:8px 0;font-weight:700;">Restaurante</td>
                    <td style="padding:8px 0;">{{ $restaurantName }}</td>
                </tr>
                @endif
                @if($ipAddress)
                <tr>
                    <td style="padding:8px 0;font-weight:700;">IP</td>
                    <td style="padding:8px 0;">{{ $ipAddress }}</td>
                </tr>
                @endif
            </table>

            <div style="margin-top:24px;padding:16px;border-left:4px solid #2563eb;background:#eff6ff;border-radius:12px;white-space:pre-wrap;line-height:1.6;">
                {{ $message }}
            </div>

            @if($userAgent)
            <p style="margin:24px 0 0;color:#64748b;font-size:12px;word-break:break-word;">User agent: {{ $userAgent }}</p>
            @endif
        </div>
    </div>
</body>
</html>