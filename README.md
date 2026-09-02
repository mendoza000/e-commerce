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

Parkear el sitio con Herd para que quede en `http://api.tienda.test`:

```bash
herd link api.tienda
```

Correr las migraciones (contra el Postgres de Docker, ya expuesto en
`127.0.0.1:5432`):

```bash
php artisan migrate --seed
```

Crear el symlink de almacenamiento público, que es por donde el servidor web
sirve las imágenes de producto (los comprobantes de pago viven aparte, en un
disco privado que nunca se expone):

```bash
php artisan storage:link
```

Probar: `http://api.tienda.test/api/health` debe responder
`{"status":"ok","db":"connected"}`.

### 3. Frontend (Next.js vía Bun)

```bash
cd apps/frontend
bun install
cp .env.example .env.local
bun dev
```

El panel admin se autentica con una sesión de Sanctum, y esa cookie no cruza
dominios distintos. Por eso el frontend **no** se abre en `localhost:3000`
sino bajo el mismo dominio padre que la API, proxeado por Herd:

```bash
herd proxy tienda http://localhost:3000
```

Abrir `http://tienda.test` — debe mostrar `API: ok · DB: connected`.

> Si cambias estos dominios, actualizá también `APP_URL`, `FRONTEND_URL`,
> `SESSION_DOMAIN` y `SANCTUM_STATEFUL_DOMAINS` en `apps/backend/.env`, y
> `API_URL` / `NEXT_PUBLIC_API_URL` en `apps/frontend/.env.local`. Frontend y
> backend tienen que compartir dominio padre (ver `docs/decisions.md`).

### 4. Panel de administración

El seeder crea dos cuentas de prueba, ambas con contraseña `password`:

| Correo | Rol | Acceso |
|---|---|---|
| `test@example.com` | `owner` | Total |
| `staff@example.com` | `staff` | Solo pedidos |

Abrir `http://tienda.test/admin`.

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
