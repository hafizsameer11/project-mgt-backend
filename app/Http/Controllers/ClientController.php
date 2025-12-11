<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Services\ClientService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    protected $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function index(Request $request)
    {
        $clients = $this->clientService->getAll($request->all());
        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create($request->validated(), $request->user()->id);
        return new ClientResource($client->load('assignedBd'));
    }

    public function show(int $id)
    {
        $client = \App\Models\Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        
        // Check if client has a user account
        $hasAccount = false;
        if ($client->email) {
            $hasAccount = \App\Models\User::where('email', $client->email)
                ->where('role', 'Client')
                ->exists();
        }
        
        $clientData = new ClientResource($client->load('assignedBd', 'projects', 'passwordVaults'));
        $clientArray = $clientData->toArray(request());
        $clientArray['has_account'] = $hasAccount;
        
        return response()->json($clientArray);
    }

    public function update(UpdateClientRequest $request, int $id)
    {
        $client = $this->clientService->update($id, $request->validated(), $request->user()->id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        return new ClientResource($client->load('assignedBd'));
    }

    public function destroy(int $id, Request $request)
    {
        $deleted = $this->clientService->delete($id, $request->user()->id);
        if (!$deleted) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        return response()->json(['message' => 'Client deleted successfully']);
    }

    /**
     * Create user account for a client
     */
    public function createUserAccount(Request $request, int $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $client = \App\Models\Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Check if user already exists with this email
        $existingUser = \App\Models\User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json(['message' => 'User account already exists with this email'], 400);
        }

        // Create user account
        $user = \App\Models\User::create([
            'name' => $client->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => 'Client',
        ]);

        // Update client email if different
        if ($client->email !== $request->email) {
            $client->update(['email' => $request->email]);
        }

        return response()->json([
            'message' => 'User account created successfully',
            'user' => $user,
        ], 201);
    }
}

