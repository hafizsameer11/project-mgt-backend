<?php

namespace App\Repositories;

use App\Models\ClientPayment;
use Illuminate\Database\Eloquent\Collection;

class ClientPaymentRepository extends BaseRepository
{
    public function __construct(ClientPayment $model)
    {
        parent::__construct($model);
    }

    public function getByClient(int $clientId): Collection
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getByProject(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getPendingPayments(): Collection
    {
        return $this->model->whereIn('status', ['Unpaid', 'Partial'])
            ->with('client', 'project')
            ->get();
    }
}

