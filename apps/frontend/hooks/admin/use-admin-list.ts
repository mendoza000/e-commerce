"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api/client";
import type { Paginated, PaginationMeta } from "@/lib/api/admin/types";

export type AdminListFetcher<T, F extends object> = (
  baseUrl: string,
  params: F & { page: number },
  signal?: AbortSignal,
) => Promise<Paginated<T>>;

export interface UseAdminListOptions<F extends object> {
  apiBaseUrl: string;
  initialFilters: F;
}

export interface UseAdminListResult<T, F extends object> {
  data: T[] | null;
  meta: PaginationMeta | null;
  loading: boolean;
  loadError: string | null;
  filters: F;
  /** Changing filters always resets to page 1 — a filtered result set rarely has as many pages as the last one. */
  setFilters: (updater: F | ((previous: F) => F)) => void;
  page: number;
  setPage: (page: number) => void;
  reload: () => Promise<void>;
}

/**
 * Generalizes `users-manager.tsx`'s fetch/AbortController/loading pattern to a
 * paginated, filterable list. `fetcher` should be a stable reference (e.g. a
 * module-level function, or wrapped in `useCallback`) — it is an effect
 * dependency, and a fresh function identity on every render would refetch on
 * every render.
 */
export function useAdminList<T, F extends object>(
  fetcher: AdminListFetcher<T, F>,
  { apiBaseUrl, initialFilters }: UseAdminListOptions<F>,
): UseAdminListResult<T, F> {
  const [filters, setFiltersState] = useState<F>(initialFilters);
  const [page, setPage] = useState(1);
  const [data, setData] = useState<T[] | null>(null);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      setLoading(true);
      try {
        const result = await fetcher(apiBaseUrl, { ...filters, page }, signal);
        setData(result.data);
        setMeta(result.meta);
        setLoadError(null);
      } catch (error) {
        if (signal?.aborted) return;
        setLoadError(
          error instanceof ApiError ? error.message : "No pudimos cargar la lista. Intenta de nuevo.",
        );
      } finally {
        if (!signal?.aborted) setLoading(false);
      }
    },
    [fetcher, apiBaseUrl, filters, page],
  );

  useEffect(() => {
    const controller = new AbortController();
    // Fetch-on-mount with AbortController — same established pattern as
    // users-manager.tsx's load effect (see that file's known
    // react-hooks/set-state-in-effect exception).
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load(controller.signal);

    return () => controller.abort();
  }, [load]);

  const setFilters = useCallback((updater: F | ((previous: F) => F)) => {
    setFiltersState(updater);
    setPage(1);
  }, []);

  const reload = useCallback(() => load(), [load]);

  return { data, meta, loading, loadError, filters, setFilters, page, setPage, reload };
}
