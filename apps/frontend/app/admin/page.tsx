import { RequireAdmin } from "@/components/admin/require-admin";
import { AdminShell } from "@/components/admin/admin-shell";
import { AdminHome } from "@/components/admin/admin-home";

export default function AdminDashboardPage() {
  return (
    <RequireAdmin>
      <AdminShell>
        <AdminHome />
      </AdminShell>
    </RequireAdmin>
  );
}
