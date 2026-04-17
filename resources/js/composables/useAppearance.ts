import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type ThemeName = Exclude<Appearance, 'system'>;

type AppearanceTheme = {
    value: Appearance;
    label: string;
    description: string;
    swatches: [string, string, string];
};

const systemThemeName = (): ThemeName =>
    prefersDark() ? 'midnight-chart' : 'journal';

const themeModes: Record<ThemeName, ResolvedAppearance> = {
    journal: 'light',
    'sea-glass': 'light',
    'sand-dusk': 'light',
    'fjord-mist': 'light',
    'midnight-chart': 'dark',
};

export const appearanceThemes: AppearanceTheme[] = [
    {
        value: 'system',
        label: 'System',
        description: 'Follow your device preference automatically.',
        swatches: ['#6772ff', '#7ad7d0', '#edf0ff'],
    },
    {
        value: 'journal',
        label: 'Journal light',
        description: 'The default soft pastel workspace.',
        swatches: ['#6772ff', '#ff9c6b', '#edf0ff'],
    },
    {
        value: 'sea-glass',
        label: 'Sea glass',
        description: 'Cool mint and coastal blues.',
        swatches: ['#2a8da6', '#7bd8ce', '#e7f6f6'],
    },
    {
        value: 'sand-dusk',
        label: 'Sand dusk',
        description: 'Warm cream tones with sunset contrast.',
        swatches: ['#d2714c', '#f2c27c', '#fbf2e7'],
    },
    {
        value: 'fjord-mist',
        label: 'Fjord mist',
        description: 'Icy neutral surfaces with crisp blue accents.',
        swatches: ['#5f78b5', '#b8d4e8', '#f2f6fb'],
    },
    {
        value: 'midnight-chart',
        label: 'Midnight chart',
        description: 'Dark navigation-table mood for late sessions.',
        swatches: ['#7aa2ff', '#7ad7d0', '#0c1430'],
    },
];

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const resolveTheme = (value: Appearance): ThemeName =>
    value === 'system' ? systemThemeName() : value;

const applyResolvedTheme = (theme: ThemeName): void => {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.dataset.theme = theme;
    document.documentElement.classList.toggle(
        'dark',
        themeModes[theme] === 'dark',
    );
};

export function updateTheme(value: Appearance): void {
    applyResolvedTheme(resolveTheme(value));
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearanceFromCookie = (): Appearance | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)appearance=([^;]+)/);

    return match ? (decodeURIComponent(match[1]) as Appearance) : null;
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        (localStorage.getItem('appearance') as Appearance | null) ||
        getStoredAppearanceFromCookie()
    );
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    if ((currentAppearance || 'system') === 'system') {
        updateTheme('system');
    }
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance() || 'system';
    appearance.value = savedAppearance;
    updateTheme(savedAppearance);

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return themeModes[resolveTheme(appearance.value)];
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
