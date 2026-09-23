import { z } from "zod";

/**
 * Mirrors CategoryStoreRequest / CategoryUpdateRequest. `slug` is optional
 * client-side on purpose — blank means "let the backend derive it from the
 * name" (create) or "leave it unchanged" (edit); it is never required here.
 */
export const categorySchema = z.object({
  name: z.string().min(1, "El nombre es obligatorio.").max(255),
  slug: z.string().max(255, "La URL no puede superar los 255 caracteres."),
  parentId: z.number().nullable(),
  description: z.string().max(2000, "La descripción no puede superar los 2000 caracteres."),
});

export type CategoryValues = z.infer<typeof categorySchema>;

/** Matches the backend's `decimal:0,6` rule — up to 6 decimal places, no sign. */
const BASE_PRICE_REGEX = /^\d+(\.\d{1,6})?$/;

/**
 * Mirrors ProductStoreRequest / ProductUpdateRequest. `basePrice` is kept as a
 * string, not coerced to a number: RHF's <Input> already hands back a string,
 * and `base_price` allows up to 999999999999 with 6 decimals — well past
 * JS's safe-integer/float precision for round-tripping through a JS `number`.
 * A regex-validated string is sent to the backend as-is (Laravel's `numeric`
 * rule accepts numeric strings), so there's no precision loss in either
 * direction. `slug` is optional client-side on purpose, same reasoning as
 * `categorySchema.slug` — blank means "derive it" (create) or "leave it
 * unchanged" (edit), never a client-side requirement.
 */
export const productSchema = z.object({
  name: z.string().min(1, "El nombre es obligatorio.").max(255),
  slug: z.string().max(255, "La URL no puede superar los 255 caracteres."),
  categoryId: z.number().nullable(),
  description: z.string().max(5000, "La descripción no puede superar los 5000 caracteres."),
  basePrice: z
    .string()
    .min(1, "El precio es obligatorio.")
    .regex(BASE_PRICE_REGEX, "Ingresa un precio válido (hasta 6 decimales).")
    .refine((value) => Number(value) <= 999999999999, "El precio es demasiado alto."),
  isActive: z.boolean(),
});

export type ProductValues = z.infer<typeof productSchema>;
