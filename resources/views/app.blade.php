<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
                const preference = storedAppearance || cookieAppearance || 'system';
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolvedTheme = preference === 'system'
                    ? (systemPrefersDark ? 'midnight-chart' : 'journal')
                    : preference;

                const backgroundByTheme = {
                    journal: '#edf0ff',
                    'sea-glass': '#e7f6f6',
                    'sand-dusk': '#fbf2e7',
                    'fjord-mist': '#f2f6fb',
                    'midnight-chart': '#0c1430',
                };

                document.documentElement.dataset.theme = resolvedTheme;
                document.documentElement.classList.toggle('dark', resolvedTheme === 'midnight-chart');
                document.documentElement.style.backgroundColor = backgroundByTheme[resolvedTheme] || backgroundByTheme.journal;
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
