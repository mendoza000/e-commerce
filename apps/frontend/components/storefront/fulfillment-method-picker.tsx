"use client";

import { useEffect, useState } from "react";
import { useWatch, type UseFormReturn } from "react-hook-form";
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { formatCurrency } from "@/lib/currency";
import { getFulfillmentMethods, type FulfillmentMethod } from "@/lib/api/fulfillment-methods";
import type { CheckoutFormValues } from "@/lib/schemas/checkout";

/**
 * Renders the cost badge for one method. `isLoading` (a refetch in flight)
 * is kept separate from `method.estimated_cost === null` ("a coordinar") —
 * conflating them would flash "a coordinar" while a price is still loading.
 */
function renderCostLabel(method: FulfillmentMethod, isLoading: boolean, hasState: boolean): string {
  if (isLoading) return "Calculando...";
  if (!hasState) return "";
  if (method.estimated_cost === null) return "A coordinar";
  if (parseFloat(method.estimated_cost) === 0) return "Gratis";
  if (!method.currency) return method.estimated_cost;
  return formatCurrency(parseFloat(method.estimated_cost), { ...method.currency, rate: null });
}

export function FulfillmentMethodPicker({
  form,
  onSelectedMethodChange,
}: {
  form: UseFormReturn<CheckoutFormValues>;
  onSelectedMethodChange?: (method: FulfillmentMethod | null) => void;
}) {
  // form.watch() only re-renders the component that also owns a FormField
  // subscription for that name; this component has neither, so it must use
  // useWatch (the standalone reactive hook) to actually react when a sibling
  // component (AddressSelects) changes these fields via form.setValue().
  const stateId = useWatch({ control: form.control, name: "stateId" });
  const municipalityId = useWatch({ control: form.control, name: "municipalityId" });
  const fulfillmentMethodId = useWatch({ control: form.control, name: "fulfillmentMethodId" });
  const requestKey = `${stateId}|${municipalityId}`;

  // `result` is only set once a fetch for `requestKey` completes, so
  // `isLoading` (a mismatch between the last completed key and the current
  // one) is derived during render instead of toggled via a setState call at
  // the top of the effect — avoids cascading-render churn on every keystroke
  // of a dependent field.
  const [result, setResult] = useState<{ key: string; methods: FulfillmentMethod[] } | null>(null);
  const fetched = result !== null;
  const isLoading = result === null || result.key !== requestKey;
  const methods = result?.methods ?? [];

  useEffect(() => {
    let cancelled = false;

    const stateNum = stateId ? Number(stateId) : undefined;
    const municipalityNum = stateNum && municipalityId ? Number(municipalityId) : undefined;

    getFulfillmentMethods(stateNum, municipalityNum)
      .then((data) => {
        if (!cancelled) setResult({ key: requestKey, methods: data });
      })
      .catch(() => {
        // Shipping cost preview is an enhancement, not a requirement — a
        // failed fetch must never block checkout submission, so we just
        // hide the section instead of surfacing an error.
        if (!cancelled) setResult({ key: requestKey, methods: [] });
      });

    return () => {
      cancelled = true;
    };
  }, [stateId, municipalityId, requestKey]);

  useEffect(() => {
    if (!onSelectedMethodChange) return;
    const selected = result?.methods.find((method) => String(method.id) === fulfillmentMethodId) ?? null;
    onSelectedMethodChange(selected);
  }, [result, fulfillmentMethodId, onSelectedMethodChange]);

  if (!fetched) {
    return isLoading ? (
      <p className="text-sm text-muted-foreground">Cargando métodos de envío...</p>
    ) : null;
  }

  if (methods.length === 0) {
    return null;
  }

  return (
    <FormField
      control={form.control}
      name="fulfillmentMethodId"
      render={({ field }) => (
        <FormItem>
          <FormLabel>Método de envío (opcional)</FormLabel>
          <FormControl>
            <RadioGroup
              value={field.value ?? ""}
              onValueChange={(value) => field.onChange(value ? String(value) : "")}
            >
              {methods.map((method) => {
                const inputId = `fulfillment-method-${method.id}`;

                return (
                  <label
                    key={method.id}
                    htmlFor={inputId}
                    className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition-colors has-[[data-checked]]:border-primary has-[[data-checked]]:bg-primary/5"
                  >
                    <RadioGroupItem value={String(method.id)} id={inputId} className="mt-0.5" />
                    <span className="flex flex-1 items-center justify-between gap-2 font-medium">
                      <span>{method.label}</span>
                      <span className="text-xs font-normal text-muted-foreground">
                        {renderCostLabel(method, isLoading, Boolean(stateId))}
                      </span>
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
