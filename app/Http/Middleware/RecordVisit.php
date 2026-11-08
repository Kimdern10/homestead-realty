<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts one visit per browser session and remembers where it came from
 * (Google, Facebook, direct…). Only daily totals are stored: no IP addresses.
 */
class RecordVisit
{
    /**
     * Pages that are not part of the public website.
     */
    private const array SKIP = ['dashboard*', 'settings*', 'livewire*', 'webhooks*', 'storage*', 'build*', 'up', 'sitemap.xml', 'robots.txt', 'homes/cards', 'login', 'register', 'email*', 'forgot-password', 'reset-password*', 'two-factor*', 'user*'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCount($request, $response)) {
            $source = Visit::sourceFrom($request->headers->get('referer'), $request->getHost());

            // Remember the source so a message sent later in the visit says where the person came from.
            $request->session()->put('visit_source', $source);

            try {
                Visit::record($source);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function shouldCount(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->isSuccessful()
            && $request->hasSession()
            && ! $request->session()->has('visit_source')
            && ! $request->ajax()
            && ! $request->is(...self::SKIP)
            && ! $request->user()?->isStaff()
            && ! preg_match('/bot|crawl|spider|slurp|preview|monitor|headless|lighthouse/i', (string) $request->userAgent());
    }
}
