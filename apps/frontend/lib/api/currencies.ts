import { apiFetch } from "./client";

export interface Currency {
  id: number;
  code: string;
  name: string;
  symbol: string;
  decimal_places: number;
  is_base: boolean;
  rate: string | null;
  rate_effective_at: string | null;
}

export async function getCurrencies(): Promise<Currency[]> {
  const res = await apiFetch<{ data: Currency[] }>("/api/currencies");
  return res.data;
}
