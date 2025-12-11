<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

class ClientRepository extends BaseRepository
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getByAssignedBd(int $userId): Collection
    {
        return $this->model->where('assigned_bd', $userId)->get();
    }

    public function getWithProjects(): Collection
    {
        return $this->model->with('projects')->get();
    }
}

