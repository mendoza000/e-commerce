import { z } from "zod";

/** Must match the backend's OrderStoreRequest regex exactly (see OrderStoreRequest::rules). */
const VENEZUELAN_PHONE_REGEX = /^\+58\d{10}$/;

export const checkoutSchema = z.object({
  customerName: z.string().trim().min(1, "Ingresá tu nombre completo").max(255),
  customerPhone: z
    .string()
    .trim()
    .regex(VENEZUELAN_PHONE_REGEX, "Formato esperado: +58 seguido de 10 dígitos"),
  documentType: z.enum(["V", "E", "RIF"], {
    message: "Seleccioná un tipo de documento",
  }),
  documentNumber: z.string().trim().min(1, "Ingresá tu número de documento").max(20),
  stateId: z.string().min(1, "Seleccioná un estado"),
  municipalityId: z.string().min(1, "Seleccioná un municipio"),
  parishId: z.string().min(1, "Seleccioná una parroquia"),
  addressReference: z.string().trim().min(1, "Ingresá una referencia de dirección"),
});

export type CheckoutFormValues = z.infer<typeof checkoutSchema>;
