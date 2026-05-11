<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoordinatorProfile;
use App\Models\RescueProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    private const MANAGED_ROLES = ['coordinator', 'rescue_team', 'citizen'];

    /**
     * Get user details for admin.
     */
    public function show(Request $request, $id)
    {
        $user = User::with(['rescueProfile', 'coordinatorProfile'])
            ->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts are managed separately',
            ], 403);
        }

        return response()->json([
            'message' => 'User details retrieved successfully',
            'data' => $user,
        ], 200);
    }


    /**
     * Create a new user as admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:15',
            'password' => 'required|string|min:8',
            'role' => 'required|in:coordinator,rescue_team,citizen',
            'address' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'],
                    'address' => $validated['address'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                $this->syncRoleProfiles($user, null, $validated['role']);

                return $user;
            });

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user->load(['rescueProfile', 'coordinatorProfile']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update user profile/role as admin.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts are managed separately',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:150|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:15',
            'role' => 'sometimes|in:coordinator,rescue_team,citizen',
            'address' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            DB::transaction(function () use ($validated, $user) {
                $oldRole = $user->role;

                $user->update($validated);

                if (isset($validated['role']) && $validated['role'] !== $oldRole) {
                    $this->syncRoleProfiles($user, $oldRole, $validated['role']);
                }
            });

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user->fresh()->load(['rescueProfile', 'coordinatorProfile']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Deactivate a user account.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts are managed separately',
            ], 403);
        }

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account',
            ], 400);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts are managed separately',
            ], 403);
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password reset successfully',
        ], 200);
    }

    /**
     * Get summary statistics for user management.
     */
    public function statistics(Request $request)
    {
        $users = User::query();

        return response()->json([
            'message' => 'User statistics retrieved successfully',
            'data' => [
                'total_users' => $users->count(),
                'active_users' => $users->clone()->where('is_active', true)->count(),
                'inactive_users' => $users->clone()->where('is_active', false)->count(),
                'by_role' => User::query()
                    ->selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->get(),
            ],
        ], 200);
    }

    /**
     * Keep role-based profiles consistent after user role changes.
     */
    private function syncRoleProfiles(User $user, ?string $oldRole, string $newRole): void
    {
        if ($oldRole === 'rescue_team' && $newRole !== 'rescue_team') {
            RescueProfile::where('user_id', $user->id)->delete();
        }

        if ($oldRole === 'coordinator' && $newRole !== 'coordinator') {
            CoordinatorProfile::where('user_id', $user->id)->delete();
        }

        if ($newRole === 'rescue_team') {
            RescueProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'available',
                ]
            );
        }

        if ($newRole === 'coordinator') {
            CoordinatorProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'authority_level' => 'xa',
                ]
            );
        }
    }
}
