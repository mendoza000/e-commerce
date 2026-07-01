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

---

### 2026-07-01 — Precisión monetaria estándar: `decimal(18,6)`

**Decisión:** usar `decimal(18,6)` como tipo estándar para todo monto y tasa
de cambio en el esquema (precios, subtotales, tasas, montos de referencia),
en vez de un tipo distinto por moneda o enteros en centavos.

**Alternativas consideradas:** `decimal` con escala distinta según la moneda
(ej. 0 decimales para Bs, 2 para USD/USDT); `integer` guardando el monto en
centavos.

**Razón:** un solo tipo de columna cubre tanto Bs (montos grandes, sin
decimales relevantes en la práctica) como USD/USDT/COP (2 decimales) sin
duplicar columnas por moneda ni acoplar el esquema a la lista de monedas
soportadas. El redondeo de presentación específico por moneda es lógica de
aplicación (Fase 3/4), no una restricción que deba resolverse a nivel de
columna.

---

### 2026-07-01 — Reserva de inventario como columnas, no tabla de reservas

**Decisión:** modelar la reserva de stock como las columnas
`reserved_quantity` y `reserved_until` en `product_variants`, en vez de una
tabla dedicada `inventory_reservations`.

**Alternativas consideradas:** tabla separada tipo ledger de reservas, con
una fila por reserva activa.

**Razón:** el PRD (sección 5quater) describe un único mecanismo de reserva
1:1 con la orden que la genera, sin reservas concurrentes múltiples por
variante que justifiquen una tabla separada — una tabla de reservas sería
sobre-ingeniería para este alcance.

**Nota:** en Fase 3, al implementar el servicio de
reserva→confirmación→liberación, `reserved_quantity` debe tratarse como
acumulador actualizado con `lockForUpdate()` (transacción con bloqueo de
fila), no como valor absoluto reescrito sin control de concurrencia — esto
es lo que protege contra sobreventa (PRD 5quater).

---

### 2026-07-01 — Roles y estados como string + enum de aplicación

**Decisión:** los roles `owner`/`staff` se modelan como columna `role`
(string) en `users`, no como tabla `roles` con pivot. El mismo criterio se
aplica a `orders.status`: columna string más un enum de PHP a nivel de
aplicación (`OrderStatus`), no un enum nativo de Postgres.

**Alternativas consideradas:** sistema de roles/permisos dinámico tipo
Spatie Permission; tipo `enum` nativo de Postgres para `status`.

**Razón:** los roles son 2 valores fijos y excluyentes, sin necesidad de
permisos configurables dinámicamente. Para `status`, usar string + enum de
aplicación en vez de un enum nativo de Postgres evita el costo de
`ALTER TYPE ... ADD VALUE` cada vez que se agregue un estado nuevo — el PRD
ya contempla que el flujo de estados de una orden puede crecer.

---

### 2026-07-01 — `Customer` como modelo/tabla separado de `User`

**Decisión:** `Customer` es un modelo y tabla independiente de `User`, no el
mismo modelo con un rol distinto. Ambos usan `HasApiTokens` de Sanctum sobre
la misma tabla polimórfica `personal_access_tokens`.

**Alternativas consideradas:** guard de Sanctum dedicado para `customers`
cableado desde el día 1, con middleware y rutas protegidas ya en esta fase.

**Razón:** no hay rutas protegidas todavía (la Fase 1 no expone endpoints de
storefront ni admin). El provider `customers` ya se agregó en
`config/auth.php`, apuntando al modelo `Customer`, para dejarlo listo; el
guard explícito y los middlewares reales se cablean en Fase 3/5 junto con
las rutas que efectivamente lo necesiten.

---

### 2026-07-01 — `store_settings` de fila única con columnas explícitas

**Decisión:** `store_settings` es una tabla de fila única con columnas
explícitas (nombre, logo, colores, moneda base, WhatsApp), y las monedas
habilitadas adicionales se modelan con el pivot `store_enabled_currencies`,
no con un key-value genérico ni un array JSON de ids de moneda.

**Alternativas consideradas:** columna JSON con la lista de ids de moneda
habilitados.

**Razón:** con el pivot, Postgres valida la integridad referencial de cada
moneda habilitada y Eloquent la consulta con `belongsToMany` sin más costo
de modelado que un array JSON, pero sin perder la garantía de que solo se
puedan referenciar monedas que existen.

---

### 2026-07-01 — `app/Domain/Enums/` como única carpeta nueva de esta fase

**Decisión:** en esta fase se crea únicamente `app/Domain/Enums/` como
carpeta nueva de arquitectura por capas, sin adelantar `app/Services`,
`app/Domain/Payments` ni `app/Domain/Fulfillment` vacíos.

**Alternativas consideradas:** crear también las carpetas de servicios y
dominios de pago/fulfillment desde ya, aunque queden vacías.

**Razón:** crear carpetas sin contenido real es ruido, no arquitectura —
nacen en Fase 3/4/6, cuando haya lógica real (servicios de reserva de
inventario, providers de pago/envío) que poner ahí.

---

### 2026-07-01 — Kardex de inventario como tabla separada de la reserva

**Decisión:** `inventory_movements` es una tabla separada de las columnas de
reserva en `product_variants` (PRD 5quater, sección agregada durante esta
fase).

**Alternativas consideradas:** registrar también los movimientos definitivos
en la misma tabla/columnas usadas para la reserva.

**Razón:** la reserva es un estado transitorio sin evento auditable propio;
el kardex solo registra movimientos definitivos (venta confirmada,
liberación por cancelación/expiración, ajuste manual) como ledger
append-only e inmutable — el mismo criterio de auditoría aplicado a
`exchange_rates`.
