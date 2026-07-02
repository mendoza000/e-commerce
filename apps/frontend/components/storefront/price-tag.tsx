"use client";

import { useCurrency } from "@/components/providers/currency-provider";
import { convertPrice, formatCurrency } from "@/lib/currency";

export function PriceTag({ basePrice }: { basePrice: string }) {
  const { selected } = useCurrency();

  if (!selected) {
    return null;
  }

  const amount = convertPrice(basePrice, selected);

  return <span>{formatCurrency(amount, selected)}</span>;
}
