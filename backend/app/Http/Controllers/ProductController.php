<?php

namespace App\Http\Controllers;

use App\Http\Resources\CatalogResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SectionResource;
use App\Models\Product;
use App\Models\Restaurant;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const ALLOWED_DIET_TAGS = [
        'vegan', 'vegetarian', 'gluten_free', 'lactose_free',
        'keto', 'halal', 'spicy', 'low_calorie',
    ];

    private const ALLOWED_ALLERGENS = [
        'gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy',
        'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulfites',
        'lupins', 'mollusks',
    ];

    public function __construct(private CatalogService $catalogService)
    {
    }

    private function normalizeAllergensInput(Request $request): void
    {
        if (!$request->exists('allergens')) return;
        $allergens = $request->input('allergens');
        if (is_string($allergens)) {
            $decoded = json_decode($allergens, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['allergens' => $decoded]);
                return;
            }
        }
        if ($allergens === null || $allergens === '') {
            $request->merge(['allergens' => []]);
        }
    }

    private function normalizeDietTagsInput(Request $request): void
    {
        if (!$request->exists('diet_tags')) return;
        $dietTags = $request->input('diet_tags');
        if (is_string($dietTags)) {
            $decoded = json_decode($dietTags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['diet_tags' => $decoded]);
                return;
            }
        }
        if ($dietTags === null || $dietTags === '') {
            $request->merge(['diet_tags' => []]);
        }
    }

    protected function authorizeRestaurant($restaurantId)
    {
        $user = Auth::user();
        if (!$user) {
            abort(response()->json(['message' => 'No autenticado'], 401));
        }
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $restaurant = Restaurant::find($restaurantId);
        if (!$restaurant) {
            abort(response()->json(['message' => 'Restaurante no encontrado'], 404));
        }

        if ($user->hasRole('superadmin')) {
            return $restaurant;
        }

        if ($user->hasRole('admin')) {
            if (!$this->canAccessRestaurant($user, (int) $restaurantId)) {
                abort(response()->json(['message' => 'No tienes permiso para acceder a este restaurante'], 403));
            }
            return $restaurant;
        }

        abort(response()->json(['message' => 'No autorizado'], 403));
    }

    public function getRestaurantsStats()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if ($user->hasRole('superadmin')) {
            $restaurants = Restaurant::with(['catalogs.sections.products'])->get();
        } elseif ($user->hasRole('admin')) {
            $restaurantIds = $this->managedRestaurantIds($user);
            $restaurants = Restaurant::with(['catalogs.sections.products'])
                ->whereIn('id', $restaurantIds)
                ->get();
        } else {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json($this->catalogService->getRestaurantsStats($restaurants));
    }

    public function getCatalogsByRestaurant($restaurantId, Request $request)
    {
        $user = $request->user('sanctum');
        if (!$user && $request->bearerToken()) {
            $user = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
            $user?->load('role');
        }

        $restaurant = Restaurant::find($restaurantId);
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurante no encontrado'], 404);
        }

        if ($user) {
            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }
            if ($user->hasRole('admin') && !$this->canAccessRestaurant($user, (int) $restaurantId)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        } elseif (!$restaurant->active) {
            return response()->json(['message' => 'Restaurante no disponible'], 404);
        }

        $isManagementView = $user && $user->hasAnyRole(['superadmin', 'admin']);
        $search = $request->query('search');
        $activeFilter = $request->has('active') ? filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN) : null;

        $catalogsQuery = $restaurant->catalogs()->orderBy('order');

        $catalogsQuery->with(['sections' => function ($query) use ($isManagementView, $search, $activeFilter) {
            if (!$isManagementView) {
                $query->where('active', true);
            }

            $query->orderBy('order')
                ->with(['products' => function ($q) use ($isManagementView, $search, $activeFilter) {
                    if (!$isManagementView) {
                        $q->where('active', true);
                    }
                    $q->when($search, fn ($pq, $s) => $pq->where('name', 'ilike', "%{$s}%"))
                      ->when($activeFilter !== null, fn ($pq) => $pq->where('active', $activeFilter));
                    $q->orderBy('name');
                }]);
        }]);

        if (!$isManagementView) {
            $catalogsQuery->where('active', true);
        }

        if (!$isManagementView) {
            $catalogs = Cache::remember("restaurant_{$restaurantId}_catalogs", 300, function () use ($catalogsQuery) {
                return $catalogsQuery->get();
            });
        } else {
            $catalogs = $catalogsQuery->get();
        }

        return CatalogResource::collection($catalogs);
    }

    public function exportCatalogsPdf($restaurantId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        return $this->catalogService->exportCatalogPdf($restaurant);
    }

    public function storeCatalog(Request $request, $restaurantId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado para gestionar el menú'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $catalog = $this->catalogService->createCatalog($restaurant, $validated);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return (new CatalogResource($catalog))->response()->setStatusCode(201);
    }

    public function updateCatalog(Request $request, $restaurantId, $catalogId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:2000',
            'active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $catalog = $this->catalogService->updateCatalog($catalog, $validated);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return new CatalogResource($catalog);
    }

    public function deleteCatalog($restaurantId, $catalogId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $this->catalogService->deleteCatalog($catalog);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return response()->json(['message' => 'Catálogo eliminado']);
    }

    public function storeSection(Request $request, $restaurantId, $catalogId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $section = $this->catalogService->createSection($catalog, $validated);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function updateSection(Request $request, $restaurantId, $catalogId, $sectionId)
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

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:2000',
            'active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $section = $this->catalogService->updateSection($section, $validated);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return new SectionResource($section);
    }

    public function deleteSection($restaurantId, $catalogId, $sectionId)
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

        $this->catalogService->deleteSection($section);
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return response()->json(['message' => 'Sección eliminada']);
    }

    public function storeProduct(Request $request, $restaurantId, $catalogId, $sectionId)
    {
        $this->normalizeAllergensInput($request);
        $this->normalizeDietTagsInput($request);

        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'active' => 'boolean',
            'show_image' => 'boolean',
            'is_new' => 'boolean',
            'allergens' => 'nullable|array',
            'allergens.*' => ['string', Rule::in(self::ALLOWED_ALLERGENS)],
            'diet_tags' => 'nullable|array',
            'diet_tags.*' => ['string', Rule::in(self::ALLOWED_DIET_TAGS)],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $productData = $request->only([
            'name', 'description', 'price', 'active', 'show_image', 'is_new', 'allergens', 'diet_tags',
        ]);

        $product = $this->catalogService->createProduct(
            $section,
            $restaurant->id,
            $productData,
            $request->hasFile('image') ? $request->file('image') : null
        );

        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function updateProduct(Request $request, $restaurantId, $catalogId, $sectionId, $productId)
    {
        $this->normalizeAllergensInput($request);
        $this->normalizeDietTagsInput($request);

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

        $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'numeric|min:0',
            'active' => 'boolean',
            'show_image' => 'boolean',
            'is_new' => 'boolean',
            'allergens' => 'nullable|array',
            'allergens.*' => ['string', Rule::in(self::ALLOWED_ALLERGENS)],
            'diet_tags' => 'nullable|array',
            'diet_tags.*' => ['string', Rule::in(self::ALLOWED_DIET_TAGS)],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_image' => 'boolean',
        ]);

        $data = $request->only([
            'name', 'description', 'price', 'active', 'show_image', 'is_new', 'allergens', 'diet_tags',
        ]);

        $product = $this->catalogService->updateProduct(
            $product,
            $data,
            $request->hasFile('image') ? $request->file('image') : null,
            $request->boolean('remove_image')
        );

        Cache::forget("restaurant_{$restaurantId}_catalogs");

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
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return response()->json(['message' => 'Producto eliminado']);
    }
}
