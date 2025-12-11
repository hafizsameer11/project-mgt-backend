<?php

namespace App\Repositories;

use App\Models\DeveloperPayment;
use Illuminate\Database\Eloquent\Collection;

class DeveloperPaymentRepository extends BaseRepository
{
    public function __construct(DeveloperPayment $model)
    {
        parent::__construct($model);
    }

    public function getByDeveloper(int $developerId): Collection
    {
        return $this->model->where('developer_id', $developerId)->get();
    }

    public function getByProject(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)->get();
    }

    public function getOutstandingBalances(): Collection
    {
        return $this->model->with('developer', 'project')
            ->get()
            ->filter(function ($payment) {
                return $payment->remaining_amount > 0;
            });
    }
}

