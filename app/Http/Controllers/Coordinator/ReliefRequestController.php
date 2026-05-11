<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ReliefRequest;
use App\Models\Assignment;
use App\Models\RescueProfile;
use App\Services\RescueTeamAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReliefRequestController extends Controller
{
    public function __construct(private RescueTeamAllocator $allocator)
    {
    }

    /**
     * Get all relief requests in coordinator's area (F19)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = ReliefRequest::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by disaster type
        if ($request->has('disaster_type')) {
            $query->where('disaster_type', $request->disaster_type);
        }

        // Filter by urgency level
        if ($request->has('urgency_level')) {
            $query->where('urgency_level', '>=', $request->urgency_level);
        }

        $requests = $query->with('citizen', 'assignments.rescueTeam')
            ->orderBy('urgency_level', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Relief requests retrieved successfully',
            'data' => $requests,
        ], 200);
    }

    /**
     * Show a specific relief request
     */
    public function show($id)
    {
        $request = ReliefRequest::with('citizen', 'coordinator', 'assignments.rescueTeam')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Relief request retrieved successfully',
            'data' => $request,
        ], 200);
    }

    /**
     * Confirm/Approve a SOS request (F20)
     */
    public function confirm(Request $request, $id)
    {
        $user = $request->user();
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Relief request is not in pending status',
            ], 400);
        }

        $validated = $request->validate([
            'note' => 'sometimes|nullable|string',
        ]);

        $reliefRequest->update([
            'status' => 'confirmed',
            'coordinator_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Relief request confirmed successfully',
            'data' => $reliefRequest,
        ], 200);
    }

    /**
     * Reject a SOS request
     */
    public function reject(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Relief request is not in pending status',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $reliefRequest->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Relief request rejected',
            'data' => $reliefRequest,
        ], 200);
    }

    /**
     * Manually assign rescue team (F21)
     */
    public function assignTeam(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status === 'pending') {
            return response()->json([
                'message' => 'Relief request must be confirmed first',
            ], 400);
        }

        $validated = $request->validate([
            'rescue_team_id' => 'required|exists:users,id',
            'algorithm' => 'required|in:Hungarian,Greedy,GeneticAlgorithm',
            'cost_score' => 'sometimes|nullable|numeric',
            'distance_km' => 'sometimes|nullable|numeric',
        ]);

        if (in_array($reliefRequest->status, ['assigned', 'in_progress', 'completed'], true)) {
            return response()->json([
                'message' => 'Relief request already has assignment in progress or completed',
            ], 400);
        }

        $profile = RescueProfile::where('user_id', $validated['rescue_team_id'])
            ->where('status', 'available')
            ->first();

        if (!$profile) {
            return response()->json([
                'message' => 'Selected rescue team is not available',
            ], 400);
        }

        $evaluation = $this->allocator->evaluate(
            $reliefRequest,
            $profile,
            $validated['algorithm'] === 'GeneticAlgorithm' ? 'Greedy' : $validated['algorithm']
        );

        $assignment = Assignment::create([
            'request_id' => $reliefRequest->id,
            'rescue_team_id' => $validated['rescue_team_id'],
            'algorithm' => $validated['algorithm'],
            'cost_score' => $validated['cost_score'] ?? ($evaluation['cost_score'] ?? null),
            'distance_km' => $validated['distance_km'] ?? ($evaluation['distance_km'] ?? null),
            'status' => 'assigned',
        ]);

        $reliefRequest->update(['status' => 'assigned']);

        return response()->json([
            'message' => 'Rescue team assigned successfully',
            'data' => $assignment,
        ], 201);
    }

    /**
     * Recommend best rescue teams for a request based on algorithm scoring.
     */
    public function recommendTeams(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        $validated = $request->validate([
            'algorithm' => 'sometimes|in:Hungarian,Greedy,GeneticAlgorithm',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $algorithm = $validated['algorithm'] ?? 'Greedy';
        if ($algorithm === 'GeneticAlgorithm') {
            $algorithm = 'Greedy';
        }

        $teams = $this->allocator->recommend(
            $reliefRequest,
            $algorithm,
            $validated['limit'] ?? 5
        );

        return response()->json([
            'message' => 'Recommended rescue teams retrieved successfully',
            'data' => [
                'request_id' => $reliefRequest->id,
                'algorithm' => $algorithm,
                'teams' => $teams,
            ],
        ], 200);
    }

    /**
     * Auto-assign top recommended rescue team to a request.
     */
    public function autoAssign(Request $request, $id)
    {
        $user = $request->user();
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status === 'pending') {
            return response()->json([
                'message' => 'Relief request must be confirmed first',
            ], 400);
        }

        if (in_array($reliefRequest->status, ['assigned', 'in_progress', 'completed'], true)) {
            return response()->json([
                'message' => 'Relief request already has assignment in progress or completed',
            ], 400);
        }

        $validated = $request->validate([
            'algorithm' => 'sometimes|in:Hungarian,Greedy,GeneticAlgorithm',
        ]);

        $algorithm = $validated['algorithm'] ?? 'Greedy';
        if ($algorithm === 'GeneticAlgorithm') {
            $algorithm = 'Greedy';
        }

        $candidate = $this->allocator->recommend($reliefRequest, $algorithm, 1)->first();

        if (!$candidate) {
            return response()->json([
                'message' => 'No available rescue team found for auto assignment',
            ], 404);
        }

        $assignment = DB::transaction(function () use ($reliefRequest, $candidate, $algorithm, $user) {
            $assignment = Assignment::create([
                'request_id' => $reliefRequest->id,
                'rescue_team_id' => $candidate['rescue_team_id'],
                'algorithm' => $algorithm,
                'cost_score' => $candidate['cost_score'],
                'distance_km' => $candidate['distance_km'],
                'status' => 'assigned',
            ]);

            $reliefRequest->update([
                'status' => 'assigned',
                'coordinator_id' => $reliefRequest->coordinator_id ?? $user->id,
            ]);

            return $assignment;
        });

        return response()->json([
            'message' => 'Rescue team auto-assigned successfully',
            'data' => [
                'assignment' => $assignment->load('rescueTeam'),
                'recommendation' => $candidate,
            ],
        ], 201);
    }

    /**
     * Get statistics for coordinator's area (F27)
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_requests' => ReliefRequest::count(),
            'by_status' => ReliefRequest::groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get(),
            'by_disaster_type' => ReliefRequest::groupBy('disaster_type')
                ->selectRaw('disaster_type, count(*) as count')
                ->get(),
            'by_urgency' => ReliefRequest::groupBy('urgency_level')
                ->selectRaw('urgency_level, count(*) as count')
                ->orderBy('urgency_level', 'desc')
                ->get(),
            'pending_requests' => ReliefRequest::where('status', 'pending')->count(),
            'confirmed_requests' => ReliefRequest::where('status', 'confirmed')->count(),
            'in_progress_requests' => ReliefRequest::where('status', 'in_progress')->count(),
            'completed_requests' => ReliefRequest::where('status', 'completed')->count(),
        ];

        return response()->json([
            'message' => 'Coordinator statistics',
            'data' => $stats,
        ], 200);
    }
}
