<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    private const VALID_PERIODS = ['all', '7d', '30d'];
    private const VALID_INSIGHT_PERIODS = ['7d', '30d', 'month'];
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

    private function resolveInsightRange(string $period): array
    {
        $now = now();

        return match ($period) {
            '7d' => [$now->copy()->subDays(7), $now],
            'month' => [$now->copy()->startOfMonth(), $now],
            default => [$now->copy()->subDays(30), $now],
        };
    }

    private function weekdayExpression(string $driver): string
    {
        return match ($driver) {
            'pgsql' => "EXTRACT(DOW FROM created_at)",
            'sqlite' => "strftime('%w', created_at)",
            default => "DAYOFWEEK(created_at) - 1",
        };
    }

    private function hourExpression(string $driver): string
    {
        return match ($driver) {
            'pgsql' => "EXTRACT(HOUR FROM created_at)",
            'sqlite' => "strftime('%H', created_at)",
            default => "HOUR(created_at)",
        };
    }

    private function trendByDay(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('analytics_events')
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as total')
            )
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row) => [
            'day' => (string) $row->day,
            'visits' => (int) $row->total,
        ])->values()->all();
    }

    /**
     * GET /api/analytics/restaurants/{restaurant}/insights?period=7d|30d|month
     * Admin/superadmin: detailed analytics for one managed restaurant.
     */
    public function restaurantInsights(Request $request, int $restaurant): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!$user->hasRole('superadmin') && !$this->canAccessRestaurant($user, $restaurant)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $period = in_array($request->query('period'), self::VALID_INSIGHT_PERIODS, true)
            ? $request->query('period')
            : '30d';

        $cacheKey = "analytics.restaurant.{$restaurant}.{$period}." . ($user->hasRole('superadmin') ? 'sa' : "u{$user->id}");

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($restaurant, $period) {
            [$from, $to] = $this->resolveInsightRange($period);
            $driver = DB::connection()->getDriverName();

            $current = DB::table('analytics_events')
                ->where('restaurant_id', $restaurant)
                ->whereBetween('created_at', [$from, $to]);

            $totalVisits = (clone $current)->count();
            $uniqueVisits = (clone $current)->whereNotNull('session_id')->distinct('session_id')->count('session_id');

            $newUniqueVisits = DB::table('analytics_events as ae')
                ->joinSub(
                    DB::table('analytics_events')
                        ->select('session_id', DB::raw('MIN(created_at) as first_seen_at'))
                        ->where('restaurant_id', $restaurant)
                        ->whereNotNull('session_id')
                        ->groupBy('session_id'),
                    'first_sessions',
                    fn ($join) => $join->on('ae.session_id', '=', 'first_sessions.session_id')
                )
                ->where('ae.restaurant_id', $restaurant)
                ->whereNotNull('ae.session_id')
                ->whereBetween('first_sessions.first_seen_at', [$from, $to])
                ->distinct('ae.session_id')
                ->count('ae.session_id');

            $monthStart = now()->startOfMonth();
            $prevMonthStart = $monthStart->copy()->subMonthNoOverflow();

            $currentMonthVisits = DB::table('analytics_events')
                ->where('restaurant_id', $restaurant)
                ->whereBetween('created_at', [$monthStart, now()])
                ->count();

            $previousMonthVisits = DB::table('analytics_events')
                ->where('restaurant_id', $restaurant)
                ->whereBetween('created_at', [$prevMonthStart, $monthStart])
                ->count();

            $monthlyChangePercent = 0.0;
            if ($previousMonthVisits > 0) {
                $monthlyChangePercent = round((($currentMonthVisits - $previousMonthVisits) / $previousMonthVisits) * 100, 2);
            } elseif ($currentMonthVisits > 0) {
                $monthlyChangePercent = 100.0;
            }

            $weekdayRows = DB::table('analytics_events')
                ->select(
                    DB::raw($this->weekdayExpression($driver) . ' as weekday_number'),
                    DB::raw('COUNT(*) as total')
                )
                ->where('restaurant_id', $restaurant)
                ->whereBetween('created_at', [$from, $to])
                ->groupBy(DB::raw($this->weekdayExpression($driver)))
                ->orderByDesc('total')
                ->get();

            $dayNames = [
                0 => 'Domingo',
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miércoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sábado',
            ];

            $bestDay = null;
            $bestDayVisits = 0;
            if ($weekdayRows->isNotEmpty()) {
                $topDay = $weekdayRows->first();
                $dayNum = (int) $topDay->weekday_number;
                $bestDay = $dayNames[$dayNum] ?? null;
                $bestDayVisits = (int) $topDay->total;
            }

            $hourRows = DB::table('analytics_events')
                ->select(
                    DB::raw($this->hourExpression($driver) . ' as hour_number'),
                    DB::raw('COUNT(*) as total')
                )
                ->where('restaurant_id', $restaurant)
                ->whereBetween('created_at', [$from, $to])
                ->groupBy(DB::raw($this->hourExpression($driver)))
                ->orderByDesc('total')
                ->get();

            $peakHour = null;
            if ($hourRows->isNotEmpty()) {
                $topHour = (int) $hourRows->first()->hour_number;
                $peakHour = str_pad((string) $topHour, 2, '0', STR_PAD_LEFT) . ':00';
            }

            $contactClicks = DB::table('analytics_events')
                ->where('restaurant_id', $restaurant)
                ->where('event_type', 'contact_click')
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $menuViews = DB::table('analytics_events')
                ->where('restaurant_id', $restaurant)
                ->where('event_type', 'menu_view')
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $contactRatePercent = $menuViews > 0 ? round(($contactClicks / $menuViews) * 100, 2) : 0.0;
            $returningUniqueVisits = max($uniqueVisits - $newUniqueVisits, 0);

            [$trendFrom, $trendTo] = [now()->subDays(7), now()];

            return [
                'restaurant_id' => $restaurant,
                'period' => $period,
                'range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'total_visits' => (int) $totalVisits,
                'unique_visits' => (int) $uniqueVisits,
                'new_unique_visits' => (int) $newUniqueVisits,
                'returning_unique_visits' => (int) $returningUniqueVisits,
                'month_over_month' => [
                    'current_month_visits' => (int) $currentMonthVisits,
                    'previous_month_visits' => (int) $previousMonthVisits,
                    'change_percent' => $monthlyChangePercent,
                ],
                'best_weekday' => [
                    'day' => $bestDay,
                    'visits' => $bestDayVisits,
                ],
                'peak_hour' => $peakHour,
                'contact_rate_percent' => $contactRatePercent,
                'trend_7d' => $this->trendByDay($restaurant, $trendFrom, $trendTo),
            ];
        });

        return response()->json($result);
    }
}
