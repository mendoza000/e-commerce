import { OrderLookupForm } from "@/components/storefront/order-lookup-form";

export default function OrderLookupPage() {
  return (
    <div className="container mx-auto max-w-sm px-4 py-8">
      <h1 className="mb-6 text-2xl font-semibold">Buscar mi pedido</h1>
      <OrderLookupForm />
    </div>
  );
}
