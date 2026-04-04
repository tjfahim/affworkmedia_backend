<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventManageSeeder extends Seeder
{
    public function run()
    {
        // Get all game IDs (1-5)
        $gameIds = [1, 2, 3, 4, 5];
        
        // Get all team IDs (1-11)
        $teamIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        
        $events = [];
        $eventId = 1;
        
        foreach ($gameIds as $gameId) {
            // Create 10 events for each game
            for ($i = 1; $i <= 10; $i++) {
                // Randomly select two different teams
                $teamPairs = $this->getRandomTeamPair($teamIds);
                
                // Determine status based on position (5 running, 3 upcoming, 2 finished)
                if ($i <= 5) {
                    $status = 'running';
                    $startDatetime = $this->getRunningEventDate();
                } elseif ($i <= 8) {
                    $status = 'upcoming';
                    $startDatetime = $this->getUpcomingEventDate();
                } else {
                    $status = 'finished';
                    $startDatetime = $this->getFinishedEventDate();
                }
                
                // Calculate end datetime (add 1-3 hours to start datetime)
                $endDatetime = $this->calculateEndDate($startDatetime);
                
                $events[] = [
                    'id' => $eventId++,
                    'game_manage_id' => $gameId,
                    'first_team_id' => $teamPairs['first_team'],
                    'second_team_id' => $teamPairs['second_team'],
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                    'status' => $status,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }
        
        // Insert all events
        DB::table('event_manages')->insert($events);
        
        // Display summary
        $this->command->info("\n✅ Event Seeder Completed!");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("📊 Total Events Created: " . count($events));
        $this->command->info("");
        $this->command->info("📈 Status Breakdown:");
        $this->command->info("  🟢 Running:  " . DB::table('event_manages')->where('status', 'running')->count() . " events");
        $this->command->info("  🔵 Upcoming: " . DB::table('event_manages')->where('status', 'upcoming')->count() . " events");
        $this->command->info("  ⚫ Finished: " . DB::table('event_manages')->where('status', 'finished')->count() . " events");
        $this->command->info("");
        $this->command->info("🎮 Events per Game:");
        foreach ($gameIds as $gameId) {
            $count = DB::table('event_manages')->where('game_manage_id', $gameId)->count();
            $this->command->info("  Game ID {$gameId}: {$count} events");
        }
        
        // Show sample dates
        $this->command->info("");
        $this->command->info("📅 Sample Event Dates:");
        $sampleEvents = DB::table('event_manages')->limit(5)->get();
        foreach ($sampleEvents as $event) {
            $this->command->info("  {$event->status}: {$event->start_datetime} → {$event->end_datetime}");
        }
    }
    
    /**
     * Get random pair of different teams
     */
    private function getRandomTeamPair($teamIds)
    {
        shuffle($teamIds);
        return [
            'first_team' => $teamIds[0],
            'second_team' => $teamIds[1]
        ];
    }
    
    /**
     * Get date for running events (5-10 days ago from today)
     * These events already started and are still running
     */
    private function getRunningEventDate()
    {
        $now = Carbon::now();
        // Random days between 5 and 10 days ago
        $daysAgo = rand(5, 10);
        // Random time between 00:00 and 23:00
        $hour = rand(0, 23);
        $minute = rand(0, 59);
        
        return $now->subDays($daysAgo)->setTime($hour, $minute, 0);
    }
    
    /**
     * Get date for upcoming events (5-10 days from today)
     * These events will start in the future
     */
    private function getUpcomingEventDate()
    {
        $now = Carbon::now();
        // Random days between 5 and 10 days in future
        $daysLater = rand(5, 10);
        // Random time between 00:00 and 23:00
        $hour = rand(0, 23);
        $minute = rand(0, 59);
        
        return $now->addDays($daysLater)->setTime($hour, $minute, 0);
    }
    
    /**
     * Get date for finished events (10-20 days ago from today)
     * These events are completed
     */
    private function getFinishedEventDate()
    {
        $now = Carbon::now();
        // Random days between 10 and 20 days ago
        $daysAgo = rand(10, 20);
        // Random time between 00:00 and 23:00
        $hour = rand(0, 23);
        $minute = rand(0, 59);
        
        return $now->subDays($daysAgo)->setTime($hour, $minute, 0);
    }
    
    /**
     * Calculate end date by adding 1-3 hours to start date
     */
    private function calculateEndDate($startDatetime)
    {
        $start = Carbon::parse($startDatetime);
        // Add 1-3 hours (60-180 minutes)
        $minutesToAdd = rand(60, 180);
        
        return $start->copy()->addMinutes($minutesToAdd);
    }
}