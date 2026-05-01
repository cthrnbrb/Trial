<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Couple;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class TreePlanterController extends Controller
{
    /**
     * List all tree planters
     */
    public function index()
    {
        $treePlanters = User::where('role', 'tree planter')
            ->with(['organization', 'couple'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                $data = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];

                // Determine type based on organization_id or couple_id
                if ($user->organization_id) {
                    $data['type'] = 'organization';
                    $data['organization_id'] = $user->organization_id;
                    $data['organization'] = $user->organization;
                    $data['address'] = $user->organization?->address;
                } elseif ($user->couple_id) {
                    $data['type'] = 'couple';
                    $data['couple_id'] = $user->couple_id;
                    $data['couple'] = $user->couple;
                    $data['or_number'] = $user->couple?->or_number;
                    $data['address'] = $user->couple?->address;
                    
                    // Find and include person 2 data (other user with same couple_id)
                    $user2 = User::where('couple_id', $user->couple_id)
                        ->where('id', '!=', $user->id)
                        ->first();
                    
                    if ($user2) {
                        $data['person2'] = [
                            'id' => $user2->id,
                            'first_name' => $user2->first_name,
                            'last_name' => $user2->last_name,
                            'email' => $user2->email,
                        ];
                    }
                }

                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $treePlanters
        ]);
    }

    /**
     * Show single tree planter
     */
    public function show($id)
    {
        $user = User::where('role', 'tree planter')
            ->with(['organization', 'couple'])
            ->findOrFail($id);

        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($user->organization_id) {
            $data['type'] = 'organization';
            $data['organization_id'] = $user->organization_id;
            $data['organization'] = $user->organization;
        } elseif ($user->couple_id) {
            $data['type'] = 'couple';
            $data['couple_id'] = $user->couple_id;
            $data['couple'] = $user->couple;
            $data['or_number'] = $user->couple?->or_number;
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Create new tree planter
     */
    public function store(Request $request)
    {
        $isCouple = $request->type === 'couple';
        
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'contact_number' => 'required|string|max:50',
            'address' => 'nullable|string',
            'type' => 'required|in:couple,organization',
            'organization_id' => 'required_if:type,organization|exists:organizations,id',
        ];
        
        // Add couple-specific rules
        if ($isCouple) {
            $rules['or_number'] = 'required|string|max:255';
            $rules['middle_name'] = 'nullable|string|max:100';
            $rules['person2_first_name'] = 'required|string|max:100';
            $rules['person2_middle_name'] = 'nullable|string|max:100';
            $rules['person2_last_name'] = 'required|string|max:100';
            $rules['person2_email'] = 'required|email|unique:users,email';
            $rules['person2_password'] = 'required|string|min:6';
        }
        
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if OR number is already in use by another couple
        if ($isCouple) {
            $existingCouple = Couple::where('or_number', $request->or_number)->first();
            if ($existingCouple) {
                return response()->json([
                    'success' => false,
                    'message' => 'This OR number is already registered to another couple',
                    'errors' => ['or_number' => 'This OR number is already registered to another couple']
                ], 422);
            }
            
            // Validate that OR number is provided
            if (empty($request->or_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OR Number is required for couple registration',
                    'errors' => ['or_number' => 'OR Number is required for couple registration']
                ], 422);
            }
        }

        try {
            $couple = null;
            $user2 = null;

            // Handle based on type
            if ($request->type === 'couple') {
                // Create couple record first (address stored here for shared address)
                $couple = Couple::create([
                    'or_number' => $request->or_number,
                    'contact_number' => $request->contact_number,
                    'address' => $request->address,
                ]);
                
                // Create first tree planter user
                $user = User::create([
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'contact_number' => $request->contact_number,
                    'address' => $request->address,
                ]);

                // Create user-organization relationship for first user
                UserOrganization::create([
                    'user_id' => $user->id,
                    'organization_id' => $request->organization_id,
                    'role' => 'couple',
                    'joined_at' => now(),
                ]);

                // Create second tree planter user for couple
                $user2 = User::create([
                    'first_name' => $request->person2_first_name,
                    'middle_name' => $request->person2_middle_name,
                    'last_name' => $request->person2_last_name,
                    'email' => $request->person2_email,
                    'password' => Hash::make($request->person2_password),
                    'contact_number' => $request->contact_number,
                    'address' => $request->address,
                ]);

                // Create user-organization relationship for second user
                UserOrganization::create([
                    'user_id' => $user2->id,
                    'organization_id' => $request->organization_id,
                    'role' => 'couple',
                    'joined_at' => now(),
                ]);
            } else {
                // Organization type - create single user
                $user = User::create([
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'contact_number' => $request->contact_number,
                    'address' => $request->address,
                ]);

                // Create user-organization relationship
                UserOrganization::create([
                    'user_id' => $user->id,
                    'organization_id' => $request->organization_id,
                    'role' => 'organization',
                    'joined_at' => now(),
                ]);
            }

            $responseData = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'type' => $request->type,
                'couple' => $couple,
            ];
            
            // Set address based on type
            if ($request->type === 'couple') {
                $responseData['address'] = $couple?->address; // From couple table (shared)
            } else {
                // For organization type, get address from organization
                $organization = Organization::find($request->organization_id);
                $responseData['address'] = $organization?->address; // From organization table
            }
            
            // Include organization data for organization type
            if ($request->type === 'organization') {
                $responseData['organization_id'] = $request->organization_id;
                $organization = Organization::find($request->organization_id);
                $responseData['organization'] = $organization;
            }
            
            // Include second person data for couples
            if ($user2) {
                $responseData['person2'] = [
                    'id' => $user2->id,
                    'first_name' => $user2->first_name,
                    'last_name' => $user2->last_name,
                    'email' => $user2->email,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => $user2 ? 'Couple tree planters created successfully' : 'Tree planter created successfully',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tree planter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tree planter
     */
    public function update(Request $request, $id)
    {
        $user = User::where('role', 'tree planter')->findOrFail($id);
        
        // Check if this is a couple type
        $isCouple = $user->couple_id;

        $rules = [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'contact_number' => 'sometimes|required|string|max:50',
            'organization_id' => 'sometimes|required|exists:organizations,id',
            'couple_id' => 'sometimes|required|exists:couples,id',
        ];
        
        // Add couple-specific rules
        if ($isCouple) {
            $rules['or_number'] = 'sometimes|required|string|max:255';
            $rules['middle_name'] = 'nullable|string|max:100';
            $rules['person2_middle_name'] = 'nullable|string|max:100';
            $rules['person2_first_name'] = 'sometimes|required|string|max:100';
            $rules['person2_last_name'] = 'sometimes|required|string|max:100';
            $rules['person2_email'] = 'sometimes|required|email';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if OR number is already in use by another couple (excluding current couple)
        if ($isCouple && $request->has('or_number')) {
            $existingCouple = Couple::where('or_number', $request->or_number)
                ->where('id', '!=', $user->couple_id)
                ->first();
            if ($existingCouple) {
                return response()->json([
                    'success' => false,
                    'errors' => ['or_number' => 'This OR number is already registered to another couple']
                ], 422);
            }
        }

        try {
            // Update user details
            $updateData = [];
            if ($request->has('first_name')) $updateData['first_name'] = $request->first_name;
            if ($request->has('last_name')) $updateData['last_name'] = $request->last_name;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('contact_number')) $updateData['contact_number'] = $request->contact_number;
            if ($request->has('organization_id')) $updateData['organization_id'] = $request->organization_id;
            if ($request->has('couple_id')) $updateData['couple_id'] = $request->couple_id;

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Update couple OR number
            if ($isCouple && $request->has('or_number')) {
                Couple::where('id', $user->couple_id)->update([
                    'or_number' => $request->or_number,
                ]);
            }
            
            // Update person 2 details if this is a couple
            if ($isCouple && ($request->has('person2_first_name') || $request->has('person2_last_name') || $request->has('person2_email'))) {
                // Find the other person in the couple
                $user2 = User::where('couple_id', $user->couple_id)
                    ->where('id', '!=', $user->id)
                    ->first();
                
                if ($user2) {
                    $user2UpdateData = [];
                    if ($request->has('person2_first_name')) $user2UpdateData['first_name'] = $request->person2_first_name;
                    if ($request->has('person2_last_name')) $user2UpdateData['last_name'] = $request->person2_last_name;
                    if ($request->has('person2_email')) $user2UpdateData['email'] = $request->person2_email;
                    
                    if (!empty($user2UpdateData)) {
                        $user2->update($user2UpdateData);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Tree planter updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tree planter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete tree planter
     */
    public function destroy($id)
    {
        $user = User::where('role', 'tree planter')->findOrFail($id);

        try {
            // If this is a couple user, check if we need to delete the couple
            if ($user->couple_id) {
                // Check if there are other users with this couple_id
                $otherUsersCount = User::where('couple_id', $user->couple_id)
                    ->where('id', '!=', $user->id)
                    ->count();
                
                // If no other users, delete the couple
                if ($otherUsersCount === 0) {
                    Couple::where('id', $user->couple_id)->delete();
                }
            }

            // Delete user
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tree planter deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tree planter: ' . $e->getMessage()
            ], 500);
        }
    }
}
