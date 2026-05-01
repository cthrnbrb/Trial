<?php

namespace App\Http\Controllers;

use App\Models\Tree;
use App\Models\PlantingActivity;
use App\Models\MonitoringRecord;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * PHASE 4: Admin Dashboard & Reporting
     * Admin views statistics, GIS map, and generates reports
     */

    /**
     * Get dashboard statistics
     * Step 13 of Phase 4
     */
    public function statistics()
    {
        // Get all trees with their latest monitoring status
        $trees = Tree::with(['monitoringRecords'])->get();

        $totalTrees = $trees->count();
        $aliveTrees = 0;
        $deadTrees = 0;
        $pendingTrees = 0;

        foreach ($trees as $tree) {
            $latestStatus = $tree->monitoringRecords()->latest()->first()?->status;

            if ($latestStatus === 'alive') {
                $aliveTrees++;
            } elseif ($latestStatus === 'dead') {
                $deadTrees++;
            } else {
                $pendingTrees++;
            }
        }

        $survivalRate = $totalTrees > 0
            ? round(($aliveTrees / $totalTrees) * 100, 2)
            : 0;

        // Get activity counts
        $totalActivities = PlantingActivity::count();
        $completedActivities = PlantingActivity::whereHas('trees', function ($q) {
            $q->whereHas('monitoringRecords');
        }, '>=', 1)->count();

        // Get organization count
        $totalOrganizations = Organization::count();

        // Recent monitoring count
        $recentMonitoringCount = MonitoringRecord::where('created_at', '>=', now()->subDays(30))->count();

        return response()->json([
            'success' => true,
            'data' => [
                'trees' => [
                    'total' => $totalTrees,
                    'alive' => $aliveTrees,
                    'dead' => $deadTrees,
                    'pending' => $pendingTrees,
                ],
                'survival_rate' => $survivalRate,
                'activities' => [
                    'total' => $totalActivities,
                    'completed' => $completedActivities,
                ],
                'organizations' => $totalOrganizations,
                'recent_monitoring' => $recentMonitoringCount,
            ]
        ]);
    }

    /**
     * Get GIS map data with color-coded markers
     */
    public function gisMap(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'activity_id' => 'nullable|exists:planting_activities,id',
            'status' => 'nullable|in:alive,dead,pending',
            'species' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Tree::with(['activity', 'planter', 'activity.organization']);

        if ($request->has('activity_id')) {
            $query->where('activity_id', $request->activity_id);
        }

        if ($request->has('species')) {
            $query->whereHas('activity', function ($q) use ($request) {
                $q->where('tree_species', 'like', '%' . $request->species . '%');
            });
        }

        $trees = $query->get();

        // Transform for map display with color coding
        $mapData = $trees->map(function ($tree) {
            $latestStatus = $tree->monitoringRecords()->latest()->first()?->status ?? 'pending';

            // Color coding based on status
            $color = match ($latestStatus) {
                'alive' => '#4CAF50', // Green
                'dead' => '#f44336',  // Red
                default => '#FF9800', // Orange for pending
            };

            return [
                'id' => $tree->id,
                'tree_id' => 'TREE-' . strtoupper(substr($tree->activity?->tree_species, 0, 3)) . '-' . $tree->id,
                'latitude' => $tree->latitude,
                'longitude' => $tree->longitude,
                'status' => $latestStatus,
                'color' => $color,
                'species' => $tree->activity?->tree_species,
                'photo' => $tree->photo,
                'planted_at' => $tree->planted_at,
                'planter' => [
                    'id' => $tree->planter?->id,
                    'name' => $tree->planter?->first_name . ' ' . $tree->planter?->last_name,
                ],
                'organization' => [
                    'id' => $tree->activity?->organization?->id,
                    'name' => $tree->activity?->organization?->org_name,
                ],
            ];
        });

        // Apply status filter
        if ($request->has('status')) {
            $mapData = $mapData->filter(function ($tree) use ($request) {
                return $tree['status'] === $request->status;
            })->values();
        }

        return response()->json([
            'success' => true,
            'count' => $mapData->count(),
            'data' => $mapData
        ]);
    }

    /**
     * Get monitoring history
     */
    public function monitoringHistory(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365',
            'activity_id' => 'nullable|exists:planting_activities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $days = $request->input('days', 30);

        $query = MonitoringRecord::with([
            'tree',
            'tree.activity',
            'tree.activity.organization',
            'staff'
        ])
            ->where('created_at', '>=', now()->subDays($days));

        if ($request->has('activity_id')) {
            $query->whereHas('assignment', function ($q) use ($request) {
                $q->where('activity_id', $request->activity_id);
            });
        }

        $history = $query->orderBy('created_at', 'desc')->get();

        // Group by date for analytics
        $groupedByDate = $history->groupBy(function ($record) {
            return $record->created_at->format('Y-m-d');
        });

        $timeline = $groupedByDate->map(function ($records, $date) {
            return [
                'date' => $date,
                'total_checked' => $records->count(),
                'alive' => $records->where('status', 'alive')->count(),
                'dead' => $records->where('status', 'dead')->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'count' => $history->count(),
            'data' => [
                'records' => $history,
                'timeline' => $timeline,
            ]
        ]);
    }

    /**
     * Get survival analytics by organization/activity
     */
    public function survivalAnalytics(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = PlantingActivity::with([
            'organization',
            'trees',
            'trees.monitoringRecords'
        ]);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $activities = $query->get();

        $analytics = $activities->map(function ($activity) {
            $totalTrees = $activity->trees->count();

            if ($totalTrees === 0) {
                return [
                    'activity_id' => $activity->id,
                    'organization_name' => $activity->organization?->org_name,
                    'tree_species' => $activity->tree_species,
                    'site' => $activity->proposed_site,
                    'scheduled_date' => $activity->scheduled_date,
                    'total_trees' => 0,
                    'alive' => 0,
                    'dead' => 0,
                    'pending' => 0,
                    'survival_rate' => 0,
                ];
            }

            $alive = 0;
            $dead = 0;
            $pending = 0;

            foreach ($activity->trees as $tree) {
                $status = $tree->monitoringRecords()->latest()->first()?->status;
                if ($status === 'alive') {
                    $alive++;
                } elseif ($status === 'dead') {
                    $dead++;
                } else {
                    $pending++;
                }
            }

            return [
                'activity_id' => $activity->id,
                'organization_name' => $activity->organization?->org_name,
                'tree_species' => $activity->tree_species,
                'site' => $activity->proposed_site,
                'scheduled_date' => $activity->scheduled_date,
                'total_trees' => $totalTrees,
                'alive' => $alive,
                'dead' => $dead,
                'pending' => $pending,
                'survival_rate' => round(($alive / $totalTrees) * 100, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Generate report (Step 14-15)
     */
    public function generateReport(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'type' => 'required|in:summary,detailed,activity',
            'activity_id' => 'nullable|exists:planting_activities,id',
            'organization_id' => 'nullable|exists:organizations,id',
            'format' => 'required|in:pdf,excel,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reportData = [];

        // Get base statistics
        $trees = Tree::with(['monitoringRecords', 'activity'])->get();
        $totalTrees = $trees->count();
        $aliveTrees = 0;
        $deadTrees = 0;

        foreach ($trees as $tree) {
            $status = $tree->monitoringRecords()->latest()->first()?->status;
            if ($status === 'alive') {
                $aliveTrees++;
            } elseif ($status === 'dead') {
                $deadTrees++;
            }
        }

        $survivalRate = $totalTrees > 0
            ? round(($aliveTrees / $totalTrees) * 100, 2)
            : 0;

        $reportData['summary'] = [
            'generated_at' => now()->toDateTimeString(),
            'report_type' => $request->type,
            'date_range' => [
                'from' => $request->start_date ?? 'all time',
                'to' => $request->end_date ?? now()->toDateString(),
            ],
            'statistics' => [
                'total_trees' => $totalTrees,
                'alive_trees' => $aliveTrees,
                'dead_trees' => $deadTrees,
                'survival_rate' => $survivalRate . '%',
                'total_organizations' => Organization::count(),
                'total_activities' => PlantingActivity::count(),
            ],
        ];

        // Add detailed data based on report type
        if ($request->type === 'activity' && $request->activity_id) {
            $activity = PlantingActivity::with([
                'organization',
                'trees',
                'trees.planter',
                'trees.monitoringRecords'
            ])->findOrFail($request->activity_id);

            $reportData['activity_details'] = $activity;
        } elseif ($request->type === 'detailed') {
            $reportData['organizations'] = Organization::with([
                'plantingActivities',
                'plantingActivities.trees',
                'plantingActivities.trees.monitoringRecords'
            ])->get();
        }

        // For JSON format, return directly
        if ($request->format === 'json') {
            return response()->json([
                'success' => true,
                'report' => $reportData
            ]);
        }

        // For PDF/Excel, return download URL (would need implementation)
        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully',
            'report' => $reportData,
            'download_url' => null, // Would be set by PDF/Excel generator
        ]);
    }

    /**
     * Get tree species summary for filtering
     */
    public function speciesList()
    {
        $species = PlantingActivity::distinct()
            ->pluck('tree_species')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'count' => $species->count(),
            'data' => $species
        ]);
    }

    /**
     * Get calendar events for scheduled planting activities
     */
    public function calendarEvents(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Default to current month if no dates provided
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        $activities = PlantingActivity::with(['organization', 'couple'])
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->orderBy('scheduled_date')
            ->get();

        $events = $activities->map(function ($activity) {
            $participant = $activity->organization ?? $activity->couple;
            $participantName = $activity->organization 
                ? $activity->organization->name 
                : ($activity->couple ? 'Couple #' . $activity->couple->or_number : 'Unknown');
            
            return [
                'id' => $activity->id,
                'title' => $activity->tree_species ?? 'Tree Planting Activity',
                'date' => $activity->scheduled_date->format('Y-m-d'),
                'time' => $activity->scheduled_date->format('h:i A'),
                'participant_name' => $participantName,
                'participant_type' => $activity->organization ? 'organization' : 'couple',
                'site' => $activity->proposed_site,
                'species' => $activity->tree_species,
                'status' => $this->getActivityStatus($activity),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $events->count(),
            'data' => $events
        ]);
    }

    /**
     * Helper method to determine activity status
     */
    private function getActivityStatus($activity)
    {
        $hasTrees = $activity->trees()->count() > 0;
        $hasMonitoring = $activity->trees()
            ->whereHas('monitoringRecords')
            ->count() > 0;

        if (!$hasTrees) {
            return 'scheduled';
        }

        if ($hasMonitoring) {
            return 'in_progress';
        }

        return 'planted';
    }
}
