import defaultTheme from 'tailwindcss/defaultTheme';

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
                sans: ['Inter', 'Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Primary Brand Colors
                'primary-dark': '#063A1C',
                'primary-darker': '#052d15',
                'primary': '#063A1C',
                'secondary': '#205A44',
                
                // Background Colors
                'brand-bg': '#F7F6F3',
                'brand-hover': '#F7F6F3',
                'brand-border': '#E5DED4',
                
                // Text Colors
                'text-primary': '#063A1C',
                'text-secondary': '#205A44',
                'text-muted': '#B3B5B4',
                
                // Status Colors
                'success': {
                    bg: '#D4EDDA',
                    text: '#155724',
                },
                'warning': {
                    bg: '#FFF3CD',
                    text: '#856404',
                },
                'info': {
                    bg: '#D1ECF1',
                    text: '#0C5460',
                },
                'error': {
                    bg: '#F8D7DA',
                    text: '#721C24',
                },
                
                // Special Colors
                'whatsapp': '#25D366',
                'success-indicator': '#28a745',
                
                // Primary Green Shades (for Tailwind utilities)
                primary: {
                    50: '#f0f9f4',
                    100: '#dcf2e6',
                    200: '#bce5d0',
                    300: '#8fd1b0',
                    400: '#5bb58a',
                    500: '#205A44',
                    600: '#1a4a37',
                    700: '#063A1C',
                    800: '#052d15',
                    900: '#042010',
                },
                
                // Secondary Green Shades
                secondary: {
                    50: '#f0f9f4',
                    100: '#dcf2e6',
                    200: '#bce5d0',
                    300: '#8fd1b0',
                    400: '#5bb58a',
                    500: '#205A44',
                    600: '#1a4a37',
                    700: '#063A1C',
                    800: '#052d15',
                    900: '#042010',
                },
                
                // Keep indigo for backward compatibility but map to green
                indigo: {
                    50: '#f0f9f4',
                    100: '#dcf2e6',
                    200: '#bce5d0',
                    300: '#8fd1b0',
                    400: '#5bb58a',
                    500: '#205A44',
                    600: '#1a4a37',
                    700: '#063A1C',
                    800: '#052d15',
                    900: '#042010',
                },
            },
            borderRadius: {
                'xl': '10px',
                '2xl': '12px',
            },
        },
    },
    plugins: [],
};

