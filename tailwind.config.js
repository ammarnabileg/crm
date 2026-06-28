/** @type {import('tailwindcss').Config} */
module.exports = {
  // Scan every server-rendered view (and any PHP that emits class names) so the
  // build includes exactly the utilities the app uses.
  content: [
    './resources/views/**/*.php',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      // One unified font identity across every page/component.
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      // Single brand accent. The whole app references `indigo-*`; we map that key
      // to the brand's azure blue so the identity is unified in one place (no
      // per-view churn). Values are Tailwind's blue scale.
      colors: {
        indigo: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
      },
    },
  },
  plugins: [],
}
