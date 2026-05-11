<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Get all warehouses for coordinator (F22)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $warehouses = Warehouse::where('coordinator_id', $user->id)
            ->with('inventoryItems')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Warehouses retrieved successfully',
            'data' => $warehouses,
        ], 200);
    }

    /**
     * Create a new warehouse (F22)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string',
            'max_capacity' => 'sometimes|nullable|numeric|min:0',
        ]);

        $warehouse = Warehouse::create([
            'coordinator_id' => $user->id,
            ...$validated,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse,
        ], 201);
    }

    /**
     * Show a specific warehouse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->with('inventoryItems')
            ->firstOrFail();

        return response()->json([
            'message' => 'Warehouse retrieved successfully',
            'data' => $warehouse,
        ], 200);
    }

    /**
     * Update a warehouse (F22)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'sometimes|string',
            'max_capacity' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|in:active,closed',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse,
        ], 200);
    }

    /**
     * Delete a warehouse
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Check if warehouse has distributions
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
     * Get inventory items for a warehouse (F23)
     */
    public function inventory(Request $request, $id)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $items = $warehouse->inventoryItems()
            ->orderBy('category')
            ->get();

        $total_value = $items->sum(function($item) {
            return $item->quantity;
        });

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
     * Add inventory item (F23)
     */
    public function addInventory(Request $request, $id)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

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
     * Update inventory item quantity (F23)
     */
    public function updateInventory(Request $request, $id, $itemId)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

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
     * Delete inventory item
     */
    public function deleteInventory(Request $request, $id, $itemId)
    {
        $user = $request->user();

        $warehouse = Warehouse::where('coordinator_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $item = InventoryItem::where('warehouse_id', $warehouse->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully',
        ], 200);
    }
}
