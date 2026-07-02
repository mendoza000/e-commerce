import { apiFetch } from "./client";

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  children: Category[];
}

export async function getCategories(): Promise<Category[]> {
  const res = await apiFetch<{ data: Category[] }>("/api/categories");
  return res.data;
}
