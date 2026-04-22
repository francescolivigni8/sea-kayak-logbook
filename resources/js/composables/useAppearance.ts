import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

type AppearanceTheme = {
    value: Appearance;
    label: string;
    description: string;
    swatches: [string, string, string];
};

const lightThemeNames: Appearance[] = [
    'journal',
    'sea-glass',
    'sand-dusk',
    'fjord-mist',
];

const themeModes: Record<Appearance, ResolvedAppearance> = {
    journal: 'light',
    'sea-glass': 'light',
    'sand-dusk': 'light',
    'fjord-mist': 'light',
};

export const appearanceThemes: AppearanceTheme[] = [
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
];

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const normalizeAppearance = (value: string | null | undefined): Appearance =>
    lightThemeNames.includes(value as Appearance)
        ? (value as Appearance)
        : 'journal';

const applyResolvedTheme = (theme: Appearance): void => {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.dataset.theme = theme;
    document.documentElement.classList.remove('dark');
};

export function updateTheme(value: Appearance): void {
    applyResolvedTheme(normalizeAppearance(value));
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearanceFromCookie = (): Appearance | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)appearance=([^;]+)/);

    return match ? normalizeAppearance(decodeURIComponent(match[1])) : null;
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedAppearance = localStorage.getItem('appearance');

    if (storedAppearance) {
        return normalizeAppearance(storedAppearance);
    }

    return getStoredAppearanceFromCookie();
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance() || 'journal';
    appearance.value = savedAppearance;
    updateTheme(savedAppearance);
}

const appearance = ref<Appearance>('journal');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return themeModes[normalizeAppearance(appearance.value)];
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
