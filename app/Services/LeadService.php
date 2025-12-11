<?php

namespace App\Services;

use App\Models\Lead;
use App\Repositories\LeadRepository;
use App\Repositories\ActivityLogRepository;
use App\Events\LeadAssigned;
use Illuminate\Support\Facades\DB;

class LeadService
{
    protected $leadRepository;
    protected $activityLogRepository;

    public function __construct(
        LeadRepository $leadRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->leadRepository = $leadRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function getAll(array $filters = [])
    {
        $query = Lead::query();

        if (isset($filters['status'])) {
            $query->where('lead_status', $filters['status']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query->with('assignedUser')->paginate(15);
    }

    public function create(array $data, int $userId): Lead
    {
        $lead = $this->leadRepository->create($data);

        $this->logActivity($lead, $userId, 'created', null, $data);

        if (isset($data['assigned_to'])) {
            event(new LeadAssigned($lead));
        }

        return $lead;
    }

    public function update(int $id, array $data, int $userId): ?Lead
    {
        $lead = $this->leadRepository->find($id);
        if (!$lead) {
            return null;
        }

        $oldData = $lead->toArray();
        $this->leadRepository->update($id, $data);
        $lead->refresh();

        $this->logActivity($lead, $userId, 'updated', $oldData, $lead->toArray());

        if (isset($data['assigned_to']) && $data['assigned_to'] != $oldData['assigned_to']) {
            event(new LeadAssigned($lead));
        }

        return $lead;
    }

    public function delete(int $id, int $userId): bool
    {
        $lead = $this->leadRepository->find($id);
        if (!$lead) {
            return false;
        }

        $oldData = $lead->toArray();
        $this->logActivity($lead, $userId, 'deleted', $oldData, null);
        
        return $this->leadRepository->delete($id);
    }

    public function convertToClient(int $leadId, array $clientData, array $projectData, int $userId)
    {
        return DB::transaction(function () use ($leadId, $clientData, $projectData, $userId) {
            $lead = $this->leadRepository->find($leadId);
            if (!$lead) {
                return null;
            }

            // Create client
            $client = \App\Models\Client::create([
                'name' => $clientData['name'] ?? $lead->name,
                'email' => $clientData['email'] ?? $lead->email,
                'phone' => $clientData['phone'] ?? $lead->phone,
                'company' => $clientData['company'] ?? null,
                'assigned_bd' => $lead->assigned_to,
                'status' => 'Active',
            ]);

            // Create project
            $project = \App\Models\Project::create([
                'title' => $projectData['title'] ?? $lead->name . ' Project',
                'client_id' => $client->id,
                'budget' => $projectData['budget'] ?? $lead->estimated_budget,
                'assigned_bd' => $lead->assigned_to,
                'status' => 'Planning',
            ]);

            // Update lead
            $this->leadRepository->update($leadId, [
                'lead_status' => 'Converted',
                'converted_client_id' => $client->id,
                'project_id_after_conversion' => $project->id,
                'conversion_date' => now(),
            ]);

            $this->logActivity($lead, $userId, 'converted', null, [
                'client_id' => $client->id,
                'project_id' => $project->id,
            ]);

            return [
                'lead' => $lead->fresh(),
                'client' => $client,
                'project' => $project,
            ];
        });
    }

    public function getFollowUpReminders()
    {
        return $this->leadRepository->getFollowUpToday();
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

