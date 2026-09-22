"use client";

import { useState, type ChangeEvent, type FormEvent } from "react";
import { Loader2Icon, UploadIcon } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ApiError, type ApiErrorBody } from "@/lib/api/client";
import { submitPaymentProof, type Order } from "@/lib/api/orders";

// Mirrors `commerce.payment_proof` config — client-side pre-check only, a
// friendly nudge before hitting the network. The backend
// (PaymentProofStoreRequest) remains the source of truth and re-validates
// both on every submit.
const ACCEPTED_MIME_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
const MAX_FILE_SIZE_BYTES = 5120 * 1024;

function validateFile(file: File): string | null {
  if (!ACCEPTED_MIME_TYPES.includes(file.type)) {
    return "Formato no permitido. Usa una imagen (JPG, PNG, WEBP) o un PDF.";
  }
  if (file.size > MAX_FILE_SIZE_BYTES) {
    return "El archivo no puede pesar más de 5 MB.";
  }
  return null;
}

/**
 * Only rendered while a proof is both required and still missing. The parent
 * (OrderSummary) is expected to gate mounting this on the same condition
 * checked here defensively: `payment_method.requires_proof &&
 * status === "pending_payment" && payment_proof === null`.
 *
 * On success, hands the fresh `Order` (now `status: "payment_submitted"`
 * with a populated `payment_proof`) up via `onSubmitted` so the parent can
 * swap it into state — simpler and faster than `router.refresh()`, since the
 * API response already IS the updated order, no extra round trip needed.
 */
export function PaymentProofUpload({
  order,
  documentNumber,
  onSubmitted,
}: {
  order: Order;
  documentNumber?: string;
  onSubmitted: (order: Order) => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [reference, setReference] = useState("");
  const [fileError, setFileError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (!order.payment_method.requires_proof || order.status !== "pending_payment" || order.payment_proof) {
    return null;
  }

  function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
    const selected = event.target.files?.[0] ?? null;
    setFile(selected);
    setFileError(selected ? validateFile(selected) : null);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();

    if (!file) {
      setFileError("Selecciona un archivo.");
      return;
    }

    setSubmitting(true);
    try {
      const updated = await submitPaymentProof(order.order_number, {
        proof: file,
        reference: reference || undefined,
        documentNumber,
      });
      toast.success("Comprobante enviado. Estamos revisándolo.");
      onSubmitted(updated);
    } catch (error) {
      const fieldMessage =
        error instanceof ApiError ? (error.body as ApiErrorBody | null)?.error.fields?.proof?.[0] : undefined;
      toast.error(
        fieldMessage ??
          (error instanceof ApiError ? error.message : "No pudimos enviar el comprobante. Intenta de nuevo."),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-lg border p-4">
      <h2 className="font-semibold">Subir comprobante de pago</h2>

      <div className="space-y-1.5">
        <Label htmlFor="payment-proof-file">Archivo</Label>
        <FileInput
          id="payment-proof-file"
          accept={ACCEPTED_MIME_TYPES.join(",")}
          onChange={handleFileChange}
          aria-invalid={fileError ? true : undefined}
        />
        {fileError ? <p className="text-xs text-destructive">{fileError}</p> : null}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="payment-proof-reference">Referencia (opcional)</Label>
        <Input
          id="payment-proof-reference"
          value={reference}
          onChange={(event) => setReference(event.target.value)}
          placeholder="Número de referencia de la transacción"
        />
      </div>

      <Button type="submit" disabled={submitting}>
        {submitting ? <Loader2Icon className="animate-spin" /> : <UploadIcon />}
        Enviar comprobante
      </Button>
    </form>
  );
}
