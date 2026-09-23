import { adminFetch } from "@/lib/api/admin/client";
import type { Paginated } from "@/lib/api/admin/types";

/**
 * Field names and nesting mirror `App\Http\Resources\Admin\OrderResource`
 * exactly (apps/backend/app/Http/Resources/Admin/OrderResource.php) — the
 * list and detail endpoints share that one resource class, so both return
 * this same shape.
 */
export type AdminOrderStatus =
  | "pending_payment"
  | "payment_submitted"
  | "paid"
  | "preparing"
  | "shipped"
  | "delivered"
  | "cancelled";

export interface AdminOrderCustomer {
  name: string;
  phone: string;
  document_type: string | null;
  document_number: string;
  is_registered: boolean;
}

export interface AdminOrderAddress {
  state: string | null;
  municipality: string | null;
  parish: string | null;
  reference: string;
}

export interface AdminOrderCurrencyRef {
  code: string;
  symbol: string;
}

export interface AdminOrderPaymentMethodRef {
  id: number;
  type: string;
  label: string;
}

export interface AdminOrderFulfillmentMethodRef {
  id: number;
  type: string;
  label: string;
}

export interface AdminOrderShipping {
  courier: string | null;
  tracking_code: string | null;
  note: string | null;
}

export interface AdminOrderItem {
  product_name: string;
  variant_description: string;
  sku: string;
  unit_price: string;
  quantity: number;
  subtotal: string;
}

export interface AdminPaymentProof {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  is_image: boolean;
  reference: string | null;
  submitted_at: string | null;
  /**
   * Absolute URL from Laravel's `route()` helper (see
   * Admin\PaymentProofResource) — already ready for an `<a href>`/`<img
   * src>`, no baseUrl prefixing needed.
   */
  download_url: string;
}

export interface AdminOrderStatusHistoryEntry {
  from_status: AdminOrderStatus | null;
  from_status_label: string | null;
  to_status: AdminOrderStatus;
  to_status_label: string;
  reason: string | null;
  changed_by: { id: number; name: string } | null;
  created_at: string | null;
}

export interface AdminOrderTransitionOption {
  value: AdminOrderStatus;
  label: string;
}

/**
 * What the current status legally allows, computed server-side from the
 * `OrderStatus` state machine. The panel must gate every action button on
 * this block and never reimplement the transition graph client-side.
 */
export interface AdminOrderActions {
  can_confirm_payment: boolean;
  can_reject_payment: boolean;
  can_cancel: boolean;
  available_transitions: AdminOrderTransitionOption[];
}

export interface AdminOrder {
  order_number: string;
  status: AdminOrderStatus;
  status_label: string;
  customer: AdminOrderCustomer;
  address: AdminOrderAddress;
  base_currency: AdminOrderCurrencyRef;
  payment_currency: AdminOrderCurrencyRef;
  base_amount: string;
  exchange_rate_applied: string;
  payment_amount: string;
  payment_method: AdminOrderPaymentMethodRef | null;
  fulfillment_method: AdminOrderFulfillmentMethodRef | null;
  shipping_amount: string | null;
  shipping: AdminOrderShipping;
  items: AdminOrderItem[];
  items_count: number;
  payment_proofs: AdminPaymentProof[];
  status_history: AdminOrderStatusHistoryEntry[];
  reservation_expires_at: string | null;
  created_at: string | null;
  actions: AdminOrderActions;
}

export interface ListOrdersParams {
  status?: AdminOrderStatus;
  search?: string;
  page?: number;
  per_page?: number;
}

/** Only appends params with a real value — `adminFetch` sends the query string as-is, so an empty `status=` would ask the backend to filter on the empty string instead of "any". */
function toQueryString(params: ListOrdersParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

export async function list(
  baseUrl: string,
  params: ListOrdersParams,
  signal?: AbortSignal,
): Promise<Paginated<AdminOrder>> {
  return adminFetch<Paginated<AdminOrder>>(baseUrl, `/orders${toQueryString(params)}`, { signal });
}

export async function show(
  baseUrl: string,
  orderNumber: string,
  signal?: AbortSignal,
): Promise<AdminOrder> {
  const { data } = await adminFetch<{ data: AdminOrder }>(baseUrl, `/orders/${orderNumber}`, { signal });

  return data;
}

export async function confirmPayment(baseUrl: string, orderNumber: string): Promise<AdminOrder> {
  const { data } = await adminFetch<{ data: AdminOrder }>(
    baseUrl,
    `/orders/${orderNumber}/confirm-payment`,
    { method: "POST" },
  );

  return data;
}

export async function rejectPayment(
  baseUrl: string,
  orderNumber: string,
  reason: string,
): Promise<AdminOrder> {
  const { data } = await adminFetch<{ data: AdminOrder }>(
    baseUrl,
    `/orders/${orderNumber}/reject-payment`,
    { method: "POST", body: { reason } },
  );

  return data;
}

export async function cancel(
  baseUrl: string,
  orderNumber: string,
  reason: string,
): Promise<AdminOrder> {
  const { data } = await adminFetch<{ data: AdminOrder }>(baseUrl, `/orders/${orderNumber}/cancel`, {
    method: "POST",
    body: { reason },
  });

  return data;
}

/** Only the fulfilment statuses — see `TransitionOrderRequest`. courier/tracking_code/note only make sense (and are only accepted) when `status === "shipped"`. */
export interface TransitionPayload {
  status: "preparing" | "shipped" | "delivered";
  reason?: string;
  courier?: string;
  tracking_code?: string;
  note?: string;
}

export async function transition(
  baseUrl: string,
  orderNumber: string,
  payload: TransitionPayload,
): Promise<AdminOrder> {
  const { data } = await adminFetch<{ data: AdminOrder }>(
    baseUrl,
    `/orders/${orderNumber}/transition`,
    { method: "POST", body: payload },
  );

  return data;
}
