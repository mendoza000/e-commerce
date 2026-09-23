/**
 * Laravel's standard paginator shape (`AnonymousResourceCollection::paginate()`),
 * as returned by every paginated `/api/admin/*` list endpoint. Unlike the
 * simple `{data: T[]}` wrapper used by unpaginated admin lists (see
 * `lib/api/admin/users.ts`), this one also carries `links`/`meta` so a list UI
 * can build pagination controls without guessing field names.
 */
export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}
