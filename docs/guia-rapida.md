# Guia Rapida

## Requisitos

- Docker y Docker Compose.
- Node.js 20+ (para docs y e2e).

## Arranque local

1. Copia variables raiz:

```bash
cp .env.example .env
```

2. Arranca stack local:

```bash
DB_PASSWORD=postgres docker compose up -d --build
```

3. Ejecuta migraciones y seed:

```bash
DB_PASSWORD=postgres docker compose exec -T php php artisan migrate --seed --force --no-interaction
```

4. Pruebas basicas:

```bash
curl -k https://localhost:8443/api/hello
curl -k https://localhost:8443/api/health
```

## Comandos utiles

```bash
# detener
DB_PASSWORD=postgres docker compose down

# detener y borrar volumenes de base de datos
DB_PASSWORD=postgres docker compose down -v

# logs
DB_PASSWORD=postgres docker compose logs -f php nginx postgres
```

## Nota importante

Si cambias `DB_PASSWORD` y ya existia volumen de PostgreSQL, deberas recrear volumenes con `docker compose down -v`.
