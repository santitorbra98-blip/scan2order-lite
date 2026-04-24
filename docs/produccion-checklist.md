# Checklist Final de Produccion (Render)

## Dominio objetivo exacto

Este checklist usa como dominio real el esperado por defecto en Render para el servicio:

- `https://scan2order-lite.onrender.com`

Si eliges otro nombre de servicio, reemplaza el dominio en todos los campos `URL`, `CORS` y `SANCTUM`.

## Variables exactas recomendadas

Configura estas variables en el servicio web de Render:

- `APP_NAME=Scan2Order`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://scan2order-lite.onrender.com`
- `FRONTEND_URL=https://scan2order-lite.onrender.com`
- `LOG_CHANNEL=stack`
- `LOG_LEVEL=error`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<autogenerada por Render DB>`
- `DB_PORT=<autogenerada por Render DB>`
- `DB_DATABASE=<autogenerada por Render DB>`
- `DB_USERNAME=<autogenerada por Render DB>`
- `DB_PASSWORD=<autogenerada por Render DB>`
- `CACHE_STORE=file`
- `FILESYSTEM_DISK=local`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=file`
- `SESSION_LIFETIME=120`
- `SANCTUM_EXPIRATION=240`
- `SANCTUM_STATEFUL_DOMAINS=scan2order-lite.onrender.com`
- `CORS_ALLOWED_ORIGINS=https://scan2order-lite.onrender.com`
- `HEALTH_CHECK_TOKEN=<token aleatorio largo>`
- `RUN_MIGRATIONS=true` (solo primer deploy)

### SMTP

- `MAIL_MAILER=smtp`
- `MAIL_HOST=<tu smtp>`
- `MAIL_PORT=587`
- `MAIL_USERNAME=<usuario smtp>`
- `MAIL_PASSWORD=<password smtp>`
- `MAIL_ENCRYPTION=tls`
- `MAIL_FROM_ADDRESS=no-reply@scan2order-lite.onrender.com`
- `MAIL_FROM_NAME=Scan2Order`

### Legal obligatorio

- `LEGAL_COMPANY_NAME=<razon social>`
- `LEGAL_OWNER_TYPE=<autonomo|sociedad_limitada|...>`
- `LEGAL_TAX_ID=<nif/cif>`
- `LEGAL_ADDRESS=<direccion legal>`
- `LEGAL_POSTAL_CODE=<cp>`
- `LEGAL_CITY=<ciudad>`
- `LEGAL_PROVINCE=<provincia>`
- `LEGAL_COUNTRY=España`
- `LEGAL_CONTACT_EMAIL=legal@scan2order-lite.onrender.com`
- `LEGAL_SUPPORT_EMAIL=soporte@scan2order-lite.onrender.com`
- `LEGAL_PRIVACY_EMAIL=privacidad@scan2order-lite.onrender.com`
- `LEGAL_SUPPORT_PHONE=<telefono>`
- `LEGAL_REGISTRY_DATA=<datos registro mercantil>`
- `LEGAL_JURISDICTION_CITY=Madrid`

## Verificacion final

1. `GET /api/hello` -> 200.
2. `GET /api/health?token=<HEALTH_CHECK_TOKEN>` -> 200.
3. Login de usuario smoke -> 200 con token.
4. Crear restaurante/catalago de prueba -> OK.
5. Exportacion PDF -> OK.
6. Revisar logs de errores en Render -> sin errores criticos.

## Endurecimiento despues del primer deploy

1. Cambiar `RUN_MIGRATIONS=false`.
2. Rotar tokens y credenciales de prueba.
3. Configurar alertas basicas sobre caidas y 5xx.
