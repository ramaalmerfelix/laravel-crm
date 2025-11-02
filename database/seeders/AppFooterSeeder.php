<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppFooterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = date('Y');

        // Footer copy (English) without mentioning Krayin
        $footerHtml = '© ' . $year . ' PT Famindo Teknik Karya Utama — Market Basket Analysis (Apriori) for sales recommendations.';

        $code = 'general.settings.footer.label';

        $existing = DB::table('core_config')->where('code', $code)->first();

        if ($existing) {
            DB::table('core_config')
                ->where('code', $code)
                ->update(['value' => $footerHtml, 'updated_at' => now()]);
        } else {
            DB::table('core_config')->insert([
                'code'       => $code,
                'value'      => $footerHtml,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('[AppFooterSeeder] Footer label seeded.');
    }
}
