"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { useAdminList, type AdminListFetcher } from "@/hooks/admin/use-admin-list";
import { list, type AdminOrder, type AdminOrderStatus } from "@/lib/api/admin/orders";
import { ORDER_STATUS_BADGE_VARIANT } from "@/lib/orders-status";
import { formatCurrency, toDisplayCurrency } from "@/lib/currency";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

interface OrdersFilters {
  status: AdminOrderStatus | "all";
  search: string;
}

const INITIAL_FILTERS: OrdersFilters = { status: "all", search: "" };

const STATUS_OPTIONS: Array<{ value: AdminOrderStatus | "all"; label: string }> = [
  { value: "all", label: "Todos los estados" },
  { value: "pending_payment", label: "Pendiente de pago" },
  { value: "payment_submitted", label: "Comprobante enviado" },
  { value: "paid", label: "Pagada" },
  { value: "preparing", label: "En preparación" },
  { value: "shipped", label: "Enviada" },
  { value: "delivered", label: "Entregada" },
  { value: "cancelled", label: "Cancelada" },
];

/** Module-level so its identity is stable across renders — see useAdminList's fetcher requirement. */
const fetchOrders: AdminListFetcher<AdminOrder, OrdersFilters> = (baseUrl, params, signal) =>
  list(
    baseUrl,
    {
      status: params.status === "all" ? undefined : params.status,
      search: params.search || undefined,
      page: params.page,
      per_page: 25,
    },
    signal,
  );

function formatDate(iso: string | null): string {
  if (!iso) return "—";

  return new Intl.DateTimeFormat("es-VE", { dateStyle: "medium", timeStyle: "short" }).format(
    new Date(iso),
  );
}

export function OrdersList() {
  const { apiBaseUrl } = useAdminSession();
  const { data, meta, loading, loadError, filters, setFilters, page, setPage } = useAdminList(
    fetchOrders,
    { apiBaseUrl, initialFilters: INITIAL_FILTERS },
  );

  const [searchInput, setSearchInput] = useState("");

  // Debounced so typing doesn't refetch on every keystroke — filters.search
  // (and therefore the fetch) only updates 300ms after the last change.
  useEffect(() => {
    const timeout = setTimeout(() => {
      setFilters((previous) =>
        previous.search === searchInput ? previous : { ...previous, search: searchInput },
      );
    }, 300);

    return () => clearTimeout(timeout);
  }, [searchInput, setFilters]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold">Pedidos</h1>
        <p className="text-sm text-muted-foreground">
          Revisa el estado de cada pedido y avanza su ciclo de vida.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Buscar por número, cliente, cédula o teléfono"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          className="max-w-xs"
        />

        <Select
          value={filters.status}
          onValueChange={(value) =>
            setFilters((previous) => ({
              ...previous,
              status: (value as AdminOrderStatus | "all" | null) ?? "all",
            }))
          }
        >
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {loadError ? (
        <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          {loadError}
        </p>
      ) : null}

      {loading && data === null ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : null}

      {data !== null ? (
        <>
          <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
              <thead className="border-b bg-muted/50 text-left">
                <tr>
                  <th className="px-4 py-2 font-medium">Orden</th>
                  <th className="px-4 py-2 font-medium">Cliente</th>
                  <th className="px-4 py-2 font-medium">Estado</th>
                  <th className="px-4 py-2 font-medium">Total</th>
                  <th className="px-4 py-2 font-medium">Ítems</th>
                  <th className="px-4 py-2 font-medium">Creado</th>
                  <th className="px-4 py-2" />
                </tr>
              </thead>
              <tbody>
                {data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground">
                      No hay pedidos que coincidan con estos filtros.
                    </td>
                  </tr>
                ) : (
                  data.map((order) => (
                    <tr key={order.order_number} className="border-b last:border-0">
                      <td className="px-4 py-3 font-medium">
                        <Link href={`/admin/pedidos/${order.order_number}`} className="hover:underline">
                          {order.order_number}
                        </Link>
                      </td>
                      <td className="px-4 py-3">{order.customer.name}</td>
                      <td className="px-4 py-3">
                        <Badge variant={ORDER_STATUS_BADGE_VARIANT[order.status]}>
                          {order.status_label}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        {formatCurrency(parseFloat(order.payment_amount), toDisplayCurrency(order.payment_currency))}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">{order.items_count}</td>
                      <td className="px-4 py-3 text-muted-foreground">{formatDate(order.created_at)}</td>
                      <td className="px-4 py-3 text-right">
                        <Button
                          variant="ghost"
                          size="sm"
                          nativeButton={false}
                          render={<Link href={`/admin/pedidos/${order.order_number}`} />}
                        >
                          Ver
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {meta ? (
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
              <span>{meta.total} pedidos en total</span>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page <= 1}
                  onClick={() => setPage(page - 1)}
                >
                  <ChevronLeftIcon />
                  Anterior
                </Button>
                <span>
                  Página {meta.current_page} de {meta.last_page}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setPage(page + 1)}
                >
                  Siguiente
                  <ChevronRightIcon />
                </Button>
              </div>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
