"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { ApiError } from "@/lib/api/client";
import {
  login as loginRequest,
  logout as logoutRequest,
  me as meRequest,
  type AdminUser,
  type LoginCredentials,
} from "@/lib/api/admin/auth";

type SessionStatus = "loading" | "authenticated" | "guest";

interface AdminSession {
  /** API origin as the browser reaches it, handed down from the server layout. */
  apiBaseUrl: string;
  user: AdminUser | null;
  status: SessionStatus;
  login: (credentials: LoginCredentials) => Promise<AdminUser>;
  logout: () => Promise<void>;
  /** Re-reads /me — call it after an action that may have changed the session. */
  refresh: () => Promise<void>;
}

const AdminSessionContext = createContext<AdminSession | null>(null);

export function AdminSessionProvider({
  apiBaseUrl,
  children,
}: {
  apiBaseUrl: string;
  children: React.ReactNode;
}) {
  const [user, setUser] = useState<AdminUser | null>(null);
  const [status, setStatus] = useState<SessionStatus>("loading");

  const refresh = useCallback(async () => {
    try {
      setUser(await meRequest(apiBaseUrl));
      setStatus("authenticated");
    } catch (error) {
      // A 401 is the ordinary "not logged in" answer, not a failure worth
      // surfacing. Anything else still leaves the panel unusable, so it lands
      // on the login screen all the same.
      if (!(error instanceof ApiError) || error.status !== 401) {
        console.error("No se pudo verificar la sesión de admin.", error);
      }

      setUser(null);
      setStatus("guest");
    }
  }, [apiBaseUrl]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const login = useCallback(
    async (credentials: LoginCredentials) => {
      const authenticated = await loginRequest(apiBaseUrl, credentials);

      setUser(authenticated);
      setStatus("authenticated");

      return authenticated;
    },
    [apiBaseUrl],
  );

  const logout = useCallback(async () => {
    try {
      await logoutRequest(apiBaseUrl);
    } finally {
      // Whatever the server answered, this browser is done with the session.
      setUser(null);
      setStatus("guest");
    }
  }, [apiBaseUrl]);

  const value = useMemo<AdminSession>(
    () => ({ apiBaseUrl, user, status, login, logout, refresh }),
    [apiBaseUrl, user, status, login, logout, refresh],
  );

  return <AdminSessionContext value={value}>{children}</AdminSessionContext>;
}

export function useAdminSession(): AdminSession {
  const session = useContext(AdminSessionContext);

  if (session === null) {
    throw new Error("useAdminSession debe usarse dentro de <AdminSessionProvider>.");
  }

  return session;
}
