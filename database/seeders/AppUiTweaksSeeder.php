<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppUiTweaksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Remove the "logo + version" strip from the admin profile dropdown
        // by injecting a small JS snippet via Configuration -> Custom Scripts.
        $js = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  try {
    var logoEl = document.querySelector('img[src$="/cache/logo.png"]');
    if (logoEl && logoEl.parentElement) {
      logoEl.parentElement.remove();
    }
  } catch (e) { /* noop */ }
});
JS;

        $code = 'general.content.custom_scripts.custom_javascript';

        $existing = DB::table('core_config')->where('code', $code)->first();

        if ($existing) {
            DB::table('core_config')
                ->where('code', $code)
                ->update(['value' => $js, 'updated_at' => now()]);
        } else {
            DB::table('core_config')->insert([
                'code'       => $code,
                'value'      => $js,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('[AppUiTweaksSeeder] Injected custom JS to remove logo+version strip in profile dropdown.');
    }
}

