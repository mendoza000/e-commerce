import type { ProductImage, ProductVariant } from "@/lib/api/products";

export function findMatchingVariant(
  variants: ProductVariant[],
  selected: Record<number, number>,
): ProductVariant | undefined {
  const selectedValueIds = Object.values(selected);

  return variants.find((v) => selectedValueIds.every((id) => v.option_value_ids.includes(id)));
}

export function isValueSelectable(
  variants: ProductVariant[],
  selected: Record<number, number>,
  optionId: number,
  candidateValueId: number,
): boolean {
  const otherSelections = Object.entries(selected)
    .filter(([oid]) => Number(oid) !== optionId)
    .map(([, valueId]) => valueId);

  return variants.some(
    (v) =>
      v.available_stock > 0 &&
      v.option_value_ids.includes(candidateValueId) &&
      otherSelections.every((id) => v.option_value_ids.includes(id)),
  );
}

export function selectImagesForSelection(
  images: ProductImage[],
  selected: Record<number, number>,
): ProductImage[] {
  const selectedIds = Object.values(selected);
  const specific = images.filter(
    (img) => img.product_option_value_id !== null && selectedIds.includes(img.product_option_value_id),
  );

  if (specific.length > 0) {
    return specific;
  }

  return images.filter((img) => img.product_option_value_id === null);
}
