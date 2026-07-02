import Link from "next/link";
import { Input } from "@/components/ui/input";
import { CurrencySelector } from "@/components/storefront/currency-selector";
import { CartSheet } from "@/components/storefront/cart-sheet";

export function SiteHeader() {
  return (
    <header className="border-b">
      <div className="container mx-auto flex items-center gap-4 px-4 py-4">
        <Link href="/" className="font-semibold whitespace-nowrap">
          Tienda Demo
        </Link>
        <nav className="flex items-center gap-4 text-sm">
          <Link href="/productos">Productos</Link>
        </nav>
        <form action="/productos" method="get" className="ml-auto flex-1 max-w-sm">
          <Input type="search" name="search" placeholder="Buscar productos..." />
        </form>
        <CurrencySelector />
        <CartSheet />
      </div>
    </header>
  );
}
