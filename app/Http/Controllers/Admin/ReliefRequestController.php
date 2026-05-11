<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ReliefRequest;
use App\Models\RescueProfile;
use App\Models\User;
use App\Services\RescueTeamAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReliefRequestController extends Controller
{
    public function __construct(private RescueTeamAllocator $allocator)
    {
    }

    /**
     * Get all relief requests in the system.
     */
    public function index(Request $request)
    {
        $query = ReliefRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('disaster_type')) {
            $query->where('disaster_type', $request->disaster_type);
        }

        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', '>=', $request->urgency_level);
        }

        if ($request->filled('coordinator_id')) {
            $query->where('coordinator_id', $request->coordinator_id);
        }

        if ($request->filled('citizen_id')) {
            $query->where('citizen_id', $request->citizen_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $requests = $query->with('citizen', 'coordinator', 'assignments.rescueTeam')
            ->orderBy('urgency_level', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Relief requests retrieved successfully',
            'data' => $requests,
        ], 200);
    }

    /**
     * Show a specific relief request.
     */
    public function show(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::with('citizen', 'coordinator', 'assignments.rescueTeam')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Relief request retrieved successfully',
            'data' => $reliefRequest,
        ], 200);
    }

    /**
     * Update relief request status or coordinator assignment.
     */
    public function update(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,confirmed,assigned,in_progress,completed,cancelled',
            'coordinator_id' => 'sometimes|nullable|exists:users,id',
            'note' => 'sometimes|nullable|string|max:500',
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

        if (($validated['status'] ?? null) === 'completed' && !$reliefRequest->completed_at) {
            $validated['completed_at'] = now();
        }

        $reliefRequest->update($validated);

        return response()->json([
            'message' => 'Relief request updated successfully',
            'data' => $reliefRequest->fresh(),
        ], 200);
    }

    /**
     * Confirm a pending relief request.
     */
    public function confirm(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Relief request is not in pending status',
            ], 400);
        }

        $validated = $request->validate([
            'coordinator_id' => 'sometimes|nullable|exists:users,id',
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

        $reliefRequest->update([
            'status' => 'confirmed',
            'coordinator_id' => $validated['coordinator_id'] ?? $reliefRequest->coordinator_id,
        ]);

        return response()->json([
            'message' => 'Relief request confirmed successfully',
            'data' => $reliefRequest->fresh(),
        ], 200);
    }

    /**
     * Reject a pending relief request.
     */
    public function reject(Request $request, $id)
    {
        $reliefRequest = ReliefRequest::findOrFail($id);

        if ($reliefRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Relief request is not in pending status',
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reliefRequest->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Relief request rejected',
            'data' => $reliefRequest->fresh(),
        ], 200);
    }

    /**
     * Assign a rescue team to a relief request.
     */
    public function assignTeam(Request $request, $id)
    {
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
            'rescue_team_id' => 'required|exists:users,id',
            'algorithm' => 'required|in:Hungarian,Greedy,GeneticAlgorithm',
            'cost_score' => 'sometimes|nullable|numeric',
            'distance_km' => 'sometimes|nullable|numeric',
            'coordinator_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $rescueUser = User::where('id', $validated['rescue_team_id'])
            ->where('role', 'rescue_team')
            ->first();
        if (!$rescueUser) {
            return response()->json([
                'message' => 'Selected rescue team is not valid',
            ], 400);
        }

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

        $profile = RescueProfile::where('user_id', $validated['rescue_team_id'])
            ->where('status', 'available')
            ->first();

        if (!$profile) {
            return response()->json([
                'message' => 'Selected rescue team is not available',
            ], 400);
        }

        $algorithm = $validated['algorithm'] === 'GeneticAlgorithm' ? 'Greedy' : $validated['algorithm'];
        $evaluation = $this->allocator->evaluate($reliefRequest, $profile, $algorithm);

        $assignment = DB::transaction(function () use ($reliefRequest, $validated, $evaluation, $algorithm) {
            $assignment = Assignment::create([
                'request_id' => $reliefRequest->id,
                'rescue_team_id' => $validated['rescue_team_id'],
                'algorithm' => $validated['algorithm'],
                'cost_score' => $validated['cost_score'] ?? ($evaluation['cost_score'] ?? null),
                'distance_km' => $validated['distance_km'] ?? ($evaluation['distance_km'] ?? null),
                'status' => 'assigned',
            ]);

            $reliefRequest->update([
                'status' => 'assigned',
                'coordinator_id' => $validated['coordinator_id'] ?? $reliefRequest->coordinator_id,
            ]);

            return $assignment;
        });

        return response()->json([
            'message' => 'Rescue team assigned successfully',
            'data' => $assignment,
        ], 201);
    }
}
