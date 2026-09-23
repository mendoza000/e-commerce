"use client";

import { useCallback, useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon } from "lucide-react";
import { toast } from "sonner";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { ApiError } from "@/lib/api/client";
import {
  cancel,
  confirmPayment,
  rejectPayment,
  show,
  transition,
  type AdminOrder,
} from "@/lib/api/admin/orders";
import {
  reasonSchema,
  transitionSchema,
  type ReasonValues,
  type TransitionValues,
} from "@/lib/schemas/admin-orders";
import { ORDER_STATUS_BADGE_VARIANT } from "@/lib/orders-status";
import { formatCurrency, toDisplayCurrency } from "@/lib/currency";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

type ActiveForm = "reject" | "cancel" | "shipped" | null;

function formatDate(iso: string | null): string {
  if (!iso) return "—";

  return new Intl.DateTimeFormat("es-VE", { dateStyle: "medium", timeStyle: "short" }).format(
    new Date(iso),
  );
}

export function OrderDetail({ orderNumber }: { orderNumber: string }) {
  const { apiBaseUrl } = useAdminSession();

  const [order, setOrder] = useState<AdminOrder | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [activeForm, setActiveForm] = useState<ActiveForm>(null);
  const [pendingTransition, setPendingTransition] = useState<string | null>(null);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      try {
        setOrder(await show(apiBaseUrl, orderNumber, signal));
        setLoadError(null);
      } catch (error) {
        if (signal?.aborted) return;
        setLoadError(
          error instanceof ApiError ? error.message : "No pudimos cargar el pedido. Intenta de nuevo.",
        );
      }
    },
    [apiBaseUrl, orderNumber],
  );

  useEffect(() => {
    const controller = new AbortController();
    // Fetch-on-mount with AbortController — same established pattern as
    // users-manager.tsx's load effect (see that file's known
    // react-hooks/set-state-in-effect exception).
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load(controller.signal);

    return () => controller.abort();
  }, [load]);

  // Reject-payment and cancel share this one instance — they're never shown
  // at the same time (both gated by `activeForm`) and have the exact same
  // shape (a single required `reason`), so a second form instance would only
  // duplicate state that's reset on close anyway.
  const reasonForm = useForm<ReasonValues>({
    resolver: zodResolver(reasonSchema),
    defaultValues: { reason: "" },
  });

  const transitionForm = useForm<TransitionValues>({
    resolver: zodResolver(transitionSchema),
    defaultValues: { courier: "", tracking_code: "", note: "" },
  });

  function closeForms() {
    setActiveForm(null);
    reasonForm.reset({ reason: "" });
    transitionForm.reset({ courier: "", tracking_code: "", note: "" });
  }

  function toggleForm(form: Exclude<ActiveForm, null>) {
    setActiveForm((current) => (current === form ? null : form));
  }

  /**
   * No reason field on this action, unlike reject/cancel — a native
   * `confirm()` stands in for the "are you sure?" step those get for free
   * from a required reason field. Fires immediately, same convention as
   * `users-manager.tsx`'s activate/deactivate toggle.
   */
  async function handleConfirmPayment() {
    if (!window.confirm("¿Confirmar que el pago de este pedido fue recibido?")) return;

    setConfirming(true);
    try {
      setOrder(await confirmPayment(apiBaseUrl, orderNumber));
      toast.success("Pago confirmado.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos confirmar el pago.");
    } finally {
      setConfirming(false);
    }
  }

  async function handleRejectPayment(values: ReasonValues) {
    try {
      setOrder(await rejectPayment(apiBaseUrl, orderNumber, values.reason));
      toast.success("Pago rechazado.");
      closeForms();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos rechazar el pago.");
    }
  }

  async function handleCancel(values: ReasonValues) {
    try {
      setOrder(await cancel(apiBaseUrl, orderNumber, values.reason));
      toast.success("Pedido cancelado.");
      closeForms();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos cancelar el pedido.");
    }
  }

  async function handlePlainTransition(status: "preparing" | "delivered") {
    setPendingTransition(status);
    try {
      setOrder(await transition(apiBaseUrl, orderNumber, { status }));
      toast.success("Pedido actualizado.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos actualizar el pedido.");
    } finally {
      setPendingTransition(null);
    }
  }

  async function handleShippedTransition(values: TransitionValues) {
    setPendingTransition("shipped");
    try {
      setOrder(
        await transition(apiBaseUrl, orderNumber, {
          status: "shipped",
          courier: values.courier || undefined,
          tracking_code: values.tracking_code || undefined,
          note: values.note || undefined,
        }),
      );
      toast.success("Pedido marcado como enviado.");
      closeForms();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "No pudimos actualizar el pedido.");
    } finally {
      setPendingTransition(null);
    }
  }

  if (loadError) {
    return (
      <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
        {loadError}
      </p>
    );
  }

  if (order === null) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  const showBaseAmount = order.base_currency.code !== order.payment_currency.code;
  const paymentCurrency = toDisplayCurrency(order.payment_currency);
  const baseCurrency = toDisplayCurrency(order.base_currency);
  const hasShippingUpdate = Boolean(
    order.shipping.courier || order.shipping.tracking_code || order.shipping.note,
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-semibold">Pedido {order.order_number}</h1>
          <p className="text-sm text-muted-foreground">Creado el {formatDate(order.created_at)}</p>
        </div>
        <Badge variant={ORDER_STATUS_BADGE_VARIANT[order.status]}>{order.status_label}</Badge>
      </div>

      {order.reservation_expires_at ? (
        <p className="rounded-md border bg-muted/50 p-3 text-sm">
          Reserva de stock vigente hasta <strong>{formatDate(order.reservation_expires_at)}</strong>.
        </p>
      ) : null}

      <div className="grid gap-6 sm:grid-cols-2">
        <section className="space-y-1 rounded-lg border p-4">
          <h2 className="font-semibold">Cliente</h2>
          <p className="text-sm">
            {order.customer.name}
            {order.customer.is_registered ? (
              <span className="ml-2 text-xs text-muted-foreground">(cuenta registrada)</span>
            ) : null}
          </p>
          <p className="text-sm text-muted-foreground">{order.customer.phone}</p>
          <p className="text-sm text-muted-foreground">
            {order.customer.document_type}-{order.customer.document_number}
          </p>
        </section>

        <section className="space-y-1 rounded-lg border p-4">
          <h2 className="font-semibold">Dirección de entrega</h2>
          <p className="text-sm text-muted-foreground">
            {[order.address.parish, order.address.municipality, order.address.state]
              .filter(Boolean)
              .join(", ")}
          </p>
          <p className="text-sm text-muted-foreground">{order.address.reference}</p>
        </section>
      </div>

      <section className="rounded-lg border p-4">
        {/* items_count relies on withCount('items'), which the detail
            endpoint doesn't call (only the list endpoint does) — items.length
            is always accurate here since the full array is always loaded. */}
        <h2 className="mb-2 font-semibold">Productos ({order.items.length})</h2>
        <ul className="divide-y">
          {order.items.map((item, index) => (
            <li key={index} className="flex items-center justify-between gap-4 py-2 text-sm">
              <div>
                <p className="font-medium">{item.product_name}</p>
                {item.variant_description ? (
                  <p className="text-xs text-muted-foreground">{item.variant_description}</p>
                ) : null}
                <p className="text-xs text-muted-foreground">
                  {item.quantity} × {formatCurrency(parseFloat(item.unit_price), paymentCurrency)}
                </p>
              </div>
              <span className="font-medium">
                {formatCurrency(parseFloat(item.subtotal), paymentCurrency)}
              </span>
            </li>
          ))}
        </ul>

        <div className="mt-3 ml-auto max-w-xs space-y-1 border-t pt-2 text-sm">
          {showBaseAmount ? (
            <div className="flex justify-between text-muted-foreground">
              <span>Total ({order.base_currency.code})</span>
              <span>{formatCurrency(parseFloat(order.base_amount), baseCurrency)}</span>
            </div>
          ) : null}
          {showBaseAmount ? (
            <div className="flex justify-between text-muted-foreground">
              <span>Tasa aplicada</span>
              <span>{order.exchange_rate_applied}</span>
            </div>
          ) : null}
          {order.fulfillment_method ? (
            <div className="flex justify-between text-muted-foreground">
              <span>Envío</span>
              <span>
                {order.shipping_amount === null
                  ? "A coordinar"
                  : formatCurrency(parseFloat(order.shipping_amount), baseCurrency)}
              </span>
            </div>
          ) : null}
          <div className="flex justify-between border-t pt-1 text-base font-semibold">
            <span>Total a pagar ({order.payment_currency.code})</span>
            <span>{formatCurrency(parseFloat(order.payment_amount), paymentCurrency)}</span>
          </div>
        </div>
      </section>

      <section className="space-y-2 rounded-lg border p-4">
        <h2 className="font-semibold">Pago</h2>
        <p className="text-sm text-muted-foreground">
          {order.payment_method ? order.payment_method.label : "Sin método de pago"}
        </p>

        {order.payment_proofs.length > 0 ? (
          <ul className="space-y-3">
            {order.payment_proofs.map((proof) => (
              <li key={proof.id} className="rounded-md border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{proof.original_name}</span>
                  <span className="text-xs text-muted-foreground">{formatDate(proof.submitted_at)}</span>
                </div>
                {proof.reference ? (
                  <p className="text-xs text-muted-foreground">Referencia: {proof.reference}</p>
                ) : null}
                {proof.is_image ? (
                  <a href={proof.download_url} target="_blank" rel="noreferrer">
                    {/* Plain <img>, not next/image: the API origin is only known
                        at runtime (see getPublicApiBaseUrl), so next/image's
                        build-time remotePatterns can't cover it. */}
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={proof.download_url}
                      alt={proof.original_name}
                      className="mt-2 max-h-64 rounded-md border object-contain"
                    />
                  </a>
                ) : (
                  <a
                    href={proof.download_url}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-1 inline-block text-primary hover:underline"
                  >
                    Descargar comprobante
                  </a>
                )}
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted-foreground">Sin comprobantes cargados.</p>
        )}
      </section>

      {hasShippingUpdate ? (
        <section className="space-y-1 rounded-lg border p-4">
          <h2 className="font-semibold">Envío</h2>
          {order.shipping.courier ? (
            <p className="text-sm">
              <span className="text-muted-foreground">Transportista: </span>
              {order.shipping.courier}
            </p>
          ) : null}
          {order.shipping.tracking_code ? (
            <p className="text-sm">
              <span className="text-muted-foreground">Código de seguimiento: </span>
              {order.shipping.tracking_code}
            </p>
          ) : null}
          {order.shipping.note ? (
            <p className="text-sm">
              <span className="text-muted-foreground">Nota: </span>
              {order.shipping.note}
            </p>
          ) : null}
        </section>
      ) : null}

      <section className="rounded-lg border p-4">
        <h2 className="mb-2 font-semibold">Historial</h2>
        <ol className="space-y-2 border-l pl-4">
          {order.status_history.map((entry, index) => (
            <li key={index} className="text-sm">
              <p>
                <span className="font-medium">{entry.from_status_label ?? "—"}</span>
                {" → "}
                <span className="font-medium">{entry.to_status_label}</span>
              </p>
              <p className="text-xs text-muted-foreground">
                {formatDate(entry.created_at)}
                {entry.changed_by ? ` · ${entry.changed_by.name}` : " · automático"}
              </p>
              {entry.reason ? <p className="text-xs text-muted-foreground">{entry.reason}</p> : null}
            </li>
          ))}
        </ol>
      </section>

      <section className="space-y-4 rounded-lg border p-4">
        <h2 className="font-semibold">Acciones</h2>

        <div className="flex flex-wrap gap-2">
          {order.actions.can_confirm_payment ? (
            <Button onClick={handleConfirmPayment} disabled={confirming}>
              {confirming ? <Loader2Icon className="animate-spin" /> : null}
              Confirmar pago
            </Button>
          ) : null}

          {order.actions.can_reject_payment ? (
            <Button variant="outline" onClick={() => toggleForm("reject")}>
              Rechazar pago
            </Button>
          ) : null}

          {order.actions.can_cancel ? (
            <Button variant="destructive" onClick={() => toggleForm("cancel")}>
              Cancelar pedido
            </Button>
          ) : null}

          {order.actions.available_transitions.map((option) =>
            option.value === "shipped" ? (
              <Button key={option.value} variant="outline" onClick={() => toggleForm("shipped")}>
                {option.label}
              </Button>
            ) : (
              <Button
                key={option.value}
                variant="outline"
                disabled={pendingTransition === option.value}
                onClick={() => handlePlainTransition(option.value as "preparing" | "delivered")}
              >
                {pendingTransition === option.value ? <Loader2Icon className="animate-spin" /> : null}
                {option.label}
              </Button>
            ),
          )}
        </div>

        {activeForm === "reject" ? (
          <Form {...reasonForm}>
            <form onSubmit={reasonForm.handleSubmit(handleRejectPayment)} className="space-y-3">
              <FormField
                control={reasonForm.control}
                name="reason"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Motivo del rechazo</FormLabel>
                    <FormControl>
                      <Textarea placeholder="Explica por qué se rechaza el comprobante" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="flex gap-2">
                <Button type="submit" disabled={reasonForm.formState.isSubmitting}>
                  {reasonForm.formState.isSubmitting ? <Loader2Icon className="animate-spin" /> : null}
                  Rechazar pago
                </Button>
                <Button type="button" variant="ghost" onClick={closeForms}>
                  Cancelar
                </Button>
              </div>
            </form>
          </Form>
        ) : null}

        {activeForm === "cancel" ? (
          <Form {...reasonForm}>
            <form onSubmit={reasonForm.handleSubmit(handleCancel)} className="space-y-3">
              <FormField
                control={reasonForm.control}
                name="reason"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Motivo de la cancelación</FormLabel>
                    <FormControl>
                      <Textarea placeholder="Explica por qué se cancela el pedido" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="flex gap-2">
                <Button type="submit" variant="destructive" disabled={reasonForm.formState.isSubmitting}>
                  {reasonForm.formState.isSubmitting ? <Loader2Icon className="animate-spin" /> : null}
                  Cancelar pedido
                </Button>
                <Button type="button" variant="ghost" onClick={closeForms}>
                  Volver
                </Button>
              </div>
            </form>
          </Form>
        ) : null}

        {activeForm === "shipped" ? (
          <Form {...transitionForm}>
            <form onSubmit={transitionForm.handleSubmit(handleShippedTransition)} className="space-y-3">
              <div className="grid gap-3 sm:grid-cols-2">
                <FormField
                  control={transitionForm.control}
                  name="courier"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Transportista (opcional)</FormLabel>
                      <FormControl>
                        <Input placeholder="Nombre de la empresa de envío" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={transitionForm.control}
                  name="tracking_code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Código de seguimiento (opcional)</FormLabel>
                      <FormControl>
                        <Input placeholder="Número de guía" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={transitionForm.control}
                name="note"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nota (opcional)</FormLabel>
                    <FormControl>
                      <Textarea placeholder="Cualquier detalle adicional del envío" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="flex gap-2">
                <Button type="submit" disabled={pendingTransition === "shipped"}>
                  {pendingTransition === "shipped" ? <Loader2Icon className="animate-spin" /> : null}
                  Marcar como enviado
                </Button>
                <Button type="button" variant="ghost" onClick={closeForms}>
                  Cancelar
                </Button>
              </div>
            </form>
          </Form>
        ) : null}
      </section>
    </div>
  );
}
