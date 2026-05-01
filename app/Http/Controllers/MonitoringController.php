<?php

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Models\MonitoringAssignment;
use App\Models\Tree;
use App\Models\PlantingActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MonitoringController extends Controller
{
    /**
     * PHASE 3: Monitoring & Status Update
     * Monitoring staff update tree status and take photos
     */

    /**
     * List all monitoring assignments for a staff member
     */
    public function assignments()
    {
        $assignments = MonitoringAssignment::with([
            'activity',
            'activity.organization',
            'activity.trees',
        ])
            ->where('staff_id', auth()->id())
            ->where('is_completed', false)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $assignments->count(),
            'data' => $assignments
        ]);
    }

    /**
     * Get trees for monitoring (GIS Map view)
     */
    public function getTreesForMonitoring(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|exists:monitoring_assignments,id',
            'barangay' => 'nullable|string',
            'species' => 'nullable|string',
            'status' => 'nullable|in:alive,dead,pending',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify staff has this assignment
        $assignment = MonitoringAssignment::where('id', $request->assignment_id)
            ->where('staff_id', auth()->id())
            ->firstOrFail();

        $query = Tree::with(['activity', 'planter', 'monitoringRecords'])
            ->where('activity_id', $assignment->activity_id);

        // Apply filters
        if ($request->has('species')) {
            $query->whereHas('activity', function ($q) use ($request) {
                $q->where('tree_species', $request->species);
            });
        }

        if ($request->has('status')) {
            // Filter by latest monitoring status
            if ($request->status === 'pending') {
                $query->whereDoesntHave('monitoringRecords');
            } else {
                $query->whereHas('monitoringRecords', function ($q) use ($request) {
                    $q->latest()->where('status', $request->status);
                });
            }
        }

        $trees = $query->get();

        // Add current status to each tree
        $trees->each(function ($tree) {
            $latestRecord = $tree->monitoringRecords()->latest()->first();
            $tree->current_status = $latestRecord?->status ?? 'pending';
            $tree->last_checked = $latestRecord?->checked_at;
        });

        return response()->json([
            'success' => true,
            'count' => $trees->count(),
            'data' => $trees
        ]);
    }

    /**
     * Create monitoring record (update tree status)
     * Step 7-12 of Phase 3
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tree_id' => 'required|exists:trees,id',
            'assignment_id' => 'nullable|exists:monitoring_assignments,id',
            'status' => 'required|in:alive,dead',
            'photo' => 'required|string', // Base64 encoded, camera only
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify assignment if provided
            $assignment = null;
            if ($request->assignment_id) {
                $assignment = MonitoringAssignment::where('id', $request->assignment_id)
                    ->where('staff_id', auth()->id())
                    ->first();

                if (!$assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not assigned to monitor this activity'
                    ], 403);
                }

                // Verify tree belongs to this assignment's activity
                $tree = Tree::findOrFail($request->tree_id);
                if ($tree->activity_id !== $assignment->activity_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tree does not belong to this monitoring assignment'
                    ], 403);
                }
            }

            // Handle base64 photo upload (camera only)
            $photoPath = $this->saveBase64Photo($request->photo);

            // Create monitoring record
            $record = MonitoringRecord::create([
                'tree_id' => $request->tree_id,
                'staff_id' => auth()->id(),
                'assignment_id' => $request->assignment_id,
                'photo' => $photoPath,
                'status' => $request->status,
                'checked_at' => now(),
                'synced_at' => now(),
            ]);

            // Recalculate survival rate for this activity if assignment exists
            if ($assignment) {
                $this->recalculateSurvivalRate($assignment->activity_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tree status updated successfully',
                'data' => [
                    'record' => $record->load(['tree', 'staff']),
                    'new_status' => $request->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tree status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync offline monitoring records
     */
    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'records' => 'required|array',
            'records.*.tree_id' => 'required|exists:trees,id',
            'records.*.assignment_id' => 'nullable|exists:monitoring_assignments,id',
            'records.*.status' => 'required|in:alive,dead',
            'records.*.photo' => 'required|string',
            'records.*.local_id' => 'required|string',
            'records.*.checked_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $syncedRecords = [];
        $errors = [];
        $affectedActivities = [];

        foreach ($request->records as $recordData) {
            try {
                // Verify assignment if provided
                $assignment = null;
                if ($recordData['assignment_id']) {
                    $assignment = MonitoringAssignment::where('id', $recordData['assignment_id'])
                        ->where('staff_id', auth()->id())
                        ->first();

                    if (!$assignment) {
                        throw new \Exception('Unauthorized assignment');
                    }
                }

                $photoPath = $this->saveBase64Photo($recordData['photo']);

                $record = MonitoringRecord::create([
                    'tree_id' => $recordData['tree_id'],
                    'staff_id' => auth()->id(),
                    'assignment_id' => $recordData['assignment_id'],
                    'photo' => $photoPath,
                    'status' => $recordData['status'],
                    'checked_at' => $recordData['checked_at'] ?? now(),
                    'synced_at' => now(),
                ]);

                $syncedRecords[] = [
                    'local_id' => $recordData['local_id'],
                    'server_id' => $record->id,
                ];

                if ($assignment) {
                    $affectedActivities[] = $assignment->activity_id;
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'local_id' => $recordData['local_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Recalculate survival rates for all affected activities
        foreach (array_unique($affectedActivities) as $activityId) {
            $this->recalculateSurvivalRate($activityId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync completed',
            'synced_count' => count($syncedRecords),
            'error_count' => count($errors),
            'synced_records' => $syncedRecords,
            'errors' => $errors,
        ]);
    }

    /**
     * Get monitoring history
     */
    public function history(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tree_id' => 'nullable|exists:trees,id',
            'activity_id' => 'nullable|exists:planting_activities,id',
            'staff_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = MonitoringRecord::with(['tree', 'staff', 'assignment']);

        if ($request->has('tree_id')) {
            $query->where('tree_id', $request->tree_id);
        }

        if ($request->has('activity_id')) {
            $query->whereHas('assignment', function ($q) use ($request) {
                $q->where('activity_id', $request->activity_id);
            });
        }

        if ($request->has('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        // Staff can only see their own records
        if (auth()->user()->role === 'monitoring staff') {
            $query->where('staff_id', auth()->id());
        }

        $records = $query->orderBy('checked_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $records->count(),
            'data' => $records
        ]);
    }

    /**
     * Helper: Recalculate survival rate for an activity
     */
    private function recalculateSurvivalRate($activityId)
    {
        $activity = PlantingActivity::with(['trees'])->find($activityId);

        if (!$activity) {
            return;
        }

        $totalTrees = $activity->trees->count();

        if ($totalTrees === 0) {
            return;
        }

        $aliveTrees = 0;
        $deadTrees = 0;

        foreach ($activity->trees as $tree) {
            $latestStatus = $tree->monitoringRecords()->latest()->first()?->status;
            if ($latestStatus === 'alive') {
                $aliveTrees++;
            } elseif ($latestStatus === 'dead') {
                $deadTrees++;
            }
        }

        $survivalRate = round(($aliveTrees / $totalTrees) * 100, 2);

        // Store in activity (optional: add survival_rate column to planting_activities)
        // $activity->update(['survival_rate' => $survivalRate]);

        return [
            'total' => $totalTrees,
            'alive' => $aliveTrees,
            'dead' => $deadTrees,
            'survival_rate' => $survivalRate,
        ];
    }

    /**
     * Helper: Save base64 photo
     */
    private function saveBase64Photo($base64String)
    {
        $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);
        $imageData = base64_decode($base64String);

        if (!$imageData) {
            throw new \Exception('Invalid image data');
        }

        $filename = 'monitoring/' . uniqid() . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $imageData);

        return $filename;
    }
}
