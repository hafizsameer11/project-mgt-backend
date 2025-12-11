<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('vendor', 'project', 'creator');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('order_date', 'desc')->paginate(15);
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'project_id' => 'nullable|exists:projects,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:draft,sent,confirmed,received,cancelled',
            'notes' => 'nullable|string',
        ]);

        $poNumber = 'PO-' . strtoupper(Str::random(8));

        $data = $request->all();
        $data['po_number'] = $poNumber;
        $data['created_by'] = $request->user()->id;

        $order = PurchaseOrder::create($data);
        return response()->json($order->load('vendor', 'project', 'creator'), 201);
    }

    public function show(int $id)
    {
        $order = PurchaseOrder::with('vendor', 'project', 'creator', 'bills')->find($id);
        if (!$order) {
            return response()->json(['message' => 'Purchase order not found'], 404);
        }
        return response()->json($order);
    }

    public function update(Request $request, int $id)
    {
        $order = PurchaseOrder::find($id);
        if (!$order) {
            return response()->json(['message' => 'Purchase order not found'], 404);
        }

        $request->validate([
            'vendor_id' => 'sometimes|exists:vendors,id',
            'project_id' => 'nullable|exists:projects,id',
            'order_date' => 'sometimes|date',
            'expected_delivery_date' => 'nullable|date',
            'total_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,sent,confirmed,received,cancelled',
            'notes' => 'nullable|string',
        ]);

        $order->update($request->all());
        return response()->json($order->load('vendor', 'project', 'creator'));
    }

    public function destroy(int $id)
    {
        $order = PurchaseOrder::find($id);
        if (!$order) {
            return response()->json(['message' => 'Purchase order not found'], 404);
        }
        $order->delete();
        return response()->json(['message' => 'Purchase order deleted successfully']);
    }
}
