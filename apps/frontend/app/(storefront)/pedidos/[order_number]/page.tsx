import { notFound } from "next/navigation";
import { getOrderByNumber } from "@/lib/api/orders";
import { OrderSummary } from "@/components/storefront/order-summary";

interface OrderPageProps {
  params: Promise<{ order_number: string }>;
  searchParams: Promise<{ document_number?: string }>;
}

export default async function OrderPage({ params, searchParams }: OrderPageProps) {
  const { order_number } = await params;
  const { document_number } = await searchParams;
  const order = await getOrderByNumber(order_number, document_number);

  if (!order) {
    notFound();
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <OrderSummary order={order} />
    </div>
  );
}
