<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GameManageSeeder extends Seeder
{
    public function run()
    {
        // Data from your game_manages table
        $games = [
            [
                'id' => 1,
                'name' => 'Soccer',
                'order_number' => 1,
                'image' => 'games/1775199513_Soccer.jpg',
                'status' => 1,
                'created_at' => '2026-04-03 06:58:34',
                'updated_at' => '2026-04-03 06:58:34',
            ],
            [
                'id' => 2,
                'name' => 'NBA',
                'order_number' => 2,
                'image' => 'games/1775199578_NBA.png',
                'status' => 1,
                'created_at' => '2026-04-03 06:58:51',
                'updated_at' => '2026-04-03 06:59:38',
            ],
            [
                'id' => 3,
                'name' => 'MLB',
                'order_number' => 3,
                'image' => 'games/1775199564_MLB.png',
                'status' => 1,
                'created_at' => '2026-04-03 06:59:24',
                'updated_at' => '2026-04-03 06:59:24',
            ],
            [
                'id' => 4,
                'name' => 'Tennis',
                'order_number' => 4,
                'image' => 'games/1775199607_Tennis.jpg',
                'status' => 1,
                'created_at' => '2026-04-03 07:00:07',
                'updated_at' => '2026-04-03 07:00:07',
            ],
            [
                'id' => 5,
                'name' => 'NFL',
                'order_number' => 5,
                'image' => 'games/1775199621_NFL.jpg',
                'status' => 1,
                'created_at' => '2026-04-03 07:00:21',
                'updated_at' => '2026-04-03 07:00:21',
            ],
        ];

        // Insert records (preserve IDs)
        DB::table('game_manages')->insert($games);

        // Create games directory in storage if it doesn't exist
        $storageGamesPath = storage_path('app/public/games');
        if (!File::exists($storageGamesPath)) {
            File::makeDirectory($storageGamesPath, 0755, true);
        }

        // Copy images from public/backup_image/ to storage/app/public/games/
        foreach ($games as $game) {
            $imagePath = $game['image']; // e.g., "games/1775199513_Soccer.jpg"
            $backupFilename = basename($imagePath); // e.g., "1775199513_Soccer.jpg"
            $source = public_path('backup_image/' . $backupFilename);
            $destination = storage_path('app/public/' . $imagePath);

            if (File::exists($source)) {
                File::copy($source, $destination);
                $this->command->info("Copied: {$source} -> {$destination}");
            } else {
                $this->command->warn("Source file not found: {$source}");
            }
        }
    }
}