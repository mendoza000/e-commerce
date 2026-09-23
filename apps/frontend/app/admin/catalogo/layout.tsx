"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { RequireAdmin } from "@/components/admin/require-admin";
import { AdminShell } from "@/components/admin/admin-shell";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/** First nested admin sub-nav — Pedidos and Usuarios are flat, single-route sections. */
const SUB_NAV = [
  { href: "/admin/catalogo/categorias", label: "Categorías", ready: true },
  // Built in M6 — kept visible now, same not-ready styling admin-shell.tsx
  // uses for a top-level section that isn't linkable yet.
  { href: "/admin/catalogo/productos", label: "Productos", ready: false },
];

export default function CatalogLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  return (
    <RequireAdmin permission="manage_catalog">
      <AdminShell>
        <div className="space-y-6">
          <nav className="flex flex-wrap items-center gap-1 border-b pb-3">
            {SUB_NAV.map(({ href, label, ready }) =>
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
                  {label}
                </Button>
              ),
            )}
          </nav>

          {children}
        </div>
      </AdminShell>
    </RequireAdmin>
  );
}
