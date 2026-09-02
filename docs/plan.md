# Plan de Desarrollo — Ecommerce Template

> Referencia: ver `PRD.md` para el detalle funcional de cada feature.
> Este plan asume que el propósito es doble: aprender arquitectura sólida y
> terminar con un template realmente replicable. Por eso cada fase cierra con
> algo funcional y probado antes de pasar a la siguiente — nada de dejar cabos
> sueltos "para después" salvo que se indique explícitamente.

---

## Fase 0 — Setup del monorepo y entorno base

**Objetivo:** tener el esqueleto del proyecto corriendo localmente con Docker,
sin lógica de negocio todavía.

- [x] Crear estructura de carpetas del monorepo (`/apps/backend`, `/apps/frontend`, `/docker`, `/docs`)
- [x] Inicializar repo Git, `.gitignore` general (Laravel + Node + Docker)
- [x] Instalar Laravel limpio en `/apps/backend` (API-only, sin Breeze/Jetstream)
- [x] Instalar Next.js (App Router + TypeScript + Tailwind) en `/apps/frontend` usando Bun (`bun create next-app` o instalación manual + `bun install`)
- [x] Base de datos: **PostgreSQL** (decisión tomada, documentar en `docs/decisions.md`)
- [x] Admin: **embebido en el mismo Next.js** bajo rutas `/admin/*` (decisión tomada, documentar en `docs/decisions.md`)
- [x] Gestor de paquetes frontend: **Bun** (decisión tomada, documentar en `docs/decisions.md` — usar imagen base `oven/bun` en el Dockerfile del frontend en vez de `node`)
- [x] Crear `docker-compose.yml` con servicios: `backend`, `frontend`, `db`, `redis` (para colas/cache)
- [x] Crear `Dockerfile` para backend (PHP-FPM + Nginx o usar `php artisan serve` en dev)
- [x] Crear `Dockerfile` para frontend usando imagen base `oven/bun` (multi-stage build: `bun install` → `bun run build` → runtime standalone de Next.js)
- [x] Configurar `.env.example` en backend y frontend con todas las variables previstas (aunque no se usen todas aún) — el del frontend se creó en la Fase 5a
- [x] Verificar que `docker-compose up` levanta los 3-4 servicios sin errores
- [x] Verificar conectividad backend → base de datos (migración de prueba)
- [x] Verificar que frontend puede hacer un fetch de prueba al backend (endpoint `/api/health`)
- [x] Documentar en `README.md` cómo levantar el entorno local desde cero

**Entregable de fase:** `docker-compose up` levanta todo, endpoint de salud responde, sin features de negocio aún.

---

## Fase 1 — Modelo de datos y arquitectura base del backend

**Objetivo:** dejar el dominio de datos sólido antes de construir endpoints o UI.

- [x] Diseñar esquema de base de datos: `products`, `categories`, `product_options`, `product_option_values`, `product_variants`, `variant_option_values` (pivot), `product_images` (con `option_value_id` nullable), `customers`, `orders`, `order_items`, `order_status_history`, `store_settings`, `payment_methods`, `fulfillment_methods`
- [x] Modelar el sistema de variantes como genérico (opciones + valores + combinaciones), no con columnas fijas tipo `color`/`talla` — ver `PRD.md` sección 5bis
- [x] Definir regla de "variante implícita" para productos sin opciones (todo producto tiene al menos una variante, aunque no tenga opciones configuradas)
- [x] Diseñar tablas de **multimoneda**: `currencies` (código, nombre, símbolo), `exchange_rates` (moneda_origen, moneda_destino, valor, fuente, vigente_desde) con historial, `exchange_rate_settings` (por par: modo `manual`/`automático`, provider usado, frecuencia de actualización, monto de referencia para APIs tipo CriptoYa) — ver `PRD.md` secciones 5ter y 8bis
- [x] Agregar a `orders` los campos de congelamiento de tasa: `base_currency`, `base_amount`, `payment_currency`, `exchange_rate_applied`, `payment_amount`
- [x] Diseñar catálogos de ubicación: `states` (Estados), `municipalities` (Municipios, FK a Estado), `parishes` (Parroquias, FK a Municipio) — ver `PRD.md` sección 9
- [x] Agregar a `customers`/`orders` los campos de dirección (state_id, municipality_id, parish_id, address_reference) y de identificación (document_type, document_number, phone con formato +58)
- [x] Agregar campo de reserva temporal de inventario a `product_variants` u `order_items` (`reserved_until` o tabla `inventory_reservations`)
- [x] Diseñar tabla `inventory_movements` (kardex): variante, tipo de movimiento (venta, liberación, ajuste manual), cantidad, motivo, orden relacionada (nullable), usuario admin relacionado (nullable para movimientos automáticos) — ver `PRD.md` sección 5quater
- [x] Crear migraciones para todas las tablas anteriores
- [x] Crear modelos Eloquent con relaciones (`Product hasMany Variants`, `Order hasMany Items`, `Order belongsTo Municipality`, etc.)
- [x] Crear seeders de datos de ejemplo: productos ficticios, categorías, una tienda demo, catálogo base de Estados/Municipios/Parroquias de Venezuela, monedas base (VES, USD, USDT, COP)
- [x] Definir estructura de carpetas del backend orientada a capas: `app/Domain`, `app/Http/Controllers/Api`, `app/Services`, `app/Providers` (para payment/fulfillment providers más adelante)
- [x] Configurar Laravel Sanctum para autenticación de API (customer + admin)
- [x] Crear sistema de roles: `owner` (admin dueño, acceso total) y `staff` (acceso limitado a órdenes, sin acceso a configuración sensible) — ver `PRD.md` sección 4
- [x] Escribir tests unitarios básicos de los modelos principales (factories + relaciones)
- [x] Documentar el esquema de datos en `docs/schema.md` (diagrama o tabla descriptiva)

**Entregable de fase:** base de datos completa, poblada con datos de prueba, sin endpoints públicos todavía.

---

## Fase 2 — API de catálogo (backend) + consumo en frontend

**Objetivo:** primer flujo end-to-end visible: productos del backend renderizados en el frontend.

- [x] Endpoint `GET /api/products` (listado con paginación y filtros básicos: categoría, búsqueda)
- [x] Endpoint `GET /api/products/{slug}` (detalle de producto con opciones, valores, variantes e imágenes asociadas por valor de opción)
- [x] Endpoint `GET /api/categories`
- [x] Endpoint `GET /api/currencies` (monedas habilitadas por la tienda + tasa de cambio vigente)
- [x] Formato de respuesta consistente (API Resources de Laravel, no exponer modelos crudos)
- [x] Manejo de errores estandarizado (formato JSON consistente para 404/422/500)
- [x] Configurar CORS para permitir requests del frontend
- [x] Crear capa de servicios en Next.js para consumir la API (`/lib/api/products.ts`)
- [x] Página de listado de productos (storefront)
- [x] Página de detalle de producto
- [x] Selector de variantes en la página de detalle (por opción: ej. botones de color, dropdown de talla) que actualice precio, stock disponible e imágenes mostradas según la combinación seleccionada
- [x] Deshabilitar en el selector las combinaciones de variante sin stock disponible (no permitir agregar al carrito una variante agotada — sin backorder, ver `PRD.md` sección 5quater)
- [x] Selector de moneda de visualización (si la tienda tiene más de una habilitada) que recalcule los precios mostrados con la tasa vigente
- [x] Componente de tarjeta de producto reutilizable
- [x] Manejo de estados de carga/error en el frontend
- [x] Definir sistema de theming inicial (variables CSS/Tailwind config) para personalización por cliente

**Entregable de fase:** storefront público navegable mostrando productos reales desde el backend, con precios en la moneda elegida.

---

## Fase 3 — Carrito y checkout (sin pago real todavía)

**Objetivo:** flujo de compra completo hasta creación de orden, con pago pendiente.

- [x] Implementar estado de carrito en frontend (Zustand o Context) persistido en localStorage
- [x] UI de carrito (agregar, quitar, actualizar cantidad, ver totales)
- [x] Endpoint `POST /api/orders` (crear orden en estado `pending_payment`) con validación de stock
- [x] Reserva temporal de inventario al crear la orden (ventana configurable, ej. 30-60 min — ver `PRD.md` sección 12), implementada con transacción de base de datos + bloqueo de fila (`SELECT ... FOR UPDATE`) para evitar sobreventa por concurrencia
- [x] Registrar movimiento de tipo "reserva" en `inventory_movements` al crear la orden
- [x] Job programado que libera la reserva si expira sin pago (actualiza stock disponible y registra movimiento de "liberación" en `inventory_movements`)
- [x] Congelar tasa de cambio y moneda base/pago al crear la orden (no recalcular después)
- [x] Endpoints `GET /api/locations/states`, `GET /api/locations/municipalities?state_id=`, `GET /api/locations/parishes?municipality_id=` para los selects dependientes de dirección
- [x] Formulario de checkout: datos de envío (Estado/Municipio/Parroquia + referencia libre), datos de cliente (nombre, teléfono +58, tipo/número de documento), o checkout como invitado
- [x] Página de "orden creada" con resumen y siguiente paso (ir a pagar)
- [x] Endpoint `GET /api/orders/{id}` para que el cliente consulte su orden
- [x] Manejo de expiración de órdenes no pagadas (job programado que cancela y libera stock — mismo mecanismo que la reserva de inventario)

**Entregable de fase:** un usuario puede armar carrito, hacer checkout con dirección real venezolana, y queda una orden creada en base de datos con estado pendiente de pago y tasa congelada.

---

## Fase 4 — Sistema de pago manual (payment providers)

**Objetivo:** implementar la pieza más específica del contexto venezolano.

- [x] Definir interfaz `PaymentProviderInterface` (incluye `getCurrency()`) en el backend
- [x] Implementar `PagoMovilProvider` (Bs), `ZelleProvider` (USD), `TransferenciaNacionalProvider` (Bs), `EfectivoContraEntregaProvider` (moneda configurable)
- [x] Definir interfaz `ExchangeRateProviderInterface` (`getRate`, `getSourceName`) — ver `PRD.md` sección 8bis
- [x] Implementar `ManualRateProvider` y `CriptoYaRateProvider` (consulta `https://criptoya.com/api/binancep2p/{par}/{monto}` con monto configurable)
- [x] Job programado (Laravel Scheduler) que ejecuta los providers automáticos según la frecuencia configurada por par y guarda el resultado en `exchange_rates`
- [x] Manejo de fallo del provider automático: no romper el checkout, mantener la última tasa válida, registrar el incidente (log) para revisión del admin
- [x] Tabla/config de `payment_methods` editable desde admin (habilitar/deshabilitar, datos de cuenta, moneda asociada) — modelo, seeder y providers listos; el CRUD de admin es Fase 5
- [x] Endpoint `GET /api/payment-methods` (métodos activos con sus instrucciones y moneda, para mostrar en checkout)
- [x] Endpoint `POST /api/orders/{id}/payment-proof` (subida de comprobante — imagen/PDF)
- [x] Configurar almacenamiento de archivos (local en dev, definir estrategia para prod) con compresión de imágenes al subir
- [x] Validaciones de archivo (tipo, tamaño máximo)
- [ ] UI de checkout: selección de método de pago + instrucciones dinámicas según el método (mostrando el monto ya convertido a la moneda de ese método)
- [ ] UI de subida de comprobante post-checkout
- [x] Estado de orden se actualiza a `payment_submitted` al subir comprobante
- [x] Al confirmar el pago (acción de admin en Fase 5), convertir la reserva en descuento definitivo de stock y registrar movimiento de tipo "venta" en `inventory_movements` — `Order::confirmPayment()` implementado y testeado; el endpoint admin que lo dispara queda para Fase 5
- [x] Notificación al admin cuando llega un nuevo comprobante (email y/o link de WhatsApp, vía cola)

**Entregable de fase:** cliente puede elegir método de pago, ver instrucciones en la moneda correcta, subir comprobante, y la orden refleja ese estado.

### Decisiones tomadas durante la Fase 4 (backend)

- El checkout pasó de recibir `payment_currency_id` a recibir `payment_method_id`:
  la moneda de pago se deriva del método elegido, de modo que una orden nunca
  puede quedar congelada en una moneda distinta a la que realmente se cobra.
  **Rompe el contrato de la Fase 3 — el formulario de checkout del frontend debe
  actualizarse.**
- Al subir un comprobante la reserva de inventario no se vuelve eterna: se
  extiende `commerce.payment_review_minutes` (72 h por defecto) y el barrido
  programado también cubre las órdenes en `payment_submitted`.
- Los payment/exchange-rate providers viven en `app/Domain/Payments` y
  `app/Domain/ExchangeRates`, no en `app/Providers` (reservado por Laravel para
  los Service Providers).

---

## Fase 5 — Panel de administración

**Objetivo:** el admin puede operar la tienda sin tocar la base de datos directamente.

Es la fase más grande del plan, así que va partida en cuatro sub-fases que se
cierran y se demuestran por separado (ver "Notas de proceso"). El orden importa:
5a habilita a las demás, y 5b es la que entrega valor operativo real más rápido
— sin ella la tienda no puede cobrar una venta.

### Decisiones a tomar antes de escribir código

- [x] **Estrategia de sesión del admin**: se eligió **Sanctum SPA por cookie**. Implica que frontend y backend comparten dominio padre — los dominios de desarrollo pasan a ser `tienda.test` (frontend) y `api.tienda.test` (backend). Ver `docs/decisions.md`
- [x] **Convivencia de guards**: las rutas admin usan el guard `web` (que resuelve a `User`) y las de cliente el guard `customer`. Ver `docs/decisions.md`
- [x] **Frontera `owner` vs `staff`**: staff opera pedidos y lee catálogo; todo lo demás es `owner`. Ver `docs/decisions.md`

---

### Fase 5a — Autenticación, permisos y usuarios staff

**Objetivo:** que exista un admin autenticado con permisos reales antes de construir una sola pantalla de gestión.

Backend:

- [x] Endpoints de sesión: `POST /api/admin/login`, `POST /api/admin/logout`, `GET /api/admin/me`, con rate limiting en el login (5 intentos por email+IP, más `throttle:10,1` en la ruta)
- [x] Migración: agregar columna de desactivación a `users` — se eligió `is_active`, ver `docs/decisions.md`
- [x] Rechazar a usuarios desactivados en el login y en cada request (middleware `active`), e invalidar sus sesiones vigentes al desactivarlos
- [x] Middleware de rol (`role:owner`) + `UserPolicy` en `app/Policies`
- [x] Grupo de rutas `/api/admin/*` en `routes/admin.php`, separado de las rutas públicas de `routes/api.php`
- [x] CRUD de usuarios staff (crear, editar, desactivar/reactivar) restringido a `owner`, impidiendo que un owner se desactive a sí mismo o que la tienda quede sin owners activos
- [x] Manejo de 401/403 en el formato de error estándar de la API (antes un 403 caía en el catch-all de 500 en producción)
- [x] Tests de feature: 27 casos en `tests/Feature/Api/Admin` — login/logout, usuario desactivado rechazado, no divulgación de existencia de cuentas, rate limiting, staff rebotado con 403 en cada endpoint restringido, invariante de "siempre queda un owner activo"
- [x] Tests unitarios: 18 casos en `tests/Unit/Models/UserAccountTest` (activación, scopes, casos borde de `isLastActiveOwner`, invalidación de sesiones) y `tests/Unit/Http/EnsureUserHasRoleTest` (varios roles permitidos, request sin usuario, rol inexistente)

Frontend:

- [x] Rutas `/admin/*` embebidas en el mismo Next.js (decisión ya tomada en Fase 0), con layout propio separado del storefront
- [x] Pantalla de login de admin
- [x] Protección de rutas admin en el frontend (`RequireAdmin`) + manejo de sesión expirada (redirect a login)
- [x] Ocultar en la UI lo que el rol no puede usar (bloque `permissions` de `UserResource`) — sin confiar en eso como seguridad: la autoridad es el backend
- [x] Pantalla de gestión de usuarios staff
- [ ] Verificar el build del frontend (`bun install && bun run build`) — no se pudo ejecutar en el entorno donde se escribió el código

**Entregable de sub-fase:** un owner y un staff inician sesión, ven menús distintos, y el staff recibe 403 del backend si intenta tocar configuración.

---

### Fase 5b — Gestión de órdenes

**Objetivo:** cerrar el ciclo operativo que la Fase 4 dejó a medias — el admin puede cobrar y despachar.

Backend:

- [x] Endpoint `GET /api/admin/orders` (listado con filtro por estado, búsqueda por número de orden/cliente, paginación) — la búsqueda cubre también documento y teléfono, y escapa los comodines de `ILIKE`
- [x] Endpoint `GET /api/admin/orders/{order}` (detalle: cliente, items, tasa aplicada, historial de estados, comprobantes) — enlazado por `order_number` como en el storefront; el id numérico sigue sin salir de la base
- [x] Endpoint de descarga del comprobante: se eligió **streaming autenticado** (`GET /api/admin/payment-proofs/{proof}`) en vez de URL firmada temporal. Ver `docs/decisions.md`
- [x] Endpoint `POST /api/admin/orders/{order}/confirm-payment` (envuelve `Order::confirmPayment()`, ya implementado y testeado en Fase 4)
- [x] Endpoint `POST /api/admin/orders/{order}/reject-payment` con motivo obligatorio (envuelve `Order::rejectPayment()`)
- [x] Endpoint de transición `POST /api/admin/orders/{order}/transition` para marcar en preparación / enviado / entregado, apoyado en `OrderStatus::allowedTransitions()`; acepta solo esos tres estados, ver `docs/decisions.md`
- [x] **Nuevo método de dominio `Order::cancel(User $admin, string $reason)`**: libera la reserva si la orden todavía no estaba pagada, y reingresa el stock si ya lo estaba, registrando el movimiento correspondiente en `inventory_movements`. El reingreso usa un tipo de movimiento nuevo, `InventoryMovementType::Restock`, ver `docs/decisions.md`
- [x] Verificar que todas las acciones nuevas pasen por `Order::transitionTo()`, que ya escribe `order_status_history` sola, y que registren `changed_by` — las cuatro son métodos de `Order` que toman el row lock de la orden y reciben el admin que actuó
- [x] Tests: 54 casos nuevos — 37 de feature (`OrderActionsTest` 16, `OrderListTest` 14, `PaymentProofDownloadTest` 7) y 17 unitarios, de los cuales 12 en `OrderCancellationTest` recorren la cancelación desde cada estado verificando stock y kardex. `staff` ejecuta las acciones en los tests, no solo `owner`

Frontend:

- [ ] Listado de órdenes con filtros por estado
- [ ] Vista de detalle de orden: datos del cliente, items, comprobante visualizable, historial de estados, tasa de cambio aplicada
- [ ] Acciones de confirmar pago / rechazar pago (con motivo) y de avance de estado, mostrando solo las transiciones válidas para el estado actual
- [ ] Confirmación explícita en las acciones difíciles de revertir (confirmar pago, cancelar)

**Entregable de sub-fase:** una orden creada desde el storefront se cobra, se prepara y se despacha desde el panel, con inventario y kardex correctos en cada camino — incluido el de cancelación.

### Decisiones tomadas durante la Fase 5b (backend)

- El comprobante se sirve por **streaming autenticado** (`GET /api/admin/payment-proofs/{proof}`),
  no por URL firmada temporal: una URL firmada es una credencial al portador que
  sigue funcionando fuera de la sesión que la pidió y queda escrita en el
  historial del navegador. Ver `docs/decisions.md`.
- El endpoint genérico de transición acepta **solo** `preparing`, `shipped` y
  `delivered`. Cobrar, devolver a `pending_payment` y cancelar mueven stock, y
  cada uno tiene su endpoint: aceptarlos aquí sería saltarse ese efecto.
- Cancelar una orden ya pagada reingresa el stock con un tipo de movimiento
  nuevo, `InventoryMovementType::Restock`, distinto de `Release` (que solo
  suelta una reserva, sin tocar `stock`) y de `Adjustment` (que la Fase 5c
  reserva para correcciones manuales de conteo).
- **Hueco detectado, no resuelto en esta sub-fase:** una orden con un método de
  pago que no pide comprobante (`EfectivoContraEntrega`, `requiresProof() ===
  false`) se queda en `pending_payment` para siempre: la máquina de estados no
  permite `pending_payment → paid`, así que el panel no puede cobrarla. Cerrarlo
  implica cambiar un invariante de la Fase 1 (`OrderStatus::allowedTransitions`,
  con un test que lo fija) y decidir cuándo se considera pagada una venta contra
  entrega — antes o al momento de entregar. Queda como decisión de producto.

---

### Fase 5c — Catálogo, variantes e inventario

**Objetivo:** el admin puede cargar y mantener el catálogo sin seeders ni SQL.

Backend:

- [x] CRUD de categorías — con `slug` derivado del nombre, guardia contra ciclos en `parent_id` y negativa a eliminar una categoría con productos o subcategorías (ambas FK son `nullOnDelete`: la base la aceptaría descategorizando todo en silencio)
- [x] CRUD de productos, con generación y validación de `slug` único — "eliminar" es **baja lógica** y arrastra a las variantes vivas; restaurar las devuelve todas. Ver `docs/decisions.md`
- [x] CRUD de opciones de producto y sus valores ("Color", "Talla" y sus valores posibles) — agregar una opción se rechaza si ya hay variantes con combinaciones; agregar un valor siempre se permite
- [x] Generador de variantes a partir de las combinaciones de valores de opción (todas o una selección), respetando la regla de variante implícita definida en la Fase 1 — `VariantGenerator`, idempotente: la combinación que ya existe se cuenta como omitida
- [x] Edición individual de variante: SKU, `price_override`, activa/inactiva — **el stock no**: cambiarlo exige motivo y pasa por el endpoint de ajuste, ver `docs/decisions.md`
- [x] **Nuevo método `InventoryReservationService::adjust()`**: toma `lockForUpdate` releyendo la fila, exige motivo y rechaza dejar `stock` por debajo de `reserved_quantity`
- [x] Endpoint de ajuste manual de stock por variante (reposición, corrección de conteo físico, baja por daño/pérdida) con motivo obligatorio y `created_by`
- [x] Endpoint de historial de movimientos de inventario por variante (kardex), paginado y filtrable por tipo
- [x] **Pipeline de imágenes de producto**: disco `public` configurable (`commerce.product_image`), `storage:link` documentado en el README, y la compresión extraída de `PaymentProofService` a `ImageStorageService`, que ahora usan los dos
- [x] Endpoints de subida, borrado, reordenamiento e imagen principal, asociadas a un `product_option_value_id` o al producto general
- [x] Tests: 135 casos nuevos — 89 de feature (`ProductManagementTest` 19, `InventoryTest` 15, `ProductImageManagementTest` 15, `VariantManagementTest` 14, `ProductOptionManagementTest` 14, `CategoryManagementTest` 12) y 46 unitarios (`VariantGeneratorTest` 18, `ProductCatalogTest` 15, `InventoryAdjustmentTest` 7, `ImageStorageServiceTest` 6). Cubren el generador, el ajuste con reserva viva, el kardex, las validaciones de archivo y el rebote de `staff` con 403 en cada endpoint de escritura. Suite completa: 471 casos en verde

Frontend:

- [ ] CRUD de productos y categorías desde el panel
- [ ] CRUD de opciones de producto y sus valores
- [ ] UI del generador de variantes (elegir qué combinaciones crear antes de generarlas)
- [ ] Edición individual de variante: SKU, precio (override opcional), stock
- [ ] Formulario de ajuste manual de stock con motivo
- [ ] Vista de historial de movimientos de inventario por variante (kardex)
- [ ] Subida de imágenes asociadas a un valor de opción específico (ej. fotos del color "Rojo") o al producto general si no aplica opción visual, con previsualización

**Entregable de sub-fase:** un producto con opciones, variantes, imágenes y stock se crea de punta a punta desde el panel y aparece correctamente en el storefront.

### Decisiones tomadas durante la Fase 5c (backend)

- **El stock no es un campo editable.** La edición de variante acepta SKU,
  `price_override` y activa/inactiva, y rechaza `stock` con un 422 explícito.
  Toda unidad que se mueve deja una fila en el kardex, y eso exige motivo: el
  único camino es `POST /variants/{variant}/adjust-stock`. Ver
  `docs/decisions.md`.
- **"Eliminar" un producto es baja lógica** y arrastra a sus variantes vivas.
  Restaurarlo las devuelve **todas**, incluidas las que se habían retirado una
  por una: `deleted_at` tiene precisión de segundos, así que no hay forma
  fiable de distinguirlas. Ver `docs/decisions.md`.
- **El generador es el único que escribe el conjunto de variantes.** Crear un
  producto crea su variante implícita; generar combinaciones reales la archiva.
  Agregar una opción a un producto que ya tiene variantes con combinaciones se
  rechaza (esas variantes quedarían indefinidas en el eje nuevo); agregar un
  valor a una opción existente siempre se permite.
- **Las imágenes se cuelgan del valor de opción, no de la variante** (como pide
  el PRD): las fotos de "Rojo" las heredan Rojo-38, Rojo-39 y Rojo-40 sin
  duplicarlas. `ImageStorageService` es ahora el único que sabe escribir un
  archivo subido, y lo comparten comprobantes e imágenes de catálogo.
- **Hueco conocido, no cerrado en esta sub-fase:**
  `InventoryReservationService::lockVariantsForOrder()` valida
  `product_variants.is_active` pero no mira `products.is_active`. Archivar el
  producto sí cierra la puerta (la baja lógica arrastra a las variantes, y la
  consulta las excluye), pero **despublicar** no: las variantes de un producto
  con `is_active = false` siguen siendo reservables por id, aunque el
  storefront ya no las ofrezca. Cerrarlo toca una ruta de la Fase 4 con tests
  propios y merece su propia decisión.

---

## Fase 6 — Fulfillment (envío) y cierre del ciclo de orden

**Objetivo:** completar el ciclo de vida de la orden con la lógica de envío.

- [ ] Definir interfaz `FulfillmentProviderInterface`
- [ ] Implementar `DeliveryPropioProvider`, `RetiroEnTiendaProvider`, `CourierManualProvider`
- [ ] Tabla/config de métodos de envío editable desde admin, con tarifas asociadas a zonas (Estado/Municipio)
- [ ] Selección de método de envío en checkout (si aplica más de una opción)
- [ ] Cálculo de costo de envío básico (tarifa plana por zona o "a coordinar")
- [ ] Campo de tracking/courier/nota libre al marcar orden como enviada
- [ ] Notificaciones al cliente en cambios de estado clave (pago confirmado, enviado, entregado) vía email y link de WhatsApp (`wa.me` prellenado)
- [ ] Página de "mis pedidos" en el storefront (cliente autenticado) con historial y estado actual

**Entregable de fase:** ciclo de vida completo de la orden, de creación a entrega, con visibilidad para cliente y admin.

---

## Fase 7 — Dockerización final y despliegue en Dokploy

**Objetivo:** dejar el template listo para clonar y desplegar en producción con mínima fricción.

- [ ] Optimizar Dockerfile de backend para producción (multi-stage, sin herramientas de dev, opcache configurado)
- [ ] Optimizar Dockerfile de frontend para producción (Next.js standalone output, imagen `oven/bun`)
- [ ] Revisar `docker-compose.yml` para producción (o crear `docker-compose.prod.yml` separado)
- [ ] Configurar variables de entorno necesarias para Dokploy (dominios, `APP_URL`, `NEXT_PUBLIC_API_URL`, credenciales de BD, número de WhatsApp de la tienda)
- [ ] Configurar healthchecks en los contenedores (para que Dokploy detecte servicios caídos)
- [ ] Probar despliegue completo en Dokploy desde cero (clonar repo → configurar env → deploy)
- [ ] Configurar HTTPS/dominio en Dokploy para el proyecto de prueba
- [ ] Documentar en `docs/deploy.md` el proceso paso a paso de "nuevo cliente": clonar, configurar `.env`, ejecutar migraciones/seeders, desplegar
- [ ] Script o comando Artisan de "bootstrap" para nueva instancia (crea admin inicial, configura moneda base, tasas iniciales y datos base de la tienda vía prompts o archivo de config)
- [ ] Configurar backups automáticos de base de datos (cron + dump, o feature nativa de Dokploy si existe) con frecuencia mínima diaria

**Entregable de fase:** el objetivo central del proyecto cumplido — clonar, configurar, y desplegar una tienda nueva en Dokploy en menos de una hora.

---

## Fase 8 — Pulido, testing y hardening (antes de ofrecerlo a un cliente real)

**Objetivo:** pasar de "funciona en mis pruebas" a "listo para un cliente real".

- [ ] Tests de integración de los flujos críticos (crear orden, subir comprobante, confirmar pago, cambiar estado de envío, expiración/liberación de reserva de inventario)
- [ ] Test de concurrencia: dos órdenes simultáneas intentando reservar la última unidad de una variante — verificar que solo una tenga éxito y la otra reciba error de stock insuficiente
- [ ] Revisión de seguridad: sanitización de uploads, rate limiting en endpoints públicos, validación exhaustiva de inputs
- [ ] Revisión de permisos: separación real entre rol `owner` y `staff`, que un cliente no pueda ver/modificar órdenes de otro cliente
- [ ] Optimización de queries (evitar N+1, revisar índices en tablas de alto volumen: `orders`, `products`, `exchange_rates`)
- [ ] Responsive/mobile del storefront (crítico dado uso probable desde celular en Venezuela)
- [ ] Revisión de performance de imágenes (compresión, lazy loading, formatos modernos)
- [ ] Validar cálculos de conversión de moneda y redondeo en escenarios extremos (tasas muy altas, montos pequeños)
- [ ] Documentación final de uso para un "cliente tipo" (manual básico del panel admin, incluyendo cómo actualizar la tasa de cambio)
- [ ] Checklist de "nueva instancia" consolidado en `docs/onboarding-checklist.md`

**Entregable de fase:** template listo para ofrecerse comercialmente, no solo como proyecto de aprendizaje.

---

## Backlog / Fases futuras (fuera del alcance inicial, no planificar aún)

- Integración automática de Binance Pay (webhook de confirmación de pago)
- Multi-tenancy real (si el volumen de clientes lo justifica)
- Notificaciones vía API oficial de WhatsApp (más allá del link `wa.me`)
- Reportes avanzados / dashboard de analíticas
- Fuentes de tasa de cambio adicionales (BCV oficial, otros exchanges) más allá de manual y CriptoYa
- Programa de descuentos/cupones
- Facturación fiscal electrónica (SENIAT)
- CI/CD automatizado (GitHub Actions → deploy a Dokploy)

---

## Notas de proceso

- Cada fase debe cerrar con algo **demostrable**, no solo código escrito. Si una
  fase no se puede probar de punta a punta, está mal cortada.
- Evitar construir abstracciones (como el sistema de providers) antes de que el
  PRD lo pida explícitamente — la Fase 4 y 6 son las que las requieren, no antes.
- Ir documentando decisiones técnicas relevantes en `docs/decisions.md` (ej. por
  qué PostgreSQL y no MySQL, por qué admin embebido y no app separada, etc.) —
  esto es tan valioso para el aprendizaje como el código mismo.
