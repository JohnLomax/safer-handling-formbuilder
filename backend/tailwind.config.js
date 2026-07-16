import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Segoe UI"', 'Arial', 'Helvetica', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#008afc',
                    dark: '#0478d8',
                    header: '#0255a4',
                    green: 'rgb(186 218 85)',
                },
                sh: {
                    text: '#16324a',
                    mid: '#2e5d84',
                    border: '#d8e8f8',
                    surface: '#f7fbff',
                },
            },
            boxShadow: {
                card: '0 18px 45px rgba(0, 138, 252, 0.13), 0 2px 10px rgba(11, 71, 117, 0.07)',
            },
        },
    },

    plugins: [forms],
};
