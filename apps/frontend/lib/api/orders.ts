import { apiFetch, ApiError } from "./client";

export type DocumentType = "V" | "E" | "RIF";

export type OrderStatus =
  | "pending_payment"
  | "payment_submitted"
  | "paid"
  | "preparing"
  | "shipped"
  | "delivered"
  | "cancelled";

export interface OrderItem {
  product_name: string;
  variant_description: string;
  sku: string;
  unit_price: string;
  quantity: number;
  subtotal: string;
}

export interface OrderLocationRef {
  id: number;
  name: string;
}

export interface OrderAddress {
  state: OrderLocationRef | null;
  municipality: OrderLocationRef | null;
  parish: OrderLocationRef | null;
  address_reference: string;
}

export interface OrderCurrencyRef {
  id: number;
  code: string;
  symbol: string;
}

export interface Order {
  order_number: string;
  status: OrderStatus;
  customer_name: string;
  customer_phone: string;
  document_type: DocumentType;
  document_number: string;
  address: OrderAddress;
  base_currency: OrderCurrencyRef;
  payment_currency: OrderCurrencyRef;
  base_amount: string;
  exchange_rate_applied: string;
  payment_amount: string;
  items: OrderItem[];
  reservation_expires_at: string | null;
  created_at: string;
}

export interface CreateOrderItemPayload {
  product_variant_id: number;
  quantity: number;
}

/** Flat payload — mirrors OrderStoreRequest's field names exactly, no nested objects. */
export interface CreateOrderPayload {
  items: CreateOrderItemPayload[];
  customer_name: string;
  customer_phone: string;
  document_type: DocumentType;
  document_number: string;
  state_id: number;
  municipality_id: number;
  parish_id: number;
  address_reference: string;
  payment_currency_id: number;
}

export async function createOrder(payload: CreateOrderPayload): Promise<Order> {
  const res = await apiFetch<{ data: Order }>("/api/orders", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return res.data;
}

/**
 * Looks up an order by its public order_number. For guest (non-owner) callers,
 * the backend requires `documentNumber` to match exactly or it returns 404
 * (never 403/422, so a mismatch can't be used to probe which order numbers exist).
 */
export async function getOrderByNumber(
  orderNumber: string,
  documentNumber?: string,
): Promise<Order | null> {
  try {
    const qs = documentNumber ? `?document_number=${encodeURIComponent(documentNumber)}` : "";
    const res = await apiFetch<{ data: Order }>(`/api/orders/${orderNumber}${qs}`);
    return res.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }
    throw error;
  }
}
