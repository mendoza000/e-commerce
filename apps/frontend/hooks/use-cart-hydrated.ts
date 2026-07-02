import { useSyncExternalStore } from "react";
import { useCartStore } from "@/lib/store/cart";

function subscribe(callback: () => void) {
  const unsubFinishHydration = useCartStore.persist.onFinishHydration(callback);
  return () => {
    unsubFinishHydration();
  };
}

function getSnapshot() {
  return useCartStore.persist.hasHydrated();
}

function getServerSnapshot() {
  return false;
}

/**
 * True once the persisted cart has been rehydrated from localStorage on the
 * client. The server always renders an empty cart, so anything that reads
 * cart contents (empty-cart guards, item counts, etc.) must gate on this to
 * avoid a hydration mismatch.
 */
export function useCartHydrated(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
