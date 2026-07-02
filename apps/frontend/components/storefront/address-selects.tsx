"use client";

import { useState } from "react";
import type { UseFormReturn } from "react-hook-form";
import { getMunicipalities, getParishes } from "@/lib/api/locations";
import type { Municipality, Parish, State } from "@/lib/api/locations";
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { CheckoutFormValues } from "@/lib/schemas/checkout";

export function AddressSelects({
  form,
  initialStates,
}: {
  form: UseFormReturn<CheckoutFormValues>;
  initialStates: State[];
}) {
  const [municipalities, setMunicipalities] = useState<Municipality[]>([]);
  const [parishes, setParishes] = useState<Parish[]>([]);
  const [loadingMunicipalities, setLoadingMunicipalities] = useState(false);
  const [loadingParishes, setLoadingParishes] = useState(false);

  const stateId = form.watch("stateId");
  const municipalityId = form.watch("municipalityId");

  async function handleStateChange(value: string | null) {
    form.setValue("stateId", value ?? "", { shouldValidate: true });
    form.setValue("municipalityId", "", { shouldValidate: false });
    form.setValue("parishId", "", { shouldValidate: false });
    setMunicipalities([]);
    setParishes([]);

    if (!value) {
      return;
    }

    setLoadingMunicipalities(true);
    try {
      setMunicipalities(await getMunicipalities(Number(value)));
    } finally {
      setLoadingMunicipalities(false);
    }
  }

  async function handleMunicipalityChange(value: string | null) {
    form.setValue("municipalityId", value ?? "", { shouldValidate: true });
    form.setValue("parishId", "", { shouldValidate: false });
    setParishes([]);

    if (!value) {
      return;
    }

    setLoadingParishes(true);
    try {
      setParishes(await getParishes(Number(value)));
    } finally {
      setLoadingParishes(false);
    }
  }

  return (
    <div className="grid gap-4 sm:grid-cols-3">
      <FormField
        control={form.control}
        name="stateId"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Estado</FormLabel>
            <Select value={field.value || null} onValueChange={handleStateChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue>
                    {(value: string | null) =>
                      initialStates.find((state) => String(state.id) === value)?.name ??
                      "Selecciona un estado"
                    }
                  </SelectValue>
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {initialStates.map((state) => (
                  <SelectItem key={state.id} value={String(state.id)}>
                    {state.name}
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
        name="municipalityId"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Municipio</FormLabel>
            <Select
              value={field.value || null}
              onValueChange={handleMunicipalityChange}
              disabled={!stateId || loadingMunicipalities}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue>
                    {(value: string | null) =>
                      municipalities.find((municipality) => String(municipality.id) === value)?.name ??
                      (loadingMunicipalities ? "Cargando..." : "Selecciona un municipio")
                    }
                  </SelectValue>
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {municipalities.map((municipality) => (
                  <SelectItem key={municipality.id} value={String(municipality.id)}>
                    {municipality.name}
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
        name="parishId"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Parroquia</FormLabel>
            <Select
              value={field.value || null}
              onValueChange={(value) =>
                form.setValue("parishId", value ?? "", { shouldValidate: true })
              }
              disabled={!municipalityId || loadingParishes}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue>
                    {(value: string | null) =>
                      parishes.find((parish) => String(parish.id) === value)?.name ??
                      (loadingParishes ? "Cargando..." : "Selecciona una parroquia")
                    }
                  </SelectValue>
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {parishes.map((parish) => (
                  <SelectItem key={parish.id} value={String(parish.id)}>
                    {parish.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  );
}
