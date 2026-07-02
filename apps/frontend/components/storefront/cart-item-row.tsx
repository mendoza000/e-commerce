"use client";

import { useCurrency } from "@/components/providers/currency-provider";
import { convertPrice, formatCurrency } from "@/lib/currency";
import { Button } from "@/components/ui/button";
import { MinusIcon, PlusIcon, XIcon } from "lucide-react";
import type { CartItem } from "@/lib/store/cart";

export function CartItemRow({
  item,
  onUpdateQuantity,
  onRemove,
}: {
  item: CartItem;
  onUpdateQuantity: (quantity: number) => void;
  onRemove: () => void;
}) {
  const { selected: currency } = useCurrency();

  const unitPrice = currency ? convertPrice(item.unitPrice, currency) : null;
  const subtotal = currency
    ? convertPrice(parseFloat(item.unitPrice) * item.quantity, currency)
    : null;

  return (
    <div className="flex gap-3 border-b pb-4">
      <div className="size-16 shrink-0 overflow-hidden rounded-md bg-muted">
        {item.image ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={item.image} alt={item.productName} className="h-full w-full object-cover" />
        ) : null}
      </div>

      <div className="flex flex-1 flex-col gap-1">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="line-clamp-2 text-sm font-medium">{item.productName}</p>
            {item.variantDescription ? (
              <p className="text-xs text-muted-foreground">{item.variantDescription}</p>
            ) : null}
          </div>
          <Button
            variant="ghost"
            size="icon-sm"
            aria-label="Quitar del carrito"
            onClick={onRemove}
          >
            <XIcon />
          </Button>
        </div>

        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="icon-sm"
              aria-label="Restar cantidad"
              onClick={() => onUpdateQuantity(item.quantity - 1)}
            >
              <MinusIcon />
            </Button>
            <span className="w-6 text-center text-sm">{item.quantity}</span>
            <Button
              variant="outline"
              size="icon-sm"
              aria-label="Sumar cantidad"
              onClick={() => onUpdateQuantity(item.quantity + 1)}
            >
              <PlusIcon />
            </Button>
          </div>

          <div className="text-right text-sm">
            {unitPrice !== null && currency ? (
              <p className="text-muted-foreground">{formatCurrency(unitPrice, currency)} c/u</p>
            ) : null}
            {subtotal !== null && currency ? (
              <p className="font-medium">{formatCurrency(subtotal, currency)}</p>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}
