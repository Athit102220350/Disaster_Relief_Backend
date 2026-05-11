<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\ReliefRequest;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    /**
     * Get all distributions (F24)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Distribution::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Only coordinator's distributions
        if ($request->has('own_only') && $request->own_only) {
            $query->where('coordinator_id', $user->id);
        }

        $distributions = $query->with('warehouse', 'request', 'rescueTeam', 'coordinator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Distributions retrieved successfully',
            'data' => $distributions,
        ], 200);
    }

    /**
     * Create a new distribution request (F24)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'request_id' => 'required|exists:relief_requests,id',
            'items_detail' => 'required|json',
            'total_value' => 'sometimes|nullable|numeric|min:0',
        ]);

        // Verify warehouse belongs to coordinator
        $warehouse = Warehouse::where('id', $validated['warehouse_id'])
            ->where('coordinator_id', $user->id)
            ->firstOrFail();

        $distribution = Distribution::create([
            ...$validated,
            'coordinator_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Distribution created successfully',
            'data' => $distribution,
        ], 201);
    }

    /**
     * Show a specific distribution
     */
    public function show(Request $request, $id)
    {
        $distribution = Distribution::with('warehouse', 'request', 'rescueTeam', 'coordinator')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Distribution retrieved successfully',
            'data' => $distribution,
        ], 200);
    }

    /**
     * Approve/Duyệt distribution request (F24)
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();

        $distribution = Distribution::findOrFail($id);

        if ($distribution->status !== 'pending') {
            return response()->json([
                'message' => 'Distribution is not in pending status',
            ], 400);
        }

        $distribution->update([
            'status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Distribution approved successfully',
            'data' => $distribution,
        ], 200);
    }

    /**
     * Assign rescue team to distribution
     */
    public function assignTeam(Request $request, $id)
    {
        $distribution = Distribution::findOrFail($id);

        if ($distribution->status !== 'approved') {
            return response()->json([
                'message' => 'Distribution must be approved first',
            ], 400);
        }

        $validated = $request->validate([
            'rescue_team_id' => 'required|exists:users,id',
        ]);

        $distribution->update([
            'rescue_team_id' => $validated['rescue_team_id'],
            'status' => 'delivering',
        ]);

        return response()->json([
            'message' => 'Rescue team assigned to distribution',
            'data' => $distribution,
        ], 200);
    }

    /**
     * Mark distribution as delivered (F24)
     */
    public function markDelivered(Request $request, $id)
    {
        $distribution = Distribution::findOrFail($id);

        if ($distribution->status !== 'delivering') {
            return response()->json([
                'message' => 'Distribution must be in delivering status',
            ], 400);
        }

        $distribution->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Distribution marked as delivered',
            'data' => $distribution,
        ], 200);
    }

    /**
     * Reject distribution request
     */
    public function reject(Request $request, $id)
    {
        $distribution = Distribution::findOrFail($id);

        if (!in_array($distribution->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Can only reject pending or approved distributions',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $distribution->delete();

        return response()->json([
            'message' => 'Distribution rejected',
        ], 200);
    }

    /**
     * Get distribution statistics
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_distributions' => Distribution::count(),
            'by_status' => Distribution::groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get(),
            'pending' => Distribution::where('status', 'pending')->count(),
            'approved' => Distribution::where('status', 'approved')->count(),
            'delivering' => Distribution::where('status', 'delivering')->count(),
            'delivered' => Distribution::where('status', 'delivered')->count(),
        ];

        return response()->json([
            'message' => 'Distribution statistics',
            'data' => $stats,
        ], 200);
    }
}
