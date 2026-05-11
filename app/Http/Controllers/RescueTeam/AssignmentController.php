<?php

namespace App\Http\Controllers\RescueTeam;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\RescueProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    /**
     * Display a listing of assignments for rescue team
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $assignments = Assignment::where('rescue_team_id', $user->id)
            ->with('request')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Assignments retrieved successfully',
            'data' => $assignments,
        ], 200);
    }

    /**
     * Show a specific assignment
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $assignment = Assignment::where('rescue_team_id', $user->id)
            ->where('id', $id)
            ->with('request')
            ->firstOrFail();

        return response()->json([
            'message' => 'Assignment retrieved successfully',
            'data' => $assignment,
        ], 200);
    }

    /**
     * Update assignment status
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $assignment = Assignment::where('rescue_team_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:assigned,in_progress,completed,cancelled',
        ]);

        DB::transaction(function () use ($assignment, $validated, $user) {
            $oldStatus = $assignment->status;

            if ($validated['status'] === 'in_progress' && $oldStatus === 'assigned') {
                $validated['arrived_at'] = now();
            } elseif ($validated['status'] === 'completed' && $oldStatus === 'in_progress') {
                $validated['completed_at'] = now();
            }

            $assignment->update($validated);

            $profile = RescueProfile::where('user_id', $user->id)->lockForUpdate()->first();

            if ($profile) {
                if ($validated['status'] === 'in_progress') {
                    $profile->update(['status' => 'busy', 'last_seen' => now()]);
                }

                if ($validated['status'] === 'completed') {
                    if ($oldStatus !== 'completed') {
                        $profile->increment('total_missions');
                    }

                    $profile->update(['status' => 'available', 'last_seen' => now()]);
                }

                if ($validated['status'] === 'cancelled') {
                    $profile->update(['status' => 'available', 'last_seen' => now()]);
                }
            }
        });

        return response()->json([
            'message' => 'Assignment status updated successfully',
            'data' => $assignment->fresh()->load('request'),
        ], 200);
    }

    /**
     * Mission history for rescue team.
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => 'nullable|in:assigned,in_progress,completed,cancelled',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = Assignment::where('rescue_team_id', $user->id)
            ->with(['request.citizen', 'request.coordinator']);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $history = $query->orderByRaw('COALESCE(completed_at, created_at) DESC')
            ->paginate(20);

        return response()->json([
            'message' => 'Mission history retrieved successfully',
            'data' => $history,
        ], 200);
    }

    /**
     * Performance statistics for rescue team.
     */
    public function performance(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $base = Assignment::where('rescue_team_id', $user->id);

        if (isset($validated['from'])) {
            $base->whereDate('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $base->whereDate('created_at', '<=', $validated['to']);
        }

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $inProgress = (clone $base)->where('status', 'in_progress')->count();
        $cancelled = (clone $base)->where('status', 'cancelled')->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        $avgResponseMinutes = (clone $base)
            ->whereNotNull('arrived_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (arrived_at - created_at)) / 60) as avg_minutes')
            ->value('avg_minutes');

        $avgCompletionMinutes = (clone $base)
            ->whereNotNull('arrived_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - arrived_at)) / 60) as avg_minutes')
            ->value('avg_minutes');

        $algorithmStats = (clone $base)
            ->selectRaw('algorithm, COUNT(*) as count')
            ->groupBy('algorithm')
            ->get();

        $monthlyCompleted = (clone $base)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw("DATE_TRUNC('month', completed_at) as month, COUNT(*) as count")
            ->groupByRaw("DATE_TRUNC('month', completed_at)")
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        $profile = RescueProfile::where('user_id', $user->id)->first();

        return response()->json([
            'message' => 'Performance statistics retrieved successfully',
            'data' => [
                'range' => [
                    'from' => $validated['from'] ?? null,
                    'to' => $validated['to'] ?? null,
                ],
                'missions' => [
                    'total' => $total,
                    'completed' => $completed,
                    'in_progress' => $inProgress,
                    'cancelled' => $cancelled,
                    'completion_rate_percent' => $completionRate,
                    'distance_total_km' => (float) ((clone $base)->where('status', 'completed')->sum('distance_km') ?? 0),
                ],
                'timing' => [
                    'avg_response_minutes' => $avgResponseMinutes ? round((float) $avgResponseMinutes, 2) : null,
                    'avg_completion_minutes' => $avgCompletionMinutes ? round((float) $avgCompletionMinutes, 2) : null,
                ],
                'algorithm_breakdown' => $algorithmStats,
                'monthly_completed' => $monthlyCompleted,
                'profile' => $profile,
            ],
        ], 200);
    }

    /**
     * Update rescue team GPS location
     */
    public function updateLocation(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status' => 'sometimes|in:available,busy,offline',
        ]);

        $profile = RescueProfile::where('user_id', $user->id)->firstOrFail();
        
        $profile->update([
            'current_lat' => $validated['latitude'],
            'current_lng' => $validated['longitude'],
            'last_seen' => now(),
        ]);

        if (isset($validated['status'])) {
            $profile->update(['status' => $validated['status']]);
        }

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => $profile,
        ], 200);
    }
}
