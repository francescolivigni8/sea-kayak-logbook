<?php

use App\Http\Middleware\AddNoindexHeader;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureJournalOwner;
use App\Http\Middleware\EnsureLegalAcceptance;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'journal.owner' => EnsureJournalOwner::class,
            'legal.accepted' => EnsureLegalAcceptance::class,
        ]);

        $middleware->web(append: [
            AddNoindexHeader::class,
            AddSecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
