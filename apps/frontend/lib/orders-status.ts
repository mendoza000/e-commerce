import type { AdminOrderStatus } from "@/lib/api/admin/orders";

type BadgeVariant = "default" | "secondary" | "destructive" | "outline";

/**
 * Color only — labels already come from the backend as `status_label`
 * (OrderResource), so there is no client-side label map to keep in sync with
 * `OrderStatus::label()`. Shared between orders-list.tsx and order-detail.tsx.
 */
export const ORDER_STATUS_BADGE_VARIANT: Record<AdminOrderStatus, BadgeVariant> = {
  pending_payment: "outline",
  payment_submitted: "secondary",
  paid: "default",
  preparing: "secondary",
  shipped: "secondary",
  delivered: "default",
  cancelled: "destructive",
};
