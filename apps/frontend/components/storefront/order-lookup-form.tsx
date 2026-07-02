"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";

export function OrderLookupForm() {
  const router = useRouter();
  const [orderNumber, setOrderNumber] = useState("");
  const [documentNumber, setDocumentNumber] = useState("");

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    const trimmedOrderNumber = orderNumber.trim();
    const trimmedDocumentNumber = documentNumber.trim();

    if (!trimmedOrderNumber || !trimmedDocumentNumber) {
      return;
    }

    // Can't be a plain GET <form> like the header search: the order number is
    // a path segment here, not a querystring parameter.
    router.push(
      `/pedidos/${encodeURIComponent(trimmedOrderNumber)}?document_number=${encodeURIComponent(trimmedDocumentNumber)}`,
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="order-number">Número de pedido</Label>
        <Input
          id="order-number"
          placeholder="ORD-20260702-000001"
          value={orderNumber}
          onChange={(event) => setOrderNumber(event.target.value)}
          required
        />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="document-number">Número de documento</Label>
        <Input
          id="document-number"
          placeholder="12345678"
          value={documentNumber}
          onChange={(event) => setDocumentNumber(event.target.value)}
          required
        />
      </div>

      <Button type="submit" className="w-full sm:w-auto">
        Buscar pedido
      </Button>
    </form>
  );
}
