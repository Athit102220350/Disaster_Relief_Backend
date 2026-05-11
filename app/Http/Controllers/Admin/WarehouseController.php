<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Get all warehouses across the system.
     */
    public function index(Request $request)
    {
        $query = Warehouse::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('coordinator_id')) {
            $query->where('coordinator_id', $request->coordinator_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $warehouses = $query->with('coordinator')
            ->withCount('inventoryItems')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Warehouses retrieved successfully',
            'data' => $warehouses,
        ], 200);
    }

    /**
     * Create a new warehouse.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'coordinator_id' => 'required|exists:users,id',
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string',
            'max_capacity' => 'sometimes|nullable|numeric|min:0',
        ]);

        $coordinator = User::where('id', $validated['coordinator_id'])
            ->where('role', 'coordinator')
            ->first();
        if (!$coordinator) {
            return response()->json([
                'message' => 'Coordinator not found',
            ], 400);
        }

        $warehouse = Warehouse::create([
            'coordinator_id' => $validated['coordinator_id'],
            'name' => $validated['name'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'address' => $validated['address'],
            'max_capacity' => $validated['max_capacity'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse->load('coordinator'),
        ], 201);
    }

    /**
     * Show a specific warehouse.
     */
    public function show(Request $request, $id)
    {
        $warehouse = Warehouse::with('inventoryItems', 'coordinator')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Warehouse retrieved successfully',
            'data' => $warehouse,
        ], 200);
    }

    /**
     * Update a warehouse.
     */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'coordinator_id' => 'sometimes|exists:users,id',
            'name' => 'sometimes|string|max:150',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'sometimes|string',
            'max_capacity' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|in:active,closed',
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

        $warehouse->update($validated);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse->fresh()->load('coordinator'),
        ], 200);
    }

    /**
     * Delete a warehouse.
     */
    public function destroy(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        if ($warehouse->distributions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete warehouse with active distributions',
            ], 400);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ], 200);
    }

    /**
     * Get inventory items for a warehouse.
     */
    public function inventory(Request $request, $id)
    {
        $warehouse = Warehouse::with('inventoryItems')->findOrFail($id);

        $items = $warehouse->inventoryItems()
            ->orderBy('category')
            ->get();

        return response()->json([
            'message' => 'Inventory retrieved successfully',
            'data' => [
                'warehouse' => $warehouse,
                'items' => $items,
                'total_items' => $items->count(),
            ],
        ], 200);
    }

    /**
     * Add inventory item.
     */
    public function addInventory(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category' => 'required|in:luong_thuc,thuoc,quan_ao,thiet_bi,khac',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:30',
        ]);

        $item = InventoryItem::create([
            'warehouse_id' => $warehouse->id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Inventory item added successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Update inventory item quantity.
     */
    public function updateInventory(Request $request, $id, $itemId)
    {
        $warehouse = Warehouse::findOrFail($id);

        $item = InventoryItem::where('warehouse_id', $warehouse->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Inventory item updated successfully',
            'data' => $item,
        ], 200);
    }

    /**
     * Delete inventory item.
     */
    public function deleteInventory(Request $request, $id, $itemId)
    {
        $warehouse = Warehouse::findOrFail($id);

        $item = InventoryItem::where('warehouse_id', $warehouse->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully',
        ], 200);
    }
}
