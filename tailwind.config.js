import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'Cairo', 'Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Amiri', ...defaultTheme.fontFamily.serif],
                display: ['Cinzel', ...defaultTheme.fontFamily.serif],
                tajawal: ['Tajawal', 'sans-serif'],
            },
            colors: {
                luxury: {
                    gold: '#d4af37',
                    'gold-light': '#f5e7b2',
                    'gold-dark': '#9b7d23',
                    charcoal: '#0b0d13',
                    surface: '#12151f',
                    elevated: '#171b26',
                    ivory: '#fcfbf9',
                    'ivory-dark': '#f4f1ea',
                    travertine: '#f6f3eb',
                },
            },
        },
    },

    plugins: [forms],
};
