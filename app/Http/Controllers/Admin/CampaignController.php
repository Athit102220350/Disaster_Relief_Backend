<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Get all campaigns in the system.
     */
    public function index(Request $request)
    {
        $query = Campaign::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('coordinator_id')) {
            $query->where('coordinator_id', $request->coordinator_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $campaigns = $query->with('coordinator')
            ->withCount('donations')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Campaigns retrieved successfully',
            'data' => $campaigns,
        ], 200);
    }

    /**
     * Show campaign details.
     */
    public function show(Request $request, $id)
    {
        $campaign = Campaign::with('coordinator', 'donations')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Campaign retrieved successfully',
            'data' => $campaign,
        ], 200);
    }

    /**
     * Update campaign data.
     */
    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|nullable|string',
            'target_amount' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:open,closed,completed',
            'coordinator_id' => 'sometimes|exists:users,id',
        ]);

        if (isset($validated['coordinator_id'])) {
            $coordinator = User::where('id', $validated['coordinator_id'])
                ->where('role', 'coordinator')
                ->first();
            if (!$coordinator) {
                return response()->json([
                    'message' => 'Coordinator not found',
                ], 400);
            }
        }

        $campaign->update($validated);

        return response()->json([
            'message' => 'Campaign updated successfully',
            'data' => $campaign->fresh(),
        ], 200);
    }

    /**
     * Approve a campaign (open it).
     */
    public function approve(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === 'completed') {
            return response()->json([
                'message' => 'Completed campaigns cannot be reopened',
            ], 400);
        }

        $campaign->update(['status' => 'open']);

        return response()->json([
            'message' => 'Campaign approved successfully',
            'data' => $campaign->fresh(),
        ], 200);
    }

    /**
     * Close a campaign.
     */
    public function close(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === 'completed') {
            return response()->json([
                'message' => 'Completed campaigns cannot be closed',
            ], 400);
        }

        $campaign->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Campaign closed successfully',
            'data' => $campaign->fresh(),
        ], 200);
    }
}
