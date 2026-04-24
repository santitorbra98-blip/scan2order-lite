# API de Autenticacion

## Endpoint de login

- Metodo: `POST`
- Ruta: `/api/login`
- Rate limit: `throttle:auth-login`

## Request

```json
{
  "login": "admin@scan2order.com",
  "password": "tu_password_segura"
}
```

## Validaciones

- `login`: requerido, string, max 255.
- `password`: requerido, string, max 255.

## Responses esperadas

- `200 OK`:

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@scan2order.com",
    "role": "superadmin"
  },
  "token": "1|xxxx..."
}
```

- `401 Unauthorized`: credenciales invalidas.
- `403 Forbidden`: cuenta no activa.

## Endpoint de disponibilidad

- `GET /api/hello`: debe responder `200` y `{"message":"Hello from Laravel"}`.
- `GET /api/health`: en produccion requiere token (query `token` o header `X-Health-Token`).
