<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function stats()
    {
        return response()->json($this->dashboardService->getStats());
    }

    public function charts()
    {
        return response()->json($this->dashboardService->getCharts());
    }
}

