<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Display all campaigns (coordinator's campaigns)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $campaigns = Campaign::where('coordinator_id', $user->id)
            ->withCount('donations')
            ->with('donations')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Campaigns retrieved successfully',
            'data' => $campaigns,
        ], 200);
    }

    /**
     * Create a new campaign
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'target_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $campaign = Campaign::create([
            'coordinator_id' => $user->id,
            ...$validated,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Campaign created successfully',
            'data' => $campaign,
        ], 201);
    }

    /**
     * Show a specific campaign
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $campaign = Campaign::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->with('donations')
            ->firstOrFail();

        return response()->json([
            'message' => 'Campaign retrieved successfully',
            'data' => $campaign,
        ], 200);
    }

    /**
     * Update a campaign
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $campaign = Campaign::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'target_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:open,closed,completed',
        ]);

        $campaign->update($validated);

        return response()->json([
            'message' => 'Campaign updated successfully',
            'data' => $campaign,
        ], 200);
    }

    /**
     * Delete a campaign
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $campaign = Campaign::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully',
        ], 200);
    }

    /**
     * Get campaign statistics
     */
    public function statistics(Request $request, $id)
    {
        $user = $request->user();
        $campaign = Campaign::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $donations = Donation::where('campaign_id', $id)
            ->where('status', 'confirmed')
            ->get();

        return response()->json([
            'message' => 'Campaign statistics',
            'data' => [
                'campaign' => $campaign,
                'total_donations' => $donations->count(),
                'total_amount' => $donations->sum('amount'),
                'progress_percentage' => $campaign->target_amount > 0 
                    ? round(($campaign->current_amount / $campaign->target_amount) * 100, 2)
                    : 0,
            ],
        ], 200);
    }
}
