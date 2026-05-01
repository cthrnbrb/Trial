<?php

namespace App\Http\Controllers;

use App\Models\Tree;
use App\Models\PlantingActivity;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TreeController extends Controller
{
    /**
     * PHASE 2: Tree Planting & Geo-Tag Registration
     * Tree planters register trees with GPS coordinates and photos
     */

    /**
     * List all trees with filters
     */
    public function index(Request $request)
    {
        $query = Tree::with(['activity', 'planter']);

        // Filter by status
        if ($request->has('status')) {
            $query->whereHas('monitoringRecords', function ($q) use ($request) {
                $q->latest()->where('status', $request->status);
            });
        }

        // Filter by activity
        if ($request->has('activity_id')) {
            $query->where('activity_id', $request->activity_id);
        }

        // Filter by planter
        if ($request->has('planter_id')) {
            $query->where('planter_id', $request->planter_id);
        }

        $trees = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $trees->count(),
            'data' => $trees
        ]);
    }

    /**
     * Show single tree with monitoring history
     */
    public function show($id)
    {
        $tree = Tree::with([
            'activity',
            'planter',
            'monitoringRecords',
            'monitoringRecords.staff'
        ])->findOrFail($id);

        // Get latest status
        $latestRecord = $tree->monitoringRecords()->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'tree' => $tree,
                'current_status' => $latestRecord?->status ?? 'pending',
                'last_checked' => $latestRecord?->checked_at,
            ]
        ]);
    }

    /**
     * Create new tree registration
     * Step 4-6 of Phase 2
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activity_id' => 'required|exists:planting_activities,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo' => 'required|string', // Base64 encoded image
            'planted_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the activity and verify planter belongs to it
            $activity = PlantingActivity::findOrFail($request->activity_id);

            // Check if current user is authorized (tree planter for this activity)
            $isAuthorized = auth()->user()->role === 'tree planter' &&
                auth()->user()->treesAsPlanter()
                    ->where('activity_id', $activity->id)
                    ->exists();

            // Also allow if user is the assigned planter through participant_member
            // Or if admin
            $isAdmin = auth()->user()->role === 'admin';

            if (!$isAuthorized && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to plant trees for this activity'
                ], 403);
            }

            // 🔵 OFFLINE MODE
            if ($request->header('X-Offline-Mode') === 'true') {

                DB::connection('sqlite')->table('offline_trees')->insert([
                    'id' => (string) Str::uuid(),
                    'activity_id' => $request->activity_id,
                    'planter_id' => auth()->id(),
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'photo' => $request->photo,
                    'local_created_at' => now(),
                    'synced' => 0
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tree saved offline (SQLite)'
                ]);
            }

            // Handle base64 photo upload
            $photoPath = $this->saveBase64Photo($request->photo);


            // Create tree record
            $tree = Tree::create([
                'activity_id' => $request->activity_id,
                'planter_id' => auth()->id(),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'photo' => $photoPath,
                'planted_at' => $request->planted_at ?? now(),
                'synced_at' => now(),
            ]);

            // Generate unique Tree ID
            $treeId = 'TREE-' . strtoupper(substr($activity->tree_species, 0, 3)) . '-' . $tree->id;

            return response()->json([
                'success' => true,
                'message' => 'Tree registered successfully',
                'data' => [
                    'tree' => $tree,
                    'tree_id' => $treeId,
                    'gps_coordinates' => [
                        'latitude' => $tree->latitude,
                        'longitude' => $tree->longitude,
                    ],
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register tree: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync offline tree records
     * For mobile app offline capability
     */
    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trees' => 'required|array',
            'trees.*.activity_id' => 'required|exists:planting_activities,id',
            'trees.*.latitude' => 'required|numeric|between:-90,90',
            'trees.*.longitude' => 'required|numeric|between:-180,180',
            'trees.*.photo' => 'required|string',
            'trees.*.planted_at' => 'nullable|date',
            'trees.*.local_id' => 'required|string', // Local ID from mobile app
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $syncedTrees = [];
        $errors = [];

        foreach ($request->trees as $treeData) {
            try {
                $photoPath = $this->saveBase64Photo($treeData['photo']);

                $tree = Tree::create([
                    'activity_id' => $treeData['activity_id'],
                    'planter_id' => auth()->id(),
                    'latitude' => $treeData['latitude'],
                    'longitude' => $treeData['longitude'],
                    'photo' => $photoPath,
                    'planted_at' => $treeData['planted_at'] ?? now(),
                    'synced_at' => now(),
                ]);

                // ✅ ATTENDANCE AUTO MARK (SYNC VERSION)
                AttendanceRecord::updateOrCreate(
                    [
                        'activity_id' => $treeData['activity_id'],
                        'user_id' => auth()->id(),
                    ],
                    [
                        'tree_id' => $tree->id,
                        'attendance' => 'present'
                    ]
                );

                $syncedTrees[] = [
                    'local_id' => $treeData['local_id'],
                    'server_id' => $tree->id,
                    'tree_id' => 'TREE-' . strtoupper(substr($treeData['activity_id'], 0, 3)) . '-' . $tree->id,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'local_id' => $treeData['local_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync completed',
            'synced_count' => count($syncedTrees),
            'error_count' => count($errors),
            'synced_trees' => $syncedTrees,
            'errors' => $errors,
        ]);
    }

    /**
     * Get trees for a specific planter (mobile app)
     */
    public function myTrees()
    {
        $user = auth()->user();

        // For couples, get trees from their organization's activities
        if ($user->role === 'couple') {
            if ($user->organization_id) {
                $trees = Tree::with(['activity', 'monitoringRecords'])
                    ->whereHas('activity', function ($query) use ($user) {
                        $query->where('organization_id', $user->organization_id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                // Couple without organization - return empty
                $trees = collect();
            }
        } else {
            // For planters, get their own trees
            $trees = Tree::with(['activity', 'monitoringRecords'])
                ->where('planter_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'count' => $trees->count(),
            'data' => $trees
        ]);
    }

    /**
     * Get trees by activity
     */
    public function byActivity($activityId)
    {
        $trees = Tree::with(['planter', 'monitoringRecords'])
            ->where('activity_id', $activityId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate survival stats
        $total = $trees->count();
        $alive = 0;
        $dead = 0;

        foreach ($trees as $tree) {
            $latestStatus = $tree->monitoringRecords()->latest()->first()?->status;
            if ($latestStatus === 'alive') {
                $alive++;
            } elseif ($latestStatus === 'dead') {
                $dead++;
            }
        }

        $survivalRate = $total > 0 ? round(($alive / $total) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'trees' => $trees,
                'stats' => [
                    'total' => $total,
                    'alive' => $alive,
                    'dead' => $dead,
                    'survival_rate' => $survivalRate,
                ]
            ]
        ]);
    }

    /**
     * Helper: Save base64 photo
     */
    private function saveBase64Photo($base64String)
    {
        // Remove data URI scheme if present
        $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);

        // Decode base64
        $imageData = base64_decode($base64String);

        if (!$imageData) {
            throw new \Exception('Invalid image data');
        }

        // Generate unique filename
        $filename = 'trees/' . uniqid() . '_' . time() . '.jpg';

        // Save to storage
        Storage::disk('public')->put($filename, $imageData);

        return $filename;
    }
}
