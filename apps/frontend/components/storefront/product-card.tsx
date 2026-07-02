import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import { PriceTag } from "@/components/storefront/price-tag";
import type { ProductListItem } from "@/lib/api/products";

export function ProductCard({ product }: { product: ProductListItem }) {
  return (
    <Link href={`/productos/${product.slug}`}>
      <Card className="h-full overflow-hidden transition-shadow hover:shadow-md">
        <div className="aspect-square bg-muted">
          {product.primary_image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={product.primary_image.url}
              alt={product.name}
              loading="lazy"
              className="h-full w-full object-cover"
            />
          ) : null}
        </div>
        <CardContent className="space-y-1">
          <p className="line-clamp-2 font-medium">{product.name}</p>
          <div className="flex items-center justify-between">
            <PriceTag basePrice={product.base_price} />
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
