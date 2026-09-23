"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from "lucide-react";
import { toast } from "sonner";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { useAdminList, type AdminListFetcher } from "@/hooks/admin/use-admin-list";
import { ApiError } from "@/lib/api/client";
import { list as listCategories, type AdminCategory } from "@/lib/api/admin/categories";
import {
  create,
  list,
  type AdminProductListItem,
} from "@/lib/api/admin/products";
import { EMPTY_PRODUCT_FORM, ProductForm } from "@/components/admin/product-form";
import type { ProductValues } from "@/lib/schemas/admin-catalog";
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

type StatusFilter = "all" | "active" | "inactive";
type TrashedFilter = "live" | "archived";

interface ProductsFilters {
  search: string;
  status: StatusFilter;
  trashed: TrashedFilter;
  categoryId: number | "all";
}

const INITIAL_FILTERS: ProductsFilters = {
  search: "",
  status: "all",
  trashed: "live",
  categoryId: "all",
};

const STATUS_OPTIONS: Array<{ value: StatusFilter; label: string }> = [
  { value: "all", label: "Todos los estados" },
  { value: "active", label: "Activos" },
  { value: "inactive", label: "Inactivos" },
];

const TRASHED_OPTIONS: Array<{ value: TrashedFilter; label: string }> = [
  { value: "live", label: "En catálogo" },
  { value: "archived", label: "Archivados" },
];

const ALL_CATEGORIES_VALUE = "all";

/** Module-level so its identity is stable across renders — see useAdminList's fetcher requirement. */
const fetchProducts: AdminListFetcher<AdminProductListItem, ProductsFilters> = (
  baseUrl,
  params,
  signal,
) =>
  list(
    baseUrl,
    {
      search: params.search || undefined,
      status: params.status === "all" ? undefined : params.status,
      // "live" (the default) omits `trashed` entirely — the backend only
      // understands with|only, there's no explicit "live" value.
      trashed: params.trashed === "archived" ? "only" : undefined,
      category_id: params.categoryId === "all" ? undefined : params.categoryId,
      page: params.page,
      per_page: 25,
    },
    signal,
  );

/**
 * No currency ref on this resource, unlike orders' `{code, symbol}` — the
 * real store currency is a Configuración-milestone concern (M10+, not this
 * block). Format the raw number with a fixed placeholder symbol for now.
 */
function formatBasePrice(value: string): string {
  const amount = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

  return `$${amount}`;
}

export function ProductsList() {
  const { apiBaseUrl } = useAdminSession();
  const { data, meta, loading, loadError, filters, setFilters, page, setPage, reload } =
    useAdminList(fetchProducts, { apiBaseUrl, initialFilters: INITIAL_FILTERS });

  const [searchInput, setSearchInput] = useState("");
  const [categories, setCategories] = useState<AdminCategory[] | null>(null);
  const [formOpen, setFormOpen] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    // Loaded once on mount, not paginated — same "flat category list" the
    // categories screen itself uses, just consumed here for filter/form
    // options instead of a full CRUD table.
    listCategories(apiBaseUrl, controller.signal)
      .then(setCategories)
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        toast.error(
          error instanceof ApiError ? error.message : "No pudimos cargar las categorías.",
        );
      });

    return () => controller.abort();
  }, [apiBaseUrl]);

  // Debounced so typing doesn't refetch on every keystroke — same convention
  // as orders-list.tsx.
  useEffect(() => {
    const timeout = setTimeout(() => {
      setFilters((previous) =>
        previous.search === searchInput ? previous : { ...previous, search: searchInput },
      );
    }, 300);

    return () => clearTimeout(timeout);
  }, [searchInput, setFilters]);

  async function handleCreate(values: ProductValues) {
    await create(apiBaseUrl, {
      name: values.name,
      category_id: values.categoryId,
      base_price: values.basePrice,
      is_active: values.isActive,
      ...(values.slug ? { slug: values.slug } : {}),
      ...(values.description ? { description: values.description } : {}),
    });

    toast.success("Producto creado.");
    setFormOpen(false);
    await reload();
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Productos</h1>
          <p className="text-sm text-muted-foreground">
            Administra el catálogo de productos de la tienda.
          </p>
        </div>

        <Button onClick={() => setFormOpen((open) => !open)}>
          <PlusIcon />
          Nuevo producto
        </Button>
      </div>

      {formOpen ? (
        <div className="rounded-lg border p-4">
          <h2 className="mb-4 font-medium">Nuevo producto</h2>
          <ProductForm
            defaultValues={EMPTY_PRODUCT_FORM}
            onSubmit={handleCreate}
            submitLabel="Crear producto"
            categories={categories ?? []}
            onCancel={() => setFormOpen(false)}
          />
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Buscar por nombre, URL o SKU"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          className="max-w-xs"
        />

        <Select
          value={filters.status}
          onValueChange={(value) =>
            setFilters((previous) => ({ ...previous, status: (value as StatusFilter) ?? "all" }))
          }
        >
          <SelectTrigger>
            <SelectValue>
              {(value: string | null) =>
                STATUS_OPTIONS.find((option) => option.value === value)?.label ?? "Todos los estados"
              }
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={filters.trashed}
          onValueChange={(value) =>
            setFilters((previous) => ({ ...previous, trashed: (value as TrashedFilter) ?? "live" }))
          }
        >
          <SelectTrigger>
            <SelectValue>
              {(value: string | null) =>
                TRASHED_OPTIONS.find((option) => option.value === value)?.label ?? "En catálogo"
              }
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {TRASHED_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={filters.categoryId === "all" ? ALL_CATEGORIES_VALUE : String(filters.categoryId)}
          onValueChange={(value) =>
            setFilters((previous) => ({
              ...previous,
              categoryId: !value || value === ALL_CATEGORIES_VALUE ? "all" : Number(value),
            }))
          }
        >
          <SelectTrigger>
            <SelectValue>
              {(value: string | null) =>
                value === ALL_CATEGORIES_VALUE || value === null
                  ? "Todas las categorías"
                  : ((categories ?? []).find((category) => String(category.id) === value)?.name ??
                    "Todas las categorías")
              }
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL_CATEGORIES_VALUE}>Todas las categorías</SelectItem>
            {(categories ?? []).map((category) => (
              <SelectItem key={category.id} value={String(category.id)}>
                {category.name}
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
                  <th className="px-4 py-2 font-medium">Nombre</th>
                  <th className="px-4 py-2 font-medium">Categoría</th>
                  <th className="px-4 py-2 font-medium">Precio</th>
                  <th className="px-4 py-2 font-medium">Stock total</th>
                  <th className="px-4 py-2 font-medium">Variantes</th>
                  <th className="px-4 py-2 font-medium">Estado</th>
                  <th className="px-4 py-2" />
                </tr>
              </thead>
              <tbody>
                {data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground">
                      No hay productos que coincidan con estos filtros.
                    </td>
                  </tr>
                ) : (
                  data.map((product) => (
                    <tr key={product.id} className="border-b last:border-0">
                      <td className="px-4 py-3 font-medium">
                        <Link href={`/admin/catalogo/productos/${product.id}`} className="hover:underline">
                          {product.name}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {product.category?.name ?? "—"}
                      </td>
                      <td className="px-4 py-3">{formatBasePrice(product.base_price)}</td>
                      <td className="px-4 py-3 text-muted-foreground">{product.total_stock}</td>
                      <td className="px-4 py-3 text-muted-foreground">{product.variants_count}</td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-1">
                          <Badge variant={product.is_active ? "default" : "secondary"}>
                            {product.is_active ? "Activo" : "Inactivo"}
                          </Badge>
                          {product.is_archived ? <Badge variant="destructive">Archivado</Badge> : null}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <Button
                          variant="ghost"
                          size="sm"
                          nativeButton={false}
                          render={<Link href={`/admin/catalogo/productos/${product.id}`} />}
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
              <span>{meta.total} productos en total</span>
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
