"use client";

import { useEffect } from "react";
import { useCartStore } from "@/lib/store/cart";

/**
 * Triggers the one-time rehydration of the persisted cart from localStorage.
 * The store is created with `skipHydration: true` so the server and the
 * first client render both see an empty cart — this component (mounted once
 * in the root layout) is what brings in the real, persisted state right
 * after mount, without causing a hydration mismatch.
 */
export function CartHydration() {
  useEffect(() => {
    void useCartStore.persist.rehydrate();
  }, []);

  return null;
}
