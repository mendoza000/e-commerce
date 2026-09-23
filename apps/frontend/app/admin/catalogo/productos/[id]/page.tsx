import { ProductDetail } from "@/components/admin/product-detail";

// The layout at app/admin/catalogo/layout.tsx already provides
// RequireAdmin/AdminShell/sub-nav — this page needs no wrapping of its own.
interface AdminProductPageProps {
  params: Promise<{ id: string }>;
}

export default async function AdminProductPage({ params }: AdminProductPageProps) {
  const { id } = await params;

  // Id-based binding (see products.ts's `show`), not slug-based like the
  // public storefront's product page.
  return <ProductDetail productId={Number(id)} />;
}
