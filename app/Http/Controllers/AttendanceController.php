<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\PlantingActivity;
use App\Models\Tree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    /**
     * List all attendance records for an activity
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activity_id' => 'required|exists:planting_activities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $records = AttendanceRecord::with(['user', 'tree'])
            ->where('activity_id', $request->activity_id)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $records->count(),
            'data' => $records
        ]);
    }

    /**
     * Get attendance record details
     */
    public function show($id)
    {
        $record = AttendanceRecord::with(['user', 'activity', 'tree'])
            ->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }

    /**
     * Create attendance record from tree submission
     * (Called when a tree is submitted by a planter)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activity_id' => 'required|exists:planting_activities,id',
            'user_id' => 'required|exists:users,id',
            'attendance' => 'required|in:present,absent',
            'tree_id' => 'required|exists:trees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if attendance already exists for this user and activity
        $existing = AttendanceRecord::where('activity_id', $request->activity_id)
            ->where('user_id', $request->user_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record already exists for this user and activity'
            ], 422);
        }

        $record = AttendanceRecord::create([
            'activity_id' => $request->activity_id,
            'user_id' => $request->user_id,
            'attendance' => $request->attendance,
            'tree_id' => $request->tree_id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record created successfully',
            'data' => $record
        ], 201);
    }

    /**
     * Update attendance record (mark as absent/present)
     */
    public function update(Request $request, $id)
    {
        $record = AttendanceRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'attendance' => 'required|in:present,absent',
            'tree_id' => 'nullable|exists:trees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $record->update([
            'attendance' => $request->attendance,
            'tree_id' => $request->tree_id ?? $record->tree_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record updated successfully',
            'data' => $record
        ]);
    }

    /**
     * Delete attendance record
     */
    public function destroy($id)
    {
        $record = AttendanceRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ]);
    }

    /**
     * Get attendance summary for an activity
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activity_id' => 'required|exists:planting_activities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $present = AttendanceRecord::where('activity_id', $request->activity_id)
            ->where('attendance', 'present')
            ->count();

        $absent = AttendanceRecord::where('activity_id', $request->activity_id)
            ->where('attendance', 'absent')
            ->count();

        $total = AttendanceRecord::where('activity_id', $request->activity_id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'activity_id' => $request->activity_id,
                'total_participants' => $total,
                'present' => $present,
                'absent' => $absent,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ]
        ]);
    }
}
