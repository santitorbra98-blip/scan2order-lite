<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\StoreCatalogRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateCatalogRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Http\Resources\CatalogResource;
use App\Http\Resources\SectionResource;
use App\Models\Restaurant;
use App\Services\CatalogService;
use App\Support\CacheKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalogService)
    {
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
            $catalogs = Cache::remember(CacheKeys::restaurantCatalogs((int) $restaurantId), 300, function () use ($catalogsQuery) {
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

    public function importJson(Request $request, $restaurantId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado para gestionar el menú'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);

        // Accept either an uploaded .json file OR a raw JSON body
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($file->getClientOriginalExtension() !== 'json' && $file->getMimeType() !== 'application/json') {
                return response()->json(['message' => 'El archivo debe ser un JSON válido'], 422);
            }
            $json = file_get_contents($file->getRealPath());
        } else {
            $json = $request->getContent();
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return response()->json(['message' => 'JSON inválido: ' . json_last_error_msg()], 422);
        }

        if (empty($data['name'])) {
            return response()->json(['message' => 'El campo "name" del catálogo es obligatorio'], 422);
        }

        try {
            $catalog = $this->catalogService->importFromJson($restaurant, $data, $user);
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }

        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return (new CatalogResource($catalog))->response()->setStatusCode(201);
    }

    public function storeCatalog(StoreCatalogRequest $request, $restaurantId)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_products')) {
            return response()->json(['message' => 'No autorizado para gestionar el menú'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);

        try {
            $catalog = $this->catalogService->createCatalog($restaurant, $request->validated(), $user);
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return (new CatalogResource($catalog))->response()->setStatusCode(201);
    }

    public function updateCatalog(UpdateCatalogRequest $request, $restaurantId, $catalogId)
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

        $catalog = $this->catalogService->updateCatalog($catalog, $request->validated());
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

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
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return response()->json(['message' => 'Catálogo eliminado']);
    }

    public function storeSection(StoreSectionRequest $request, $restaurantId, $catalogId)
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

        $section = $this->catalogService->createSection($catalog, $request->validated());
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function updateSection(UpdateSectionRequest $request, $restaurantId, $catalogId, $sectionId)
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

        $section = $this->catalogService->updateSection($section, $request->validated());
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

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
        Cache::forget(CacheKeys::restaurantCatalogs((int) $restaurantId));

        return response()->json(['message' => 'Sección eliminada']);
    }
}
