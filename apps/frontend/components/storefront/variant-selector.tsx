import { isValueSelectable } from "@/lib/variants";
import type { ProductOption, ProductVariant } from "@/lib/api/products";
import { cn } from "@/lib/utils";

export function VariantSelector({
  options,
  variants,
  selected,
  onSelect,
}: {
  options: ProductOption[];
  variants: ProductVariant[];
  selected: Record<number, number>;
  onSelect: (optionId: number, valueId: number) => void;
}) {
  if (options.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4">
      {options.map((option) => (
        <div key={option.id}>
          <p className="mb-2 text-sm font-medium">{option.name}</p>
          <div className="flex flex-wrap gap-2">
            {option.values.map((value) => {
              const isSelected = selected[option.id] === value.id;
              const selectable = isValueSelectable(variants, selected, option.id, value.id);

              return (
                <button
                  key={value.id}
                  type="button"
                  disabled={!selectable}
                  onClick={() => onSelect(option.id, value.id)}
                  className={cn(
                    "rounded-md border px-3 py-1.5 text-sm transition-colors",
                    isSelected && "border-primary bg-primary text-primary-foreground",
                    !isSelected && selectable && "hover:border-primary",
                    !selectable && "cursor-not-allowed opacity-40 line-through",
                  )}
                >
                  {value.value}
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}
