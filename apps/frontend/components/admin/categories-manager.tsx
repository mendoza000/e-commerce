"use client";

import { useCallback, useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon, PlusIcon } from "lucide-react";
import { toast } from "sonner";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { ConfirmActionButton } from "@/components/admin/confirm-action-button";
import { fieldErrorsOf } from "@/lib/api/admin/client";
import { ApiError } from "@/lib/api/client";
import {
  create,
  destroy,
  list,
  update,
  type AdminCategory,
} from "@/lib/api/admin/categories";
import { categorySchema, type CategoryValues } from "@/lib/schemas/admin-catalog";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/** Maps backend field names to the RHF field names of categorySchema. */
const BACKEND_TO_FORM_FIELD: Record<string, keyof CategoryValues> = {
  name: "name",
  slug: "slug",
  parent_id: "parentId",
  description: "description",
};

const EMPTY_FORM: CategoryValues = {
  name: "",
  slug: "",
  parentId: null,
  description: "",
};

/** Sentinel select value standing in for "no parent" (Base UI Select needs a string). */
const ROOT_VALUE = "__root__";

export function CategoriesManager() {
  const { apiBaseUrl } = useAdminSession();

  const [categories, setCategories] = useState<AdminCategory[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [editing, setEditing] = useState<AdminCategory | null>(null);
  const [formOpen, setFormOpen] = useState(false);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      try {
        setCategories(await list(apiBaseUrl, signal));
        setLoadError(null);
      } catch (error) {
        if (signal?.aborted) return;
        setLoadError(
          error instanceof ApiError
            ? error.message
            : "No pudimos cargar las categorías. Intenta de nuevo.",
        );
      }
    },
    [apiBaseUrl],
  );

  useEffect(() => {
    const controller = new AbortController();
    // Fetch-on-mount with AbortController — same established pattern as
    // users-manager.tsx's load effect (see that file's known
    // react-hooks/set-state-in-effect exception).
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load(controller.signal);

    return () => controller.abort();
  }, [load]);

  const form = useForm<CategoryValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: EMPTY_FORM,
  });

  function openCreate() {
    setEditing(null);
    form.reset(EMPTY_FORM);
    setFormOpen(true);
  }

  function openEdit(target: AdminCategory) {
    setEditing(target);
    form.reset({
      name: target.name,
      slug: target.slug,
      parentId: target.parent_id,
      description: target.description ?? "",
    });
    setFormOpen(true);
  }

  async function onSubmit(values: CategoryValues) {
    try {
      if (editing) {
        await update(apiBaseUrl, editing.id, {
          name: values.name,
          parent_id: values.parentId,
          // Blank means "leave it as is" — the backend's `sometimes` rule
          // treats an absent key the same way.
          ...(values.slug ? { slug: values.slug } : {}),
          ...(values.description ? { description: values.description } : {}),
        });

        toast.success("Categoría actualizada.");
      } else {
        await create(apiBaseUrl, {
          name: values.name,
          parent_id: values.parentId,
          // Blank means "let the backend derive it from the name".
          ...(values.slug ? { slug: values.slug } : {}),
          ...(values.description ? { description: values.description } : {}),
        });

        toast.success("Categoría creada.");
      }

      setFormOpen(false);
      await load();
    } catch (error) {
      const fields = fieldErrorsOf(error);
      let mapped = false;

      for (const [key, message] of Object.entries(fields)) {
        const formField = BACKEND_TO_FORM_FIELD[key];
        if (formField) {
          form.setError(formField, { message });
          mapped = true;
        }
      }

      if (!mapped) {
        toast.error(
          error instanceof ApiError ? error.message : "No pudimos guardar la categoría.",
        );
      }
    }
  }

  async function handleDelete(target: AdminCategory) {
    try {
      await destroy(apiBaseUrl, target.id);
      toast.success("Categoría eliminada.");
      await load();
    } catch (error) {
      // The "category in use" 422 lands under a `category` key with no
      // matching form field (there's no form here to begin with) — same
      // unmapped-key fallback onSubmit uses above, just always unmapped.
      const fields = fieldErrorsOf(error);
      toast.error(
        fields.category ??
          (error instanceof ApiError ? error.message : "No pudimos eliminar la categoría."),
      );
      throw error;
    }
  }

  const submitting = form.formState.isSubmitting;
  const parentOptions = (categories ?? []).filter((category) => category.id !== editing?.id);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Categorías</h1>
          <p className="text-sm text-muted-foreground">
            Organiza el catálogo en categorías y subcategorías.
          </p>
        </div>

        <Button onClick={openCreate}>
          <PlusIcon />
          Nueva categoría
        </Button>
      </div>

      {formOpen ? (
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="space-y-4 rounded-lg border p-4"
          >
            <h2 className="font-medium">
              {editing ? `Editar ${editing.name}` : "Nueva categoría"}
            </h2>

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nombre</FormLabel>
                    <FormControl>
                      <Input placeholder="Nombre de la categoría" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="slug"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>URL (opcional)</FormLabel>
                    <FormControl>
                      <Input placeholder="Se genera del nombre si se deja vacío" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="parentId"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Categoría padre</FormLabel>
                    <Select
                      value={field.value === null ? ROOT_VALUE : String(field.value)}
                      onValueChange={(value) =>
                        field.onChange(!value || value === ROOT_VALUE ? null : Number(value))
                      }
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value={ROOT_VALUE}>Sin categoría padre (raíz)</SelectItem>
                        {parentOptions.map((option) => (
                          <SelectItem key={option.id} value={String(option.id)}>
                            {option.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="description"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Descripción (opcional)</FormLabel>
                    <FormControl>
                      <Textarea placeholder="Descripción de la categoría" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex gap-2">
              <Button type="submit" disabled={submitting}>
                {submitting ? <Loader2Icon className="animate-spin" /> : null}
                {editing ? "Guardar cambios" : "Crear categoría"}
              </Button>
              <Button type="button" variant="ghost" onClick={() => setFormOpen(false)}>
                Cancelar
              </Button>
            </div>
          </form>
        </Form>
      ) : null}

      {loadError ? (
        <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          {loadError}
        </p>
      ) : null}

      {categories === null && !loadError ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : null}

      {categories !== null ? (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/50 text-left">
              <tr>
                <th className="px-4 py-2 font-medium">Nombre</th>
                <th className="px-4 py-2 font-medium">Slug</th>
                <th className="px-4 py-2 font-medium">Productos</th>
                <th className="px-4 py-2 font-medium">Subcategorías</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {categories.map((row) => {
                const inUse = row.products_count + row.children_count > 0;

                return (
                  <tr key={row.id} className="border-b last:border-0">
                    <td className="px-4 py-3">
                      {row.name}
                      {row.parent ? (
                        <span className="ml-2 text-xs text-muted-foreground">
                          en {row.parent.name}
                        </span>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{row.slug}</td>
                    <td className="px-4 py-3">{row.products_count}</td>
                    <td className="px-4 py-3">{row.children_count}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(row)}>
                          Editar
                        </Button>
                        <ConfirmActionButton
                          trigger={{
                            label: "Eliminar",
                            variant: "destructive",
                            // The backend's own guard is the real backstop; this
                            // just avoids a round-trip for an obviously-blocked
                            // delete (same pre-emptive-disable style
                            // users-manager.tsx uses for "can't deactivate yourself").
                            disabled: inUse,
                            title: inUse
                              ? "Esta categoría tiene productos o subcategorías. Muévelos antes de eliminarla."
                              : undefined,
                          }}
                          title={`Eliminar "${row.name}"`}
                          description="Esta acción no se puede deshacer."
                          confirmLabel="Eliminar"
                          destructive
                          onConfirm={() => handleDelete(row)}
                        />
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
