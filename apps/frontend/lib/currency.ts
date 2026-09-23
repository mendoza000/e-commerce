export interface ConvertibleCurrency {
  code: string;
  symbol?: string;
  rate: string | null;
  decimal_places: number;
}

export function convertPrice(basePrice: string | number, currency: ConvertibleCurrency): number {
  const price = typeof basePrice === "string" ? parseFloat(basePrice) : basePrice;
  const rate = currency.rate ? parseFloat(currency.rate) : 1;
  const factor = 10 ** currency.decimal_places;

  return Math.round(price * rate * factor) / factor;
}

/**
 * Some resources (storefront and admin order refs) only carry {code, symbol}
 * — no decimal_places, since amounts there are already frozen server-side,
 * never reconverted. 2 is a reasonable display default across the currencies
 * this store actually uses (USD, VES, USDT, COP).
 */
export function toDisplayCurrency(ref: { code: string; symbol?: string }): ConvertibleCurrency {
  return { code: ref.code, symbol: ref.symbol, decimal_places: 2, rate: null };
}

export function formatCurrency(amount: number, currency: ConvertibleCurrency): string {
  const formatted = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: currency.decimal_places,
    maximumFractionDigits: currency.decimal_places,
  }).format(amount);

  return `${currency.symbol ?? currency.code} ${formatted}`;
}
