<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send reset link'
        ], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid token or email',
        ], 400);
    }

    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $code = trim($request->code);

        // Check if organization exists
        $organization = \App\Models\Organization::where('organization_code', $code)->first();

        if ($organization) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'message' => 'Organization code is valid',
                'organization_code' => $organization->organization_code,
                'organization_name' => $organization->org_name,
            ]);
        }

        // Organization does not exist
        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid organization code'
        ], 404);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'middle_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|string|email|max:50|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'contact_number' => 'required|string|max:11',
            'address' => 'required|string',
            'code' => 'required|string|max:6|exists:organizations,organization_code'
        ]);

        // Find the existing organization by code
        $organization = \App\Models\Organization::where('organization_code', trim($request->code))->first();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid organization code'
            ], 400);
        }

        // Create the user
        $user = \App\Models\User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'contact_number' => $request->contact_number,
            'address' => $request->address,
        ]);

        // Create user-organization relationship
        \App\Models\UserOrganization::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => 'organization',
            'joined_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $user,
                'organization' => $organization
            ]
        ], 201);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
