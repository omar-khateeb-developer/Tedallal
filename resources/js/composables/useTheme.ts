import { ref, computed } from 'vue';

export type ThemeMode = 'dark' | 'light';

const currentTheme = ref<ThemeMode>('dark');
const isInitialized = ref(false);

export function useTheme() {
    function applyThemeToDom(theme: ThemeMode) {
        if (typeof document !== 'undefined') {
            const root = document.documentElement;
            if (theme === 'dark') {
                root.classList.add('dark');
                root.style.colorScheme = 'dark';
            } else {
                root.classList.remove('dark');
                root.style.colorScheme = 'light';
            }
        }
    }

    function setTheme(theme: ThemeMode) {
        currentTheme.value = theme;
        applyThemeToDom(theme);
        if (typeof window !== 'undefined') {
            localStorage.setItem('tedallal_theme', theme);
            localStorage.setItem('tedallal_customer_theme', theme);
        }
    }

    function toggleTheme() {
        setTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
    }

    function initTheme(defaultTheme: ThemeMode = 'dark') {
        if (typeof window !== 'undefined') {
            const saved = (localStorage.getItem('tedallal_theme') || localStorage.getItem('tedallal_customer_theme')) as ThemeMode | null;
            if (saved === 'dark' || saved === 'light') {
                currentTheme.value = saved;
            } else {
                currentTheme.value = defaultTheme;
            }
            applyThemeToDom(currentTheme.value);
            isInitialized.value = true;
        }
    }

    return {
        currentTheme,
        isDark: computed(() => currentTheme.value === 'dark'),
        isInitialized,
        setTheme,
        toggleTheme,
        initTheme,
    };
}
