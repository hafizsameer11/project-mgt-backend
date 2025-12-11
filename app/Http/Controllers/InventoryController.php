<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::with('transactions');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderBy('item_name')->paginate(15);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'unit_of_measure' => 'nullable|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);

        $itemCode = 'INV-' . strtoupper(Str::random(8));

        $data = $request->all();
        $data['item_code'] = $itemCode;

        $item = InventoryItem::create($data);
        return response()->json($item, 201);
    }

    public function show(int $id)
    {
        $item = InventoryItem::with('transactions.project', 'transactions.vendor')->find($id);
        if (!$item) {
            return response()->json(['message' => 'Inventory item not found'], 404);
        }
        return response()->json($item);
    }

    public function update(Request $request, int $id)
    {
        $item = InventoryItem::find($id);
        if (!$item) {
            return response()->json(['message' => 'Inventory item not found'], 404);
        }

        $request->validate([
            'item_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'unit_of_measure' => 'nullable|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);

        $item->update($request->all());
        return response()->json($item);
    }

    public function adjust(Request $request, int $id)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $item = InventoryItem::find($id);
        if (!$item) {
            return response()->json(['message' => 'Inventory item not found'], 404);
        }

        $transaction = InventoryTransaction::create([
            'inventory_item_id' => $id,
            'transaction_type' => 'adjustment',
            'quantity' => $request->quantity,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        $item->current_stock += $request->quantity;
        $item->save();

        return response()->json($item->load('transactions'));
    }

    public function destroy(int $id)
    {
        $item = InventoryItem::find($id);
        if (!$item) {
            return response()->json(['message' => 'Inventory item not found'], 404);
        }
        $item->transactions()->delete();
        $item->delete();
        return response()->json(['message' => 'Inventory item deleted successfully']);
    }
}
