import { ProductsList } from "@/components/admin/products-list";

// The layout at app/admin/catalogo/layout.tsx already provides
// RequireAdmin/AdminShell/sub-nav — this page needs no wrapping of its own.
export default function AdminProductsPage() {
  return <ProductsList />;
}
