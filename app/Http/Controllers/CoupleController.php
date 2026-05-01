<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoupleController extends Controller
{
    public function index()
    {
        $couples = Couple::with('users')->get();
        return response()->json($couples);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'or_number' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $couple = Couple::create([
            'id' => (string) Str::uuid(),
            'or_number' => $validated['or_number'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        return response()->json($couple, 201);
    }

    public function show($id)
    {
        $couple = Couple::with(['users', 'plantingActivities', 'trees'])->findOrFail($id);
        return response()->json($couple);
    }

    public function update(Request $request, $id)
    {
        $couple = Couple::findOrFail($id);

        $validated = $request->validate([
            'or_number' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $couple->update($validated);

        return response()->json($couple);
    }

    public function destroy($id)
    {
        $couple = Couple::findOrFail($id);
        $couple->delete();

        return response()->json(['message' => 'Couple deleted successfully']);
    }

    public function getUsers($id)
    {
        $couple = Couple::findOrFail($id);
        $users = User::where('couple_id', $id)->get();
        return response()->json($users);
    }
}
