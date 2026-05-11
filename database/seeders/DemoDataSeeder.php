<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Campaign;
use App\Models\CoordinatorProfile;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\InventoryItem;
use App\Models\ReliefRequest;
use App\Models\RescueProfile;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@relief.local'],
            [
                'name' => 'Admin Control',
                'phone' => '0901000001',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
                'address' => 'ReliefOps HQ',
                'is_active' => true,
            ]
        );

        $coordinator1 = User::updateOrCreate(
            ['email' => 'coord.haivan@relief.local'],
            [
                'name' => 'Le Huyen Trang',
                'phone' => '0901000011',
                'password' => Hash::make('12345678'),
                'role' => 'coordinator',
                'address' => 'Da Nang Command Center',
                'is_active' => true,
            ]
        );

        $coordinator2 = User::updateOrCreate(
            ['email' => 'coord.sontra@relief.local'],
            [
                'name' => 'Tran Quang Huy',
                'phone' => '0901000012',
                'password' => Hash::make('12345678'),
                'role' => 'coordinator',
                'address' => 'Quang Nam Field Office',
                'is_active' => true,
            ]
        );

        $rescue1 = User::updateOrCreate(
            ['email' => 'team.alpha@relief.local'],
            [
                'name' => 'Team Alpha',
                'phone' => '0902000011',
                'password' => Hash::make('12345678'),
                'role' => 'rescue_team',
                'address' => 'Da Nang Base',
                'is_active' => true,
            ]
        );

        $rescue2 = User::updateOrCreate(
            ['email' => 'team.bravo@relief.local'],
            [
                'name' => 'Team Bravo',
                'phone' => '0902000012',
                'password' => Hash::make('12345678'),
                'role' => 'rescue_team',
                'address' => 'Hue Base',
                'is_active' => true,
            ]
        );

        $rescue3 = User::updateOrCreate(
            ['email' => 'team.omega@relief.local'],
            [
                'name' => 'Team Omega',
                'phone' => '0902000013',
                'password' => Hash::make('12345678'),
                'role' => 'rescue_team',
                'address' => 'Quang Tri Base',
                'is_active' => true,
            ]
        );

        $citizen1 = User::updateOrCreate(
            ['email' => 'citizen.lan@relief.local'],
            [
                'name' => 'Pham Thu Lan',
                'phone' => '0903000011',
                'password' => Hash::make('12345678'),
                'role' => 'citizen',
                'address' => 'Quang Binh',
                'is_active' => true,
            ]
        );

        $citizen2 = User::updateOrCreate(
            ['email' => 'citizen.minh@relief.local'],
            [
                'name' => 'Nguyen Van Minh',
                'phone' => '0903000012',
                'password' => Hash::make('12345678'),
                'role' => 'citizen',
                'address' => 'Quang Tri',
                'is_active' => true,
            ]
        );

        $citizen3 = User::updateOrCreate(
            ['email' => 'citizen.hoang@relief.local'],
            [
                'name' => 'Hoang Thi Kim',
                'phone' => '0903000013',
                'password' => Hash::make('12345678'),
                'role' => 'citizen',
                'address' => 'Da Nang',
                'is_active' => true,
            ]
        );

        CoordinatorProfile::updateOrCreate(
            ['user_id' => $coordinator1->id],
            [
                'area_name' => 'Da Nang Central',
                'organization' => 'ReliefOps',
                'authority_level' => 'tinh',
                'current_lat' => 16.0544,
                'current_lng' => 108.2022,
            ]
        );

        CoordinatorProfile::updateOrCreate(
            ['user_id' => $coordinator2->id],
            [
                'area_name' => 'Quang Nam Hub',
                'organization' => 'ReliefOps',
                'authority_level' => 'huyen',
                'current_lat' => 15.975,
                'current_lng' => 108.27,
            ]
        );

        RescueProfile::updateOrCreate(
            ['user_id' => $rescue1->id],
            [
                'specialization' => 'Flood rescue',
                'certificate' => 'Basic Water Rescue',
                'organization' => 'Rescue Unit A',
                'status' => 'available',
                'vehicle_type' => 'thuyen',
                'total_missions' => 12,
                'current_lat' => 16.068,
                'current_lng' => 108.214,
                'last_seen' => now(),
            ]
        );

        RescueProfile::updateOrCreate(
            ['user_id' => $rescue2->id],
            [
                'specialization' => 'Medical support',
                'certificate' => 'Emergency Medical',
                'organization' => 'Rescue Unit B',
                'status' => 'busy',
                'vehicle_type' => 'o_to',
                'total_missions' => 8,
                'current_lat' => 16.47,
                'current_lng' => 107.59,
                'last_seen' => now(),
            ]
        );

        RescueProfile::updateOrCreate(
            ['user_id' => $rescue3->id],
            [
                'specialization' => 'Landslide response',
                'certificate' => 'Mountain Rescue',
                'organization' => 'Rescue Unit C',
                'status' => 'available',
                'vehicle_type' => 'xe_may',
                'total_missions' => 5,
                'current_lat' => 16.78,
                'current_lng' => 107.08,
                'last_seen' => now(),
            ]
        );

        $campaign1 = Campaign::updateOrCreate(
            ['title' => 'Emergency Flood Fund'],
            [
                'coordinator_id' => $coordinator1->id,
                'description' => 'Emergency funding for flood response and evacuation support.',
                'target_amount' => 500000000,
                'current_amount' => 0,
                'status' => 'open',
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(30),
            ]
        );

        $campaign2 = Campaign::updateOrCreate(
            ['title' => 'Medical Response Fund'],
            [
                'coordinator_id' => $coordinator2->id,
                'description' => 'Medical supply procurement for disaster zones.',
                'target_amount' => 300000000,
                'current_amount' => 0,
                'status' => 'open',
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(40),
            ]
        );

        Donation::updateOrCreate(
            ['donor_email' => $citizen1->email, 'campaign_id' => $campaign1->id, 'amount' => 2500000],
            [
                'user_id' => $citizen1->id,
                'donor_name' => $citizen1->name,
                'method' => 'bank_transfer',
                'note' => 'Support for flood victims',
                'status' => 'confirmed',
            ]
        );

        Donation::updateOrCreate(
            ['donor_email' => $citizen2->email, 'campaign_id' => $campaign1->id, 'amount' => 1000000],
            [
                'user_id' => $citizen2->id,
                'donor_name' => $citizen2->name,
                'method' => 'cash',
                'note' => 'Local community support',
                'status' => 'confirmed',
            ]
        );

        Donation::updateOrCreate(
            ['donor_email' => $citizen3->email, 'campaign_id' => $campaign2->id, 'amount' => 500000],
            [
                'user_id' => $citizen3->id,
                'donor_name' => $citizen3->name,
                'method' => 'bank_transfer',
                'note' => 'Pending donation',
                'status' => 'pending',
            ]
        );

        $campaign1->update([
            'current_amount' => Donation::where('campaign_id', $campaign1->id)
                ->where('status', 'confirmed')
                ->sum('amount'),
        ]);

        $campaign2->update([
            'current_amount' => Donation::where('campaign_id', $campaign2->id)
                ->where('status', 'confirmed')
                ->sum('amount'),
        ]);

        $requestPending = ReliefRequest::updateOrCreate(
            ['title' => 'Flooded homes evacuation', 'citizen_id' => $citizen1->id],
            [
                'coordinator_id' => null,
                'description' => 'Multiple houses submerged, need evacuation boats.',
                'disaster_type' => 'lu_lut',
                'urgency_level' => 5,
                'people_count' => 80,
                'status' => 'pending',
                'latitude' => 17.012,
                'longitude' => 106.65,
                'address' => 'Quang Binh - Le Thuy',
                'required_skills' => ['flood', 'boat'],
            ]
        );

        $requestConfirmed = ReliefRequest::updateOrCreate(
            ['title' => 'Landslide medical support', 'citizen_id' => $citizen2->id],
            [
                'coordinator_id' => $coordinator1->id,
                'description' => 'Need medical assistance after landslide.',
                'disaster_type' => 'sat_lo',
                'urgency_level' => 4,
                'people_count' => 45,
                'status' => 'confirmed',
                'latitude' => 16.72,
                'longitude' => 107.12,
                'address' => 'Quang Tri - Dakrong',
                'required_skills' => ['medical', 'landslide'],
            ]
        );

        $requestAssigned = ReliefRequest::updateOrCreate(
            ['title' => 'Storm shelter reinforcement', 'citizen_id' => $citizen3->id],
            [
                'coordinator_id' => $coordinator1->id,
                'description' => 'Shelters need reinforcement before storm arrival.',
                'disaster_type' => 'bao',
                'urgency_level' => 3,
                'people_count' => 60,
                'status' => 'assigned',
                'latitude' => 16.052,
                'longitude' => 108.21,
                'address' => 'Da Nang - Hoa Xuan',
                'required_skills' => ['storm', 'logistics'],
            ]
        );

        $requestInProgress = ReliefRequest::updateOrCreate(
            ['title' => 'Wildfire containment support', 'citizen_id' => $citizen1->id],
            [
                'coordinator_id' => $coordinator2->id,
                'description' => 'Fire spreading to nearby villages.',
                'disaster_type' => 'chay',
                'urgency_level' => 4,
                'people_count' => 30,
                'status' => 'in_progress',
                'latitude' => 16.48,
                'longitude' => 107.58,
                'address' => 'Hue - Phu Vang',
                'required_skills' => ['fire', 'medical'],
            ]
        );

        $requestCompleted = ReliefRequest::updateOrCreate(
            ['title' => 'Bridge collapse response', 'citizen_id' => $citizen2->id],
            [
                'coordinator_id' => $coordinator1->id,
                'description' => 'Temporary bridge installed, rescue team needed.',
                'disaster_type' => 'khac',
                'urgency_level' => 2,
                'people_count' => 15,
                'status' => 'completed',
                'latitude' => 16.05,
                'longitude' => 108.22,
                'address' => 'Da Nang - Cam Le',
                'required_skills' => ['engineering'],
                'completed_at' => now()->subHours(6),
            ]
        );

        Assignment::updateOrCreate(
            ['request_id' => $requestAssigned->id, 'rescue_team_id' => $rescue1->id],
            [
                'algorithm' => 'Greedy',
                'cost_score' => 12.5,
                'distance_km' => 4.8,
                'status' => 'assigned',
            ]
        );

        Assignment::updateOrCreate(
            ['request_id' => $requestInProgress->id, 'rescue_team_id' => $rescue2->id],
            [
                'algorithm' => 'Greedy',
                'cost_score' => 10.4,
                'distance_km' => 6.1,
                'status' => 'in_progress',
                'arrived_at' => now()->subMinutes(35),
            ]
        );

        Assignment::updateOrCreate(
            ['request_id' => $requestCompleted->id, 'rescue_team_id' => $rescue3->id],
            [
                'algorithm' => 'Greedy',
                'cost_score' => 14.9,
                'distance_km' => 8.2,
                'status' => 'completed',
                'arrived_at' => now()->subHours(2),
                'completed_at' => now()->subMinutes(40),
            ]
        );

        RescueProfile::where('user_id', $rescue3->id)->update([
            'total_missions' => 6,
        ]);

        $warehouse1 = Warehouse::updateOrCreate(
            ['name' => 'Central Hub A', 'coordinator_id' => $coordinator1->id],
            [
                'latitude' => 16.07,
                'longitude' => 108.2,
                'address' => 'Da Nang - Hai Chau',
                'max_capacity' => 2000,
                'status' => 'active',
            ]
        );

        $warehouse2 = Warehouse::updateOrCreate(
            ['name' => 'Relief Depot B', 'coordinator_id' => $coordinator2->id],
            [
                'latitude' => 15.98,
                'longitude' => 108.25,
                'address' => 'Quang Nam - Tam Ky',
                'max_capacity' => 1500,
                'status' => 'active',
            ]
        );

        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse1->id, 'name' => 'Water'],
            ['category' => 'luong_thuc', 'quantity' => 800, 'unit' => 'bottle']
        );
        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse1->id, 'name' => 'Medical kits'],
            ['category' => 'thuoc', 'quantity' => 120, 'unit' => 'kit']
        );
        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse1->id, 'name' => 'Blankets'],
            ['category' => 'quan_ao', 'quantity' => 300, 'unit' => 'pcs']
        );

        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse2->id, 'name' => 'Dry food'],
            ['category' => 'luong_thuc', 'quantity' => 600, 'unit' => 'box']
        );
        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse2->id, 'name' => 'Generators'],
            ['category' => 'thiet_bi', 'quantity' => 12, 'unit' => 'unit']
        );
        InventoryItem::updateOrCreate(
            ['warehouse_id' => $warehouse2->id, 'name' => 'Life vests'],
            ['category' => 'thiet_bi', 'quantity' => 90, 'unit' => 'pcs']
        );

        Distribution::updateOrCreate(
            ['warehouse_id' => $warehouse1->id, 'request_id' => $requestAssigned->id],
            [
                'rescue_team_id' => null,
                'coordinator_id' => $coordinator1->id,
                'items_detail' => [
                    ['name' => 'Water', 'quantity' => 200, 'unit' => 'bottle'],
                    ['name' => 'Blankets', 'quantity' => 120, 'unit' => 'pcs'],
                ],
                'total_value' => 15000000,
                'status' => 'approved',
            ]
        );

        Distribution::updateOrCreate(
            ['warehouse_id' => $warehouse2->id, 'request_id' => $requestInProgress->id],
            [
                'rescue_team_id' => $rescue2->id,
                'coordinator_id' => $coordinator2->id,
                'items_detail' => [
                    ['name' => 'Medical kits', 'quantity' => 40, 'unit' => 'kit'],
                    ['name' => 'Generators', 'quantity' => 2, 'unit' => 'unit'],
                ],
                'total_value' => 22000000,
                'status' => 'delivering',
            ]
        );

        Distribution::updateOrCreate(
            ['warehouse_id' => $warehouse1->id, 'request_id' => $requestCompleted->id],
            [
                'rescue_team_id' => $rescue3->id,
                'coordinator_id' => $coordinator1->id,
                'items_detail' => [
                    ['name' => 'Water', 'quantity' => 120, 'unit' => 'bottle'],
                    ['name' => 'Medical kits', 'quantity' => 30, 'unit' => 'kit'],
                ],
                'total_value' => 12000000,
                'status' => 'delivered',
                'delivered_at' => now()->subDay(),
            ]
        );
    }
}
