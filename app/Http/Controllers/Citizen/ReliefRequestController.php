<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\ReliefRequest;
use Illuminate\Http\Request;

class ReliefRequestController extends Controller
{
    /**
     * Display all relief requests for current citizen
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $requests = ReliefRequest::where('citizen_id', $user->id)
            ->with('coordinator', 'assignments')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Relief requests retrieved successfully',
            'data' => $requests,
        ], 200);
    }

    /**
     * Create a new relief request
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'disaster_type' => 'required|in:lu_lut,sat_lo,bao,chay,khac',
            'urgency_level' => 'required|integer|between:1,5',
            'people_count' => 'sometimes|nullable|integer|min:0',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'required_skills' => 'sometimes|nullable|json',
        ]);

        $reliefRequest = ReliefRequest::create([
            'citizen_id' => $user->id,
            ...$validated,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Relief request created successfully',
            'data' => $reliefRequest,
        ], 201);
    }

    /**
     * Show a specific relief request
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $reliefRequest = ReliefRequest::where('citizen_id', $user->id)
            ->where('id', $id)
            ->with('coordinator', 'assignments.rescueTeam')
            ->firstOrFail();

        return response()->json([
            'message' => 'Relief request retrieved successfully',
            'data' => $reliefRequest,
        ], 200);
    }

    /**
     * Update a relief request
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $reliefRequest = ReliefRequest::where('citizen_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Only allow update if not confirmed yet
        if ($reliefRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update relief request that is not pending',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'disaster_type' => 'sometimes|in:lu_lut,sat_lo,bao,chay,khac',
            'urgency_level' => 'sometimes|integer|between:1,5',
            'people_count' => 'sometimes|nullable|integer|min:0',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
        ]);

        $reliefRequest->update($validated);

        return response()->json([
            'message' => 'Relief request updated successfully',
            'data' => $reliefRequest,
        ], 200);
    }

    /**
     * Cancel a relief request
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $reliefRequest = ReliefRequest::where('citizen_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($reliefRequest->status === 'completed') {
            return response()->json([
                'message' => 'Cannot cancel a completed request',
            ], 400);
        }

        $reliefRequest->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Relief request cancelled successfully',
        ], 200);
    }
}

