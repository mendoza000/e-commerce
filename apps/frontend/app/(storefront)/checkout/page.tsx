import { getStates } from "@/lib/api/locations";
import { CheckoutForm } from "@/components/storefront/checkout-form";

export default async function CheckoutPage() {
  const states = await getStates();

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="mb-6 text-2xl font-semibold">Checkout</h1>
      <CheckoutForm initialStates={states} />
    </div>
  );
}
