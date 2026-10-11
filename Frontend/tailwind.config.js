/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        forest: {
          50: '#f0f6f2',
          100: '#dbeade',
          200: '#b8d6be',
          700: '#23523b',
          800: '#1a3e2d',
          900: '#143124',
          950: '#0b1c14',
          brand: '#173929', // Main Lumiere dark forest green
          deep: '#10271c'
        },
        gold: {
          100: '#f7f1e6',
          300: '#dfc28d',
          400: '#cfa964',
          500: '#b88d43',
          600: '#a37632',
          700: '#865e26',
        },
        sand: {
          50: '#fbfaf8',
          100: '#f6f3ee',
          200: '#ede8df',
          300: '#dfd7c9'
        }
      },
      fontFamily: {
        serif: ['"Playfair Display"', 'Georgia', 'serif'],
        sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
      }
    },
  },
  plugins: [],
}

