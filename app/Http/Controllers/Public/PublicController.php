<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * Get all public campaigns (no auth required)
     */
    public function campaigns(Request $request)
    {
        $campaigns = Campaign::where('status', 'open')
            ->with('coordinator')
            ->withCount(['donations as confirmed_donations_count' => function ($query) {
                $query->where('status', 'confirmed');
            }])
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Public campaigns retrieved successfully',
            'data' => $campaigns,
        ], 200);
    }

    /**
     * Get campaign details with all donations (public info only)
     */
    public function showCampaign(Request $request, $id)
    {
        $campaign = Campaign::where('status', 'open')
            ->with('coordinator')
            ->withCount(['donations as confirmed_donations_count' => function ($query) {
                $query->where('status', 'confirmed');
            }])
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->find($id);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found',
            ], 404);
        }

        // Get public donation info (count and totals only)
        $donationStats = [
            'total_donors' => $campaign->donations()->where('status', 'confirmed')->distinct('user_id')->count(),
            'total_donations_count' => $campaign->donations()->where('status', 'confirmed')->count(),
            'total_amount' => (float) $campaign->donations()->where('status', 'confirmed')->sum('amount'),
            'progress_percentage' => $campaign->target_amount > 0 
                ? round(($campaign->donations()->where('status', 'confirmed')->sum('amount') / $campaign->target_amount) * 100, 2)
                : 0,
        ];

        return response()->json([
            'message' => 'Campaign retrieved successfully',
            'data' => [
                'campaign' => $campaign,
                'donation_stats' => $donationStats,
            ],
        ], 200);
    }

    /**
     * Get global donation statistics (public info)
     */
    public function statistics(Request $request)
    {
        $donations = Donation::where('status', 'confirmed')->get();
        $campaigns = Campaign::all();

        $stats = [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'open')->count(),
            'completed_campaigns' => $campaigns->where('status', 'completed')->count(),
            'total_donations' => (float) $donations->sum('amount'),
            'total_donors' => Donation::distinct('user_id')->count(),
            'donation_methods' => $donations->groupBy('method')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => (float) $group->sum('amount'),
                    ];
                })
                ->toArray(),
            'total_target_amount' => (float) $campaigns->sum('target_amount'),
            'raised_percentage' => $campaigns->sum('target_amount') > 0
                ? round(($donations->sum('amount') / $campaigns->sum('target_amount')) * 100, 2)
                : 0,
        ];

        return response()->json([
            'message' => 'Public statistics retrieved successfully',
            'data' => $stats,
        ], 200);
    }

    /**
     * Get top campaigns by raised amount
     */
    public function topCampaigns(Request $request)
    {
        $limit = $request->query('limit', 5);

        $campaigns = Campaign::where('status', '!=', 'closed')
            ->with('coordinator')
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->withCount(['donations as confirmed_donations_count' => function ($query) {
                $query->where('status', 'confirmed');
            }])
            ->orderByDesc('donations_sum_amount')
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Top campaigns retrieved successfully',
            'data' => $campaigns,
        ], 200);
    }

    /**
     * Search campaigns by title or description
     */
    public function searchCampaigns(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $campaigns = Campaign::where('status', 'open')
            ->where(function ($query) use ($validated) {
                $query->where('title', 'like', '%' . $validated['q'] . '%')
                    ->orWhere('description', 'like', '%' . $validated['q'] . '%');
            })
            ->with('coordinator')
            ->withCount(['donations as confirmed_donations_count' => function ($query) {
                $query->where('status', 'confirmed');
            }])
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Campaigns searched successfully',
            'data' => $campaigns,
        ], 200);
    }
}
