"use client";

import { useCurrency } from "@/components/providers/currency-provider";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export function CurrencySelector() {
  const { currencies, selected, setSelectedCode } = useCurrency();

  if (currencies.length <= 1 || !selected) {
    return null;
  }

  return (
    <Select value={selected.code} onValueChange={(code) => setSelectedCode(code as string)}>
      <SelectTrigger className="w-24" aria-label="Moneda">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {currencies.map((currency) => (
          <SelectItem key={currency.code} value={currency.code}>
            {currency.code}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
