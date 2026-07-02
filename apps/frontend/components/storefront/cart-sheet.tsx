"use client";

import Link from "next/link";
import { ShoppingCartIcon } from "lucide-react";
import { useCurrency } from "@/components/providers/currency-provider";
import { convertPrice, formatCurrency } from "@/lib/currency";
import { useCartStore } from "@/lib/store/cart";
import { useCartHydrated } from "@/hooks/use-cart-hydrated";
import { CartItemRow } from "@/components/storefront/cart-item-row";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Sheet,
  SheetContent,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";

export function CartSheet() {
  const hydrated = useCartHydrated();
  const items = useCartStore((state) => state.items);
  const updateQuantity = useCartStore((state) => state.updateQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const clear = useCartStore((state) => state.clear);
  const { selected: currency } = useCurrency();

  const itemCount = hydrated ? items.reduce((sum, item) => sum + item.quantity, 0) : 0;
  const baseTotal = items.reduce((sum, item) => sum + parseFloat(item.unitPrice) * item.quantity, 0);
  const total = currency ? convertPrice(baseTotal, currency) : null;

  return (
    <Sheet>
      <SheetTrigger
        render={<Button variant="outline" size="icon" aria-label="Ver carrito" />}
        className="relative"
      >
        <ShoppingCartIcon />
        {itemCount > 0 ? (
          <Badge className="absolute -top-1.5 -right-1.5 h-4 min-w-4 justify-center px-1 text-[10px]">
            {itemCount}
          </Badge>
        ) : null}
      </SheetTrigger>

      <SheetContent>
        <SheetHeader>
          <SheetTitle>Carrito</SheetTitle>
        </SheetHeader>

        <div className="flex-1 space-y-4 overflow-y-auto px-4">
          {!hydrated || items.length === 0 ? (
            <p className="text-sm text-muted-foreground">Tu carrito está vacío.</p>
          ) : (
            items.map((item) => (
              <CartItemRow
                key={item.variantId}
                item={item}
                onUpdateQuantity={(quantity) => updateQuantity(item.variantId, quantity)}
                onRemove={() => removeItem(item.variantId)}
              />
            ))
          )}
        </div>

        {hydrated && items.length > 0 ? (
          <SheetFooter>
            <div className="flex items-center justify-between text-sm font-medium">
              <span>Total</span>
              {total !== null && currency ? <span>{formatCurrency(total, currency)}</span> : null}
            </div>
            <Button variant="outline" onClick={clear}>
              Vaciar carrito
            </Button>
            <Button render={<Link href="/checkout" />} nativeButton={false}>
              Ir a pagar
            </Button>
          </SheetFooter>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
