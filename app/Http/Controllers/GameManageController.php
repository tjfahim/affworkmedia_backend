<?php
// app/Http/Controllers/GameManageController.php

namespace App\Http\Controllers;

use App\Models\GameManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class GameManageController extends Controller
{
    /**
     * Display a listing of the games.
     */
    public function index()
    {
        $games = GameManage::ordered()->latest()->get();
        
        return response()->json([
            'success' => true,
            'games' => $games
        ]);
    }

    /**
     * Store a newly created game.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'order_number' => 'sometimes|integer|min:0',
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
            'name' => $request->name,
            'order_number' => $request->order_number ?? 0,
            'status' => $request->status ?? true
        ];

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . str_replace(' ', '_', $request->name) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('games', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $game = GameManage::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Game created successfully',
            'game' => $game
        ], 201);
    }

    /**
     * Display the specified game.
     */
    public function show(GameManage $game)
    {
        return response()->json([
            'success' => true,
            'game' => $game
        ]);
    }

    /**
     * Update the specified game.
     */
    public function update(Request $request, GameManage $game)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'order_number' => 'sometimes|integer|min:0',
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
        }

        if ($request->has('order_number')) {
            $data['order_number'] = $request->order_number;
        }

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($game->image) {
                Storage::disk('public')->delete($game->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . str_replace(' ', '_', $request->name ?? $game->name) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('games', $imageName, 'public');
            $data['image'] = $imagePath;
        }

        $game->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Game updated successfully',
            'game' => $game
        ]);
    }

    /**
     * Remove the specified game.
     */
    public function destroy(GameManage $game)
    {
        // Delete image
        if ($game->image) {
            Storage::disk('public')->delete($game->image);
        }

        $game->delete();

        return response()->json([
            'success' => true,
            'message' => 'Game deleted successfully'
        ]);
    }

    /**
     * Toggle game status.
     */
    public function toggleStatus(GameManage $game)
    {
        $game->status = !$game->status;
        $game->save();

        return response()->json([
            'success' => true,
            'message' => 'Game status updated successfully',
            'status' => $game->status
        ]);
    }

    /**
     * Update order numbers in bulk.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'games' => 'required|array',
            'games.*.id' => 'required|exists:game_manages,id',
            'games.*.order_number' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->games as $item) {
            GameManage::where('id', $item['id'])->update(['order_number' => $item['order_number']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Game order updated successfully'
        ]);
    }

    /**
     * Get active games (public endpoint maybe).
     */
    public function getActiveGames()
    {
        $games = GameManage::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'games' => $games
        ]);
    }
}