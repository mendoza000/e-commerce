import { adminFetch } from "@/lib/api/admin/client";

export type AdminRole = "owner" | "staff";

/**
 * Mirrors UserResource on the backend. `permissions` is a UI hint so the panel
 * can hide what this account cannot use — the backend still enforces every one
 * of them on its own.
 */
export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: AdminRole;
  is_active: boolean;
  permissions: {
    manage_settings: boolean;
    manage_catalog: boolean;
    manage_users: boolean;
    manage_orders: boolean;
  };
  created_at: string | null;
}

interface Wrapped<T> {
  data: T;
}

export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

export async function login(
  baseUrl: string,
  credentials: LoginCredentials,
): Promise<AdminUser> {
  const { data } = await adminFetch<Wrapped<AdminUser>>(baseUrl, "/login", {
    method: "POST",
    body: credentials,
  });

  return data;
}

export async function logout(baseUrl: string): Promise<void> {
  await adminFetch<null>(baseUrl, "/logout", { method: "POST" });
}

export async function me(baseUrl: string, signal?: AbortSignal): Promise<AdminUser> {
  const { data } = await adminFetch<Wrapped<AdminUser>>(baseUrl, "/me", { signal });

  return data;
}
