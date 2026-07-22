<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's chosen UI language (stored in the session) on every web
 * request. Falls back to the configured default (bn). Only whitelisted locales
 * are honoured. The richer per-user preference lands with the UI milestone.
 */
class SetLocale
{
    private const SUPPORTED = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
