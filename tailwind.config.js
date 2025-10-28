import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    safelist: [
        'h-80', // Paksa Tailwind untuk membuat kelas ini
        // Anda mungkin perlu menambahkan kelas lain 
        // yang gagal dimuat di sini di masa depan
        // 'lg:col-span-1',
        // 'lg:col-span-2',
        // 'lg:col-span-3',
    ],
    
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
