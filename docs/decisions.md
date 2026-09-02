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

### 2026-07-01 — `GET /api/products` filtra categoría por slug, sin rollup a subcategorías

**Decisión:** el filtro `?category=` de `GET /api/products` matchea el slug
de la categoría exacta del producto (`whereHas('category', ...)`), sin subir
ni bajar en la jerarquía de `parent_id`. Si "Ropa" tiene la subcategoría
"Camisas", filtrar por `ropa` no devuelve los productos de `camisas`.

**Alternativas consideradas:** query recursiva que incluya productos de
todas las subcategorías al filtrar por una categoría padre.

**Razón:** el PRD no pide ese rollup, y `categories` solo tiene un nivel de
anidación (`parent_id`, sin columna de profundidad) — construir la
recursividad ahora sería anticipar una feature no pedida. Se documenta como
limitación de alcance explícita, no como gap accidental; si se necesita más
adelante, se resuelve con una query recursiva o materializando la jerarquía,
no cambia el contrato del endpoint.

---

### 2026-07-01 — `ExchangeRateService` como primera clase real en `app/Services`

**Decisión:** `app/Services/ExchangeRateService.php` (con `latestRate()` y
`enabledCurrenciesWithRates()`) es la primera clase creada en
`app/Services`, para resolver la tasa vigente de un par de monedas para
`GET /api/currencies`. `StoreSetting::current()` (config de tienda de fila
única) se queda como método estático del modelo, no como servicio.

**Alternativas consideradas:** exponer la lógica de "última tasa" como
scope/método en el modelo `ExchangeRate`, igual que `StoreSetting::current()`.

**Razón:** "última tasa vigente para un par" es lógica de negocio real y
reutilizable — Fase 3 (congelar la tasa al crear una orden) y Fase 4
(`CriptoYaRateProvider`, refresco programado) necesitan la misma consulta;
crearla ahora como servicio evita duplicarla en el código de checkout más
adelante. `StoreSetting::current()` en cambio es una query trivial contra
una tabla de fila única sin lógica que justifique una clase — envolverla en
un servicio sería la carpeta-vacía que esta misma fase ya evita crear en
otros lados.

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

---

### 2026-07-02 — Corrección: la reserva SÍ genera movimiento de kardex

**Decisión:** se agrega `InventoryMovementType::Reservation` y se registra un
movimiento de tipo `reservation` (cantidad negativa) en `inventory_movements`
al crear una orden, además del `release` ya contemplado al liberarla.

**Contexto:** esto contradice la razón dada en la entrada anterior
(2026-07-01, "la reserva es un estado transitorio sin evento auditable
propio"). `docs/plan.md` (Fase 3) pide explícitamente registrar ese
movimiento al crear la orden, y en la práctica sin él el kardex queda
incompleto: se ve el `release` de una reserva liberada/expirada pero no hay
rastro del `reservation` que le dio origen, rompiendo la trazabilidad
completa del ciclo de vida del stock reservado.

**Razón:** se prioriza la trazabilidad completa del kardex (poder reconstruir
por qué `reserved_quantity` subió o bajó en cualquier momento) sobre la
brevedad original de solo registrar movimientos "definitivos". La entrada de
2026-07-01 queda superada en este punto — se conserva por historial, no se
reescribe.

---

### 2026-09-01 — Sesión del admin: Sanctum SPA por cookie, no token Bearer

**Decisión:** el panel se autentica con una sesión first-party de Sanctum
(cookie + CSRF), no con un token Bearer. Se habilita `statefulApi()` en
`bootstrap/app.php` y el navegador llama a la API directamente.

**Alternativas consideradas:** token Bearer emitido por Laravel y guardado por
Next.js en una cookie `httpOnly`, reenviado como header `Authorization`.

**Razón:** es el camino oficial de Laravel para SPAs de primera parte, no
requiere que Next.js actúe como proxy de autenticación, y evita tener que
gestionar el ciclo de vida de un token a mano.

**Implicaciones técnicas:**
- **Frontend y backend deben compartir dominio padre**, porque la cookie de
  sesión no cruza dominios distintos. Los dominios de desarrollo cambian:
  backend `api.tienda.test`, frontend `tienda.test` (antes `backend.test` y
  `localhost:3000`). En producción se replica con `tienda.com` /
  `api.tienda.com`.
- `SESSION_DOMAIN=.tienda.test` y `SANCTUM_STATEFUL_DOMAINS` en el `.env` del
  backend; `config/cors.php` suma `sanctum/csrf-cookie` a sus `paths` y ya
  tenía `supports_credentials: true`.
- Toda escritura desde el panel manda el header `X-XSRF-TOKEN` leído de la
  cookie: `fetch` no lo hace solo (a diferencia de axios). Ver
  `lib/api/admin/client.ts`, que además reintenta una vez ante un 419.
- Las páginas del panel se renderizan en cliente: la cookie vive en el
  navegador, así que es él quien habla con la API. El layout servidor le pasa
  la URL pública de la API como prop, para no bakearla en build.
- En los tests, Sanctum solo adjunta sesión a requests cuyo `Origin` coincide
  con un dominio stateful — de ahí `TestCase::actingFromAdminPanel()`.

---

### 2026-09-01 — Frontera de permisos: staff solo opera pedidos

**Decisión:** `staff` puede listar, ver y transicionar órdenes (incluido
confirmar y rechazar pagos) y leer el catálogo. Todo lo demás — escritura de
catálogo, inventario, usuarios, tasas de cambio, métodos de pago y
configuración de tienda — es exclusivo de `owner`.

**Alternativas consideradas:** dejar que staff también edite catálogo y stock,
restringiendo solo la configuración "sensible" que menciona el PRD.

**Razón:** el PRD (sección 4) solo define staff por lo que *no* puede hacer
("configuración sensible"), lo cual no alcanza para escribir un middleware. Se
elige la lectura estricta: staff es un rol de operación de pedidos, no de
administración de la tienda. Es la frontera más fácil de explicar a un cliente
y la más fácil de ampliar después si hace falta.

**Implicaciones técnicas:**
- Se implementa con el middleware `role:owner` sobre grupos de rutas, más
  Policies en `app/Policies` para las reglas por registro.
- `UserResource` expone un bloque `permissions` para que el panel oculte lo que
  la cuenta no puede usar. Es una pista de UI: la autoridad sigue siendo el
  backend, que responde 403 igual.
- La tienda nunca puede quedarse sin owner activo. No hace falta una regla
  explícita para eso: `UserPolicy::deactivate` prohíbe autodesactivarse, y
  desactivar a otro exige ser owner activo — con lo cual el objetivo nunca era
  el último. La única fuga restante, autodegradarse a staff, la cierra
  `UserUpdateRequest::after()`.

---

### 2026-09-01 — Cuentas de admin desactivables, nunca borrables

**Decisión:** los usuarios admin se desactivan con una columna `is_active`; no
se eliminan ni se usa borrado lógico.

**Alternativas consideradas:** `deactivated_at` (timestamp), o `SoftDeletes`.

**Razón:** un operador que procesó órdenes tiene que seguir siendo resoluble
desde `order_status_history.changed_by` para siempre. `is_active` booleano
mantiene la columna consistente con el resto del esquema, que ya usa ese mismo
nombre en productos, variantes, métodos de pago, métodos de envío y
configuración de tasas.

**Implicaciones técnicas:**
- Desactivar también borra las sesiones abiertas de esa cuenta
  (`User::invalidateSessions()`, driver `database`), para que el cierre sea
  inmediato y no en el próximo login.
- El middleware `active` cubre lo que ese borrado no puede: drivers de sesión
  inalcanzables o requests ya en vuelo.
- `AuthController::logout()` llama a `Auth::forgetGuards()`, porque
  `auth:sanctum` resuelve por un `RequestGuard` que cachea el usuario y no se
  limpia al cerrar sesión del guard `web`. Con php-fpm el proceso muere antes
  de que importe; con un worker de larga vida sería identidad obsoleta.

---

### 2026-09-01 — Comprobantes: streaming autenticado, no URL firmada

**Decisión:** el panel lee los comprobantes por `GET /api/admin/payment-proofs/{proof}`,
que transmite el archivo desde el disco privado dentro de la sesión del admin.
No se emiten URLs firmadas temporales.

**Alternativas consideradas:** `Storage::temporaryUrl()` — el disco `local` de
este proyecto tiene `serve => true`, así que la soporta, y en S3 saldría gratis.

**Razón:** un comprobante es el recibo bancario de un cliente. Una URL firmada
es una credencial al portador: funciona para cualquiera que la tenga, sigue
funcionando después de que la sesión que la pidió se cerró, y queda escrita en
el historial del navegador y en los logs de cualquier proxy intermedio. El
streaming deja la sesión del admin como única llave, y se comporta igual sea
cual sea el disco que use el despliegue.

**Implicaciones técnicas:**
- La ruta no está anidada bajo la orden: cualquier admin puede ver cualquier
  orden, así que la orden en el path sería decoración que el endpoint tendría
  que volver a verificar.
- Se sirve `inline` con el `mime_type` guardado, para que el panel muestre la
  imagen o el PDF en su sitio en vez de obligar a descargarlo. Como el frontend
  vive en `tienda.test` y la API en `api.tienda.test` — mismo dominio padre —
  la cookie de sesión viaja también en un `<img src="...">`.
- Una fila cuyo archivo ya no está en disco responde 404, no un stream vacío.

---

### 2026-09-01 — Cancelar devuelve stock, con un movimiento propio en el kardex

**Decisión:** `Order::cancel(User $admin, string $reason)` decide según el
estado: si la orden todavía no estaba pagada libera la reserva (`Release`), y si
ya lo estaba reingresa las unidades a `stock` con un tipo de movimiento nuevo,
`InventoryMovementType::Restock`.

**Alternativas consideradas:** reusar `Release` para ambos casos, o reusar
`Adjustment`.

**Razón:** los tres movimientos son cosas distintas y el kardex existe
justamente para poder distinguirlas después. `Release` solo suelta una reserva
y nunca toca `stock`; usarlo para un reingreso volvería ambiguo cada renglón del
histórico. `Adjustment` es la corrección manual de un conteo físico, que la Fase
5c va a introducir con motivo obligatorio: mezclarla con las cancelaciones haría
que ese reporte mintiera. La columna `inventory_movements.type` es `string`, así
que el caso nuevo no costó migración.

**Implicaciones técnicas:**
- `InventoryReservationService::release()` y el nuevo `restock()` reciben un
  `?User $admin`, para que el kardex registre quién canceló. Sigue siendo `null`
  cuando el que libera es el barrido programado: ahí no lo decidió nadie, lo
  decidió el vencimiento.
- Cancelar una orden `shipped` o `delivered` lo rechaza la máquina de estados
  (422 `invalid_order_transition`): la mercancía ya salió, y reingresarla es una
  decisión de almacén, no un clic.
- `cancel()` es idempotente y toma el row lock de la orden, igual que
  `confirmPayment()`: dos paneles cancelando a la vez liberan una sola vez.

---

### 2026-09-01 — Un endpoint por acción de orden, no un "cambiar estado" genérico

**Decisión:** el panel tiene `confirm-payment`, `reject-payment` y `cancel` como
endpoints propios, y un `transition` genérico que solo acepta `preparing`,
`shipped` y `delivered`.

**Alternativas consideradas:** un único `PATCH /orders/{order}` con el estado
destino, apoyado en `OrderStatus::allowedTransitions()` para validar.

**Razón:** las transiciones no son equivalentes entre sí. Pasar a `paid`
descuenta stock, volver a `pending_payment` reabre la ventana de reserva, y
cancelar libera o reingresa unidades. Un endpoint genérico que las aceptara
sería una puerta trasera para ejecutar el cambio de estado sin su efecto
colateral. Las tres que no mueven nada más que la orden sí comparten endpoint.

**Implicaciones técnicas:**
- `OrderStatus::fulfillmentStatuses()` es la única fuente de esa lista: la usan
  la validación del request y el bloque `actions` del recurso.
- El recurso de orden expone `actions` (`can_confirm_payment`,
  `can_reject_payment`, `can_cancel` y `available_transitions`) derivado de la
  máquina de estados, para que el panel dibuje solo los botones que funcionan.
  Es una pista de UI, como `permissions` en `UserResource`: la autoridad sigue
  siendo el backend, que responde 422 igual.
- Las órdenes quedan fuera del grupo `role:owner`: `staff` es un rol de
  operación de pedidos y ejecuta las cuatro acciones.

---

### 2026-09-01 — El stock no se edita: se ajusta con motivo

**Decisión:** `PATCH /api/admin/variants/{variant}` acepta `sku`,
`price_override` e `is_active`, y **rechaza** `stock` con un 422 explícito
(`stock.prohibited`). La única forma de cambiarlo es
`POST /api/admin/variants/{variant}/adjust-stock`, que exige un
`quantity_change` con signo y un motivo, y escribe una fila `adjustment` en el
kardex con el `created_by` del admin.

**Alternativas consideradas:** aceptar `stock` en la edición de variante como
un campo más (que es como lo enunciaba el plan) y emitir el movimiento por
detrás, con un motivo genérico tipo "edición manual".

**Razón:** `inventory_movements` existe para poder responder "¿por qué hay 7 y
no 10?". Un campo de stock en un formulario no trae esa respuesta: trae un
número. Aceptarlo obligaría a inventar un motivo, y un kardex lleno de
"edición manual" no es un kardex, es un log de escrituras. Además el ajuste es
relativo con signo (`+12 llegaron`, `-3 rotas`) mientras que un campo es
absoluto, y un valor absoluto pisa en silencio una venta confirmada un segundo
antes.

**Implicaciones técnicas:**
- `InventoryReservationService::adjust()` relee la fila con `lockForUpdate` en
  vez de confiar en la instancia recibida, para que el delta se aplique al
  stock como está ahora y no como lo vio el panel.
- Se rechaza dejar `stock` por debajo de `reserved_quantity`: esas unidades ya
  están prometidas a órdenes abiertas. Bajar hasta exactamente ese número sí se
  permite.
- `reserved_quantity` también está prohibido en la edición: lo maneja el ciclo
  de vida de las órdenes, no el editor de catálogo.
- Es el primer código que emite `InventoryMovementType::Adjustment`, que existía
  en el enum desde la Fase 1 sin emisor.

---

### 2026-09-01 — Eliminar un producto es baja lógica, y restaurar lo devuelve entero

**Decisión:** `DELETE /api/admin/products/{product}` archiva (soft delete) el
producto y, en la misma transacción, sus variantes vivas.
`POST /api/admin/products/{product}/restore` devuelve el producto y **todas**
sus variantes archivadas, incluidas las que se habían retirado una por una
antes de archivar el producto.

**Alternativas consideradas:** (a) borrado real; (b) archivar el producto sin
tocar las variantes; (c) sellar las variantes con el `deleted_at` del producto
para restaurar solo las que se llevó ese archivado.

**Razón:** el borrado real está descartado por `order_items.product_variant_id`
— la tienda tiene que poder responder qué vendió en marzo mucho después de que
el producto salga del catálogo. Dejar las variantes vivas tampoco sirve: la
reserva de stock las resuelve por id, así que seguirían siendo pedibles con su
producto ya archivado. Y (c) se probó y no funciona: `deleted_at` es una
columna de precisión de segundos, así que una variante borrada en el mismo
segundo que el producto es indistinguible de una que se llevó el archivado.
Devolver todo es la regla que siempre se cumple, y es el lado seguro del error:
volver a retirar una variante es un clic, mientras que una variante que falta
en silencio es invisible.

**Implicaciones técnicas:**
- `Product::archive()` / `Product::unarchive()` llevan las dos operaciones; el
  controlador solo decide si están permitidas.
- Se rechaza archivar un producto con `reserved_quantity > 0` en cualquiera de
  sus variantes: hay clientes en camino a pagar esas unidades.
- La misma negativa protege el borrado de una variante suelta, más una segunda:
  no se puede archivar la última variante viva de un producto, porque todo
  producto debe tener al menos una (regla de la Fase 1). Para retirar el
  producto entero está el archivado del producto.
- `GET /products/{product}` y el restore resuelven con `withTrashed()`; el
  listado esconde lo archivado salvo `?trashed=with|only`.
- Las reglas `unique` de `slug` y `sku` cuentan las filas archivadas, igual que
  el índice de la base: un slug libre solo a ojos de Eloquent sigue fallando en
  el insert.

---

### 2026-09-01 — El generador es el único que escribe el conjunto de variantes

**Decisión:** las variantes no se crean de a una con su combinación escrita a
mano. `POST /api/admin/products/{product}/variants` recibe todas las
combinaciones o una selección, y `VariantGenerator` decide qué crear. Crear un
producto crea su variante implícita; generar combinaciones reales la archiva.
Agregar una opción a un producto que ya tiene variantes con combinaciones se
rechaza; agregar un valor a una opción existente siempre se permite.

**Alternativas consideradas:** un `POST /variants` clásico donde el panel manda
`option_value_ids`, y dejar que la validación se encargue.

**Razón:** una variante no es una fila suelta, es un punto de la grilla que
forman las opciones. Un endpoint que acepte cualquier conjunto de valores deja
crear un punto que no está en la grilla — una variante sin talla en un producto
con tallas — que el storefront no puede resolver a partir de lo que el cliente
selecciona. Concentrarlo en el generador es lo que permite que la regla de la
variante implícita ("todo producto tiene al menos una variante") sea una
invariante y no una recomendación.

**Implicaciones técnicas:**
- Generar es idempotente: la combinación que ya existe se cuenta como omitida.
  El `meta` de la respuesta dice `created` / `skipped` / `archived_implicit`,
  que es lo que hace seguro volver a pulsar el botón después de agregar un
  valor.
- Agregar una opción se rechaza porque las variantes existentes quedarían
  indefinidas en el eje nuevo (un "Rojo-M" que no dice nada de Material).
  Eliminar una opción o un valor en uso se rechaza por lo contrario:
  `variant_option_values` cascadea, así que la base no fallaría — dejaría dos
  variantes idénticas donde antes había "Rojo" y "Azul".
- Renombrar una opción o un valor siempre se permite: son etiquetas, y ninguna
  identidad de variante depende de ellas.
- El SKU se deriva del producto y de la combinación (`CAMISA-ROJO-M`) porque un
  SKU se dicta en voz alta en un depósito; las colisiones se resuelven con
  sufijo numérico, contando las variantes archivadas.
- Hay un tope configurable (`commerce.catalog.max_variants_per_product`, 500)
  para que un "generar todas" sobre cuatro opciones sea un 422 legible y no una
  petición que se cae a la mitad.
- Las variantes nacen con `stock = 0`: las unidades entran por un ajuste de
  inventario, que es lo que escribe el kardex.

---

### 2026-09-01 — Imágenes de catálogo: disco público, no streaming como los comprobantes

**Decisión:** las imágenes de producto viven en el disco `public`
(configurable en `commerce.product_image`) y las sirve el servidor web a través
del symlink de `storage:link`. No pasan por la API. La compresión que vivía
dentro de `PaymentProofService` se extrajo a `ImageStorageService`, que ahora
usan los dos.

**Alternativas consideradas:** servirlas con streaming autenticado, como los
comprobantes de pago (decisión del 2026-09-01 más arriba).

**Razón:** son cosas opuestas. Un comprobante es el recibo bancario de un
cliente y solo debe verlo un admin con sesión; una foto de producto está para
que la vea cualquiera que entre a la tienda, y hacerla pasar por PHP en cada
carga de la portada es gasto puro. Lo que sí comparten es cómo llega el
archivo: una foto sacada con un teléfono, que hay que reencodear, reducir y
guardar con un nombre que no eligió quien la subió.

**Implicaciones técnicas:**
- `ImageStorageService` es el único que escribe un archivo subido. Reencodea a
  JPEG y reduce con `scaleDown` (que nunca amplía), y deja intacto lo que no es
  imagen — un comprobante en PDF se destruiría si se reencodeara.
- El nombre guardado siempre es un UUID: el nombre original es texto controlado
  por quien sube, y conservarlo filtra cómo llamó el cliente al archivo.
- Las imágenes se asocian al **valor de opción**, no a la variante (PRD): las
  fotos de "Rojo" las heredan Rojo-38, Rojo-39 y Rojo-40 sin duplicarlas. Si el
  producto no tiene una opción visual, quedan colgadas del producto.
- Un producto tiene exactamente una imagen principal: la primera que se sube lo
  es, marcar otra se la quita a la anterior, y borrar la principal se la pasa a
  la siguiente. El storefront ordena por `is_primary` y luego por `position`.
- `ProductImageResource` toma el disco de la config y no de un literal, para
  que un despliegue pueda mover el catálogo a S3 sin tocar código.
