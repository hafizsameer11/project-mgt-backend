<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Services\LeadService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    protected $leadService;
    protected $fileUploadService;

    public function __construct(LeadService $leadService, FileUploadService $fileUploadService)
    {
        $this->leadService = $leadService;
        $this->fileUploadService = $fileUploadService;
    }

    public function index(Request $request)
    {
        $leads = $this->leadService->getAll($request->all());
        return LeadResource::collection($leads);
    }

    public function store(StoreLeadRequest $request)
    {
        $data = $request->validated();
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'leads'
            );
        }
        
        $lead = $this->leadService->create($data, $request->user()->id);
        return new LeadResource($lead->load('assignedUser'));
    }

    public function show(int $id)
    {
        $lead = \App\Models\Lead::find($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        return new LeadResource($lead->load('assignedUser', 'convertedClient', 'projectAfterConversion'));
    }

    public function update(UpdateLeadRequest $request, int $id)
    {
        $data = $request->validated();
        
        // Handle file uploads
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->fileUploadService->uploadMultiple(
                $request->file('attachments'),
                'leads'
            );
        }
        
        $lead = $this->leadService->update($id, $data, $request->user()->id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        return new LeadResource($lead->load('assignedUser'));
    }

    public function destroy(int $id, Request $request)
    {
        $deleted = $this->leadService->delete($id, $request->user()->id);
        if (!$deleted) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        return response()->json(['message' => 'Lead deleted successfully']);
    }

    public function convertToClient(Request $request, int $id)
    {
        $request->validate([
            'client' => 'required|array',
            'client.name' => 'required|string',
            'project' => 'required|array',
            'project.title' => 'required|string',
        ]);

        $result = $this->leadService->convertToClient(
            $id,
            $request->input('client'),
            $request->input('project'),
            $request->user()->id
        );

        if (!$result) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        return response()->json([
            'message' => 'Lead converted to client successfully',
            'data' => $result,
        ]);
    }

    public function followUpReminders()
    {
        $leads = $this->leadService->getFollowUpReminders();
        return LeadResource::collection($leads);
    }
}

