# Decisiones técnicas

Registro de decisiones de arquitectura tomadas durante el desarrollo, con su
justificación. Se actualiza a medida que avanza el proyecto.

---

### 2026-07-01 — Base de datos: PostgreSQL

**Decisión:** usar PostgreSQL como motor de base de datos.

**Alternativas consideradas:** MySQL.

**Razón:** buen soporte nativo en Laravel, tipos de datos más robustos (JSON,
arrays, enums nativos) útiles para configuración flexible por tienda (ej.
`store_settings`, datos de métodos de pago), y buena compatibilidad con Dokploy.

---

### 2026-07-01 — Panel admin embebido en Next.js

**Decisión:** el panel de administración vive dentro de la misma app Next.js,
bajo rutas protegidas `/admin/*`, en vez de ser una aplicación separada.

**Alternativas consideradas:** app admin independiente (otro proyecto Next.js
o incluso otro framework).

**Razón:** reduce la complejidad de despliegue (un solo contenedor de frontend
en vez de dos), reduce duplicación de componentes de UI (theming, design
system compartido), y es suficiente para el volumen de uso esperado por
cliente (tiendas pequeñas/medianas, no requieren escalar el admin por
separado). Se puede revisar esta decisión si en el futuro el admin crece
mucho en complejidad o necesita despliegue/escala independiente.

---

### 2026-07-01 — Gestor de paquetes del frontend: Bun

**Decisión:** usar Bun en vez de npm/pnpm/yarn para instalar dependencias y
correr scripts del frontend (Next.js).

**Alternativas consideradas:** pnpm (recomendación inicial por eficiencia en
monorepos).

**Razón:** preferencia del desarrollador, velocidad de instalación/ejecución
notablemente mayor. Next.js es compatible con Bun tanto en desarrollo como en
build de producción.

**Implicaciones técnicas a tener en cuenta:**
- El `Dockerfile` del frontend debe usar la imagen base `oven/bun` en vez de
  `node`, y los comandos `bun install` / `bun run build` / `bun run start`
  en vez de sus equivalentes de npm/yarn.
- Si en el futuro se agrega alguna librería con bindings nativos con soporte
  limitado en Bun, evaluar puntualmente si migrar esa dependencia o volver a
  Node solo para ese caso — no se anticipa que esto ocurra dado el alcance
  del proyecto (Next.js + fetch a API REST, sin librerías nativas complejas).

---

### 2026-07-01 — Workflow de desarrollo local: Herd para backend, Docker solo para infra

**Decisión:** en desarrollo local, el backend Laravel corre nativo vía Herd
(parkeado como `backend.test`), no dentro de un contenedor. Docker Compose en
el día a día solo levanta `db` (Postgres) y `redis`. El `docker-compose.yml`
completo (con `backend` y `frontend` containerizados) existe desde la Fase 0
y se usa como verificación puntual de que los Dockerfiles funcionan — es la
misma base que se usará para el despliegue en Dokploy (Fase 7).

**Alternativas consideradas:** correr todo (`backend`, `frontend`, `db`,
`redis`) dentro de Docker Compose también en dev, desde el día 1.

**Razón:** hot-reload de PHP nativo vía Herd es notablemente más rápido que
dentro de un contenedor en Windows. Ya existe este mismo patrón validado en
otro proyecto del desarrollador (Herd Pro para el backend + Docker solo para
infra). El `docker-compose.yml` completo no se descarta: sigue siendo
necesario para Dokploy, solo que no es el loop de desarrollo diario.

**Implicaciones técnicas:**
- El frontend, en dev, corre nativo con `bun dev` (no requiere Docker).
- El `docker-compose.yml` pasa las variables de entorno de `backend` y
  `frontend` explícitamente (no depende del `.env` local de Herd), para que
  el stack completo sea autocontenido al verificarlo o desplegarlo.
- El Server Component del frontend usa `API_URL` (variable server-side, leída
  en runtime, sin bakear en build) para el fetch al backend, en vez de
  `NEXT_PUBLIC_API_URL` (que se bakea en build time y por lo tanto no sirve
  para diferenciar "dentro de Docker" vs "en el host" sin pasar build args).
  `NEXT_PUBLIC_API_URL` queda reservada para uso futuro desde componentes
  cliente.
