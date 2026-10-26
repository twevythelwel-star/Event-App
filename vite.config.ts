import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// The Laravel backend (php artisan serve) runs on :8000; proxying /api keeps the
// session cookie and XSRF token same-origin for the single-page app.
const BACKEND_URL = "http://127.0.0.1:8000";

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      "/api": { target: BACKEND_URL, changeOrigin: false },
    },
  },
});
