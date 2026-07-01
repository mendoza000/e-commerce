// API_URL: URL interna para fetch server-side (no NEXT_PUBLIC_, se lee en
// runtime sin bakear en el build — necesario porque dentro de docker-compose
// "backend" resuelve por nombre de servicio, no por localhost).
// NEXT_PUBLIC_API_URL: URL publica, se usaria desde componentes cliente.
const apiUrl = process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL;

async function getHealth() {
  try {
    const res = await fetch(`${apiUrl}/api/health`, { cache: "no-store" });
    return (await res.json()) as { status: string; db: string };
  } catch {
    return null;
  }
}

export default async function Home() {
  const health = await getHealth();

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 font-sans">
      <h1 className="text-2xl font-semibold">Ecommerce Template — Fase 0</h1>
      {health ? (
        <p className="text-green-600">
          API: {health.status} · DB: {health.db}
        </p>
      ) : (
        <p className="text-red-600">No se pudo conectar al backend en {apiUrl}</p>
      )}
    </main>
  );
}
