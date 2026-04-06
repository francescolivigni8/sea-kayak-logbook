import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import JournalLayout from '@/layouts/JournalLayout.vue';
import 'leaflet/dist/leaflet.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Workspace':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('profiles/'):
                return null;
            default:
                return JournalLayout;
        }
    },
    progress: {
        color: '#6772ff',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
