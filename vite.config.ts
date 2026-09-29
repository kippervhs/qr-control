import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

export default defineConfig(({ command }) => ({
  root: path.resolve(__dirname, "frontend"),
  base: command === "build" ? "/app/" : "/",
  plugins: [react()],
  build: { outDir: path.resolve(__dirname, "backend/public/app"), emptyOutDir: true },
  server: { host: "127.0.0.1", port: 3000, proxy: { "/api": "http://127.0.0.1:8080", "/q": "http://127.0.0.1:8080" } },
}));
