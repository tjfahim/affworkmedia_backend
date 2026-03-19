<?php
// app/Http/Controllers/EventManageController.php

namespace App\Http\Controllers;

use App\Models\EventManage;
use App\Models\GameManage;
use App\Models\TeamManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EventManageController extends Controller
{
    /**
     * Display a listing of the events.
     */
    public function index()
    {
        $events = EventManage::with(['game', 'firstTeam', 'secondTeam'])
            ->orderBy('start_datetime')
            ->get();
        
        return response()->json([
            'success' => true,
            'events' => $events
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_manage_id' => 'required|exists:game_manages,id',
            'first_team_id' => 'required|exists:team_manages,id',
            'second_team_id' => 'required|exists:team_manages,id|different:first_team_id',
            'start_datetime' => 'required|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'status' => 'sometimes|in:upcoming,running,finished'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        $event = EventManage::create($data);
        
        // Load relationships
        $event->load(['game', 'firstTeam', 'secondTeam']);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'event' => $event
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show($id)
    {
        $event = EventManage::with(['game', 'firstTeam', 'secondTeam'])->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'event' => $event
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, $id)
    {
        $event = EventManage::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'game_manage_id' => 'sometimes|exists:game_manages,id',
            'first_team_id' => 'sometimes|exists:team_manages,id',
            'second_team_id' => 'sometimes|exists:team_manages,id|different:first_team_id',
            'start_datetime' => 'sometimes|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'status' => 'sometimes|in:upcoming,running,finished'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $event->update($request->all());
        $event->load(['game', 'firstTeam', 'secondTeam']);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'event' => $event
        ]);
    }

    /**
     * Remove the specified event.
     */
    public function destroy($id)
    {
        $event = EventManage::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}