<?php

namespace App\Repositories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository extends BaseRepository
{
    public function __construct(Team $model)
    {
        parent::__construct($model);
    }

    public function getByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->get();
    }

    public function getDevelopers(): Collection
    {
        return $this->model->where('role', 'Developer')->get();
    }

    public function getByPaymentType(string $paymentType): Collection
    {
        return $this->model->where('payment_type', $paymentType)->get();
    }
}

