<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Couple;
use App\Models\PlantingActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * PHASE 1: Organization Interview & Setup
     * Admin inputs organization details and planting schedule
     */

    /**
     * List all organizations
     */
    public function index()
    {
        $organizations = Organization::with(['plantingActivities'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $organizations
        ]);
    }

    /**
     * Show single organization with details
     */
    public function show($id)
    {
        $organization = Organization::with([
            'plantingActivities',
            'plantingActivities.trees',
            'users'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $organization
        ]);
    }

    /**
     * Create new organization
     * Step 1-3 of Phase 1 - Admin only creates organization first
     * Planters and planting activities are added separately later
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'org_name' => 'required|string|max:255',
            'president_first_name' => 'required|string|max:100',
            'president_middle_name' => 'nullable|string|max:100',
            'president_last_name' => 'required|string|max:100',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'organization_code' => 'required|string|max:50|unique:organizations',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create organization only
            $organization = Organization::create([
                'org_name' => $request->org_name,
                'president_first_name' => $request->president_first_name,
                'president_middle_name' => $request->president_middle_name,
                'president_last_name' => $request->president_last_name,
                'contact_number' => $request->contact_number,
                'address' => $request->address,
                'organization_code' => $request->organization_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully',
                'data' => $organization
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update organization details
     */
    public function update(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'org_name' => 'sometimes|required|string|max:255',
            'president_first_name' => 'sometimes|required|string|max:100',
            'president_middle_name' => 'nullable|string|max:100',
            'president_last_name' => 'sometimes|required|string|max:100',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'organization_code' => 'sometimes|required|string|max:50|unique:organizations,organization_code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $organization->update($request->only([
            'org_name',
            'president_first_name',
            'president_middle_name',
            'president_last_name',
            'contact_number',
            'address',
            'organization_code'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully',
            'data' => $organization
        ]);
    }

    /**
     * Delete organization
     */
    public function destroy($id)
    {
        $organization = Organization::findOrFail($id);
        $organization->delete();

        return response()->json([
            'success' => true,
            'message' => 'Organization deleted successfully'
        ]);
    }

    /**
     * Get organization by code
     */
    public function getByCode($code)
    {
        $organization = Organization::where('organization_code', $code)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $organization
        ]);
    }

    /**
     * Get users by organization
     */
    public function getUsers($id)
    {
        $organization = Organization::findOrFail($id);
        
        // Get all users linked to this organization
        $users = User::where('organization_id', $id)
            ->select('id', 'first_name', 'last_name', 'email', 'contact_number', 'role')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}
