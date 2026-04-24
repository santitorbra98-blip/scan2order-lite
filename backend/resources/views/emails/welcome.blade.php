<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 1.8rem;">🎉 ¡Bienvenido a Scan2Order!</h1>
    </div>
    <div style="padding: 2rem; color: #334155; line-height: 1.6;">
        <p style="font-size: 1.1rem;">Hola <strong>{{ $userName }}</strong>,</p>
        <p>Tu cuenta ha sido creada correctamente. Ya puedes acceder a tu panel de administración para empezar a configurar tus restaurantes y menús digitales.</p>
        <div style="text-align: center; margin: 2rem 0;">
            <a href="{{ config('app.url') }}/login"
               style="display: inline-block; padding: 0.85rem 2rem; background: linear-gradient(135deg, #667eea, #764ba2); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 1rem;">
                Acceder al panel
            </a>
        </div>
        <p style="color: #64748b; font-size: 0.9rem;">Si no creaste esta cuenta, puedes ignorar este correo.</p>
    </div>
    <div style="background: #f8fafc; padding: 1rem 2rem; text-align: center; border-radius: 0 0 8px 8px; color: #94a3b8; font-size: 0.8rem;">
        &copy; {{ date('Y') }} Scan2Order · Todos los derechos reservados
    </div>
</div>
