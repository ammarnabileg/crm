/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'Tajawal', 'system-ui', 'sans-serif'],
      },
      colors: {
        brand: {
          50: '#eef5ff',
          100: '#d9e8ff',
          200: '#bcd7ff',
          300: '#8ebdff',
          400: '#5897ff',
          500: '#3b76f6',
          600: '#2457eb',
          700: '#1c43d8',
          800: '#1d39af',
          900: '#1d348a',
          950: '#162253',
        },
      },
    },
  },
  plugins: [],
}
