<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Section;
use App\Models\User;
use App\Support\CacheKeys;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class CatalogService
{
    private function imageDisk(): string
    {
        return config('filesystems.image_disk', 'public');
    }

    /**
     * Invalidate the public catalog cache for the given restaurant.
     * Controllers call this instead of referencing CacheKeys directly.
     */
    public function forgetRestaurantCache(int $restaurantId): void
    {
        Cache::forget(CacheKeys::restaurantCatalogs($restaurantId));
    }

    /**
     * Build the catalog query with section/product eager-loads already applied.
     * Encapsulates the nested query logic so controllers stay thin.
     */
    public function buildCatalogsQuery(Restaurant $restaurant, bool $isManagementView, ?string $search, ?bool $activeFilter)
    {
        $query = $restaurant->catalogs()->orderBy('order');

        if (!$isManagementView) {
            $query->where('active', true);
        }

        $query->with(['sections' => function ($q) use ($isManagementView, $search, $activeFilter) {
            if (!$isManagementView) {
                $q->where('active', true);
            }
            $q->orderBy('order')
              ->with(['products' => function ($pq) use ($isManagementView, $search, $activeFilter) {
                  if (!$isManagementView) {
                      $pq->where('active', true);
                  }
                  $pq->when($search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
                     ->when($activeFilter !== null, fn ($q) => $q->where('active', $activeFilter));
                  $pq->orderBy('name');
              }]);
        }]);

        return $query;
    }

    /**
     * Fetch catalogs for display, applying the public cache for non-management views.
     */
    public function getCatalogsForDisplay(Restaurant $restaurant, bool $isManagementView, ?string $search, ?bool $activeFilter)
    {
        $query = $this->buildCatalogsQuery($restaurant, $isManagementView, $search, $activeFilter);

        if ($isManagementView) {
            return $query->get();
        }

        return Cache::remember(
            CacheKeys::restaurantCatalogs($restaurant->id),
            CacheKeys::CATALOG_TTL,
            fn () => $query->get()
        );
    }

    public function storeProductImage($image): string
    {
        $disk      = $this->imageDisk();
        $imageName = Str::uuid()->toString() . '.jpg';
        $path      = 'products/' . $imageName;

        // Compress: scale down to max 1200 px wide and encode as JPEG 80 %.
        // Reduces typical phone uploads from 3–8 MB to ~100–400 KB.
        $manager = new ImageManager(new GdDriver());
        $encoded = $manager->read($image->getRealPath())
            ->scaleDown(width: 1200)
            ->toJpeg(quality: 80);

        // Do NOT pass ['visibility' => 'public'] — R2 rejects per-object ACL headers.
        // Public access is controlled at bucket level in Cloudflare dashboard.
        $ok = Storage::disk($disk)->put($path, (string) $encoded);

        if ($ok === false) {
            throw new \RuntimeException('No se pudo guardar la imagen del producto');
        }

        return $path;
    }

    public function deleteStoredProductImages(iterable $products): void
    {
        foreach ($products as $product) {
            if (!empty($product->image)) {
                Storage::disk($this->imageDisk())->delete($product->image);
            }
        }
    }

    public function createCatalog(Restaurant $restaurant, array $data, ?User $admin = null): Catalog
    {
        if ($admin && $admin->hasRole('admin') && !$admin->hasRole('superadmin')) {
            $limit = $admin->max_catalogs;
            if ($limit !== null) {
                $managedIds = $admin->managedRestaurantIds();
                $current = Catalog::whereIn('restaurant_id', $managedIds)->count();
                if ($current >= $limit) {
                    throw new BusinessException(
                        "Has alcanzado el límite de {$limit} catálogo(s) permitido(s) para tu cuenta.",
                        403
                    );
                }
            }
        }

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

    public function createProduct(Section $section, int $restaurantId, array $data, $imageFile = null, ?User $admin = null): Product
    {
        if ($admin && $admin->hasRole('admin') && !$admin->hasRole('superadmin')) {
            $limit = $admin->max_products;
            if ($limit !== null) {
                $managedIds = $admin->managedRestaurantIds();
                $current = Product::whereIn('restaurant_id', $managedIds)->count();
                if ($current >= $limit) {
                    throw new BusinessException(
                        "Has alcanzado el límite de {$limit} producto(s) permitido(s) para tu cuenta.",
                        403
                    );
                }
            }
        }

        $data['restaurant_id'] = $restaurantId;

        if ($imageFile) {
            $data['image'] = $this->storeProductImage($imageFile);
            if (!array_key_exists('show_image', $data)) {
                $data['show_image'] = true;
            }
        }

        $product = $section->products()->create($data);
        $product->refresh();

        return $product;
    }

    public function updateProduct(Product $product, array $data, $imageFile = null, bool $removeImage = false): Product
    {
        if ($removeImage && $product->image) {
            Storage::disk($this->imageDisk())->delete($product->image);
            $data['image'] = null;
            if (!array_key_exists('show_image', $data)) {
                $data['show_image'] = false;
            }
        }

        if ($imageFile) {
            if ($product->image) {
                Storage::disk($this->imageDisk())->delete($product->image);
            }
            $data['image'] = $this->storeProductImage($imageFile);
            if (!array_key_exists('show_image', $data)) {
                $data['show_image'] = true;
            }
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
                // Use withCount-preloaded aggregate instead of loading full product rows.
                $catalogProducts = 0;
                foreach ($catalog->sections as $section) {
                    $catalogProducts += (int) ($section->products_count ?? 0);
                }
                $productsPerMenu[] = [
                    'menu_name'      => $catalog->name,
                    'products_count' => $catalogProducts,
                ];
                $totalProducts += $catalogProducts;
            }

            return [
                'id'               => $restaurant->id,
                'name'             => $restaurant->name,
                'address'          => $restaurant->address ?? '',
                'phone'            => $restaurant->phone ?? '',
                'menus_count'      => $catalogs->count(),
                'total_products'   => $totalProducts,
                'products_per_menu' => $productsPerMenu,
            ];
        })->all();
    }

    /**
     * Import a full catalog (with sections and products) from a decoded JSON array.
     * No images are imported — products are created without images.
     */
    public function importFromJson(Restaurant $restaurant, array $data, ?User $admin = null): Catalog
    {
        // Respect catalog limit for admin users
        if ($admin && $admin->hasRole('admin') && !$admin->hasRole('superadmin')) {
            $limit = $admin->max_catalogs;
            if ($limit !== null) {
                $managedIds = $admin->managedRestaurantIds();
                $current = Catalog::whereIn('restaurant_id', $managedIds)->count();
                if ($current >= $limit) {
                    throw new BusinessException(
                        "Has alcanzado el límite de {$limit} catálogo(s) permitido(s) para tu cuenta.",
                        403
                    );
                }
            }
        }

        $catalog = $restaurant->catalogs()->create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'active'      => $data['active'] ?? true,
            'order'       => $data['order'] ?? 0,
        ]);

        foreach ($data['sections'] ?? [] as $sectionData) {
            $section = $catalog->sections()->create([
                'name'        => $sectionData['name'],
                'description' => $sectionData['description'] ?? null,
                'active'      => $sectionData['active'] ?? true,
                'order'       => $sectionData['order'] ?? 0,
            ]);

            // Respect product limit for admin users
            if ($admin && $admin->hasRole('admin') && !$admin->hasRole('superadmin')) {
                $productLimit = $admin->max_products;
                if ($productLimit !== null) {
                    $managedIds = $admin->managedRestaurantIds();
                    $current = Product::whereIn('restaurant_id', $managedIds)->count();
                    $incoming = count($sectionData['products'] ?? []);
                    if (($current + $incoming) > $productLimit) {
                        throw new BusinessException(
                            "Importación cancelada: se superaría el límite de {$productLimit} producto(s) permitido(s).",
                            403
                        );
                    }
                }
            }

            foreach ($sectionData['products'] ?? [] as $productData) {
                $section->products()->create([
                    'restaurant_id' => $restaurant->id,
                    'name'          => $productData['name'],
                    'description'   => $productData['description'] ?? null,
                    'price'         => (float) ($productData['price'] ?? 0),
                    'active'        => $productData['active'] ?? true,
                    'is_new'        => $productData['is_new'] ?? false,
                    'allergens'     => $productData['allergens'] ?? [],
                    'diet_tags'     => $productData['diet_tags'] ?? [],
                    'show_image'    => false,
                ]);
            }
        }

        $catalog->load('sections.products');

        return $catalog;
    }
}
