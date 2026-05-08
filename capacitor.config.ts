import type { CapacitorConfig } from '@capacitor/cli';

const appUrl = process.env.CAPACITOR_SERVER_URL ?? 'https://yourkayakingjournal.com';

const config: CapacitorConfig = {
    appId: 'com.francescolivigni.yourkayakingjournal',
    appName: 'Your Kayaking Journal',
    webDir: 'public',
    server: {
        url: appUrl,
        cleartext: false,
        allowNavigation: [
            'yourkayakingjournal.com',
            '*.yourkayakingjournal.com',
            'api.maptiler.com',
            '*.maptiler.com',
        ],
    },
    ios: {
        backgroundColor: '#f6f7ff',
        limitsNavigationsToAppBoundDomains: true,
        contentInset: 'always',
    },
    plugins: {
        SplashScreen: {
            launchShowDuration: 1200,
            launchAutoHide: true,
            backgroundColor: '#f6f7ff',
            showSpinner: false,
        },
        StatusBar: {
            style: 'DARK',
            backgroundColor: '#f6f7ff',
            overlaysWebView: false,
        },
        Keyboard: {
            resize: 'body',
            resizeOnFullScreen: true,
        },
    },
};

export default config;
