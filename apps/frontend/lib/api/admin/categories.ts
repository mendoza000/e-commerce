import { adminFetch } from "@/lib/api/admin/client";

interface Wrapped<T> {
  data: T;
}

/**
 * Mirrors Admin\CategoryResource. `children` is declared there via
 * `whenLoaded`, but no admin endpoint (`index`, `show`, `store`, `update`)
 * ever eager-loads it — every response omits the key entirely, so it is
 * typed optional rather than a guaranteed array.
 */
export interface AdminCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  parent: { id: number; name: string; slug: string } | null;
  children?: AdminCategory[];
  products_count: number;
  children_count: number;
  created_at: string | null;
}

export interface CreateCategoryPayload {
  name: string;
  slug?: string;
  parent_id: number | null;
  description?: string;
}

export interface UpdateCategoryPayload {
  name?: string;
  slug?: string;
  parent_id?: number | null;
  description?: string;
}

/** The whole tree, flat — categories don't paginate (see CategoryController::index). */
export async function list(baseUrl: string, signal?: AbortSignal): Promise<AdminCategory[]> {
  const { data } = await adminFetch<Wrapped<AdminCategory[]>>(baseUrl, "/categories", { signal });

  return data;
}

export async function create(
  baseUrl: string,
  payload: CreateCategoryPayload,
): Promise<AdminCategory> {
  const { data } = await adminFetch<Wrapped<AdminCategory>>(baseUrl, "/categories", {
    method: "POST",
    body: payload,
  });

  return data;
}

export async function update(
  baseUrl: string,
  id: number,
  payload: UpdateCategoryPayload,
): Promise<AdminCategory> {
  const { data } = await adminFetch<Wrapped<AdminCategory>>(baseUrl, `/categories/${id}`, {
    method: "PATCH",
    body: payload,
  });

  return data;
}

export async function destroy(baseUrl: string, id: number): Promise<void> {
  await adminFetch<null>(baseUrl, `/categories/${id}`, { method: "DELETE" });
}
