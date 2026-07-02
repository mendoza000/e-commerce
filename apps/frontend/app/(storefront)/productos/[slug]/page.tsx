import { notFound } from "next/navigation";
import { getProductBySlug } from "@/lib/api/products";
import { ProductDetail } from "@/components/storefront/product-detail";

interface ProductDetailPageProps {
  params: Promise<{ slug: string }>;
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const { slug } = await params;
  const product = await getProductBySlug(slug);

  if (!product) {
    notFound();
  }

  return <ProductDetail product={product} />;
}
