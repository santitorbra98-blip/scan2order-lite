<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 1.8rem;">🔐 Código de verificación</h1>
    </div>
    <div style="padding: 2rem; color: #334155; line-height: 1.6;">
        <p>Hola,</p>
        <p>Tu código de verificación para <strong>{{ $purpose }}</strong> es:</p>
        <div style="text-align: center; margin: 1.5rem 0;">
            <span style="display: inline-block; padding: 1rem 2rem; background: #f1f5f9; border-radius: 12px; font-size: 2rem; font-weight: 700; letter-spacing: 8px; color: #1e293b;">{{ $code }}</span>
        </div>
        <p style="text-align: center; color: #64748b;">Este código expira en <strong>{{ $minutes }} minutos</strong>.</p>
        <p style="color: #64748b; font-size: 0.9rem;">Si no solicitaste esta acción, ignora este correo.</p>
    </div>
    <div style="background: #f8fafc; padding: 1rem 2rem; text-align: center; border-radius: 0 0 8px 8px; color: #94a3b8; font-size: 0.8rem;">
        &copy; {{ date('Y') }} Scan2Order · Todos los derechos reservados
    </div>
</div>
