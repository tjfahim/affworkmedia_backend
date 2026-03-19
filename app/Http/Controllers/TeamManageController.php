<?php
// app/Http/Controllers/TeamManageController.php

namespace App\Http\Controllers;

use App\Models\TeamManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeamManageController extends Controller
{
    /**
     * Display a listing of the team members.
     */
    public function index()
    {
        $teams = TeamManage::latest()->get();
        
        return response()->json([
            'success' => true,
            'teams' => $teams
        ]);
    }

    /**
     * Store a newly created team member.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'status' => $request->status ?? true
        ];

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($request->name) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('teams', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $team = TeamManage::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Team member created successfully',
            'team' => $team
        ], 201);
    }

    /**
     * Display the specified team member.
     */
    public function show($id)
    {
        $team = TeamManage::find($id);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'team' => $team
        ]);
    }

    /**
     * Update the specified team member.
     */
    public function update(Request $request, $id)
    {
        $team = TeamManage::find($id);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
            $data['slug'] = Str::slug($request->name);
        }

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($team->image) {
                Storage::disk('public')->delete($team->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($request->name ?? $team->name) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('teams', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $team->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Team member updated successfully',
            'team' => $team
        ]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy($id)
    {
        $team = TeamManage::find($id);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        // Delete image
        if ($team->image) {
            Storage::disk('public')->delete($team->image);
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team member deleted successfully'
        ]);
    }

    /**
     * Toggle team member status.
     */
    public function toggleStatus($id)
    {
        $team = TeamManage::find($id);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $team->status = !$team->status;
        $team->save();

        return response()->json([
            'success' => true,
            'message' => 'Team member status updated successfully',
            'status' => $team->status
        ]);
    }
}