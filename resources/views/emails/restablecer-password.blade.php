{{-- Correo de recuperación de contraseña. Estilos en línea: los clientes de correo no cargan CSS externo. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer su contraseña · SIBIP</title>
</head>
<body style="margin:0; padding:0; background:#eef4fb; font-family:Arial, Helvetica, sans-serif; color:#334155;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #dbe7f6;">
                    <tr>
                        <td style="background:#082c55; padding:24px 28px; color:#ffffff;">
                            <p style="margin:0; font-size:24px; font-weight:bold; letter-spacing:1px;">SIBIP</p>
                            <p style="margin:4px 0 0; font-size:13px; color:#b9d0ec;">Sistema Integrado de Bienes Patrimoniales · Colegio Adventista Túpac Amaru</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px; font-size:16px; font-weight:bold; color:#082c55;">Hola, {{ $usuario->nombre }}:</p>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6;">
                                Recibimos una solicitud para restablecer la contraseña de su cuenta en SIBIP.
                                Para elegir una contraseña nueva, presione el botón:
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background:#0b3b70; border-radius:12px;">
                                        <a href="{{ $url }}" style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">Restablecer contraseña</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6;">
                                El enlace vence en {{ $minutos }} minutos y solo se puede usar una vez.
                            </p>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6;">
                                Si usted no lo solicitó, ignore este correo: su contraseña no cambiará.
                            </p>
                            <p style="margin:24px 0 0; font-size:12px; line-height:1.6; color:#64748b; border-top:1px solid #e2e8f0; padding-top:16px;">
                                Si el botón no funciona, copie este enlace en su navegador:<br>
                                <a href="{{ $url }}" style="color:#1d559c; word-break:break-all;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; padding:16px 28px; font-size:12px; color:#64748b; text-align:center;">
                            Colegio Adventista Túpac Amaru · Juliaca<br>
                            Este es un mensaje automático, no lo responda.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
