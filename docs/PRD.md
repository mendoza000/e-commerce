# PRD — Ecommerce Template (Single-Tenant Replicable)

## 1. Contexto y motivación

Se busca construir un boilerplate de ecommerce **single-tenant**, pensado para ser
clonado y desplegado de forma independiente por cada cliente, adaptado al contexto
venezolano donde:

- Las pasarelas de pago automatizadas (Stripe, PayPal) no son viables o tienen
  adopción muy baja.
- Los métodos de pago reales son: Pago Móvil, transferencia bancaria nacional,
  Zelle, Binance Pay/USDT, efectivo contra entrega.
- Coexisten múltiples monedas de uso corriente según la zona del país: bolívares
  (Bs), dólares (USD), USDT, e incluso pesos colombianos (COP) en zonas
  fronterizas.
- Los envíos combinan couriers nacionales (MRW, Zoom, Tealca) sin APIs públicas
  confiables, y delivery propio (moto/carro) coordinado manualmente.
- La confirmación de pago y coordinación de envío suele requerir intervención
  humana (revisar comprobante, coordinar por WhatsApp, etc.)
- No existe un sistema de códigos postales de uso masivo confiable; las
  direcciones se ubican por Estado/Municipio/Parroquia y referencias.
- WhatsApp es el canal de comunicación dominante, muy por encima del email.
- La conectividad y el acceso a datos móviles son limitados en varias zonas.

El proyecto tiene un doble objetivo: **aprendizaje técnico profundo** (arquitectura
limpia, Laravel + Next.js, Docker, deploy) y **producto replicable** (poder
ofrecer este ecommerce a distintos clientes con mínima fricción de setup).

## 2. Objetivos del producto

1. Backend (Laravel/PHP) que exponga una API REST/JSON para gestionar catálogo,
   inventario, órdenes, clientes y configuración de la tienda.
2. Frontend (Next.js) que consuma esa API, con un storefront público y un panel
   administrativo embebido bajo rutas protegidas.
3. Sistema de **pago manual verificable**: el cliente sube comprobante, el admin
   confirma/rechaza, la orden cambia de estado.
4. Sistema de **fulfillment/envío semi-manual**: estados de orden gestionados por
   un humano, con datos de tracking opcionales cuando el courier lo permita.
5. Soporte de **variantes de producto genéricas** (talla, color, sabor, etc.) sin
   anticipar tipos de producto en el código.
6. Soporte de **multimoneda** (Bs, USD, USDT, COP) con tasas configurables por el
   admin y trazabilidad de la tasa usada en cada orden.
7. Arquitectura de "providers" desacoplada para pago y envío, de forma que agregar
   un nuevo método (ej. Binance Pay con webhook automático) no rompa el resto del
   sistema.
8. Todo dockerizado y orquestado para desplegarse en **Dokploy** como stack único
   por cliente (backend + frontend + DB + posible cola/redis).
9. Repositorio estructurado como **monorepo** con separación clara de apps, para
   poder clonar → configurar `.env` → desplegar sin tocar código de lógica de
   negocio.

## 3. No-objetivos (fuera de alcance inicial)

- Multi-tenancy real (un despliegue sirviendo múltiples clientes). Se documenta
  como posible evolución futura, no como requisito de esta fase.
- Integraciones automáticas con couriers nacionales (no existen APIs confiables).
- Marketplace multi-vendedor.
- App móvil nativa.
- Pasarelas de pago internacionales tipo Stripe (se puede dejar el provider
  preparado para el futuro, pero no es prioridad).
- Facturación fiscal electrónica (SENIAT) — se puede dejar el modelo de datos
  preparado (campos de RIF, numeración de orden) pero la generación de
  comprobante fiscal formal queda fuera de esta fase.
- Soporte offline/PWA completo — se prioriza únicamente la optimización de peso
  de datos (imágenes, payloads), no funcionamiento sin conexión.
- Fuentes de tasa de cambio adicionales más allá de manual y CriptoYa (ej. BCV
  oficial, otros exchanges) — el patrón de `ExchangeRateProvider` ya deja esto
  preparado para agregarse sin refactor, pero no se implementa en esta fase.
- Gestión de inventario multi-almacén/multi-sucursal — se asume un único punto
  de inventario por tienda. El modelo de stock por variante no impide agregarlo
  después, pero no se diseña para eso ahora.

## 4. Usuarios y roles

| Rol | Descripción |
|---|---|
| **Cliente final (comprador)** | Navega catálogo, arma carrito, hace checkout, sube comprobante de pago, consulta estado de su orden. |
| **Admin dueño de la tienda** | Acceso total: catálogo, órdenes, configuración de tienda, métodos de pago/envío, tasas de cambio, usuarios staff. |
| **Staff / operador** | Rol limitado: puede ver y gestionar órdenes (confirmar pagos, actualizar estados de envío) pero no accede a configuración sensible (tasas, métodos de pago, datos bancarios). |
| **Super admin / dueño del template** | (uso interno, no del cliente final) Configura la instancia al desplegarla para un nuevo cliente: datos de la tienda, métodos de pago habilitados, theming. |

## 5. Funcionalidades — Storefront (cliente final)

- Catálogo de productos con categorías, variantes (talla/color/presentación),
  imágenes múltiples, precios mostrados en la(s) moneda(s) habilitada(s) por la
  tienda.
- Selector de moneda de visualización (si la tienda tiene más de una habilitada).
- Búsqueda y filtros básicos.
- Carrito de compras (persistente vía sesión/local storage + sincronizado si el
  usuario inicia sesión).
- Checkout:
  - Datos de envío: Estado, Municipio, Parroquia (selects dependientes) +
    dirección/referencia en texto libre — sin depender de código postal.
  - Datos del cliente: nombre, teléfono (formato +58 para habilitar contacto por
    WhatsApp), tipo y número de documento (cédula V-/E- o RIF si aplica).
  - Selección de método de pago habilitado (Pago Móvil / Zelle / Binance /
    transferencia / efectivo contra entrega), con la moneda correspondiente a
    cada método (ej. Pago Móvil en Bs, Zelle en USD, Binance en USDT).
  - Instrucciones específicas del método elegido (número de Pago Móvil, cédula,
    banco, wallet USDT, etc. — configurable por tienda).
  - Subida de comprobante de pago (imagen/PDF).
- Registro/login de cliente (opcional, se puede permitir checkout como invitado).
- Vista de "mis pedidos" con estado (pendiente de pago, pago confirmado, en
  preparación, enviado, entregado, cancelado).
- Notificaciones en cambios de estado clave por **email y/o WhatsApp** (link
  `wa.me` prellenado como mínimo viable; API oficial de WhatsApp queda en
  backlog).

## 5bis. Sistema de variantes de producto

El catálogo debe soportar productos con múltiples variantes (talla, color,
presentación, sabor, material, etc.) de forma **genérica**, sin anticipar tipos
de producto específicos en el código. El admin define las opciones que necesite
desde el panel, sin requerir cambios de schema ni de backend.

### Modelo de datos

- **Producto**: entidad base (nombre, slug, descripción, precio base en la
  moneda base de la tienda).
- **Opciones del producto**: atributos que varían (ej: "Color", "Talla",
  "Sabor"). Un producto puede tener 0, 1 o varias opciones.
- **Valores de opción**: los valores posibles de cada opción (ej: Color →
  Rojo, Azul, Negro).
- **Variante**: combinación específica de valores de opción (ej: Rojo + Talla
  40). Es la unidad real de venta: tiene SKU propio, stock propio, y
  opcionalmente un precio distinto al del producto base.
- **Imágenes**: se asocian preferentemente al **valor de opción visual**
  (normalmente color), no a la variante final — así todas las variantes que
  comparten ese valor (ej. Rojo-38, Rojo-39, Rojo-40) heredan el mismo set de
  fotos sin duplicación. Si el producto no tiene una opción visual, las
  imágenes quedan asociadas directamente al producto.

### Reglas de negocio clave

- Todo producto tiene al menos una variante, aunque no tenga opciones
  configuradas (variante implícita = el producto mismo). Esto evita tener que
  ramificar la lógica de carrito/inventario entre "productos simples" y
  "productos con variantes".
- El stock se controla **a nivel de variante**, nunca a nivel de producto.
- El precio de una variante puede sobrescribir (`price_override`) el precio
  base del producto; si es nulo, se usa el precio base.
- El SKU es único por variante, no por producto.
- Las opciones y sus valores son configurables libremente por el admin, sin
  intervención de desarrollo — el sistema no debe tener nombres de opciones
  "hardcodeados" (evitar columnas fijas tipo `color`, `talla` en la tabla de
  productos).

### Ejemplos de variaciones a soportar (referencia, no exhaustivo)

| Tipo de tienda | Opciones típicas |
|---|---|
| Zapatos | Talla, color, ancho |
| Ropa | Talla, color, material |
| Electrónica | Capacidad de almacenamiento, color, voltaje/región |
| Alimentos y bebidas | Presentación/peso, sabor |
| Cosméticos y perfumes | Tamaño de frasco, fragancia |
| Joyería | Material, talla de anillo |
| Muebles | Color/acabado, dimensión |

## 5ter. Sistema multimoneda

Dada la coexistencia real de varias monedas en el país (Bs, USD, USDT, y COP en
zonas fronterizas), el sistema debe manejar precios y pagos de forma
multimoneda sin asumir una única moneda "correcta".

### Conceptos clave

- **Moneda base de la tienda**: la moneda en la que se almacena el precio de
  referencia de cada producto (recomendado: USD, por ser la más estable frente
  a la inflación de Bs). Configurable por tienda al momento del setup.
- **Monedas habilitadas**: cada tienda decide qué monedas acepta mostrar y
  cobrar (ej. solo USD y Bs, o las cuatro). Configurable desde el panel admin.
- **Tasas de cambio**: tabla de tasas (`moneda_origen` → `moneda_destino`,
  valor, fuente, fecha de vigencia), actualizada manualmente por el admin **o
  automáticamente vía API externa**, según se configure por par de moneda. Se
  guarda **historial de tasas**, no solo el valor actual — esto es necesario
  para poder reconstruir a qué tasa se cobró una orden pasada.
- **Fuente de la tasa por par de moneda**: cada par (ej. USD→VES, USDT→VES) se
  configura de forma independiente como `manual` o `automática`. En modo
  automático, un job programado consulta una API externa (ej.
  [CriptoYa](https://criptoya.com/api/binancep2p/USDT/VES/2000), que expone el
  precio de referencia del mercado P2P de Binance) y guarda el valor obtenido
  en el historial. El monto de referencia usado en la consulta (ej. `2000` en
  el ejemplo) es configurable, no hardcodeado, ya que afecta el spread
  devuelto por la API.
- **Fallback ante fallo de la API**: si la fuente automática no responde o
  responde con error, el sistema usa la última tasa guardada exitosamente
  (nunca bloquea el checkout por un fallo externo) y registra el incidente
  para que el admin lo note.
- **Override manual siempre disponible**: independientemente del modo
  configurado para un par, el admin puede forzar un valor manual en cualquier
  momento; ese valor queda vigente hasta que se actualice de nuevo (manual o
  automáticamente).
- **Congelamiento de tasa por orden**: al crear una orden, se registra la tasa
  usada en ese momento (no un valor "vivo" que cambie después). Esto evita
  disputas si la tasa cambia entre que el cliente ve el precio y paga.
- **Redondeo**: definir reglas de redondeo por moneda (ej. Bs se redondea a
  entero dada la volatilidad; USD/USDT a 2 decimales).

### Flujo funcional

1. El admin configura la moneda base y las monedas habilitadas.
2. Para cada par de moneda relevante, el admin decide si la tasa se actualiza
   manualmente o de forma automática vía API externa (ej. CriptoYa para
   USDT/USD → VES).
3. Si es automática, un job programado consulta la API periódicamente
   (frecuencia configurable) y guarda el valor con su fuente en el historial;
   si falla, se mantiene la última tasa válida conocida.
4. El storefront muestra el precio convertido a la moneda que el cliente elija
   ver, calculado con la tasa vigente al momento de la visita.
5. Al confirmar el checkout, la orden guarda: monto en moneda base, moneda de
   pago elegida, tasa aplicada, fuente de esa tasa, y monto final en la moneda
   de pago.
6. El método de pago elegido determina implícitamente la moneda de cobro (ej.
   Pago Móvil → Bs, Zelle → USD, Binance Pay → USDT) — no se le pide al cliente
   elegir moneda y método por separado si eso genera ambigüedad; el método de
   pago ya define la moneda.

## 5quater. Gestión de inventario

El inventario se controla a nivel de variante (ver sección 5bis) y debe
soportar el ciclo completo de reserva → confirmación → liberación, con
protección contra sobreventa y trazabilidad de movimientos.

### Ciclo de vida del stock por orden

1. **Al crear la orden** (`pending_payment`): el stock solicitado se
   **reserva**, no se descuenta como venta definitiva todavía. La reserva
   tiene una ventana de expiración configurable (ver sección 12, riesgo de
   reserva de inventario).
2. **Al confirmar el pago** (`paid`): la reserva se convierte en **descuento
   definitivo** del stock disponible.
3. **Si la orden se cancela, se rechaza, o la reserva expira sin pago**: el
   stock reservado se **libera** y vuelve a estar disponible para otros
   clientes.

### Protección contra sobreventa (concurrencia)

Reservar stock debe ser una operación atómica: la lectura del stock
disponible y su reserva ocurren dentro de una misma transacción de base de
datos con bloqueo de fila (`SELECT ... FOR UPDATE` o equivalente en Eloquent).
Esto evita que dos clientes reserven simultáneamente la última unidad
disponible de una variante.

### Historial de movimientos (kardex)

Todo cambio de stock queda registrado con su motivo, no solo el número
resultante:

- Venta confirmada (descuento).
- Cancelación/expiración de orden (liberación).
- Ajuste manual del admin (reposición, corrección de conteo físico, baja por
  daño/pérdida).

Esto permite auditar por qué el stock de una variante cambió en un momento
dado, algo que un simple contador no responde.

### Política de stock agotado

Por defecto, **no se permite backorder/preventa**: si una variante tiene
stock 0, no puede agregarse al carrito ni completarse una orden con ella. Esta
es la decisión inicial más segura para una tienda pequeña; queda como mejora
futura permitir configurar esta política por producto si el negocio lo
requiere.

### Comportamiento esperado en el storefront

- Ocultar o deshabilitar en la UI las combinaciones de variante sin stock
  (ej. si "Rojo, Talla 38" está agotado, esa opción se muestra deshabilitada
  al seleccionar talla).
- Mostrar aviso de "últimas unidades" cuando el stock de una variante está por
  debajo de un umbral configurable (opcional, mejora de UX).

## 6. Funcionalidades — Panel Admin

- Login de administrador (roles: dueño / staff).
- Gestión de usuarios staff con permisos limitados (sin acceso a configuración
  de pagos, tasas de cambio o datos bancarios).
- CRUD de productos, categorías, inventario.
- Gestión de opciones y variantes por producto: crear opciones (ej. "Color",
  "Talla"), agregar valores a cada opción, generar variantes a partir de las
  combinaciones, y editar SKU/precio/stock por variante individualmente.
- Ajuste manual de stock por variante (reposición, corrección de conteo
  físico, baja por daño/pérdida), registrado en el historial de movimientos
  de inventario con su motivo.
- Asociación de imágenes a valores de opción (ej. subir fotos para "Rojo" y
  que apliquen a todas las variantes de ese color) o al producto general si no
  aplica una opción visual.
- Listado de órdenes con filtros por estado.
- Vista de detalle de orden: datos del cliente, items, comprobante de pago
  adjunto, historial de estados, tasa de cambio aplicada.
- Acciones: confirmar pago / rechazar pago (con motivo), marcar en preparación,
  marcar enviado (con campo opcional de tracking/courier/nota), marcar entregado,
  cancelar orden.
- Configuración de la tienda:
  - Datos generales (nombre, logo, colores de theme, moneda base, monedas
    habilitadas).
  - Métodos de pago habilitados y sus datos (cuenta bancaria, Pago Móvil, wallet,
    Zelle email, etc.), cada uno asociado a su moneda correspondiente.
  - Gestión de tasas de cambio (valor actual + historial).
  - Zonas/tarifas de envío básicas (ej. tarifa plana por zona, o "a coordinar").
  - Configuración de número de WhatsApp de contacto de la tienda (para links
    `wa.me` de notificación y soporte).
- Reporte básico: ventas por período, productos más vendidos (puede ir en fase
  posterior).

## 7. Arquitectura de Pago (Payment Providers)

Diseño basado en interfaz común, para que cada método de pago sea un módulo
independiente:

```
PaymentProviderInterface
  - getInstructions(order): array   // qué mostrarle al cliente en checkout
  - getCurrency(): string           // moneda en la que este método cobra
  - requiresProof(): bool           // si necesita subir comprobante
  - confirm(order, adminUser): void // acción manual de confirmación
  - handleWebhook(payload): void    // opcional, para providers automatizables
```

Providers iniciales (todos manuales, sin webhook):
- `PagoMovilProvider` (Bs)
- `ZelleProvider` (USD)
- `TransferenciaNacionalProvider` (Bs)
- `EfectivoContraEntregaProvider` (Bs o USD, configurable)

Provider semi-automático (fase posterior, opcional):
- `BinancePayProvider` (USDT, si se decide integrar webhook de Binance Pay para
  confirmación automática).

Cada tienda (instancia del template) habilita/deshabilita providers y configura
sus datos desde el panel admin — sin tocar código.

## 8. Arquitectura de Envío (Fulfillment Providers)

Similar en espíritu, pero más simple dado que no hay APIs de courier confiables:

```
FulfillmentProviderInterface
  - getLabel(): string              // "Delivery propio", "MRW", "Retiro en tienda"
  - requiresTrackingCode(): bool
  - getEstimatedCost(zone): decimal|null
```

Providers iniciales:
- `DeliveryPropioProvider`
- `RetiroEnTiendaProvider`
- `CourierManualProvider` (genérico: MRW/Zoom/Tealca, con campo libre de courier
  y número de guía si el cliente lo obtiene).

## 8bis. Arquitectura de tasas de cambio (Exchange Rate Providers)

Mismo patrón de providers desacoplados usado para pago y envío, aplicado a la
obtención de tasas de cambio:

```
ExchangeRateProviderInterface
  - getRate(fromCurrency, toCurrency): decimal
  - getSourceName(): string
```

Providers iniciales:
- `ManualRateProvider` (el admin ingresa el valor directamente).
- `CriptoYaRateProvider` (consulta `https://criptoya.com/api/binancep2p/{par}/{monto}`,
  con el monto de referencia configurable).

Cada par de moneda (ej. USD→VES, USDT→VES) se configura de forma independiente
con el provider que le corresponde. Un job programado ejecuta los providers
automáticos según la frecuencia configurada y persiste el resultado en
`exchange_rates` junto con la fuente usada. Si el provider automático falla,
no se escribe un nuevo registro y el sistema sigue usando el último valor
válido — el checkout nunca depende de la disponibilidad de una API externa en
tiempo real.

## 9. Modelo de dirección y datos del cliente

Dado que no existe un sistema de código postal de uso masivo confiable en
Venezuela, el modelo de dirección se basa en división político-territorial:

- **Estado** (ej. Miranda, Zulia, Táchira).
- **Municipio** (dependiente del Estado seleccionado).
- **Parroquia** (dependiente del Municipio seleccionado).
- **Dirección/referencia**: campo de texto libre (calle, urbanización, punto de
  referencia — común dado que muchas direcciones no tienen nomenclatura
  formal).

Estos tres niveles (Estado/Municipio/Parroquia) se modelan como catálogos
propios (tablas de referencia), no como texto libre, para poder usarlos luego
en zonas de envío y tarifas.

**Datos de identificación del cliente**:
- Tipo de documento: Cédula (V/E) o RIF.
- Número de documento.
- Teléfono en formato `+58` (usado también como identificador para contacto
  directo por WhatsApp).

## 10. Requisitos técnicos

### Backend
- Laravel (última LTS estable), API-only (sin Blade para el storefront).
- Laravel Sanctum para autenticación (API tokens / SPA auth).
- PostgreSQL como motor de base de datos.
- Estructura orientada a Domain/Service layer, no todo en controllers.
- Migraciones + seeders para bootstrap rápido de una nueva instancia (incluye
  catálogos base de Estado/Municipio/Parroquia).
- Manejo de archivos (comprobantes, imágenes de producto) vía filesystem
  configurable (local en dev, S3-compatible en prod si aplica), con
  compresión/optimización de imágenes al subirlas.
- Cola de trabajos (Laravel Queue) para notificaciones (email y generación de
  links de WhatsApp), para no bloquear requests.
- Job programado (scheduler) para liberar reservas de inventario de órdenes no
  pagadas tras un tiempo configurable.

### Frontend
- Next.js (App Router).
- Bun como gestor de paquetes y runtime de build.
- Consumo de API vía fetch/axios con capa de servicios tipada (TypeScript).
- Gestión de estado del carrito (Zustand o Context API — decidir en Fase 1).
- Theming vía variables CSS / Tailwind config, para personalizar por cliente sin
  tocar componentes.
- Panel admin embebido en el mismo Next.js, bajo rutas protegidas `/admin/*`.
- Optimización de imágenes (formatos modernos, lazy loading, tamaños
  responsivos) dado el contexto de datos móviles limitados.

### Infraestructura
- Monorepo con estructura:
  ```
  /apps
    /backend   (Laravel)
    /frontend  (Next.js)
  /docker
    docker-compose.yml
    backend.Dockerfile
    frontend.Dockerfile
  /docs
    PRD.md
    plan.md
    decisions.md
  ```
- Dockerización de ambos servicios + base de datos.
- Configuración pensada para **Dokploy**: cada cliente = un proyecto/stack nuevo
  en Dokploy apuntando a un fork/clone del repo con su propio `.env`.
- Variables de entorno centralizadas por servicio, documentadas en `.env.example`.
- Backups automáticos de base de datos (frecuencia mínima diaria, dada la
  inestabilidad de infraestructura local).
- CI básico (lint + test) — opcional en fase inicial, recomendable antes de
  ofrecerlo a clientes reales.

## 11. Métricas de éxito (para el propio aprendizaje/proyecto)

- Poder clonar el repo, configurar `.env`, y tener una tienda funcional
  desplegada en Dokploy en menos de 1 hora (objetivo de "replicabilidad").
- Flujo completo funcional: producto → carrito → checkout → pago manual →
  confirmación admin → envío → entrega, sin errores.
- Código organizado de forma que agregar un nuevo payment/fulfillment provider
  no requiera tocar lógica existente (principio abierto/cerrado).
- Un mismo template puede configurarse para una tienda que solo use USD, y para
  otra que use Bs + USD + USDT, sin tocar código.

## 12. Riesgos y consideraciones

- **Riesgo de sobre-ingeniería**: al ser proyecto de aprendizaje, hay tentación
  de abstraer demasiado desde el día 1. Mitigación: seguir el plan por fases,
  no construir el sistema de providers hasta tener 2-3 casos reales que
  justifiquen la abstracción.
- **Manejo de comprobantes de pago**: son datos sensibles (info bancaria
  parcial). Definir política de almacenamiento y acceso (solo admin de esa
  tienda).
- **Volatilidad de la tasa de cambio**: la tasa puede variar significativamente
  entre el momento en que el cliente ve el precio y paga. Mitigación: congelar
  la tasa al crear la orden (ver sección 5ter) y definir una ventana de validez
  corta para órdenes pendientes de pago.
- **Reserva de inventario**: al no haber confirmación de pago instantánea, dos
  clientes podrían intentar comprar la última unidad de una variante. Definir
  una ventana de reserva temporal (ej. 30-60 min) que libere el stock si no se
  sube comprobante o no se confirma el pago.
- **Fraude en comprobantes de pago**: es posible que un cliente suba un
  comprobante falso o reciclado. Mitigación mínima: el admin siempre confirma
  manualmente, se recomienda validar contra el estado de cuenta real antes de
  despachar.
- **Dependencia de WhatsApp como canal principal**: si se usa solo el enfoque
  de link `wa.me`, no hay confirmación automática de que el mensaje fue
  entregado o leído; es un canal de mejor esfuerzo, no garantizado.
