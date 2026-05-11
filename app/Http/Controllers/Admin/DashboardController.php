<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ReliefRequest;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Assignment;
use App\Models\RescueProfile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function statistics()
    {
        // Users by role
        $users_by_role = User::groupBy('role')
            ->selectRaw('role, count(*) as count')
            ->get();

        // Relief requests statistics
        $relief_stats = ReliefRequest::groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->get();

        // Campaigns statistics
        $total_campaigns = Campaign::count();
        $active_campaigns = Campaign::where('status', 'open')->count();

        // Donation statistics
        $total_donations = Donation::where('status', 'confirmed')->sum('amount');
        $total_donors = Donation::where('status', 'confirmed')->distinct('user_id')->count();

        $assignmentQuery = Assignment::query();
        $assignmentTotals = [
            'total' => $assignmentQuery->count(),
            'completed' => (clone $assignmentQuery)->where('status', 'completed')->count(),
            'in_progress' => (clone $assignmentQuery)->whereIn('status', ['assigned', 'in_progress'])->count(),
            'avg_cost_score' => (float) (clone $assignmentQuery)->avg('cost_score'),
            'avg_distance_km' => (float) (clone $assignmentQuery)->avg('distance_km'),
        ];

        $aiByAlgorithm = Assignment::selectRaw('algorithm, COUNT(*) as total, COALESCE(AVG(cost_score), 0) as avg_cost_score, COALESCE(AVG(distance_km), 0) as avg_distance_km')
            ->groupBy('algorithm')
            ->get();

        $rescueTeamsTotal = User::where('role', 'rescue_team')->count();
        $rescueAvailable = RescueProfile::where('status', 'available')->count();
        $rescueOffline = RescueProfile::where('status', 'offline')->count();
        $rescueActive = RescueProfile::whereNotIn('status', ['available', 'offline'])->count();

        return response()->json([
            'message' => 'Dashboard statistics',
            'data' => [
                'users_by_role' => $users_by_role,
                'relief_requests' => [
                    'total' => ReliefRequest::count(),
                    'by_status' => $relief_stats,
                    'by_urgency' => ReliefRequest::groupBy('urgency_level')
                        ->selectRaw('urgency_level, count(*) as count')
                        ->get(),
                ],
                'campaigns' => [
                    'total' => $total_campaigns,
                    'active' => $active_campaigns,
                ],
                'donations' => [
                    'total_amount' => $total_donations,
                    'total_transactions' => Donation::where('status', 'confirmed')->count(),
                    'total_donors' => $total_donors,
                ],
                'ai_performance' => [
                    'totals' => $assignmentTotals,
                    'by_algorithm' => $aiByAlgorithm,
                ],
                'rescue_team_activity' => [
                    'total_teams' => $rescueTeamsTotal,
                    'available' => $rescueAvailable,
                    'offline' => $rescueOffline,
                    'active' => $rescueActive,
                    'active_assignments' => $assignmentTotals['in_progress'],
                ],
            ],
        ], 200);
    }

    /**
     * Get all users with filtering
     */
    public function users(Request $request)
    {
        $allowedRoles = ['coordinator', 'rescue_team', 'citizen'];
        $query = User::query()->whereIn('role', $allowedRoles);

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ], 200);
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts are managed separately',
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User status updated successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Get all relief requests
     */
    public function reliefRequests(Request $request)
    {
        $query = ReliefRequest::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('disaster_type')) {
            $query->where('disaster_type', $request->disaster_type);
        }

        if ($request->has('urgency_level')) {
            $query->where('urgency_level', '>=', $request->urgency_level);
        }

        $requests = $query->with(['citizen', 'coordinator'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Relief requests retrieved successfully',
            'data' => $requests,
        ], 200);
    }

    /**
     * Get all campaigns
     */
    public function campaigns(Request $request)
    {
        $query = Campaign::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
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
}
