import { RequireAdmin } from "@/components/admin/require-admin";
import { AdminShell } from "@/components/admin/admin-shell";
import { OrderDetail } from "@/components/admin/order-detail";

interface AdminOrderPageProps {
  params: Promise<{ order_number: string }>;
}

export default async function AdminOrderPage({ params }: AdminOrderPageProps) {
  const { order_number } = await params;

  return (
    <RequireAdmin permission="manage_orders">
      <AdminShell>
        <OrderDetail orderNumber={order_number} />
      </AdminShell>
    </RequireAdmin>
  );
}
