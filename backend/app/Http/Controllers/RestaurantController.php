<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Resources\RestaurantResource;
use App\Jobs\TrackAnalyticsEvent;
use App\Models\Restaurant;
use App\Services\RestaurantService;
use App\Support\CacheKeys;
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

    private function loadForPublic(Restaurant $restaurant): Restaurant
    {
        // Public visitors only need the base restaurant fields.
        // The admins and creator relations are intentionally NOT loaded
        // (they're guarded by $this->whenLoaded / $this->when($isAuthenticated)
        // in RestaurantResource, so they won't appear in the response).
        return $restaurant;
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
                Restaurant::with(['admins' => $adminSelector, 'creator'])->paginate(25)
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

        // Public: only active restaurants, cached for 60 s.
        // Cache is busted in store() and update() so new/toggled restaurants
        // appear within one minute at most.
        $restaurants = Cache::remember(CacheKeys::publicRestaurants(), CacheKeys::PUBLIC_RESTAURANTS_TTL, function () use ($adminSelector) {
            return Restaurant::with(['admins' => $adminSelector])
                ->where('active', true)
                ->get();
        });

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
            'name'     => 'required|string|max:255',
            'address'  => 'nullable|string|max:500',
            'phone'    => 'nullable|string|max:30',
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'active'   => 'boolean',
            'schedule'              => 'nullable|array|max:7',
            'schedule.*'            => 'array|max:3',
            'schedule.*.enabled'    => 'boolean',
            'schedule.*.open'       => ['nullable', 'string', 'max:10', fn ($a, $v, $fail) => $v !== null && !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $v) ? $fail('Formato de hora inválido (HH:MM).') : null],
            'schedule.*.close'      => ['nullable', 'string', 'max:10', fn ($a, $v, $fail) => $v !== null && !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $v) ? $fail('Formato de hora inválido (HH:MM).') : null],
        ]);

        $data = array_filter($request->only(['name', 'address', 'phone', 'active', 'schedule']), fn ($v) => $v !== null);

        try {
            $restaurant = $this->restaurantService->createRestaurant(
                $data,
                $user,
                $request->hasFile('image') ? $request->file('image') : null
            );

            Cache::forget(CacheKeys::publicRestaurants());

            return (new RestaurantResource($this->loadRestaurantWithRelations($restaurant)))
                ->response()
                ->setStatusCode(201);
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Unexpected error in restaurant store', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return response()->json(['message' => 'Error interno del servidor'], 500);
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

        // Track every public menu view asynchronously so the HTTP response
        // is not blocked by a synchronous DB write.
        if (!$user) {
            $req = request();
            $sessionProxy = md5(($req->ip() ?? '') . '|' . ($req->userAgent() ?? ''));
            TrackAnalyticsEvent::dispatch(
                $restaurant->id,
                'menu_view',
                $sessionProxy,
                $req->ip(),
                mb_substr($req->userAgent() ?? '', 0, 500),
            );
        }

        // Public visitors do not need the admins relation — skip the JOIN.
        if (!$user) {
            return new RestaurantResource($this->loadForPublic($restaurant));
        }

        return new RestaurantResource($this->loadRestaurantWithRelations($restaurant));
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $user = $request->user('sanctum');
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            if (!$this->canAccessRestaurant($user, (int) $restaurant->id)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $this->normalizeScheduleInput($request);

        $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'address'      => 'nullable|string|max:500',
            'phone'        => 'nullable|string|max:30',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_image' => 'boolean',
            'active'       => 'boolean',
            'schedule'              => 'nullable|array|max:7',
            'schedule.*'            => 'array|max:3',
            'schedule.*.enabled'    => 'boolean',
            'schedule.*.open'       => ['nullable', 'string', 'max:10', fn ($a, $v, $fail) => $v !== null && !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $v) ? $fail('Formato de hora inválido (HH:MM).') : null],
            'schedule.*.close'      => ['nullable', 'string', 'max:10', fn ($a, $v, $fail) => $v !== null && !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $v) ? $fail('Formato de hora inválido (HH:MM).') : null],
        ]);

        $data = $request->only(['name', 'address', 'phone', 'active', 'schedule']);

        try {
            $restaurant = $this->restaurantService->updateRestaurant(
                $restaurant,
                $data,
                $request->hasFile('image') ? $request->file('image') : null,
                $request->boolean('remove_image')
            );

            Cache::forget(CacheKeys::publicRestaurants());

            return new RestaurantResource($this->loadRestaurantWithRelations($restaurant));
        } catch (BusinessException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function destroy(Restaurant $restaurant)
    {
        $user = request()->user('sanctum');
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            if (!$this->canAccessRestaurant($user, (int) $restaurant->id)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $restaurantId = $restaurant->id;
        $this->restaurantService->deleteRestaurant($restaurant);

        Cache::forget(CacheKeys::publicRestaurants());
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
