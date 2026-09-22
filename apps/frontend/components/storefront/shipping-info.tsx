import { formatCurrency, type ConvertibleCurrency } from "@/lib/currency";
import type { Order, OrderCurrencyRef } from "@/lib/api/orders";

function toDisplayCurrency(ref: OrderCurrencyRef): ConvertibleCurrency {
  return { code: ref.code, symbol: ref.symbol, decimal_places: 2, rate: null };
}

/**
 * Renders nothing when no fulfillment method was chosen at checkout (M2 made
 * it optional) — `order.fulfillment_method` is the single source of truth
 * for whether this section applies at all.
 */
export function ShippingInfo({ order }: { order: Order }) {
  if (!order.fulfillment_method) {
    return null;
  }

  const baseCurrency = toDisplayCurrency(order.base_currency);
  const hasShippingUpdate = Boolean(
    order.shipping.courier || order.shipping.tracking_code || order.shipping.note,
  );

  return (
    <section className="space-y-2 rounded-lg border p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-semibold">Envío</h2>
        <span className="text-sm text-muted-foreground">{order.fulfillment_method.label}</span>
      </div>
      <p className="text-sm">
        Costo de envío:{" "}
        <strong>
          {order.shipping_amount === null
            ? "A coordinar"
            : formatCurrency(parseFloat(order.shipping_amount), baseCurrency)}
        </strong>
      </p>
      {hasShippingUpdate ? (
        <dl className="grid gap-1 border-t pt-2 text-sm">
          {order.shipping.courier ? (
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Transportista</dt>
              <dd className="text-right font-medium">{order.shipping.courier}</dd>
            </div>
          ) : null}
          {order.shipping.tracking_code ? (
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Código de seguimiento</dt>
              <dd className="text-right font-medium">{order.shipping.tracking_code}</dd>
            </div>
          ) : null}
          {order.shipping.note ? (
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Nota</dt>
              <dd className="text-right font-medium">{order.shipping.note}</dd>
            </div>
          ) : null}
        </dl>
      ) : null}
    </section>
  );
}
