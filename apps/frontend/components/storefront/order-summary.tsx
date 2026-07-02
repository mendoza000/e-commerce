import { Badge } from "@/components/ui/badge";
import { formatCurrency, type ConvertibleCurrency } from "@/lib/currency";
import type { Order, OrderCurrencyRef, OrderStatus } from "@/lib/api/orders";

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending_payment: "Pago pendiente",
  payment_submitted: "Comprobante enviado",
  paid: "Pagado",
  preparing: "En preparación",
  shipped: "Enviado",
  delivered: "Entregado",
  cancelled: "Cancelado",
};

/**
 * OrderResource's currency refs only carry {id, code, symbol} — unlike the
 * full Currency resource used elsewhere in the storefront, they don't
 * include decimal_places. All amounts on this page are already frozen by
 * the backend, so we only need formatCurrency (never convertPrice, which
 * would incorrectly reapply the *current* live exchange rate instead of the
 * one that was frozen at order creation time).
 */
function toDisplayCurrency(ref: OrderCurrencyRef): ConvertibleCurrency {
  return { code: ref.code, symbol: ref.symbol, decimal_places: 2, rate: null };
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat("es-VE", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(iso));
}

export function OrderSummary({ order }: { order: Order }) {
  const paymentCurrency = toDisplayCurrency(order.payment_currency);
  const baseCurrency = toDisplayCurrency(order.base_currency);
  const showBaseAmount = order.base_currency.code !== order.payment_currency.code;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">Pedido {order.order_number}</h1>
          <p className="text-sm text-muted-foreground">Creado el {formatDate(order.created_at)}</p>
        </div>
        <Badge variant="secondary">{STATUS_LABELS[order.status] ?? order.status}</Badge>
      </div>

      {order.reservation_expires_at ? (
        <p className="rounded-md border bg-muted/50 p-3 text-sm">
          Tu stock está reservado hasta el <strong>{formatDate(order.reservation_expires_at)}</strong>.
          Completá el pago antes de esa fecha para no perder la reserva.
        </p>
      ) : null}

      <div className="grid gap-6 sm:grid-cols-2">
        <section className="space-y-1">
          <h2 className="font-semibold">Datos del cliente</h2>
          <p className="text-sm">{order.customer_name}</p>
          <p className="text-sm text-muted-foreground">{order.customer_phone}</p>
          <p className="text-sm text-muted-foreground">
            {order.document_type}-{order.document_number}
          </p>
        </section>

        <section className="space-y-1">
          <h2 className="font-semibold">Dirección de entrega</h2>
          <p className="text-sm text-muted-foreground">
            {[order.address.parish?.name, order.address.municipality?.name, order.address.state?.name]
              .filter(Boolean)
              .join(", ")}
          </p>
          <p className="text-sm text-muted-foreground">{order.address.address_reference}</p>
        </section>
      </div>

      <section>
        <h2 className="mb-2 font-semibold">Productos</h2>
        <ul className="divide-y rounded-lg border">
          {order.items.map((item, index) => (
            <li key={index} className="flex items-center justify-between gap-4 p-3 text-sm">
              <div>
                <p className="font-medium">{item.product_name}</p>
                {item.variant_description ? (
                  <p className="text-xs text-muted-foreground">{item.variant_description}</p>
                ) : null}
                <p className="text-xs text-muted-foreground">
                  {item.quantity} × {formatCurrency(parseFloat(item.unit_price), paymentCurrency)}
                </p>
              </div>
              <span className="font-medium">
                {formatCurrency(parseFloat(item.subtotal), paymentCurrency)}
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section className="ml-auto max-w-xs space-y-1 text-sm">
        {showBaseAmount ? (
          <div className="flex justify-between text-muted-foreground">
            <span>Total ({order.base_currency.code})</span>
            <span>{formatCurrency(parseFloat(order.base_amount), baseCurrency)}</span>
          </div>
        ) : null}
        {showBaseAmount ? (
          <div className="flex justify-between text-muted-foreground">
            <span>Tasa aplicada</span>
            <span>{order.exchange_rate_applied}</span>
          </div>
        ) : null}
        <div className="flex justify-between border-t pt-1 text-base font-semibold">
          <span>Total a pagar ({order.payment_currency.code})</span>
          <span>{formatCurrency(parseFloat(order.payment_amount), paymentCurrency)}</span>
        </div>
      </section>
    </div>
  );
}
