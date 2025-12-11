<?php

namespace App\Http\Controllers;

use App\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected $logRepository;

    public function __construct(ActivityLogRepository $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    public function index(Request $request)
    {
        $query = \App\Models\ActivityLog::query()->with('user');

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->has('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json($logs);
    }

    public function getByModel(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $logs = $this->logRepository->getByModel(
            $request->model_type,
            $request->model_id
        );

        return response()->json($logs);
    }
}

