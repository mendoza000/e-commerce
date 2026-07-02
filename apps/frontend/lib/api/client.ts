export function getApiBaseUrl(): string {
  return process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "";
}

/** Shape of the JSON body the backend returns for error responses (see bootstrap/app.php). */
export interface ApiErrorBody {
  error: {
    message: string;
    code: string;
    fields?: Record<string, string[]>;
  };
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body: unknown = null,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${getApiBaseUrl()}${path}`, {
    cache: "no-store",
    ...init,
  });

  if (!res.ok) {
    const body = await res.json().catch(() => null);
    throw new ApiError(res.status, `${path} → ${res.status}`, body);
  }

  return res.json() as Promise<T>;
}
