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
