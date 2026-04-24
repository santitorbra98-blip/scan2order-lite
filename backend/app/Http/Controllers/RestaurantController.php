<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Resources\RestaurantResource;
use App\Models\Product;
use App\Models\Restaurant;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RestaurantController extends Controller
{
    public function __construct(private RestaurantService $restaurantService)
    {
    }

    private function normalizeScheduleInput(Request $request): void
    {
        if (!$request->exists('schedule')) {
            return;
        }
        $schedule = $request->input('schedule');
        if (is_string($schedule)) {
            $decoded = json_decode($schedule, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['schedule' => $decoded]);
            }
        }
    }

    private function loadRestaurantWithRelations(Restaurant $restaurant): Restaurant
    {
        return $restaurant->load([
            'admins' => fn ($query) => $query->select('users.id', 'users.name', 'users.email', 'users.phone'),
        ]);
    }

    public function index(Request $request)
    {
        $currentUser = $request->user('sanctum');
        if (!$currentUser && $request->bearerToken()) {
            $currentUser = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
            $currentUser?->load('role');
        }
        $adminSelector = fn ($query) => $query->select('users.id', 'users.name', 'users.email', 'users.phone');

        if ($currentUser && $currentUser->hasRole('superadmin')) {
            return RestaurantResource::collection(
                Restaurant::with(['admins' => $adminSelector])->paginate(25)
            );
        }

        if ($currentUser && $currentUser->hasRole('admin')) {
            $restaurantIds = $this->managedRestaurantIds($currentUser);
            if (empty($restaurantIds)) {
                return RestaurantResource::collection(collect());
            }

            return RestaurantResource::collection(
                Restaurant::with(['admins' => $adminSelector])
                    ->whereIn('id', $restaurantIds)
                    ->get()
            );
        }

        // Public: only active restaurants — no server-side cache so new
        // restaurants appear immediately after creation.
        $restaurants = Restaurant::with(['admins' => $adminSelector])
            ->where('active', true)
            ->get();

        return RestaurantResource::collection($restaurants);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $this->normalizeScheduleInput($request);

        $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'active' => 'boolean',
            'schedule' => 'nullable|array',
        ]);

        $data = array_filter($request->only(['name', 'address', 'phone', 'active', 'schedule']), fn ($v) => $v !== null);

        try {
            $restaurant = $this->restaurantService->createRestaurant(
                $data,
                $user,
                $request->hasFile('image') ? $request->file('image') : null
            );

            Cache::forget('public_restaurants');

            return (new RestaurantResource($this->loadRestaurantWithRelations($restaurant)))
                ->response()
                ->setStatusCode(201);
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function show(Restaurant $restaurant)
    {
        $user = request()->user();

        if ($user && $user->hasRole('superadmin')) {
            return new RestaurantResource($this->loadRestaurantWithRelations($restaurant));
        }

        if ($user && $user->hasRole('admin') && !$this->canAccessRestaurant($user, (int) $restaurant->id)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!$user && !$restaurant->active) {
            return response()->json(['message' => 'Restaurante no disponible'], 404);
        }

        return new RestaurantResource($this->loadRestaurantWithRelations($restaurant));
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            $isLinkedAdmin = $restaurant->admins()->where('users.id', $user->id)->exists();
            if (!$isLinkedAdmin && $restaurant->created_by !== $user->id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $this->normalizeScheduleInput($request);

        $request->validate([
            'name' => 'sometimes|required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_image' => 'boolean',
            'active' => 'boolean',
            'schedule' => 'nullable|array',
        ]);

        $data = $request->only(['name', 'address', 'phone', 'active', 'schedule']);

        try {
            $restaurant = $this->restaurantService->updateRestaurant(
                $restaurant,
                $data,
                $request->hasFile('image') ? $request->file('image') : null,
                $request->boolean('remove_image')
            );

            Cache::forget('public_restaurants');
            Cache::forget("restaurant_{$restaurant->id}");

            return new RestaurantResource($this->loadRestaurantWithRelations($restaurant));
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function destroy(Restaurant $restaurant)
    {
        $user = request()->user();
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            $isLinkedAdmin = $restaurant->admins()->where('users.id', $user->id)->exists();
            if (!$isLinkedAdmin && $restaurant->created_by !== $user->id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $restaurantId = $restaurant->id;
        $restaurant->delete();

        Cache::forget('public_restaurants');
        Cache::forget("restaurant_{$restaurantId}");
        Cache::forget("restaurant_{$restaurantId}_catalogs");

        return response()->json(null, 204);
    }

    public function syncAdmins(Request $request, Restaurant $restaurant)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Verify the admin actually manages this restaurant (superadmin bypasses)
        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            if (!$this->canAccessRestaurant($user, (int) $restaurant->id)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $data = $request->validate([
            'admin_ids' => 'required|array|size:1',
            'admin_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $this->restaurantService->syncAdmins($restaurant, $data['admin_ids'], $user);
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }

        return response()->json([
            'message' => 'Admins del restaurante actualizados',
            'restaurant' => new RestaurantResource($this->loadRestaurantWithRelations($restaurant)),
        ]);
    }
}
