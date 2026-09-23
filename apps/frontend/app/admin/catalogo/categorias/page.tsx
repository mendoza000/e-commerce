import { CategoriesManager } from "@/components/admin/categories-manager";

// The layout at app/admin/catalogo/layout.tsx already provides
// RequireAdmin/AdminShell/sub-nav — this page needs no wrapping of its own.
export default function AdminCategoriesPage() {
  return <CategoriesManager />;
}
