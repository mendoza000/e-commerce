import { apiFetch } from "./client";

export interface PaymentMethodCurrencyRef {
  id: number;
  code: string;
  symbol: string;
  decimal_places: number;
}

export interface PaymentMethod {
  id: number;
  type: string;
  label: string;
  requires_proof: boolean;
  currency: PaymentMethodCurrencyRef;
  instructions: Record<string, string>;
}

export async function getPaymentMethods(): Promise<PaymentMethod[]> {
  const res = await apiFetch<{ data: PaymentMethod[] }>("/api/payment-methods");
  return res.data;
}
