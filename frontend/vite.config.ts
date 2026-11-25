import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// In development the PHP API runs on :8000. Proxying /api and /images through Vite
// means the browser sees one origin, exactly like in production (where Apache serves both).
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/images': 'http://127.0.0.1:8000',
    },
  },
})
