"use client";

import { createContext, useContext, useMemo, useState } from "react";
import type { Currency } from "@/lib/api/currencies";

interface CurrencyContextValue {
  currencies: Currency[];
  selected: Currency | null;
  setSelectedCode: (code: string) => void;
}

const CurrencyContext = createContext<CurrencyContextValue | null>(null);

export function CurrencyProvider({
  currencies,
  children,
}: {
  currencies: Currency[];
  children: React.ReactNode;
}) {
  const base = currencies.find((c) => c.is_base) ?? currencies[0] ?? null;
  const [selectedCode, setSelectedCode] = useState<string | undefined>(base?.code);
  const selected = currencies.find((c) => c.code === selectedCode) ?? base;

  const value = useMemo<CurrencyContextValue>(
    () => ({ currencies, selected, setSelectedCode }),
    [currencies, selected],
  );

  return <CurrencyContext.Provider value={value}>{children}</CurrencyContext.Provider>;
}

export function useCurrency(): CurrencyContextValue {
  const ctx = useContext(CurrencyContext);
  if (!ctx) {
    throw new Error("useCurrency must be used within a CurrencyProvider");
  }
  return ctx;
}
