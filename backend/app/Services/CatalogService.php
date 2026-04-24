<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Section;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogService
{
    public function storeProductImage($image): string
    {
        Storage::disk('public')->makeDirectory('products');

        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        $storedPath = Storage::disk('public')->putFileAs('products', $image, $imageName);

        if ($storedPath === false) {
            throw new \RuntimeException('No se pudo guardar la imagen del producto');
        }

        return 'products/' . $imageName;
    }

    public function deleteStoredProductImages(iterable $products): void
    {
        foreach ($products as $product) {
            if (!empty($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
        }
    }

    public function createCatalog(Restaurant $restaurant, array $data): Catalog
    {
        return $restaurant->catalogs()->create($data);
    }

    public function updateCatalog(Catalog $catalog, array $data): Catalog
    {
        $catalog->update($data);
        return $catalog;
    }

    public function deleteCatalog(Catalog $catalog): void
    {
        $products = Product::query()
            ->whereIn('section_id', $catalog->sections()->pluck('id'))
            ->whereNotNull('image')
            ->get(['image']);

        $this->deleteStoredProductImages($products);
        $catalog->delete();
    }

    public function createSection(Catalog $catalog, array $data): Section
    {
        return $catalog->sections()->create($data);
    }

    public function updateSection(Section $section, array $data): Section
    {
        $section->update($data);
        return $section;
    }

    public function deleteSection(Section $section): void
    {
        $this->deleteStoredProductImages(
            $section->products()->whereNotNull('image')->get(['image'])
        );
        $section->delete();
    }

    public function createProduct(Section $section, int $restaurantId, array $data, $imageFile = null): Product
    {
        $data['restaurant_id'] = $restaurantId;

        if ($imageFile) {
            $data['image'] = $this->storeProductImage($imageFile);
        }

        $product = $section->products()->create($data);
        $product->refresh();

        return $product;
    }

    public function updateProduct(Product $product, array $data, $imageFile = null, bool $removeImage = false): Product
    {
        if ($removeImage && $product->image) {
            Storage::disk('public')->delete($product->image);
            $data['image'] = null;
        }

        if ($imageFile) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $this->storeProductImage($imageFile);
        }

        $product->update($data);
        $product->refresh();

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }

    public function exportCatalogPdf(Restaurant $restaurant)
    {
        $restaurant->load([
            'catalogs' => function ($catalogQuery) {
                $catalogQuery->orderBy('order')->with([
                    'sections' => function ($sectionQuery) {
                        $sectionQuery->orderBy('order')->with([
                            'products' => function ($productQuery) {
                                $productQuery->orderBy('name');
                            },
                        ]);
                    },
                ]);
            },
        ]);

        $pdf = Pdf::loadView('pdf.menu-export', [
            'restaurant' => $restaurant,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $fileName = 'menus-' . Str::slug((string) $restaurant->name, '-') . '-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function getRestaurantsStats($restaurants): array
    {
        return $restaurants->map(function ($restaurant) {
            $catalogs = $restaurant->catalogs;
            $totalProducts = 0;
            $productsPerMenu = [];

            foreach ($catalogs as $catalog) {
                $catalogProducts = 0;
                foreach ($catalog->sections as $section) {
                    $catalogProducts += $section->products->count();
                }
                $productsPerMenu[] = [
                    'menu_name' => $catalog->name,
                    'products_count' => $catalogProducts,
                ];
                $totalProducts += $catalogProducts;
            }

            return [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'address' => $restaurant->address ?? '',
                'phone' => $restaurant->phone ?? '',
                'menus_count' => $catalogs->count(),
                'total_products' => $totalProducts,
                'products_per_menu' => $productsPerMenu,
            ];
        })->all();
    }
}
