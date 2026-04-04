<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TeamManageSeeder extends Seeder
{
    public function run()
    {
        // Data from your team_manages table (first 11 records as shown)
        $teams = [
            [
                'id' => 1,
                'name' => 'Millwall',
                'slug' => 'millwall',
                'image' => 'teams/1775199712_millwall.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:01:53',
                'updated_at' => '2026-04-03 07:01:53',
            ],
            [
                'id' => 2,
                'name' => 'Middlesbrough',
                'slug' => 'middlesbrough',
                'image' => 'teams/1775199722_middlesbrough.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:02:02',
                'updated_at' => '2026-04-03 07:02:02',
            ],
            [
                'id' => 3,
                'name' => 'Preston North End',
                'slug' => 'preston-north-end',
                'image' => 'teams/1775199770_preston-north-end.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:02:50',
                'updated_at' => '2026-04-03 07:02:50',
            ],
            [
                'id' => 4,
                'name' => 'Leicester City',
                'slug' => 'leicester-city',
                'image' => 'teams/1775199782_leicester-city.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:03:02',
                'updated_at' => '2026-04-03 07:03:02',
            ],
            [
                'id' => 5,
                'name' => 'Hull City',
                'slug' => 'hull-city',
                'image' => 'teams/1775199837_hull-city.jpg',
                'status' => 1,
                'created_at' => '2026-04-03 07:03:57',
                'updated_at' => '2026-04-03 07:03:57',
            ],
            [
                'id' => 6,
                'name' => 'Oxford United',
                'slug' => 'oxford-united',
                'image' => 'teams/1775199848_oxford-united.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:04:08',
                'updated_at' => '2026-04-03 07:04:08',
            ],
            [
                'id' => 7,
                'name' => 'Swansea City',
                'slug' => 'swansea-city',
                'image' => 'teams/1775199909_swansea-city.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:05:09',
                'updated_at' => '2026-04-03 07:05:09',
            ],
            [
                'id' => 8,
                'name' => 'Sheffield United',
                'slug' => 'sheffield-united',
                'image' => 'teams/1775199921_sheffield-united.jpg',
                'status' => 1,
                'created_at' => '2026-04-03 07:05:21',
                'updated_at' => '2026-04-03 07:05:21',
            ],
            [
                'id' => 9,
                'name' => 'Wrexham A.F.C.',
                'slug' => 'wrexham-afc',
                'image' => 'teams/1775199972_wrexham-afc.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:06:12',
                'updated_at' => '2026-04-03 07:06:12',
            ],
            [
                'id' => 10,
                'name' => 'West Bromwich Albion',
                'slug' => 'west-bromwich-albion',
                'image' => 'teams/1775199988_west-bromwich-albion.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:06:28',
                'updated_at' => '2026-04-03 07:06:28',
            ],
            [
                'id' => 11,
                'name' => 'Gil Vicente FC',
                'slug' => 'gil-vicente-fc',
                'image' => 'teams/1775200018_gil-vicente-fc.png',
                'status' => 1,
                'created_at' => '2026-04-03 07:06:58',
                'updated_at' => '2026-04-03 07:06:58',
            ],
        ];

        // Insert records
        DB::table('team_manages')->insert($teams);

        // Create teams directory in storage if it doesn't exist
        $storageTeamsPath = storage_path('app/public/teams');
        if (!File::exists($storageTeamsPath)) {
            File::makeDirectory($storageTeamsPath, 0755, true);
        }

        // Copy images from public/backup_image/ to storage/app/public/teams/
        foreach ($teams as $team) {
            $imagePath = $team['image']; // e.g., "teams/1775199712_millwall.png"
            $backupFilename = basename($imagePath);
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