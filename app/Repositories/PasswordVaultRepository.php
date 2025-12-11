<?php

namespace App\Repositories;

use App\Models\PasswordVault;

class PasswordVaultRepository extends BaseRepository
{
    public function __construct(PasswordVault $model)
    {
        parent::__construct($model);
    }

    public function getByClient(int $clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getByCategory(int $clientId, string $category)
    {
        return $this->model->where('client_id', $clientId)
            ->where('category', $category)
            ->get();
    }
}

