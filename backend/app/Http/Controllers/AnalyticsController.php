<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    private const VALID_PERIODS = ['all', '7d', '30d'];
    private const CACHE_TTL = 300; // 5 minutes

    /** Allowlist of accepted event types from public clients. */
    private const VALID_EVENT_TYPES = [
        'menu_view',
        'catalog_view',
        'product_view',
        'qr_scan',
        'contact_click',
        'schedule_view',
    ];

    private function resolveStartDate(string $period): ?\Carbon\Carbon
    {
        return match ($period) {
            '7d'    => now()->subDays(7),
            '30d'   => now()->subDays(30),
            default => null,
        };
    }

    private function buildRankingQuery(string $period)
    {
        $query = DB::table('analytics_events as ae')
            ->join('restaurants as r', 'ae.restaurant_id', '=', 'r.id')
            ->select(
                'ae.restaurant_id',
                'r.name as restaurant_name',
                DB::raw('COUNT(*) as total_visits'),
                DB::raw('COUNT(DISTINCT ae.session_id) as unique_visits')
            )
            ->where('r.active', true)
            ->groupBy('ae.restaurant_id', 'r.name')
            ->orderByDesc('total_visits');

        $startDate = $this->resolveStartDate($period);
        if ($startDate) {
            $query->where('ae.created_at', '>=', $startDate);
        }

        return $query;
    }

    private function mapRows($rows): array
    {
        return $rows->map(fn ($row) => [
            'restaurant_id'   => $row->restaurant_id,
            'restaurant_name' => $row->restaurant_name,
            'total_visits'    => (int) $row->total_visits,
            'unique_visits'   => (int) $row->unique_visits,
        ])->values()->all();
    }

    /**
     * POST /api/analytics/event
     * Public endpoint to track an analytics event for a restaurant.
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'event_type'    => ['required', 'string', Rule::in(self::VALID_EVENT_TYPES)],
            'session_id'    => 'nullable|string|max:64',
            'metadata'      => 'nullable|array|max:10',
            'metadata.*'    => 'nullable|string|max:255',
        ]);

        // Restrict metadata keys to alphanumeric + underscores to prevent
        // arbitrary key injection into the analytics JSON store.
        if (!empty($data['metadata'])) {
            foreach (array_keys($data['metadata']) as $key) {
                if (!preg_match('/^[a-zA-Z0-9_]{1,50}$/', (string) $key)) {
                    return response()->json(['message' => 'Las claves de metadata solo pueden contener letras, números y guiones bajos (máx. 50 caracteres).'], 422);
                }
            }
        }

        AnalyticsEvent::create([
            'restaurant_id' => $data['restaurant_id'],
            'event_type'    => $data['event_type'],
            'session_id'    => $data['session_id'] ?? null,
            'ip_address'    => $request->ip(),
            'user_agent'    => mb_substr($request->userAgent() ?? '', 0, 500),
            'metadata'      => $data['metadata'] ?? null,
            'created_at'    => now(),
        ]);

        // The cached rankings have a 5-minute TTL and will refresh naturally.
        // Busting 6 keys per event is wasteful at high traffic volumes.

        return response()->json(['ok' => true], 201);
    }

    /**
     * GET /api/analytics/my-stats?period=all|7d|30d
     * Admin: total and unique visits for their own restaurants.
     */
    public function myStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = in_array($request->query('period'), self::VALID_PERIODS, true)
            ? $request->query('period')
            : '30d';

        $restaurantIds = $user->hasRole('superadmin')
            ? null
            : $this->managedRestaurantIds($user);

        // Admin with no restaurants yet
        if ($restaurantIds !== null && empty($restaurantIds)) {
            return response()->json(['total_visits' => 0, 'unique_visits' => 0]);
        }

        $query = DB::table('analytics_events')
            ->select(
                DB::raw('COUNT(*) as total_visits'),
                DB::raw('COUNT(DISTINCT session_id) as unique_visits')
            );

        if ($restaurantIds !== null) {
            $query->whereIn('restaurant_id', $restaurantIds);
        }

        $startDate = $this->resolveStartDate($period);
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $row = $query->first();

        return response()->json([
            'total_visits'  => (int) ($row->total_visits ?? 0),
            'unique_visits' => (int) ($row->unique_visits ?? 0),
            'period'        => $period,
        ]);
    }

    /**
     * GET /api/analytics/ranking?period=all|7d|30d
     * Superadmin: full restaurant ranking (up to 50).
     */
    public function ranking(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), self::VALID_PERIODS, true)
            ? $request->query('period')
            : 'all';

        $result = Cache::remember("analytics.ranking.{$period}", self::CACHE_TTL, function () use ($period) {
            $rows = $this->buildRankingQuery($period)->limit(50)->get();
            return $this->mapRows($rows);
        });

        return response()->json($result);
    }

    /**
     * GET /api/analytics/top-restaurants?period=7d|30d|all
     * Public: top-5 most visited restaurants.
     */
    public function topRestaurants(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), self::VALID_PERIODS, true)
            ? $request->query('period')
            : '7d';

        $result = Cache::remember("analytics.top.{$period}", self::CACHE_TTL, function () use ($period) {
            $rows = $this->buildRankingQuery($period)->limit(5)->get();
            return $this->mapRows($rows);
        });

        return response()->json($result);
    }
}
