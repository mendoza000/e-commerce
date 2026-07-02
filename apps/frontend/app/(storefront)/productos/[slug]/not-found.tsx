import Link from "next/link";

export default function ProductNotFound() {
  return (
    <div className="container mx-auto flex flex-col items-center gap-4 px-4 py-16 text-center">
      <h1 className="text-xl font-semibold">Producto no encontrado</h1>
      <p className="text-muted-foreground">El producto que buscás no existe o ya no está disponible.</p>
      <Link href="/productos" className="underline">
        Volver al catálogo
      </Link>
    </div>
  );
}
