import Link from "next/link";

export default function OrderNotFound() {
  return (
    <div className="container mx-auto flex flex-col items-center gap-4 px-4 py-16 text-center">
      <h1 className="text-xl font-semibold">Pedido no encontrado</h1>
      <p className="text-muted-foreground">
        No encontramos ese pedido, o el número de documento no coincide.
      </p>
      <Link href="/pedidos" className="underline">
        Buscar otro pedido
      </Link>
    </div>
  );
}
