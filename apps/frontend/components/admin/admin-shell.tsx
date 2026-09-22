"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState } from "react";
import { LogOutIcon, PackageIcon, SettingsIcon, ShoppingBagIcon, UsersIcon } from "lucide-react";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import type { AdminUser } from "@/lib/api/admin/auth";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type Permission = keyof AdminUser["permissions"];

interface NavItem {
  href: string;
  label: string;
  permission: Permission;
  icon: typeof UsersIcon;
  /** Sections whose screens land in phases 5b–5d are shown, but not linkable. */
  ready: boolean;
}

/**
 * Sections are declared with the permission they need so the nav and the page
 * guards agree on one rule (see RequireAdmin).
 */
const NAV: NavItem[] = [
  { href: "/admin/pedidos", label: "Pedidos", permission: "manage_orders", icon: ShoppingBagIcon, ready: false },
  { href: "/admin/catalogo", label: "Catálogo", permission: "manage_catalog", icon: PackageIcon, ready: false },
  { href: "/admin/usuarios", label: "Usuarios", permission: "manage_users", icon: UsersIcon, ready: true },
  { href: "/admin/configuracion", label: "Configuración", permission: "manage_settings", icon: SettingsIcon, ready: false },
];

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAdminSession();
  const pathname = usePathname();
  const router = useRouter();
  const [signingOut, setSigningOut] = useState(false);

  if (user === null) {
    return <>{children}</>;
  }

  const sections = NAV.filter((item) => user.permissions[item.permission]);

  async function handleLogout() {
    setSigningOut(true);
    await logout();
    router.replace("/admin/login");
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b">
        <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
          <Link href="/admin" className="font-semibold">
            Panel
          </Link>

          <nav className="flex flex-1 flex-wrap items-center gap-1">
            {sections.map(({ href, label, icon: Icon, ready }) =>
              ready ? (
                <Button
                  key={href}
                  variant="ghost"
                  size="sm"
                  nativeButton={false}
                  render={<Link href={href} />}
                  className={cn(
                    "text-muted-foreground",
                    pathname.startsWith(href) && "bg-muted text-foreground",
                  )}
                >
                  <Icon />
                  {label}
                </Button>
              ) : (
                <Button
                  key={href}
                  variant="ghost"
                  size="sm"
                  disabled
                  title="Disponible en una fase siguiente."
                  className="text-muted-foreground"
                >
                  <Icon />
                  {label}
                </Button>
              ),
            )}
          </nav>

          <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">{user.name}</span>
            <Badge variant={user.role === "owner" ? "default" : "secondary"}>
              {user.role === "owner" ? "Dueño" : "Operador"}
            </Badge>
            <Button
              variant="ghost"
              size="icon-sm"
              onClick={handleLogout}
              disabled={signingOut}
              aria-label="Cerrar sesión"
            >
              <LogOutIcon />
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>
    </div>
  );
}
