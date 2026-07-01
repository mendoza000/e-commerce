<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $ropa = Category::where('slug', 'ropa')->first();
        $calzado = Category::where('slug', 'calzado')->first();
        $electronica = Category::where('slug', 'electronica')->first();

        $simpleProduct = Product::factory()
            ->withVariants([])
            ->create([
                'category_id' => $electronica?->id,
                'name' => 'Audífonos Bluetooth',
                'slug' => 'audifonos-bluetooth',
            ]);

        ProductImage::create([
            'product_id' => $simpleProduct->id,
            'product_option_value_id' => null,
            'path' => 'products/placeholder.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        $oneOptionProduct = Product::factory()
            ->withVariants(['Talla' => ['S', 'M', 'L']])
            ->create([
                'category_id' => $calzado?->id,
                'name' => 'Zapatos Deportivos',
                'slug' => 'zapatos-deportivos',
            ]);

        ProductImage::create([
            'product_id' => $oneOptionProduct->id,
            'product_option_value_id' => null,
            'path' => 'products/placeholder.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        $twoOptionsProduct = Product::factory()
            ->withVariants([
                'Color' => ['Rojo', 'Azul'],
                'Talla' => ['M', 'L'],
            ])
            ->create([
                'category_id' => $ropa?->id,
                'name' => 'Camisa Casual',
                'slug' => 'camisa-casual',
            ]);

        ProductImage::create([
            'product_id' => $twoOptionsProduct->id,
            'product_option_value_id' => null,
            'path' => 'products/placeholder.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        $colorOption = $twoOptionsProduct->options()->where('name', 'Color')->first();

        if ($colorOption !== null) {
            foreach ($colorOption->values as $index => $colorValue) {
                ProductImage::create([
                    'product_id' => $twoOptionsProduct->id,
                    'product_option_value_id' => $colorValue->id,
                    'path' => "products/placeholder-{$colorValue->value}.jpg",
                    'position' => $index + 1,
                    'is_primary' => false,
                ]);
            }
        }
    }
}
