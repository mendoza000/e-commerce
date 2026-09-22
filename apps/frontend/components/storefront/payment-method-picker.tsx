"use client";

import type { UseFormReturn } from "react-hook-form";
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import type { PaymentMethod } from "@/lib/api/payment-methods";
import type { CheckoutFormValues } from "@/lib/schemas/checkout";

export function PaymentMethodPicker({
  form,
  paymentMethods,
}: {
  form: UseFormReturn<CheckoutFormValues>;
  paymentMethods: PaymentMethod[];
}) {
  return (
    <FormField
      control={form.control}
      name="paymentMethodId"
      render={({ field }) => (
        <FormItem>
          <FormLabel>Método de pago</FormLabel>
          <FormControl>
            <RadioGroup
              value={field.value}
              onValueChange={(value) => field.onChange(String(value))}
            >
              {paymentMethods.map((method) => {
                const inputId = `payment-method-${method.id}`;

                return (
                  <label
                    key={method.id}
                    htmlFor={inputId}
                    className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition-colors has-[[data-checked]]:border-primary has-[[data-checked]]:bg-primary/5"
                  >
                    <RadioGroupItem value={String(method.id)} id={inputId} className="mt-0.5" />
                    <span className="flex flex-1 flex-col gap-0.5">
                      <span className="flex items-center justify-between gap-2 font-medium">
                        <span>{method.label}</span>
                        <span className="text-xs font-normal text-muted-foreground">
                          {method.currency.code}
                        </span>
                      </span>
                      {method.requires_proof ? (
                        <span className="text-xs text-muted-foreground">
                          Requiere subir comprobante de pago.
                        </span>
                      ) : null}
                    </span>
                  </label>
                );
              })}
            </RadioGroup>
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}
