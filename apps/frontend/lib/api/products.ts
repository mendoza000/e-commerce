import { apiFetch, ApiError } from "./client";

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ProductCategoryRef {
  id: number;
  name: string;
  slug: string;
}

export interface ProductImage {
  id: number;
  path: string;
  url: string;
  position: number;
  is_primary: boolean;
  product_option_value_id: number | null;
}

export interface ProductListItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: string;
  is_active: boolean;
  category: ProductCategoryRef | null;
  primary_image: ProductImage | null;
}

export interface ListProductsParams {
  category?: string;
  search?: string;
  page?: number;
}

export async function getProducts(
  params: ListProductsParams = {},
): Promise<PaginatedResponse<ProductListItem>> {
  const query = new URLSearchParams();
  if (params.category) query.set("category", params.category);
  if (params.search) query.set("search", params.search);
  if (params.page) query.set("page", String(params.page));

  const qs = query.toString();
  return apiFetch<PaginatedResponse<ProductListItem>>(`/api/products${qs ? `?${qs}` : ""}`);
}

export interface ProductOptionValue {
  id: number;
  value: string;
  position: number;
}

export interface ProductOption {
  id: number;
  name: string;
  position: number;
  values: ProductOptionValue[];
}

export interface ProductVariant {
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

export interface ProductDetail {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: string;
  is_active: boolean;
  category: ProductCategoryRef | null;
  options: ProductOption[];
  variants: ProductVariant[];
  images: ProductImage[];
}

export async function getProductBySlug(slug: string): Promise<ProductDetail | null> {
  try {
    const res = await apiFetch<{ data: ProductDetail }>(`/api/products/${slug}`);
    return res.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }
    throw error;
  }
}
