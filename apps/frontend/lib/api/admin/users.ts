import { adminFetch } from "@/lib/api/admin/client";
import type { AdminRole, AdminUser } from "@/lib/api/admin/auth";

interface Paginated<T> {
  data: T[];
}

interface Wrapped<T> {
  data: T;
}

export interface CreateUserPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: AdminRole;
}

export interface UpdateUserPayload {
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
  role?: AdminRole;
}

export async function listUsers(baseUrl: string, signal?: AbortSignal): Promise<AdminUser[]> {
  const { data } = await adminFetch<Paginated<AdminUser>>(baseUrl, "/users", { signal });

  return data;
}

export async function createUser(
  baseUrl: string,
  payload: CreateUserPayload,
): Promise<AdminUser> {
  const { data } = await adminFetch<Wrapped<AdminUser>>(baseUrl, "/users", {
    method: "POST",
    body: payload,
  });

  return data;
}

export async function updateUser(
  baseUrl: string,
  id: number,
  payload: UpdateUserPayload,
): Promise<AdminUser> {
  const { data } = await adminFetch<Wrapped<AdminUser>>(baseUrl, `/users/${id}`, {
    method: "PATCH",
    body: payload,
  });

  return data;
}

export async function setUserActive(
  baseUrl: string,
  id: number,
  active: boolean,
): Promise<AdminUser> {
  const { data } = await adminFetch<Wrapped<AdminUser>>(
    baseUrl,
    `/users/${id}/${active ? "activate" : "deactivate"}`,
    { method: "POST" },
  );

  return data;
}
