<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private const VALID_PERIODS = ['all', '7d', '30d'];
    private const CACHE_TTL = 300; // 5 minutes

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
            'event_type'    => 'required|string|max:50',
            'session_id'    => 'nullable|string|max:64',
            'metadata'      => 'nullable|array',
            'metadata.*'    => 'nullable|string|max:255',
        ]);

        AnalyticsEvent::create([
            'restaurant_id' => $data['restaurant_id'],
            'event_type'    => $data['event_type'],
            'session_id'    => $data['session_id'] ?? null,
            'ip_address'    => $request->ip(),
            'user_agent'    => mb_substr($request->userAgent() ?? '', 0, 500),
            'metadata'      => $data['metadata'] ?? null,
            'created_at'    => now(),
        ]);

        // Bust cached rankings for all periods so fresh data is served soon
        foreach (self::VALID_PERIODS as $period) {
            Cache::forget("analytics.ranking.{$period}");
            Cache::forget("analytics.top.{$period}");
        }

        return response()->json(['ok' => true], 201);
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
