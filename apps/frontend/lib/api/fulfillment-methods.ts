import { apiFetch } from "./client";

export interface FulfillmentMethodCurrencyRef {
  id: number;
  code: string;
  symbol: string;
  decimal_places: number;
}

export interface FulfillmentMethod {
  id: number;
  type: string;
  label: string;
  requires_tracking_code: boolean;
  currency: FulfillmentMethodCurrencyRef | null;
  /**
   * Only priced (non-null) when `stateId` was passed. Null either means no
   * destination was given yet or the zone has no rate configured ("a
   * coordinar") — same value, different reason, so callers must track their
   * own loading/fetched state to tell those two apart.
   */
  estimated_cost: string | null;
}

/** `municipalityId` is only sent when `stateId` is also set (backend cross-validates it belongs to that state). */
export async function getFulfillmentMethods(
  stateId?: number,
  municipalityId?: number,
): Promise<FulfillmentMethod[]> {
  const params = new URLSearchParams();
  if (stateId) {
    params.set("state_id", String(stateId));
    if (municipalityId) {
      params.set("municipality_id", String(municipalityId));
    }
  }

  const qs = params.toString();
  const res = await apiFetch<{ data: FulfillmentMethod[] }>(
    `/api/fulfillment-methods${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}
