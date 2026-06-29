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
      // to CSS variables so the accent is themeable per context in ONE place with
      // no per-view churn. `:root` defines the variables as azure blue (the default
      // workspace identity); `.theme-platform` (the HaHireAI owner shell) overrides
      // them to red. The `rgb(var(--brand-N) / <alpha-value>)` form keeps Tailwind's
      // opacity modifiers (e.g. bg-indigo-50/60) working — see resources/css/app.css.
      colors: {
        indigo: {
          50: 'rgb(var(--brand-50) / <alpha-value>)',
          100: 'rgb(var(--brand-100) / <alpha-value>)',
          200: 'rgb(var(--brand-200) / <alpha-value>)',
          300: 'rgb(var(--brand-300) / <alpha-value>)',
          400: 'rgb(var(--brand-400) / <alpha-value>)',
          500: 'rgb(var(--brand-500) / <alpha-value>)',
          600: 'rgb(var(--brand-600) / <alpha-value>)',
          700: 'rgb(var(--brand-700) / <alpha-value>)',
          800: 'rgb(var(--brand-800) / <alpha-value>)',
          900: 'rgb(var(--brand-900) / <alpha-value>)',
          950: 'rgb(var(--brand-950) / <alpha-value>)',
        },
      },
    },
  },
  plugins: [],
}
