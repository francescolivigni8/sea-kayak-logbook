<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (config('kayak.noindex'))
            <meta name="robots" content="noindex, nofollow, noarchive">
        @endif

        <style>
            html {
                background-color: #edf0ff;
            }
        </style>

        <script>
            (() => {
                const cookieMatch = document.cookie.match(/(?:^|;\s*)appearance=([^;]+)/);
                const cookieAppearance = cookieMatch ? decodeURIComponent(cookieMatch[1]) : null;
                const storedAppearance = localStorage.getItem('appearance');
                const lightThemes = new Set([
                    'journal',
                    'sea-glass',
                    'sand-dusk',
                    'fjord-mist',
                ]);
                const preference = storedAppearance || cookieAppearance || 'journal';
                const resolvedTheme = lightThemes.has(preference) ? preference : 'journal';

                const backgroundByTheme = {
                    journal: '#edf0ff',
                    'sea-glass': '#e7f6f6',
                    'sand-dusk': '#fbf2e7',
                    'fjord-mist': '#f2f6fb',
                };

                document.documentElement.dataset.theme = resolvedTheme;
                document.documentElement.classList.remove('dark');
                document.documentElement.style.backgroundColor = backgroundByTheme[resolvedTheme] || backgroundByTheme.journal;

                if (storedAppearance && storedAppearance !== resolvedTheme) {
                    localStorage.setItem('appearance', resolvedTheme);
                }
            })();
        </script>

        <link rel="icon" href="/brand/ykj-logo-192.png" type="image/png">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap"
            rel="stylesheet"
        />

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
