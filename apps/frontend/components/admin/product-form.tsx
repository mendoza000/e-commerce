"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon } from "lucide-react";
import { toast } from "sonner";
import { fieldErrorsOf } from "@/lib/api/admin/client";
import { ApiError } from "@/lib/api/client";
import type { AdminProductCategoryRef } from "@/lib/api/admin/products";
import { productSchema, type ProductValues } from "@/lib/schemas/admin-catalog";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/** Maps backend field names to the RHF field names of productSchema — same convention as categories-manager.tsx's BACKEND_TO_FORM_FIELD. */
const BACKEND_TO_FORM_FIELD: Record<string, keyof ProductValues> = {
  name: "name",
  slug: "slug",
  category_id: "categoryId",
  description: "description",
  base_price: "basePrice",
  is_active: "isActive",
};

/** Sentinel select value standing in for "no category" (Base UI Select needs a string), same convention as categories-manager.tsx's ROOT_VALUE. */
const NO_CATEGORY_VALUE = "__none__";

export const EMPTY_PRODUCT_FORM: ProductValues = {
  name: "",
  slug: "",
  categoryId: null,
  description: "",
  basePrice: "",
  isActive: true,
};

interface ProductFormProps {
  defaultValues: ProductValues;
  /** Must throw (an ApiError, ideally) on failure — this component maps a 422's field errors onto the form and shows a toast fallback for anything unmapped. Do the success side effects (toast, close, reload/setState) at the call site, same convention as categories-manager.tsx's onSubmit. */
  onSubmit: (values: ProductValues) => Promise<unknown>;
  submitLabel: string;
  categories: AdminProductCategoryRef[];
  onCancel?: () => void;
}

/**
 * Shared name/slug/category/description/base_price/is_active form, used both
 * by products-list.tsx's inline create toggle and product-detail.tsx's
 * General tab in edit mode — written once so the two flows can't drift on
 * which fields exist.
 */
export function ProductForm({
  defaultValues,
  onSubmit,
  submitLabel,
  categories,
  onCancel,
}: ProductFormProps) {
  const form = useForm<ProductValues>({
    resolver: zodResolver(productSchema),
    defaultValues,
  });

  async function handleSubmit(values: ProductValues) {
    try {
      await onSubmit(values);
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
        toast.error(error instanceof ApiError ? error.message : "No pudimos guardar el producto.");
      }
    }
  }

  const submitting = form.formState.isSubmitting;

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="name"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Nombre</FormLabel>
                <FormControl>
                  <Input placeholder="Nombre del producto" {...field} />
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
            name="categoryId"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Categoría (opcional)</FormLabel>
                <Select
                  value={field.value === null ? NO_CATEGORY_VALUE : String(field.value)}
                  onValueChange={(value) =>
                    field.onChange(!value || value === NO_CATEGORY_VALUE ? null : Number(value))
                  }
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue>
                        {(value: string | null) =>
                          categories.find((category) => String(category.id) === value)?.name ??
                          "Sin categoría"
                        }
                      </SelectValue>
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value={NO_CATEGORY_VALUE}>Sin categoría</SelectItem>
                    {categories.map((category) => (
                      <SelectItem key={category.id} value={String(category.id)}>
                        {category.name}
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
            name="basePrice"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Precio base</FormLabel>
                <FormControl>
                  <Input inputMode="decimal" placeholder="0.00" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="description"
            render={({ field }) => (
              <FormItem className="sm:col-span-2">
                <FormLabel>Descripción (opcional)</FormLabel>
                <FormControl>
                  <Textarea placeholder="Descripción del producto" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="isActive"
            render={({ field }) => (
              <FormItem className="flex flex-row items-center gap-2 sm:col-span-2">
                <FormControl>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={(event) => field.onChange(event.target.checked)}
                    className="h-4 w-4 rounded border-input"
                  />
                </FormControl>
                <FormLabel className="!mt-0">Producto activo</FormLabel>
              </FormItem>
            )}
          />
        </div>

        <div className="flex gap-2">
          <Button type="submit" disabled={submitting}>
            {submitting ? <Loader2Icon className="animate-spin" /> : null}
            {submitLabel}
          </Button>
          {onCancel ? (
            <Button type="button" variant="ghost" onClick={onCancel}>
              Cancelar
            </Button>
          ) : null}
        </div>
      </form>
    </Form>
  );
}
