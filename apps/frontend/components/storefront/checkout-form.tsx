"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon } from "lucide-react";
import { useCartHydrated } from "@/hooks/use-cart-hydrated";
import { useCartStore } from "@/lib/store/cart";
import { useCurrency } from "@/components/providers/currency-provider";
import { convertPrice, formatCurrency } from "@/lib/currency";
import { checkoutSchema, type CheckoutFormValues } from "@/lib/schemas/checkout";
import { createOrder, type CreateOrderPayload } from "@/lib/api/orders";
import { ApiError, type ApiErrorBody } from "@/lib/api/client";
import type { State } from "@/lib/api/locations";
import { AddressSelects } from "@/components/storefront/address-selects";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";

/** Maps backend field names (OrderStoreRequest) to the RHF field names of checkoutSchema. */
const BACKEND_TO_FORM_FIELD: Record<string, keyof CheckoutFormValues> = {
  customer_name: "customerName",
  customer_phone: "customerPhone",
  document_type: "documentType",
  document_number: "documentNumber",
  state_id: "stateId",
  municipality_id: "municipalityId",
  parish_id: "parishId",
  address_reference: "addressReference",
};

export function CheckoutForm({ initialStates }: { initialStates: State[] }) {
  const router = useRouter();
  const hydrated = useCartHydrated();
  const items = useCartStore((state) => state.items);
  const clear = useCartStore((state) => state.clear);
  const { selected: currency } = useCurrency();

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [itemErrors, setItemErrors] = useState<Record<number, string>>({});

  const form = useForm<CheckoutFormValues>({
    resolver: zodResolver(checkoutSchema),
    defaultValues: {
      customerName: "",
      customerPhone: "",
      documentType: "V",
      documentNumber: "",
      stateId: "",
      municipalityId: "",
      parishId: "",
      addressReference: "",
    },
  });

  if (!hydrated) {
    return null;
  }

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4 py-16 text-center">
        <p className="text-muted-foreground">Tu carrito está vacío.</p>
        <Button render={<Link href="/productos" />} nativeButton={false}>
          Ver productos
        </Button>
      </div>
    );
  }

  const baseTotal = items.reduce((sum, item) => sum + parseFloat(item.unitPrice) * item.quantity, 0);
  const total = currency ? convertPrice(baseTotal, currency) : null;

  async function onSubmit(values: CheckoutFormValues) {
    if (!currency) {
      setSubmitError("No hay una moneda seleccionada. Recargá la página e intentá de nuevo.");
      return;
    }

    setSubmitError(null);
    setItemErrors({});
    setSubmitting(true);

    try {
      const payload: CreateOrderPayload = {
        items: items.map((item) => ({
          product_variant_id: item.variantId,
          quantity: item.quantity,
        })),
        customer_name: values.customerName,
        customer_phone: values.customerPhone,
        document_type: values.documentType,
        document_number: values.documentNumber,
        state_id: Number(values.stateId),
        municipality_id: Number(values.municipalityId),
        parish_id: Number(values.parishId),
        address_reference: values.addressReference,
        payment_currency_id: currency.id,
      };

      const order = await createOrder(payload);

      // Pessimistic UI: only clear the cart and navigate away once the
      // backend has confirmed the order (real stock locks happened server-side).
      clear();
      router.push(
        `/pedidos/${order.order_number}?document_number=${encodeURIComponent(values.documentNumber)}`,
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as ApiErrorBody | null;
        const fields = body?.error?.fields ?? {};
        const nextItemErrors: Record<number, string> = {};
        let unmappedError = false;

        for (const [key, messages] of Object.entries(fields)) {
          const message = messages[0] ?? "Dato inválido.";
          const itemMatch = /^items\.(\d+)(\.|$)/.exec(key);

          if (itemMatch) {
            nextItemErrors[Number(itemMatch[1])] = message;
            continue;
          }

          const formField = BACKEND_TO_FORM_FIELD[key];
          if (formField) {
            form.setError(formField, { message });
          } else {
            unmappedError = true;
          }
        }

        setItemErrors(nextItemErrors);

        if (unmappedError || Object.keys(fields).length === 0) {
          setSubmitError(
            body?.error?.message ?? "No pudimos procesar tu pedido. Revisá los datos e intentá de nuevo.",
          );
        }
      } else {
        setSubmitError("No pudimos procesar tu pedido. Intentá de nuevo en un momento.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[1.5fr_1fr]">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="customerName"
              render={({ field }) => (
                <FormItem className="sm:col-span-2">
                  <FormLabel>Nombre completo</FormLabel>
                  <FormControl>
                    <Input placeholder="Nombre y apellido" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="customerPhone"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Teléfono</FormLabel>
                  <FormControl>
                    <Input placeholder="+584121234567" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-[auto_1fr] gap-2">
              <FormField
                control={form.control}
                name="documentType"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Tipo</FormLabel>
                    <Select
                      value={field.value}
                      onValueChange={(value) => field.onChange(value ?? "V")}
                    >
                      <FormControl>
                        <SelectTrigger className="w-20">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="V">V</SelectItem>
                        <SelectItem value="E">E</SelectItem>
                        <SelectItem value="RIF">RIF</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="documentNumber"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Documento</FormLabel>
                    <FormControl>
                      <Input placeholder="12345678" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          </div>

          <AddressSelects form={form} initialStates={initialStates} />

          <FormField
            control={form.control}
            name="addressReference"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Referencia de dirección</FormLabel>
                <FormControl>
                  <Textarea
                    placeholder="Calle, casa/edificio, punto de referencia..."
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          {submitError ? (
            <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
              {submitError}
            </p>
          ) : null}

          <Button type="submit" disabled={submitting} className="w-full sm:w-auto">
            {submitting ? <Loader2Icon className="animate-spin" /> : null}
            {submitting ? "Procesando..." : "Confirmar pedido"}
          </Button>
        </form>
      </Form>

      <aside className="h-fit space-y-4 rounded-lg border p-4">
        <h2 className="font-semibold">Resumen del pedido</h2>

        <ul className="space-y-3">
          {items.map((item, index) => {
            const subtotal = currency
              ? convertPrice(parseFloat(item.unitPrice) * item.quantity, currency)
              : null;
            const error = itemErrors[index];

            return (
              <li key={item.variantId} className="space-y-1 text-sm">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-medium">{item.productName}</p>
                    {item.variantDescription ? (
                      <p className="text-xs text-muted-foreground">{item.variantDescription}</p>
                    ) : null}
                    <p className="text-xs text-muted-foreground">Cantidad: {item.quantity}</p>
                  </div>
                  {subtotal !== null && currency ? (
                    <span>{formatCurrency(subtotal, currency)}</span>
                  ) : null}
                </div>
                {error ? (
                  <p className="rounded-md border border-destructive/40 bg-destructive/10 p-2 text-xs text-destructive">
                    {error}
                  </p>
                ) : null}
              </li>
            );
          })}
        </ul>

        <div className="flex items-center justify-between border-t pt-3 font-semibold">
          <span>Total</span>
          {total !== null && currency ? <span>{formatCurrency(total, currency)}</span> : null}
        </div>
      </aside>
    </div>
  );
}
