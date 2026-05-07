<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(private CatalogService $catalogService)
    {
    }

    public function storeProduct(StoreProductRequest $request, $restaurantId, $catalogId, $sectionId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $productData = $request->only([
            'name', 'description', 'price', 'active', 'show_image', 'is_new', 'allergens', 'diet_tags',
        ]);

        try {
            $product = $this->catalogService->createProduct(
                $section,
                $restaurant->id,
                $productData,
                $request->hasFile('image') ? $request->file('image') : null,
                $user
            );
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }

        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function updateProduct(UpdateProductRequest $request, $restaurantId, $catalogId, $sectionId, $productId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $product = $section->products()->find($productId);
        if (!$product) return response()->json(['message' => 'Producto no encontrado'], 404);

        $data = $request->only([
            'name', 'description', 'price', 'active', 'show_image', 'is_new', 'allergens', 'diet_tags',
        ]);

        $product = $this->catalogService->updateProduct(
            $product,
            $data,
            $request->hasFile('image') ? $request->file('image') : null,
            $request->boolean('remove_image')
        );

        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return new ProductResource($product);
    }

    public function deleteProduct($restaurantId, $catalogId, $sectionId, $productId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $product = $section->products()->find($productId);
        if (!$product) return response()->json(['message' => 'Producto no encontrado'], 404);

        $this->catalogService->deleteProduct($product);
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return response()->json(['message' => 'Producto eliminado']);
    }
}
