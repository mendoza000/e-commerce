import { redirect } from "next/navigation";

// Productos doesn't exist as a real route until M6 lands — Categorías is the
// only functional page in this milestone, so it's the safe landing spot.
export default function CatalogIndexPage() {
  redirect("/admin/catalogo/categorias");
}
