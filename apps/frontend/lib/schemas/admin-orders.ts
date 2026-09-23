import { z } from "zod";

/**
 * Shared by reject-payment and cancel: both endpoints require the exact same
 * `reason` rule (`required|string|min:3|max:500`, see `RejectPaymentRequest`
 * and `CancelOrderRequest`), and the reason field itself doubles as the
 * confirmation — no separate "are you sure?" dialog needed.
 */
export const reasonSchema = z.object({
  reason: z
    .string()
    .min(3, "El motivo es demasiado corto.")
    .max(500, "El motivo es demasiado largo."),
});

export type ReasonValues = z.infer<typeof reasonSchema>;

/**
 * Only relevant when transitioning to "shipped" — `TransitionOrderRequest`
 * treats all three as optional free text and rejects them outright for any
 * other target status, which the caller (not this schema) is responsible for
 * enforcing by only rendering these fields for the "shipped" transition.
 */
export const transitionSchema = z.object({
  courier: z.string().max(255, "Máximo 255 caracteres.").optional(),
  tracking_code: z.string().max(255, "Máximo 255 caracteres.").optional(),
  note: z.string().max(1000, "Máximo 1000 caracteres.").optional(),
});

export type TransitionValues = z.infer<typeof transitionSchema>;
