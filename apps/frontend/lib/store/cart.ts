import { create } from "zustand";
import { persist } from "zustand/middleware";

export interface CartItem {
  variantId: number;
  sku: string;
  productName: string;
  variantDescription: string;
  /** Unit price in the store's base currency, as returned by the API — never pre-converted. */
  unitPrice: string;
  quantity: number;
  /** UI hint only, captured at add-time. Not authoritative — the backend re-checks stock on order creation. */
  availableStockAtAdd: number;
  image: string | null;
}

interface CartState {
  items: CartItem[];
  addItem: (item: Omit<CartItem, "quantity">, quantity?: number) => void;
  removeItem: (variantId: number) => void;
  updateQuantity: (variantId: number, quantity: number) => void;
  clear: () => void;
}

export const useCartStore = create<CartState>()(
  persist(
    (set) => ({
      items: [],

      addItem: (item, quantity = 1) =>
        set((state) => {
          const existing = state.items.find((i) => i.variantId === item.variantId);

          if (existing) {
            return {
              items: state.items.map((i) =>
                i.variantId === item.variantId
                  ? { ...i, quantity: i.quantity + quantity }
                  : i,
              ),
            };
          }

          return { items: [...state.items, { ...item, quantity }] };
        }),

      removeItem: (variantId) =>
        set((state) => ({
          items: state.items.filter((i) => i.variantId !== variantId),
        })),

      updateQuantity: (variantId, quantity) =>
        set((state) => {
          if (quantity <= 0) {
            return { items: state.items.filter((i) => i.variantId !== variantId) };
          }

          return {
            items: state.items.map((i) => (i.variantId === variantId ? { ...i, quantity } : i)),
          };
        }),

      clear: () => set({ items: [] }),
    }),
    {
      name: "ecommerce-cart",
      version: 1,
      skipHydration: true,
    },
  ),
);
