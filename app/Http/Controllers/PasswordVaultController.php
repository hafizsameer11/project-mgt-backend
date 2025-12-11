<?php

namespace App\Http\Controllers;

use App\Models\PasswordVault;
use App\Repositories\PasswordVaultRepository;
use Illuminate\Http\Request;

class PasswordVaultController extends Controller
{
    protected $vaultRepository;

    public function __construct(PasswordVaultRepository $vaultRepository)
    {
        $this->vaultRepository = $vaultRepository;
    }

    public function index(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
        ]);

        $vaults = $this->vaultRepository->getByClient($request->client_id);
        return response()->json(['data' => $vaults]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'category' => 'nullable|in:Server,Domain,Hosting,Admin Panel',
            'extra_notes' => 'nullable|string',
        ]);

        $vault = PasswordVault::create($request->all());
        return response()->json($vault, 201);
    }

    public function show(int $id)
    {
        $vault = PasswordVault::find($id);
        if (!$vault) {
            return response()->json(['message' => 'Password vault not found'], 404);
        }
        return response()->json($vault);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'category' => 'nullable|in:Server,Domain,Hosting,Admin Panel',
            'extra_notes' => 'nullable|string',
        ]);

        $vault = PasswordVault::find($id);
        if (!$vault) {
            return response()->json(['message' => 'Password vault not found'], 404);
        }

        $vault->update($request->all());
        return response()->json($vault);
    }

    public function destroy(int $id)
    {
        $vault = PasswordVault::find($id);
        if (!$vault) {
            return response()->json(['message' => 'Password vault not found'], 404);
        }

        $vault->delete();
        return response()->json(['message' => 'Password vault deleted successfully']);
    }
}

