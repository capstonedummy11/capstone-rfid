import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

// @function updateTheme: Ina-update ang theme sa use Appearance flow.
// @useIn updateTheme: resources/js/composables/useAppearance.ts:70
export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

// @function setCookie: Sine-set ang cookie sa use Appearance flow.
// @useIn setCookie: resources/js/composables/useAppearance.ts:114
const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

// @function mediaQuery: Binubuo ang media query database query.
// @useIn mediaQuery: resources/js/composables/useAppearance.ts:83
const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

// @function getStoredAppearance: Kinukuha ang stored appearance sa use Appearance flow.
// @useIn getStoredAppearance: resources/js/composables/useAppearance.ts:68
const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

// @function prefersDark: Kinukuha ang prefers dark result para sa use Appearance.
// @useIn prefersDark: resources/js/composables/useAppearance.ts:101
const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

// @function handleSystemThemeChange: Pinoproseso ang system theme change sa use Appearance flow.
// @useIn handleSystemThemeChange: resources/js/composables/useAppearance.ts:83
const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

// @function initializeTheme: Kinukuha ang initialize theme result para sa use Appearance.
// @useIn initializeTheme: resources/js/app.js
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

// @function useAppearance: Kinukuha ang use appearance result para sa use Appearance.
// @useIn useAppearance: resources/js/app.js
export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    // @function updateAppearance: Ina-update ang appearance sa use Appearance flow.
    // @useIn updateAppearance: resources/js/composables/useAppearance.ts:10
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
