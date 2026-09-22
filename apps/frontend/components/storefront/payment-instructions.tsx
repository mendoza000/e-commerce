import { formatCurrency, type ConvertibleCurrency } from "@/lib/currency";
import type { Order, OrderCurrencyRef } from "@/lib/api/orders";

function toDisplayCurrency(ref: OrderCurrencyRef): ConvertibleCurrency {
  return { code: ref.code, symbol: ref.symbol, decimal_places: 2, rate: null };
}

/** "bank_code" -> "Bank code" — cosmetic only, keys stay whatever the backend sends. */
function humanizeKey(key: string): string {
  const spaced = key.replace(/_/g, " ");
  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/**
 * Only meaningful while the order is still waiting for payment — once a
 * proof was submitted (or the order moved past pending_payment) these
 * instructions no longer apply. The parent (OrderSummary) decides whether to
 * mount this component at all; the check here is a defensive guard against a
 * stale prop, not the only gate.
 */
export function PaymentInstructions({ order }: { order: Order }) {
  if (order.status !== "pending_payment") {
    return null;
  }

  const paymentCurrency = toDisplayCurrency(order.payment_currency);
  const accountEntries = Object.entries(order.payment_instructions.account).filter(
    ([, value]) => value !== null,
  );

  return (
    <section className="space-y-2 rounded-lg border p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-semibold">Instrucciones de pago</h2>
        <span className="text-sm text-muted-foreground">{order.payment_method.label}</span>
      </div>
      <p className="text-sm">
        Monto a pagar:{" "}
        <strong>{formatCurrency(parseFloat(order.payment_amount), paymentCurrency)}</strong>
      </p>
      {accountEntries.length > 0 ? (
        <dl className="grid gap-1 border-t pt-2 text-sm">
          {accountEntries.map(([key, value]) => (
            <div key={key} className="flex justify-between gap-4">
              <dt className="text-muted-foreground">{humanizeKey(key)}</dt>
              <dd className="text-right font-medium">{value}</dd>
            </div>
          ))}
        </dl>
      ) : null}
      {order.payment_instructions.notes ? (
        <p className="border-t pt-2 text-sm text-muted-foreground">{order.payment_instructions.notes}</p>
      ) : null}
    </section>
  );
}
