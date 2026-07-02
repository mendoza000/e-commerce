"use client";

import { Button } from "@/components/ui/button";

export default function OrderError({ reset }: { error: Error; reset: () => void }) {
  return (
    <div className="container mx-auto flex flex-col items-center gap-4 px-4 py-16 text-center">
      <p className="text-muted-foreground">
        No pudimos cargar el pedido. Intentá de nuevo en un momento.
      </p>
      <Button onClick={() => reset()}>Reintentar</Button>
    </div>
  );
}
