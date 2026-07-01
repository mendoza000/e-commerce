# Ecommerce Template

Boilerplate de ecommerce single-tenant, pensado para clonar y desplegar por
cliente. Ver `docs/PRD.md` y `docs/plan.md` para el detalle funcional y el
plan de desarrollo por fases.

## Stack

- **Backend**: Laravel (API-only) — `apps/backend`
- **Frontend**: Next.js App Router + TypeScript + Tailwind, gestionado con
  Bun — `apps/frontend` (incluye el panel admin embebido bajo `/admin/*`)
- **Base de datos**: PostgreSQL
- **Cache/colas**: Redis

## Requisitos locales

- [Laravel Herd](https://herd.laravel.com/) (Pro, para el mailbox de dev) —
  corre el backend nativo en dev.
- Docker Desktop — solo para `db` (Postgres) y `redis` en dev.
- [Bun](https://bun.sh/) — para el frontend.

## Setup desde cero

### 1. Clonar y levantar infraestructura

```bash
git clone <repo>
cd e-commerce
docker compose -f docker/docker-compose.yml up -d db redis
```

### 2. Backend (Laravel vía Herd)

```bash
cd apps/backend
composer install
cp .env.example .env
php artisan key:generate
```

Parkear el sitio con Herd para que quede en `http://backend.test`:

```bash
herd link backend
```

Correr las migraciones (contra el Postgres de Docker, ya expuesto en
`127.0.0.1:5432`):

```bash
php artisan migrate
```

Probar: `http://backend.test/api/health` debe responder
`{"status":"ok","db":"connected"}`.

### 3. Frontend (Next.js vía Bun)

```bash
cd apps/frontend
bun install
cp .env.example .env
bun dev
```

Abrir `http://localhost:3000` — debe mostrar `API: ok · DB: connected`.

## Verificación de paridad con producción (Docker completo)

Este es el mismo `docker-compose.yml` que se usa como base para Dokploy
(Fase 7). No es el flujo diario de desarrollo, pero conviene correrlo cuando
se toquen los Dockerfiles.

`docker-compose.yml` trae un `APP_KEY` placeholder para el servicio
`backend` — reemplazalo por uno real antes de levantar el stack completo:

```bash
cd apps/backend && php artisan key:generate --show
# pegar el valor en docker/docker-compose.yml (servicio backend, APP_KEY)
```

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

Verifica:
- `http://localhost:8000/api/health` (backend containerizado)
- `http://localhost:3000` (frontend containerizado, debe mostrar `API: ok · DB: connected`)

Para volver al flujo diario:

```bash
docker compose -f docker/docker-compose.yml stop backend frontend
```

## Estructura

```
/apps
  /backend    Laravel (API-only)
  /frontend   Next.js (storefront + admin embebido)
/docker
  docker-compose.yml
  backend.Dockerfile
  frontend.Dockerfile
/docs
  PRD.md
  plan.md
  decisions.md
```
