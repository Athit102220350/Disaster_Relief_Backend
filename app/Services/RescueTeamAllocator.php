<?php

namespace App\Services;

use App\Models\ReliefRequest;
use App\Models\RescueProfile;
use Illuminate\Support\Collection;

class RescueTeamAllocator
{
    /**
     * Recommend rescue teams for a relief request.
     */
    public function recommend(ReliefRequest $request, string $algorithm = 'Greedy', int $limit = 5): Collection
    {
        $profiles = RescueProfile::query()
            ->with('user')
            ->where('status', 'available')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->whereHas('user', function ($query) {
                $query->where('role', 'rescue_team')
                    ->where('is_active', true);
            })
            ->get();

        $evaluated = $profiles->map(function (RescueProfile $profile) use ($request, $algorithm) {
            return $this->evaluate($request, $profile, $algorithm);
        })->filter();

        return $evaluated
            ->sortBy('cost_score')
            ->take(max(1, min($limit, 20)))
            ->values();
    }

    /**
     * Evaluate one team against one request.
     */
    public function evaluate(ReliefRequest $request, RescueProfile $profile, string $algorithm = 'Greedy'): ?array
    {
        if (!$request->latitude || !$request->longitude) {
            return null;
        }

        $distanceKm = $this->haversine(
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $profile->current_lat,
            (float) $profile->current_lng
        );

        $requiredSkills = is_array($request->required_skills) ? $request->required_skills : [];
        $teamSpecialization = strtolower((string) ($profile->specialization ?? ''));

        $skillMatched = empty($requiredSkills)
            ? true
            : collect($requiredSkills)->contains(function ($skill) use ($teamSpecialization) {
                return str_contains($teamSpecialization, strtolower((string) $skill));
            });

        $missions = (int) ($profile->total_missions ?? 0);
        $urgency = (int) ($request->urgency_level ?? 1);

        // Lower score means better candidate.
        if ($algorithm === 'Hungarian') {
            $costScore = ($distanceKm * 0.65)
                + ($missions * 0.15)
                + ($skillMatched ? 0 : 25)
                - ($urgency * 1.5);
        } else {
            // Greedy default: prioritize shortest distance more aggressively.
            $costScore = ($distanceKm * 0.75)
                + ($missions * 0.10)
                + ($skillMatched ? 0 : 30)
                - ($urgency * 1.0);
        }

        return [
            'rescue_team_id' => $profile->user_id,
            'rescue_profile_id' => $profile->id,
            'team_name' => optional($profile->user)->name,
            'distance_km' => round($distanceKm, 2),
            'skill_matched' => $skillMatched,
            'vehicle_type' => $profile->vehicle_type,
            'total_missions' => $missions,
            'cost_score' => round(max($costScore, 0), 2),
            'algorithm' => $algorithm,
        ];
    }

    /**
     * Great-circle distance in KM.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
