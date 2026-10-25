/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: 'var(--primary-color, #2563eb)',
          hover: 'var(--primary-hover, #1d4ed8)',
          light: 'var(--primary-light, #eff6ff)',
        },
        secondary: {
          DEFAULT: 'var(--secondary-color, #10b981)',
          hover: 'var(--secondary-hover, #059669)',
          light: 'var(--secondary-light, #ecfdf5)',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Cairo', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
