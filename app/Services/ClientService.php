<?php

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Repositories\ActivityLogRepository;

class ClientService
{
    protected $clientRepository;
    protected $activityLogRepository;

    public function __construct(
        ClientRepository $clientRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->clientRepository = $clientRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function getAll(array $filters = [])
    {
        $query = \App\Models\Client::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['assigned_bd'])) {
            $query->where('assigned_bd', $filters['assigned_bd']);
        }

        $clients = $query->with('assignedBd', 'projects')->paginate(15);
        
        // Add has_account flag to each client
        $clients->getCollection()->transform(function ($client) {
            $hasAccount = false;
            if ($client->email) {
                $hasAccount = \App\Models\User::where('email', $client->email)
                    ->where('role', 'Client')
                    ->exists();
            }
            $client->has_account = $hasAccount;
            return $client;
        });
        
        return $clients;
    }

    public function create(array $data, int $userId)
    {
        $client = $this->clientRepository->create($data);
        $this->logActivity($client, $userId, 'created', null, $data);
        return $client;
    }

    public function update(int $id, array $data, int $userId)
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            return null;
        }

        $oldData = $client->toArray();
        $this->clientRepository->update($id, $data);
        $client->refresh();

        $this->logActivity($client, $userId, 'updated', $oldData, $client->toArray());

        return $client;
    }

    public function delete(int $id, int $userId): bool
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            return false;
        }

        $oldData = $client->toArray();
        $this->logActivity($client, $userId, 'deleted', $oldData, null);
        
        return $this->clientRepository->delete($id);
    }

    protected function logActivity($model, int $userId, string $action, $oldValue = null, $newValue = null)
    {
        $this->activityLogRepository->create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}

