<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with('user');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('date')) {
            $query->whereDate('attendance_date', $request->date);
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('attendance_date', [$request->start_date, $request->end_date]);
        }

        $attendance = $query->orderBy('attendance_date', 'desc')->paginate(15);
        return response()->json($attendance);
    }

    public function clockIn(Request $request)
    {
        $currentUser = $request->user();
        $today = Carbon::today();
        
        // Allow admins/managers to clock in for other users
        $targetUserId = $request->input('user_id', $currentUser->id);
        $canManageOthers = in_array($currentUser->role, ['Admin', 'Project Manager']);
        
        if ($targetUserId != $currentUser->id && !$canManageOthers) {
            return response()->json(['message' => 'You do not have permission to mark attendance for others'], 403);
        }

        $existing = Attendance::where('user_id', $targetUserId)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($existing && $existing->clock_in) {
            return response()->json(['message' => 'Already clocked in today'], 400);
        }

        if ($existing) {
            $existing->update([
                'clock_in' => now()->format('H:i:s'),
                'status' => 'present',
                'ip_address' => $request->ip(),
                'location' => $request->input('location'),
            ]);
            return response()->json($existing->load('user'));
        }

        $attendance = Attendance::create([
            'user_id' => $targetUserId,
            'attendance_date' => $today,
            'clock_in' => now()->format('H:i:s'),
            'status' => 'present',
            'ip_address' => $request->ip(),
            'location' => $request->input('location'),
        ]);

        return response()->json($attendance->load('user'), 201);
    }

    public function clockOut(Request $request)
    {
        $currentUser = $request->user();
        $today = Carbon::today();
        
        // Allow admins/managers to clock out for other users
        $targetUserId = $request->input('user_id', $currentUser->id);
        $canManageOthers = in_array($currentUser->role, ['Admin', 'Project Manager']);
        
        if ($targetUserId != $currentUser->id && !$canManageOthers) {
            return response()->json(['message' => 'You do not have permission to mark attendance for others'], 403);
        }

        $attendance = Attendance::where('user_id', $targetUserId)
            ->whereDate('attendance_date', $today)
            ->first();

        if (!$attendance || !$attendance->clock_in) {
            return response()->json(['message' => 'Please clock in first'], 400);
        }

        if ($attendance->clock_out) {
            return response()->json(['message' => 'Already clocked out today'], 400);
        }

        $attendanceDate = $attendance->attendance_date instanceof \Carbon\Carbon 
            ? $attendance->attendance_date->format('Y-m-d')
            : $attendance->attendance_date;
        $clockIn = Carbon::parse($attendanceDate . ' ' . $attendance->clock_in);
        $clockOut = now();
        $totalHours = $clockIn->diffInHours($clockOut) + ($clockIn->diffInMinutes($clockOut) % 60) / 60;
        $overtimeHours = max(0, $totalHours - 8); // Assuming 8 hours is standard

        $attendance->update([
            'clock_out' => $clockOut->format('H:i:s'),
            'total_hours' => round($totalHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
        ]);

        return response()->json($attendance->load('user'));
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        $canManageOthers = in_array($currentUser->role, ['Admin', 'Project Manager']);
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'attendance_date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i:s',
            'clock_out' => 'nullable|date_format:H:i:s',
            'status' => 'required|in:present,absent,late,half_day,holiday,leave',
            'notes' => 'nullable|string',
        ]);

        // Only allow admins/managers to create attendance for others
        if ($request->user_id != $currentUser->id && !$canManageOthers) {
            return response()->json(['message' => 'You do not have permission to create attendance for others'], 403);
        }

        $attendance = Attendance::create($request->all());
        return response()->json($attendance->load('user'), 201);
    }

    public function show(int $id)
    {
        $attendance = Attendance::with('user')->find($id);
        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found'], 404);
        }
        return response()->json($attendance);
    }

    public function update(Request $request, int $id)
    {
        $currentUser = $request->user();
        $attendance = Attendance::find($id);
        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found'], 404);
        }

        $canManageOthers = in_array($currentUser->role, ['Admin', 'Project Manager']);
        
        // Only allow users to update their own attendance, or admins/managers to update any
        if ($attendance->user_id != $currentUser->id && !$canManageOthers) {
            return response()->json(['message' => 'You do not have permission to update this attendance record'], 403);
        }

        $request->validate([
            'attendance_date' => 'sometimes|date',
            'clock_in' => 'nullable|date_format:H:i:s',
            'clock_out' => 'nullable|date_format:H:i:s',
            'total_hours' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:present,absent,late,half_day,holiday,leave',
            'notes' => 'nullable|string',
        ]);

        $attendance->update($request->all());
        return response()->json($attendance->load('user'));
    }

    public function destroy(int $id)
    {
        $attendance = Attendance::find($id);
        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found'], 404);
        }
        $attendance->delete();
        return response()->json(['message' => 'Attendance record deleted successfully']);
    }
}
