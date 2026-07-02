"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useCurrency } from "@/components/providers/currency-provider";
import { convertPrice, formatCurrency } from "@/lib/currency";
import { describeSelection, findMatchingVariant, selectImagesForSelection } from "@/lib/variants";
import { useCartStore } from "@/lib/store/cart";
import { ProductGallery } from "@/components/storefront/product-gallery";
import { VariantSelector } from "@/components/storefront/variant-selector";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import type { ProductDetail as ProductDetailData, ProductVariant } from "@/lib/api/products";

function defaultSelection(product: ProductDetailData): Record<number, number> {
  const variant =
    product.variants.find((v) => v.available_stock > 0) ?? product.variants[0];

  if (!variant) {
    return {};
  }

  const selection: Record<number, number> = {};
  for (const option of product.options) {
    const value = option.values.find((v) => variant.option_value_ids.includes(v.id));
    if (value) {
      selection[option.id] = value.id;
    }
  }
  return selection;
}

export function ProductDetail({ product }: { product: ProductDetailData }) {
  const [selected, setSelected] = useState<Record<number, number>>(() => defaultSelection(product));
  const { selected: currency } = useCurrency();
  const addItem = useCartStore((state) => state.addItem);

  const matchingVariant: ProductVariant | undefined = findMatchingVariant(product.variants, selected);
  const images = selectImagesForSelection(product.images, selected);
  const inStock = (matchingVariant?.available_stock ?? 0) > 0;

  const handleAddToCart = () => {
    if (!matchingVariant || !inStock) {
      return;
    }

    addItem({
      variantId: matchingVariant.id,
      sku: matchingVariant.sku,
      productName: product.name,
      variantDescription: describeSelection(product.options, selected),
      unitPrice: matchingVariant.effective_price,
      availableStockAtAdd: matchingVariant.available_stock,
      image: images[0]?.url ?? null,
    });

    toast.success("Agregado al carrito", {
      description: product.name,
    });
  };

  return (
    <div className="container mx-auto grid gap-8 px-4 py-8 md:grid-cols-2">
      <ProductGallery images={images} alt={product.name} />

      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold">{product.name}</h1>
          {product.description ? (
            <p className="mt-2 text-muted-foreground">{product.description}</p>
          ) : null}
        </div>

        <div className="flex items-center gap-3">
          {matchingVariant && currency ? (
            <span className="text-xl font-semibold">
              {formatCurrency(convertPrice(matchingVariant.effective_price, currency), currency)}
            </span>
          ) : null}
          {!inStock ? <Badge variant="secondary">Agotado</Badge> : null}
        </div>

        <VariantSelector
          options={product.options}
          variants={product.variants}
          selected={selected}
          onSelect={(optionId, valueId) =>
            setSelected((prev) => ({ ...prev, [optionId]: valueId }))
          }
        />

        <Button
          disabled={!matchingVariant || !inStock}
          onClick={handleAddToCart}
          className="w-full md:w-auto"
        >
          Agregar al carrito
        </Button>
      </div>
    </div>
  );
}
