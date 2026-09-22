"use client";

import { useAdminSession } from "@/components/admin/admin-session-provider";

/**
 * Landing screen of the panel. Deliberately thin: the real dashboard content
 * arrives with the sections of phases 5b–5d.
 */
export function AdminHome() {
  const { user } = useAdminSession();

  if (user === null) {
    return null;
  }

  const pending = [
    { label: "Pedidos", phase: "5b", available: user.permissions.manage_orders },
    { label: "Catálogo e inventario", phase: "5c", available: user.permissions.manage_catalog },
    { label: "Configuración de la tienda", phase: "5d", available: user.permissions.manage_settings },
  ].filter((section) => section.available);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold">Hola, {user.name}</h1>
        <p className="text-sm text-muted-foreground">
          {user.role === "owner"
            ? "Tienes acceso completo a la tienda."
            : "Tu cuenta gestiona pedidos."}
        </p>
      </div>

      <div className="rounded-lg border p-4">
        <h2 className="font-medium">Secciones en construcción</h2>
        <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
          {pending.map((section) => (
            <li key={section.label}>
              {section.label} — fase {section.phase}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
