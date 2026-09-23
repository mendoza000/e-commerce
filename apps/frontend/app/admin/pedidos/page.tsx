import { RequireAdmin } from "@/components/admin/require-admin";
import { AdminShell } from "@/components/admin/admin-shell";
import { OrdersList } from "@/components/admin/orders-list";

export default function AdminOrdersPage() {
  return (
    <RequireAdmin permission="manage_orders">
      <AdminShell>
        <OrdersList />
      </AdminShell>
    </RequireAdmin>
  );
}
