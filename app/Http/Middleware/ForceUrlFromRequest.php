<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class ForceUrlFromRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Gunakan host & skema dari request agar URL/asset()/Storage::url() mengikuti tunnel saat ini
        $root = $request->getSchemeAndHttpHost();

        // app.url & asset base
        Config::set('app.url', $root);
        URL::forceRootUrl($root);

        if ($request->isSecure()) {
            URL::forceScheme('https');
        }

        // Public storage URL (dipakai oleh Storage::url())
        // Pastikan mengarah ke host saat ini agar logo/foto/icon dari disk public termuat benar
        Config::set('filesystems.disks.public.url', $root . '/storage');

        return $next($request);
    }
}
