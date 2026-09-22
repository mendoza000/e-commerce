import { z } from "zod";

export const adminLoginSchema = z.object({
  email: z.email("Ingresa un correo válido."),
  password: z.string().min(1, "Ingresa tu contraseña."),
});

export type AdminLoginValues = z.infer<typeof adminLoginSchema>;

/**
 * Password rules mirror Laravel's `Password::defaults()` only loosely: the
 * backend is the authority and its 422 gets surfaced on the field. This is
 * here to catch the obvious mistakes before a round-trip.
 */
export const adminUserSchema = z
  .object({
    name: z.string().min(1, "El nombre es obligatorio.").max(255),
    email: z.email("Ingresa un correo válido."),
    role: z.enum(["owner", "staff"]),
    password: z.string(),
    passwordConfirmation: z.string(),
  })
  .refine((values) => values.password === values.passwordConfirmation, {
    message: "Las contraseñas no coinciden.",
    path: ["passwordConfirmation"],
  });

export type AdminUserValues = z.infer<typeof adminUserSchema>;

/** On create the password is required; on edit, blank means "leave it as is". */
export const adminUserCreateSchema = adminUserSchema.refine(
  (values) => values.password.length >= 8,
  { message: "La contraseña debe tener al menos 8 caracteres.", path: ["password"] },
);

export const adminUserEditSchema = adminUserSchema.refine(
  (values) => values.password.length === 0 || values.password.length >= 8,
  { message: "La contraseña debe tener al menos 8 caracteres.", path: ["password"] },
);
