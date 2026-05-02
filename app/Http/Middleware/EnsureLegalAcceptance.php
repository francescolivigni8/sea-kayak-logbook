<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalAcceptance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresLegalAcceptance()) {
            return $next($request);
        }

        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('legal.acceptance.edit');
    }
}
