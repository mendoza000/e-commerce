import type { Metadata } from "next";
import { AdminSessionProvider } from "@/components/admin/admin-session-provider";
import { getPublicApiBaseUrl } from "@/lib/api/client";

export const metadata: Metadata = {
  title: "Panel de administración",
};

/**
 * The panel talks to the API straight from the browser, because the Sanctum
 * session cookie lives there. The one thing it cannot work out on its own is
 * where the API is, so this server layout reads it at runtime and passes it
 * down — see getPublicApiBaseUrl.
 */
export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <AdminSessionProvider apiBaseUrl={getPublicApiBaseUrl()}>
      {children}
    </AdminSessionProvider>
  );
}
