"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Loader2Icon, LockIcon } from "lucide-react";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import type { AdminUser } from "@/lib/api/admin/auth";

type Permission = keyof AdminUser["permissions"];

/**
 * Gate for every admin page. It keeps people out of screens they cannot use,
 * but it is a convenience, not the security boundary: the backend rejects the
 * same requests with 401/403 whatever this component decides.
 */
export function RequireAdmin({
  permission,
  children,
}: {
  permission?: Permission;
  children: React.ReactNode;
}) {
  const { user, status } = useAdminSession();
  const router = useRouter();

  useEffect(() => {
    if (status === "guest") {
      router.replace("/admin/login");
    }
  }, [status, router]);

  if (status === "loading") {
    return (
      <div className="flex min-h-64 items-center justify-center text-muted-foreground">
        <Loader2Icon className="size-5 animate-spin" />
      </div>
    );
  }

  if (status === "guest" || user === null) {
    return null;
  }

  if (permission && !user.permissions[permission]) {
    return (
      <div className="mx-auto flex max-w-md flex-col items-center gap-3 py-16 text-center">
        <LockIcon className="size-8 text-muted-foreground" />
        <h1 className="text-lg font-semibold">Sin acceso</h1>
        <p className="text-sm text-muted-foreground">
          Tu cuenta de {user.role === "staff" ? "operador" : "administrador"} no tiene permisos
          para esta sección. Pídele acceso al dueño de la tienda.
        </p>
      </div>
    );
  }

  return <>{children}</>;
}
