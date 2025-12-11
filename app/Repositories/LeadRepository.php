<?php

namespace App\Repositories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

class LeadRepository extends BaseRepository
{
    public function __construct(Lead $model)
    {
        parent::__construct($model);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('lead_status', $status)->get();
    }

    public function getByAssignedTo(int $userId): Collection
    {
        return $this->model->where('assigned_to', $userId)->get();
    }

    public function getFollowUpToday(): Collection
    {
        return $this->model->where('follow_up_date', today())
            ->where('lead_status', '!=', 'Converted')
            ->where('lead_status', '!=', 'Lost')
            ->get();
    }

    public function getConverted(): Collection
    {
        return $this->model->where('lead_status', 'Converted')->get();
    }
}

