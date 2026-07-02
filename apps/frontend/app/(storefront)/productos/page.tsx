import Link from "next/link";
import { getProducts } from "@/lib/api/products";
import { getCategories } from "@/lib/api/categories";
import { ProductCard } from "@/components/storefront/product-card";

interface ProductsPageProps {
  searchParams: Promise<{ category?: string; search?: string; page?: string }>;
}

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const { category, search, page } = await searchParams;

  const [products, categories] = await Promise.all([
    getProducts({ category, search, page: page ? Number(page) : undefined }),
    getCategories(),
  ]);

  const buildHref = (params: Record<string, string | number | undefined>) => {
    const query = new URLSearchParams();
    if (category) query.set("category", category);
    if (search) query.set("search", search);
    Object.entries(params).forEach(([key, value]) => {
      if (value === undefined) {
        query.delete(key);
      } else {
        query.set(key, String(value));
      }
    });
    const qs = query.toString();
    return `/productos${qs ? `?${qs}` : ""}`;
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="mb-6 flex flex-wrap gap-2">
        <Link
          href={buildHref({ category: undefined, page: undefined })}
          className={!category ? "font-semibold underline" : "text-muted-foreground"}
        >
          Todas
        </Link>
        {categories.map((c) => (
          <Link
            key={c.id}
            href={buildHref({ category: c.slug, page: undefined })}
            className={category === c.slug ? "font-semibold underline" : "text-muted-foreground"}
          >
            {c.name}
          </Link>
        ))}
      </div>

      {products.data.length === 0 ? (
        <p className="text-muted-foreground">No se encontraron productos.</p>
      ) : (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {products.data.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}

      {products.meta.last_page > 1 ? (
        <div className="mt-8 flex justify-center gap-4">
          {products.meta.current_page > 1 ? (
            <Link href={buildHref({ page: products.meta.current_page - 1 })}>Anterior</Link>
          ) : null}
          {products.meta.current_page < products.meta.last_page ? (
            <Link href={buildHref({ page: products.meta.current_page + 1 })}>Siguiente</Link>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
