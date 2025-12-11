<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDepreciation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::with('assignedUser', 'creator', 'depreciations');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        $assets = $query->orderBy('asset_name')->paginate(15);
        return response()->json($assets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'asset_name' => 'required|string|max:255',
            'asset_type' => 'required|in:fixed,current,intangible',
            'category' => 'required|in:equipment,vehicle,furniture,software,building,other',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'required|numeric|min:0',
            'depreciation_method' => 'nullable|in:straight_line,declining_balance,none',
            'useful_life_years' => 'nullable|integer|min:1',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,disposed,maintenance,retired',
            'assigned_to' => 'nullable|exists:users,id',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'serial_number' => 'nullable|string|max:255',
        ]);

        $assetCode = 'AST-' . strtoupper(Str::random(8));

        $data = $request->all();
        $data['asset_code'] = $assetCode;
        $data['current_value'] = $request->purchase_cost;
        $data['created_by'] = $request->user()->id;

        $asset = Asset::create($data);
        return response()->json($asset->load('assignedUser', 'creator'), 201);
    }

    public function show(int $id)
    {
        $asset = Asset::with('assignedUser', 'creator', 'depreciations')->find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }
        return response()->json($asset);
    }

    public function update(Request $request, int $id)
    {
        $asset = Asset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        $request->validate([
            'asset_name' => 'sometimes|string|max:255',
            'asset_type' => 'sometimes|in:fixed,current,intangible',
            'category' => 'sometimes|in:equipment,vehicle,furniture,software,building,other',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'sometimes|numeric|min:0',
            'depreciation_method' => 'nullable|in:straight_line,declining_balance,none',
            'useful_life_years' => 'nullable|integer|min:1',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:active,disposed,maintenance,retired',
            'assigned_to' => 'nullable|exists:users,id',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'serial_number' => 'nullable|string|max:255',
        ]);

        $asset->update($request->all());
        return response()->json($asset->load('assignedUser', 'creator'));
    }

    public function depreciate(Request $request, int $id)
    {
        $asset = Asset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        if ($asset->depreciation_method === 'none') {
            return response()->json(['message' => 'Asset does not have depreciation enabled'], 422);
        }

        $request->validate([
            'depreciation_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $lastDepreciation = AssetDepreciation::where('asset_id', $id)
            ->orderBy('depreciation_date', 'desc')
            ->first();

        $accumulatedDepreciation = $lastDepreciation ? $lastDepreciation->accumulated_depreciation : 0;
        $bookValue = $lastDepreciation ? $lastDepreciation->book_value : $asset->purchase_cost;

        $depreciationAmount = 0;
        if ($asset->depreciation_method === 'straight_line' && $asset->useful_life_years) {
            $annualDepreciation = $asset->purchase_cost / $asset->useful_life_years;
            $depreciationAmount = $annualDepreciation / 12; // Monthly
        } elseif ($asset->depreciation_method === 'declining_balance' && $asset->depreciation_rate) {
            $depreciationAmount = $bookValue * ($asset->depreciation_rate / 100) / 12; // Monthly
        }

        if ($depreciationAmount <= 0) {
            return response()->json(['message' => 'Invalid depreciation calculation'], 422);
        }

        $newAccumulated = $accumulatedDepreciation + $depreciationAmount;
        $newBookValue = $asset->purchase_cost - $newAccumulated;

        $depreciation = AssetDepreciation::create([
            'asset_id' => $id,
            'depreciation_date' => $request->depreciation_date,
            'depreciation_amount' => $depreciationAmount,
            'accumulated_depreciation' => $newAccumulated,
            'book_value' => $newBookValue,
            'notes' => $request->notes,
        ]);

        $asset->current_value = $newBookValue;
        $asset->save();

        return response()->json($depreciation->load('asset'));
    }

    public function destroy(int $id)
    {
        $asset = Asset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }
        $asset->depreciations()->delete();
        $asset->delete();
        return response()->json(['message' => 'Asset deleted successfully']);
    }
}
