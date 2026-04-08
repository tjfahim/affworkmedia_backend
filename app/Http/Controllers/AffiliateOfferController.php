<?php

namespace App\Http\Controllers;
use App\Models\GameManage;
use App\Models\EventManage;
use App\Models\TeamManage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AffiliateOfferController extends Controller
{
   
    public function getGames()
    {
        try {
            $games = GameManage::withCount(['events' => function($query) {
                $query->where('status', 'running');
            }])->active()->ordered()->get();
            
            return response()->json([
                'success' => true,
                'games' => $games,
                'message' => 'Games retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve games: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getGameEvents(Request $request, $gameId)
    {
        try {
            $game = GameManage::where('id', $gameId)->active()->first();
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found or inactive'
                ], 404);
            }
            
            $events = EventManage::with(['firstTeam', 'secondTeam', 'game'])
                ->where('game_manage_id', $gameId)
                ->orderBy('start_datetime', 'asc')
                ->get();
            
            $groupedEvents = [
                'running' => $events->where('status', 'running')->values(),
                'upcoming' => $events->where('status', 'upcoming')->values(),
                'finished' => $events->where('status', 'finished')->values()
            ];
            
            return response()->json([
                'success' => true,
                'game' => $game,
                'events' => $events,
                'grouped_events' => $groupedEvents,
                'total_events' => $events->count(),
                'message' => 'Events retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getGameEventsWithTracking(Request $request, $gameId)
    {
        try {
            $game = GameManage::where('id', $gameId)->active()->first();
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found or inactive'
                ], 404);
            }
            
            $events = EventManage::with(['firstTeam', 'secondTeam', 'game'])
                ->where('game_manage_id', $gameId)
                ->whereIn('status', ['running', 'upcoming'])
                ->orderBy('start_datetime', 'asc')
                ->get();
            
            $settings = Setting::getSettings();
            $landerpageDomain = $settings->landerpage_domain ?? 'http://127.0.0.1:8000/';
            $affiliateId = Auth::id();
            
            return response()->json([
                'success' => true,
                'game' => $game,
                'events' => $events,
                'landerpage_domain' => $landerpageDomain,
                'affiliate_id' => $affiliateId,
                'message' => 'Events retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventDetails(Request $request, $eventId)
    {
        try {
            $event = EventManage::with(['firstTeam', 'secondTeam', 'game'])
                ->whereHas('game', function($q) {
                    $q->where('status', true);
                })
                ->find($eventId);
            
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }
            
            $settings = Setting::getSettings();
            $landerpageDomain = $settings->landerpage_domain ?? 'http://127.0.0.1:8000/';
            $affiliateId = Auth::id();
            
            $baseTrackingLink = rtrim($landerpageDomain, '/');
            $baseTrackingLink .= "/click?pid={$affiliateId}&offer_id={$event->game_manage_id}&event_id={$eventId}";
            
            return response()->json([
                'success' => true,
                'event' => $event,
                'landerpage_domain' => $landerpageDomain,
                'affiliate_id' => $affiliateId,
                'base_tracking_link' => $baseTrackingLink,
                'message' => 'Event details retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve event details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateTrackingLink(Request $request)
    {
        try {
            $eventId = $request->event_id;
            $sub1 = $request->sub1;
            $sub2 = $request->sub2;
            $sub3 = $request->sub3;
            $sub4 = $request->sub4;
            $sub5 = $request->sub5;
            $sub6 = $request->sub6;
            
            $event = EventManage::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }
            
            $settings = Setting::getSettings();
            $landerpageDomain = $settings->landerpage_domain ?? 'http://127.0.0.1:8000/';
            $affiliateId = Auth::id();
            
            $trackingLink = rtrim($landerpageDomain, '/');
            $trackingLink .= "/click?pid={$affiliateId}&offer_id={$event->game_manage_id}&event_id={$eventId}";
            
            // Add sub parameters if they exist
            if (!empty($sub1)) $trackingLink .= "&sub1=" . urlencode($sub1);
            if (!empty($sub2)) $trackingLink .= "&sub2=" . urlencode($sub2);
            if (!empty($sub3)) $trackingLink .= "&sub3=" . urlencode($sub3);
            if (!empty($sub4)) $trackingLink .= "&sub4=" . urlencode($sub4);
            if (!empty($sub5)) $trackingLink .= "&sub5=" . urlencode($sub5);
            if (!empty($sub6)) $trackingLink .= "&sub6=" . urlencode($sub6);
            
            return response()->json([
                'success' => true,
                'tracking_link' => $trackingLink,
                'message' => 'Tracking link generated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tracking link: ' . $e->getMessage()
            ], 500);
        }
    }
}
