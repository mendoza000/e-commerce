import { adminFetch } from "@/lib/api/admin/client";
import type { Paginated } from "@/lib/api/admin/types";

/**
 * The backend splits `ProductResource` (list — counts, `total_stock`,
 * `primary_image`) from `ProductDetailResource` (detail — full
 * `options`/`variants`/`images`, no counts). Mirrors `lib/api/products.ts`'s
 * storefront split: two non-optional types instead of one type with
 * everything optional, so a list-only field used on a detail object (or vice
 * versa) is a compile error, not an `undefined` at runtime.
 */

export interface AdminProductCategoryRef {
  id: number;
  name: string;
  slug: string;
}

/** Reused/re-exported by M9's `product-images.ts` once that module exists. */
export interface AdminProductImage {
  id: number;
  path: string;
  url: string;
  position: number;
  is_primary: boolean;
  product_option_value_id: number | null;
}

export interface AdminProductListItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: string;
  is_active: boolean;
  is_archived: boolean;
  archived_at: string | null;
  category: AdminProductCategoryRef | null;
  variants_count: number;
  options_count: number;
  images_count: number;
  total_stock: number;
  primary_image: AdminProductImage | null;
  created_at: string | null;
  updated_at: string | null;
}

/**
 * Typed loosely/minimally on purpose — M7 (options) and M8 (variants) will
 * refine these as they build the panels that actually render them. All that
 * matters for M6 is that `AdminProductDetail` carries these three keys as
 * arrays so `product-detail.tsx`'s tab containers can pass them down without
 * a type error once M7-M9 wire in real content.
 */
export interface AdminProductOptionValueDetail {
  id: number;
  value: string;
  position: number;
}

export interface AdminProductOptionDetail {
  id: number;
  name: string;
  position: number;
  values: AdminProductOptionValueDetail[];
}

export interface AdminProductVariantDetail {
  id: number;
  sku: string;
  price_override: string | null;
  effective_price: string;
  stock: number;
  reserved_quantity: number;
  available_stock: number;
  is_active: boolean;
  option_value_ids: number[];
}

export interface AdminProductDetail {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: string;
  is_active: boolean;
  is_archived: boolean;
  archived_at: string | null;
  category: AdminProductCategoryRef | null;
  options: AdminProductOptionDetail[];
  variants: AdminProductVariantDetail[];
  images: AdminProductImage[];
  created_at: string | null;
  updated_at: string | null;
}

export interface ListProductsParams {
  search?: string;
  category_id?: number;
  status?: "active" | "inactive";
  /** Maps to the backend's `trashed=with|only` — omit for live-only. */
  trashed?: "with" | "only";
  page?: number;
  per_page?: number;
}

export interface CreateProductPayload {
  name: string;
  /** Blank/omitted means "let the backend derive it from the name". */
  slug?: string;
  category_id: number | null;
  description?: string | null;
  base_price: string;
  is_active?: boolean;
}

export interface UpdateProductPayload {
  name?: string;
  /** Unlike create, this is never re-derived from a name change — it's an explicit field. */
  slug?: string;
  category_id?: number | null;
  description?: string | null;
  base_price?: string;
  is_active?: boolean;
}

/** Only appends params with a real value — see the identical helper in `orders.ts`. */
function toQueryString(params: ListProductsParams): string {
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
  params: ListProductsParams,
  signal?: AbortSignal,
): Promise<Paginated<AdminProductListItem>> {
  return adminFetch<Paginated<AdminProductListItem>>(baseUrl, `/products${toQueryString(params)}`, {
    signal,
  });
}

/**
 * The `{id}` route binding uses `withTrashed()` — this is the only way (along
 * with `restore`) to resolve an archived product. Every other product
 * sub-route 404s on an archived id.
 */
export async function show(
  baseUrl: string,
  id: number,
  signal?: AbortSignal,
): Promise<AdminProductDetail> {
  const { data } = await adminFetch<{ data: AdminProductDetail }>(baseUrl, `/products/${id}`, {
    signal,
  });

  return data;
}

export async function create(
  baseUrl: string,
  payload: CreateProductPayload,
): Promise<AdminProductDetail> {
  const { data } = await adminFetch<{ data: AdminProductDetail }>(baseUrl, "/products", {
    method: "POST",
    body: payload,
  });

  return data;
}

export async function update(
  baseUrl: string,
  id: number,
  payload: UpdateProductPayload,
): Promise<AdminProductDetail> {
  const { data } = await adminFetch<{ data: AdminProductDetail }>(baseUrl, `/products/${id}`, {
    method: "PATCH",
    body: payload,
  });

  return data;
}

/**
 * Archives (soft-deletes) the product and all its still-live variants.
 * Unlike every other destroy in this codebase so far, this returns 200 + the
 * detail resource, not 204 — typed accordingly.
 */
export async function archive(baseUrl: string, id: number): Promise<AdminProductDetail> {
  const { data } = await adminFetch<{ data: AdminProductDetail }>(baseUrl, `/products/${id}`, {
    method: "DELETE",
  });

  return data;
}

/**
 * Also uses `withTrashed()` for its route binding. Restores the product and
 * every variant it ever had (even manually-archived ones). Idempotent.
 */
export async function restore(baseUrl: string, id: number): Promise<AdminProductDetail> {
  const { data } = await adminFetch<{ data: AdminProductDetail }>(
    baseUrl,
    `/products/${id}/restore`,
    { method: "POST" },
  );

  return data;
}
