"use client";

import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { fieldErrorsOf } from "@/lib/api/admin/client";
import { ApiError } from "@/lib/api/client";
import { list as listCategories, type AdminCategory } from "@/lib/api/admin/categories";
import {
  archive,
  restore,
  show,
  update,
  type AdminProductDetail,
} from "@/lib/api/admin/products";
import { ProductForm } from "@/components/admin/product-form";
import type { ProductValues } from "@/lib/schemas/admin-catalog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";

function formatDate(iso: string | null): string {
  if (!iso) return "—";

  return new Intl.DateTimeFormat("es-VE", { dateStyle: "medium", timeStyle: "short" }).format(
    new Date(iso),
  );
}

export function ProductDetail({ productId }: { productId: number }) {
  const { apiBaseUrl } = useAdminSession();

  const [product, setProduct] = useState<AdminProductDetail | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [categories, setCategories] = useState<AdminCategory[] | null>(null);
  const [archiving, setArchiving] = useState(false);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      try {
        setProduct(await show(apiBaseUrl, productId, signal));
        setLoadError(null);
      } catch (error) {
        if (signal?.aborted) return;
        setLoadError(
          error instanceof ApiError ? error.message : "No pudimos cargar el producto. Intenta de nuevo.",
        );
      }
    },
    [apiBaseUrl, productId],
  );

  useEffect(() => {
    const controller = new AbortController();
    // Fetch-on-mount with AbortController — same established pattern as
    // order-detail.tsx's load effect.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load(controller.signal);

    return () => controller.abort();
  }, [load]);

  useEffect(() => {
    const controller = new AbortController();
    // Loaded once on mount for the General tab's category <Select> — same
    // flat list products-list.tsx already loads for its own filter/form.
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

  async function handleUpdate(values: ProductValues) {
    const updated = await update(apiBaseUrl, productId, {
      name: values.name,
      category_id: values.categoryId,
      base_price: values.basePrice,
      is_active: values.isActive,
      ...(values.slug ? { slug: values.slug } : {}),
      ...(values.description ? { description: values.description } : {}),
    });

    // The update endpoint returns the fresh detail resource — assign
    // directly, no reload needed (see order-detail.tsx's identical pattern
    // for endpoints that return the full resource).
    setProduct(updated);
    toast.success("Producto actualizado.");
  }

  /**
   * Reversible, no reason field — same `window.confirm` convention
   * order-detail.tsx uses for confirm-payment, instead of the
   * ConfirmActionButton dialog (which is reserved for the 5 hard deletes).
   */
  async function handleArchive() {
    if (!window.confirm("¿Archivar este producto? Sus variantes activas también se archivarán.")) {
      return;
    }

    setArchiving(true);
    try {
      setProduct(await archive(apiBaseUrl, productId));
      toast.success("Producto archivado.");
    } catch (error) {
      // hasLiveReservations() 422 lands under a `product` key with no
      // matching form field — same unmapped-key fallback categories-manager
      // uses for its own blocked-delete 422.
      const fields = fieldErrorsOf(error);
      toast.error(
        fields.product ?? (error instanceof ApiError ? error.message : "No pudimos archivar el producto."),
      );
    } finally {
      setArchiving(false);
    }
  }

  async function handleRestore() {
    if (!window.confirm("¿Restaurar este producto y todas sus variantes?")) return;

    setArchiving(true);
    try {
      setProduct(await restore(apiBaseUrl, productId));
      toast.success("Producto restaurado.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos restaurar el producto.");
    } finally {
      setArchiving(false);
    }
  }

  if (loadError) {
    return (
      <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
        {loadError}
      </p>
    );
  }

  if (product === null) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-semibold">{product.name}</h1>
          <p className="text-sm text-muted-foreground">
            Creado el {formatDate(product.created_at)}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={product.is_active ? "default" : "secondary"}>
            {product.is_active ? "Activo" : "Inactivo"}
          </Badge>
          {product.is_archived ? <Badge variant="destructive">Archivado</Badge> : null}

          {product.is_archived ? (
            <Button variant="outline" onClick={handleRestore} disabled={archiving}>
              Restaurar
            </Button>
          ) : (
            <Button variant="destructive" onClick={handleArchive} disabled={archiving}>
              Archivar
            </Button>
          )}
        </div>
      </div>

      <Tabs defaultValue="general">
        <TabsList>
          <TabsTab value="general">General</TabsTab>
          <TabsTab value="options">Opciones</TabsTab>
          <TabsTab value="variants">Variantes</TabsTab>
          <TabsTab value="images">Imágenes</TabsTab>
        </TabsList>

        <TabsPanel value="general">
          <ProductForm
            defaultValues={{
              name: product.name,
              slug: product.slug,
              categoryId: product.category?.id ?? null,
              description: product.description ?? "",
              basePrice: product.base_price,
              isActive: product.is_active,
            }}
            onSubmit={handleUpdate}
            submitLabel="Guardar cambios"
            categories={categories ?? []}
          />
        </TabsPanel>

        {/* Filled in by M7 as an additive change to this file. */}
        <TabsPanel value="options">
          <p className="text-sm text-muted-foreground">Disponible en la próxima fase.</p>
        </TabsPanel>

        {/* Filled in by M8 as an additive change to this file. */}
        <TabsPanel value="variants">
          <p className="text-sm text-muted-foreground">Disponible en la próxima fase.</p>
        </TabsPanel>

        {/* Filled in by M9 as an additive change to this file. */}
        <TabsPanel value="images">
          <p className="text-sm text-muted-foreground">Disponible en la próxima fase.</p>
        </TabsPanel>
      </Tabs>
    </div>
  );
}
