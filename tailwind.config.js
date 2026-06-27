/** @type {import('tailwindcss').Config} */
module.exports = {
  // Dark mode is class-driven: a `.dark` class on <html> flips every `dark:` variant
  // — one component set, two themes (docs/30 Design System).
  darkMode: 'class',
  content: [
    './resources/views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'Tajawal', 'system-ui', 'sans-serif'],
      },
      // Design tokens — semantic color roles. `brand` is kept as the legacy alias of
      // `primary` so existing templates never break (backward compatibility).
      colors: {
        brand: {
          50: '#eef5ff', 100: '#d9e8ff', 200: '#bcd7ff', 300: '#8ebdff', 400: '#5897ff',
          500: '#3b76f6', 600: '#2457eb', 700: '#1c43d8', 800: '#1d39af', 900: '#1d348a', 950: '#162253',
        },
        primary: {
          50: '#eef5ff', 100: '#d9e8ff', 200: '#bcd7ff', 300: '#8ebdff', 400: '#5897ff',
          500: '#3b76f6', 600: '#2457eb', 700: '#1c43d8', 800: '#1d39af', 900: '#1d348a', 950: '#162253',
        },
        success: {
          50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80',
          500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d', 950: '#052e16',
        },
        warning: {
          50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24',
          500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f', 950: '#451a03',
        },
        danger: {
          50: '#fef2f2', 100: '#fee2e2', 200: '#fecaca', 300: '#fca5a5', 400: '#f87171',
          500: '#ef4444', 600: '#dc2626', 700: '#b91c1c', 800: '#991b1b', 900: '#7f1d1d', 950: '#450a0a',
        },
        info: {
          50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc', 400: '#38bdf8',
          500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e', 950: '#082f49',
        },
      },
      borderRadius: {
        token: '0.5rem',
      },
      zIndex: {
        dropdown: '1000',
        sticky: '1020',
        drawer: '1030',
        modal: '1040',
        popover: '1050',
        toast: '1060',
      },
      transitionDuration: {
        token: '150ms',
      },
      keyframes: {
        'fade-in': { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        'slide-up': { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
        'slide-in-end': { '0%': { transform: 'translateX(100%)' }, '100%': { transform: 'translateX(0)' } },
      },
      animation: {
        'fade-in': 'fade-in 150ms ease-out',
        'slide-up': 'slide-up 180ms ease-out',
        'slide-in-end': 'slide-in-end 200ms ease-out',
      },
    },
  },
  plugins: [],
}
