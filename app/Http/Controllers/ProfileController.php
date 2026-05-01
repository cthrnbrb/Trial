<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use App\Models\Tree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get user profile with organization and statistics
     */
    public function show()
    {
        $user = auth()->user();
        
        // Load organization if user has one
        $organization = null;
        if ($user->organization_id) {
            $organization = Organization::find($user->organization_id);
        }
        
        // Calculate statistics
        $totalTrees = Tree::where('planter_id', $user->id)->count();
        
        // Calculate survival rate
        $aliveTrees = 0;
        $deadTrees = 0;
        $trees = Tree::where('planter_id', $user->id)
            ->with('monitoringRecords')
            ->get();
            
        foreach ($trees as $tree) {
            $latestRecord = $tree->monitoringRecords()->latest()->first();
            if ($latestRecord) {
                if ($latestRecord->status === 'alive') {
                    $aliveTrees++;
                } elseif ($latestRecord->status === 'dead') {
                    $deadTrees++;
                }
            }
        }
        
        $survivalRate = $totalTrees > 0 ? round(($aliveTrees / $totalTrees) * 100, 2) : 0;
        
        // Get last planted date
        $lastPlanted = Tree::where('planter_id', $user->id)
            ->orderBy('planted_at', 'desc')
            ->first();
            
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'organization' => $organization,
                'statistics' => [
                    'total_trees' => $totalTrees,
                    'alive_trees' => $aliveTrees,
                    'dead_trees' => $deadTrees,
                    'survival_rate' => $survivalRate,
                    'last_planted' => $lastPlanted ? $lastPlanted->planted_at : null,
                ]
            ]
        ]);
    }
    
    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'required|string|max:50',
            'contact_number' => 'required|string|max:11',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'contact_number' => $request->contact_number,
                'address' => $request->address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user photo only
     */
    public function updatePhoto(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $photoName = time() . '_' . $user->id . '.' . $photo->getClientOriginalExtension();
                $photo->move(public_path('uploads/photos'), $photoName);
                $user->update(['photo' => 'uploads/photos/' . $photoName]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update photo: ' . $e->getMessage()
            ], 500);
        }
    }
}
