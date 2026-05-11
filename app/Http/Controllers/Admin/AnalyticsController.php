<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\ReliefRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Admin overview metrics for a date range.
     */
    public function overview(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $reliefQuery = ReliefRequest::whereBetween('created_at', [$from, $to]);
        $donationQuery = Donation::whereBetween('created_at', [$from, $to]);
        $campaignQuery = Campaign::whereBetween('created_at', [$from, $to]);
        $distributionQuery = Distribution::whereBetween('created_at', [$from, $to]);

        $data = [
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'new_in_range' => User::whereBetween('created_at', [$from, $to])->count(),
            ],
            'relief_requests' => [
                'total_in_range' => $reliefQuery->count(),
                'completed_in_range' => (clone $reliefQuery)->where('status', 'completed')->count(),
                'urgent_in_range' => (clone $reliefQuery)->where('urgency_level', '>=', 4)->count(),
            ],
            'campaigns' => [
                'total_in_range' => $campaignQuery->count(),
                'active_now' => Campaign::where('status', 'open')->count(),
            ],
            'donations' => [
                'transactions_in_range' => $donationQuery->count(),
                'confirmed_transactions_in_range' => (clone $donationQuery)->where('status', 'confirmed')->count(),
                'confirmed_amount_in_range' => (float) (clone $donationQuery)->where('status', 'confirmed')->sum('amount'),
            ],
            'distributions' => [
                'total_in_range' => $distributionQuery->count(),
                'delivered_in_range' => (clone $distributionQuery)->where('status', 'delivered')->count(),
                'delivered_value_in_range' => (float) (clone $distributionQuery)->where('status', 'delivered')->sum('total_value'),
            ],
        ];

        return response()->json([
            'message' => 'Analytics overview retrieved successfully',
            'data' => $data,
        ], 200);
    }

    /**
     * Daily trend lines for charts.
     */
    public function trends(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $requestTrend = ReliefRequest::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get();

        $donationTrend = Donation::selectRaw('DATE(created_at) as day, COUNT(*) as transactions, COALESCE(SUM(amount), 0) as amount')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'confirmed')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get();

        $distributionTrend = Distribution::selectRaw('DATE(created_at) as day, COUNT(*) as total, COALESCE(SUM(total_value), 0) as value')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'delivered')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get();

        return response()->json([
            'message' => 'Analytics trends retrieved successfully',
            'data' => [
                'range' => [
                    'from' => $from->toDateTimeString(),
                    'to' => $to->toDateTimeString(),
                ],
                'relief_requests_daily' => $requestTrend,
                'confirmed_donations_daily' => $donationTrend,
                'delivered_distributions_daily' => $distributionTrend,
            ],
        ], 200);
    }

    /**
     * Status/method/category style breakdowns for admin charts.
     */
    public function breakdown(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $data = [
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
            'users_by_role' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->get(),
            'relief_by_status' => ReliefRequest::selectRaw('status, COUNT(*) as count')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('status')
                ->get(),
            'relief_by_disaster_type' => ReliefRequest::selectRaw('disaster_type, COUNT(*) as count')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('disaster_type')
                ->get(),
            'campaigns_by_status' => Campaign::selectRaw('status, COUNT(*) as count')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('status')
                ->get(),
            'donations_by_method' => Donation::selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
                ->whereBetween('created_at', [$from, $to])
                ->where('status', 'confirmed')
                ->groupBy('method')
                ->get(),
            'distributions_by_status' => Distribution::selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_value), 0) as value')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('status')
                ->get(),
        ];

        return response()->json([
            'message' => 'Analytics breakdown retrieved successfully',
            'data' => $data,
        ], 200);
    }

    /**
     * Resolve date range from query params or use default last 30 days.
     */
    private function resolveDateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }
}
