<?php
// app/Http/Controllers/LandingManageController.php

namespace App\Http\Controllers;

use App\Models\LandingManage;
use App\Models\GameManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class LandingManageController extends Controller
{
    /**
     * Display a listing of the landing items.
     */
    public function index()
    {
        $landings = LandingManage::with('game')->latest()->get();
        
        return response()->json([
            'success' => true,
            'landings' => $landings
        ]);
    }

    /**
     * Store a newly created landing item.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_manage_id' => 'required|exists:game_manages,id',
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'game_manage_id' => $request->game_manage_id,
            'name' => $request->name,
            'status' => $request->status ?? true
        ];

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . ($request->name ? str_replace(' ', '_', $request->name) : 'landing') . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('landings', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $landing = LandingManage::create($data);
        
        // Load relationship
        $landing->load('game');

        return response()->json([
            'success' => true,
            'message' => 'Landing item created successfully',
            'landing' => $landing
        ], 201);
    }

    /**
     * Display the specified landing item.
     */
    public function show(LandingManage $landing)
    {
        $landing->load('game');
        
        return response()->json([
            'success' => true,
            'landing' => $landing
        ]);
    }

    /**
     * Update the specified landing item.
     */
    public function update(Request $request, LandingManage $landing)
    {
        $validator = Validator::make($request->all(), [
            'game_manage_id' => 'sometimes|exists:game_manages,id',
            'name' => 'nullable|string|max:255',
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

        if ($request->has('game_manage_id')) {
            $data['game_manage_id'] = $request->game_manage_id;
        }

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($landing->image) {
                Storage::disk('public')->delete($landing->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . ($request->name ? str_replace(' ', '_', $request->name) : 'landing') . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('landings', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $landing->update($data);
        $landing->load('game');

        return response()->json([
            'success' => true,
            'message' => 'Landing item updated successfully',
            'landing' => $landing
        ]);
    }

    /**
     * Remove the specified landing item.
     */
    public function destroy(LandingManage $landing)
    {
        // Delete image
        if ($landing->image) {
            Storage::disk('public')->delete($landing->image);
        }

        $landing->delete();

        return response()->json([
            'success' => true,
            'message' => 'Landing item deleted successfully'
        ]);
    }

    /**
     * Toggle landing item status.
     */
    public function toggleStatus(LandingManage $landing)
    {
        $landing->status = !$landing->status;
        $landing->save();

        return response()->json([
            'success' => true,
            'message' => 'Landing item status updated successfully',
            'status' => $landing->status
        ]);
    }

    /**
     * Get active landing items (public endpoint).
     */
    public function getActiveLandings()
    {
        $landings = LandingManage::with('game')
            ->active()
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'landings' => $landings
        ]);
    }
}