# Production Checklist - Railway

Use this checklist right after the first successful deploy.

## 1) Security and core env

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set (not empty)
- [ ] `HEALTH_CHECK_TOKEN` is long and random
- [ ] `RUN_MIGRATIONS=false` after first successful migration run
- [ ] `SANCTUM_STATEFUL_DOMAINS` matches your public domain
- [ ] `CORS_ALLOWED_ORIGINS` matches your public domain with `https://`
- [ ] `APP_URL` and `FRONTEND_URL` match the same final domain

## 2) Database safety

- [ ] PostgreSQL service is in the same Railway project
- [ ] App service variables point to Railway Postgres variables
- [ ] Migrations completed successfully in deploy logs
- [ ] You can log in and create data from UI

Optional but recommended:

- [ ] Add a scheduled external backup (pg_dump) at least daily
- [ ] Keep at least 7 rolling backups

Example backup command (run from trusted machine):

```bash
PGPASSWORD="$DB_PASSWORD" pg_dump \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -Fc -f "scan2order_$(date +%F_%H%M).dump"
```

Restore test (staging DB):

```bash
PGPASSWORD="$DB_PASSWORD" pg_restore \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --clean --if-exists "scan2order_YYYY-MM-DD_HHMM.dump"
```

## 3) Mail and MFA

- [ ] SMTP variables are configured (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`)
- [ ] `MAIL_FROM_ADDRESS` is a real sender allowed by your provider
- [ ] Register flow sends MFA email code successfully
- [ ] Forgot password flow sends reset code successfully
- [ ] SPF/DKIM/DMARC are configured on your domain (if using custom domain mail)

## 4) Storage and files

- [ ] `public/storage` symlink exists in container startup logs (or was created without errors)
- [ ] Product image upload works
- [ ] Uploaded image is visible from public menu

Note: local filesystem is ephemeral in many PaaS environments. If persistence is required for uploads, plan migration to object storage.

## 5) Health checks and monitoring

- [ ] Railway healthcheck path responds 200: `/api/hello`
- [ ] Protected health endpoint responds 200 only with token
- [ ] No repeated 5xx errors in Railway logs during normal usage
- [ ] No repeated DB connection errors in logs

Quick checks:

```bash
curl -i "https://YOUR_DOMAIN/api/hello"
curl -i "https://YOUR_DOMAIN/api/health?token=YOUR_HEALTH_CHECK_TOKEN"
curl -i "https://YOUR_DOMAIN/api/health"
```

Expected:

- first call: `200`
- second call: `200`
- third call: `403` in production

## 6) Functional smoke test (10-15 minutes)

- [ ] Open home page and legal pages
- [ ] Register user + MFA verification
- [ ] Login and open admin dashboard
- [ ] Create restaurant
- [ ] Create catalog, section, and product
- [ ] Upload product image
- [ ] Open public menu route and verify product appears
- [ ] Export PDF works
- [ ] Logout and login again

## 7) Hardening follow-up (recommended)

- [ ] Add a dedicated scheduler worker service for `php artisan schedule:work` if you need guaranteed cron execution
- [ ] Set log retention and alerting rules (5xx spikes, container restarts)
- [ ] Add staging environment with same env model as production
- [ ] Add at least one end-to-end test run against deployed URL

## 8) Rollback plan

- [ ] Keep previous successful deployment available in Railway
- [ ] Keep latest successful DB backup before schema changes
- [ ] Document rollback steps: previous deploy + DB restore if migration was destructive
