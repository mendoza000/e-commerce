import { RequireAdmin } from "@/components/admin/require-admin";
import { AdminShell } from "@/components/admin/admin-shell";
import { UsersManager } from "@/components/admin/users-manager";

export default function AdminUsersPage() {
  return (
    <RequireAdmin permission="manage_users">
      <AdminShell>
        <UsersManager />
      </AdminShell>
    </RequireAdmin>
  );
}
