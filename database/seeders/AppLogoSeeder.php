<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class AppLogoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Source logo path inside repo (commit this file with the project)
        $candidates = [
            base_path('database/seeders/assets/admin-logo.png'),
            base_path('database/seeders/assets/admin-logo.jpg'),
            base_path('database/seeders/assets/admin-logo.jpeg'),
            base_path('database/seeders/assets/admin-logo.webp'),
            base_path('database/seeders/assets/admin-logo.svg'),
        ];

        $sourcePath = collect($candidates)->first(fn ($p) => file_exists($p));

        if (! $sourcePath) {
            $this->command?->warn('[AppLogoSeeder] No logo file found in database/seeders/assets (expected admin-logo.{png|jpg|jpeg|webp|svg}). Skipping.');
            return;
        }

        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $targetFilename = 'admin-logo.' . $ext;

        // Store under public disk so Storage::url() resolves to /storage/...
        $storedPath = Storage::disk('public')->putFileAs('configuration', new File($sourcePath), $targetFilename);

        // Keys used across views for the admin logo.
        $keys = [
            'general.general.admin_logo.logo_image',
            // Some views still reference the legacy key; keep both in sync.
            'general.design.admin_logo.logo_image',
        ];

        foreach ($keys as $code) {
            $existing = DB::table('core_config')->where('code', $code)->first();

            if ($existing) {
                DB::table('core_config')
                    ->where('code', $code)
                    ->update(['value' => $storedPath, 'updated_at' => now()]);
            } else {
                DB::table('core_config')->insert([
                    'code'       => $code,
                    'value'      => $storedPath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Optional: seed favicon if present
        $favCandidates = [
            base_path('database/seeders/assets/favicon.png'),
            base_path('database/seeders/assets/favicon.jpg'),
            base_path('database/seeders/assets/favicon.jpeg'),
            base_path('database/seeders/assets/favicon.webp'),
            base_path('database/seeders/assets/favicon.svg'),
            base_path('database/seeders/assets/favicon.ico'),
        ];

        $faviconSource = collect($favCandidates)->first(fn ($p) => file_exists($p));

        if ($faviconSource) {
            $favExt = pathinfo($faviconSource, PATHINFO_EXTENSION);
            $favFilename = 'favicon.' . $favExt;
            $favStored = Storage::disk('public')->putFileAs('configuration', new File($faviconSource), $favFilename);

            $favKey = 'general.design.admin_logo.favicon';
            $existingFav = DB::table('core_config')->where('code', $favKey)->first();

            if ($existingFav) {
                DB::table('core_config')
                    ->where('code', $favKey)
                    ->update(['value' => $favStored, 'updated_at' => now()]);
            } else {
                DB::table('core_config')->insert([
                    'code'       => $favKey,
                    'value'      => $favStored,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command?->info('[AppLogoSeeder] Favicon seeded: ' . $favStored);
        }

        $this->command?->info('[AppLogoSeeder] Admin logo seeded: ' . $storedPath);
    }
}
