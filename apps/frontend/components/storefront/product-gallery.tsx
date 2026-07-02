import type { ProductImage } from "@/lib/api/products";

export function ProductGallery({ images, alt }: { images: ProductImage[]; alt: string }) {
  if (images.length === 0) {
    return <div className="aspect-square rounded-md bg-muted" />;
  }

  return (
    <div className="grid grid-cols-2 gap-2">
      {images.map((image) => (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          key={image.id}
          src={image.url}
          alt={alt}
          loading="lazy"
          className="aspect-square w-full rounded-md object-cover"
        />
      ))}
    </div>
  );
}
