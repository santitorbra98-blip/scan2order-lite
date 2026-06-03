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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $restaurants = Restaurant::with([
                'catalogs.sections' => fn ($q) => $q->withCount('products'),
            ])->get();
        } elseif ($user->hasRole('admin')) {
            $restaurantIds = $this->managedRestaurantIds($user);
            $restaurants = Restaurant::with([
                'catalogs.sections' => fn ($q) => $q->withCount('products'),
            ])->whereIn('id', $restaurantIds)->get();
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
        $rawSearch = $request->query('search');
        $search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 100) : null;
        $search = ($search !== '') ? $search : null;
        $activeFilter = $request->has('active') ? filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN) : null;

        $catalogs = $this->catalogService->getCatalogsForDisplay($restaurant, $isManagementView, $search, $activeFilter);

        return CatalogResource::collection($catalogs);
    }

    public function exportCatalogsPdf($restaurantId)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $restaurant = $this->authorizeRestaurant($restaurantId);
        return $this->catalogService->exportCatalogPdf($restaurant);
    }

    public function importJson(Request $request, $restaurantId)
    {
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

        // Enforce a max body size (2 MB) to prevent memory exhaustion attacks.
        if (strlen($json) > 2 * 1024 * 1024) {
            return response()->json(['message' => 'El JSON supera el tamaño máximo permitido (2 MB)'], 422);
        }

        // Limit JSON nesting depth to prevent deeply-nested payloads from
        // consuming excessive memory/CPU during parsing.
        $data = json_decode($json, true, 8);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return response()->json(['message' => 'JSON inválido: ' . json_last_error_msg()], 422);
        }

        if (empty($data['name'])) {
            return response()->json(['message' => 'El campo "name" del catálogo es obligatorio'], 422);
        }

        try {
            $catalog = $this->catalogService->importFromJson($restaurant, $data, Auth::user());
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }

        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return (new CatalogResource($catalog))->response()->setStatusCode(201);
    }

    public function storeCatalog(StoreCatalogRequest $request, $restaurantId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);

        try {
            $catalog = $this->catalogService->createCatalog($restaurant, $request->validated(), Auth::user());
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return (new CatalogResource($catalog))->response()->setStatusCode(201);
    }

    public function updateCatalog(UpdateCatalogRequest $request, $restaurantId, $catalogId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $catalog = $this->catalogService->updateCatalog($catalog, $request->validated());
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return new CatalogResource($catalog);
    }

    public function deleteCatalog($restaurantId, $catalogId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $this->catalogService->deleteCatalog($catalog);
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return response()->json(['message' => 'Catálogo eliminado']);
    }

    public function storeSection(StoreSectionRequest $request, $restaurantId, $catalogId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) {
            return response()->json(['message' => 'Catálogo no encontrado'], 404);
        }

        $section = $this->catalogService->createSection($catalog, $request->validated());
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function updateSection(UpdateSectionRequest $request, $restaurantId, $catalogId, $sectionId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $section = $this->catalogService->updateSection($section, $request->validated());
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return new SectionResource($section);
    }

    public function deleteSection($restaurantId, $catalogId, $sectionId)
    {
        $restaurant = $this->authorizeRestaurant($restaurantId);
        $catalog = $restaurant->catalogs()->find($catalogId);
        if (!$catalog) return response()->json(['message' => 'Catálogo no encontrado'], 404);

        $section = $catalog->sections()->find($sectionId);
        if (!$section) return response()->json(['message' => 'Sección no encontrada'], 404);

        $this->catalogService->deleteSection($section);
        $this->catalogService->forgetRestaurantCache((int) $restaurantId);

        return response()->json(['message' => 'Sección eliminada']);
    }
}
