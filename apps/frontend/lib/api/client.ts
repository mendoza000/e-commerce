export function getApiBaseUrl(): string {
  return process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "";
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
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
    throw new ApiError(res.status, `${path} → ${res.status}`);
  }

  return res.json() as Promise<T>;
}
