import { ApiError, type ApiErrorBody } from "@/lib/api/client";

/**
 * Browser-side client for /api/admin.
 *
 * The admin panel authenticates with a Sanctum first-party session, so every
 * call goes out with cookies and every write carries the CSRF header Laravel
 * expects. `fetch` — unlike axios — does neither on its own.
 */

const CSRF_COOKIE = "XSRF-TOKEN";
const CSRF_HEADER = "X-XSRF-TOKEN";

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith(`${name}=`));

  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function fetchCsrfCookie(baseUrl: string): Promise<void> {
  await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

async function parseError(response: Response): Promise<ApiError> {
  const body = (await response.json().catch(() => null)) as ApiErrorBody | null;

  return new ApiError(
    response.status,
    body?.error?.message ?? `Error ${response.status}`,
    body,
  );
}

export interface AdminFetchOptions {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  body?: unknown;
  signal?: AbortSignal;
}

export async function adminFetch<T>(
  baseUrl: string,
  path: string,
  { method = "GET", body, signal }: AdminFetchOptions = {},
): Promise<T> {
  const isWrite = method !== "GET";

  // Laravel only issues the CSRF cookie on request. Writes need it; reads do
  // not, so a plain GET never pays for the extra round-trip.
  if (isWrite && !readCookie(CSRF_COOKIE)) {
    await fetchCsrfCookie(baseUrl);
  }

  const send = async (): Promise<Response> => {
    const token = readCookie(CSRF_COOKIE);

    return fetch(`${baseUrl}/api/admin${path}`, {
      method,
      credentials: "include",
      signal,
      headers: {
        Accept: "application/json",
        ...(body === undefined ? {} : { "Content-Type": "application/json" }),
        ...(isWrite && token ? { [CSRF_HEADER]: token } : {}),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  };

  let response = await send();

  // 419 means the CSRF token went stale (the session outlived the cookie, or
  // another tab rotated it). Worth exactly one silent retry with a fresh one.
  if (response.status === 419 && isWrite) {
    await fetchCsrfCookie(baseUrl);
    response = await send();
  }

  if (!response.ok) {
    throw await parseError(response);
  }

  if (response.status === 204) {
    return null as T;
  }

  return (await response.json()) as T;
}

/** Pulls field errors out of a 422 so a form can attach them to its inputs. */
export function fieldErrorsOf(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.status !== 422) {
    return {};
  }

  const fields = (error.body as ApiErrorBody | null)?.error?.fields ?? {};

  return Object.fromEntries(
    Object.entries(fields).map(([key, messages]) => [key, messages[0] ?? "Dato inválido."]),
  );
}
