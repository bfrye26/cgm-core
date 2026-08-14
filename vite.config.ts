import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

// Single self-contained entry so WordPress can load the output as a classic
// (non-module) script. inlineDynamicImports keeps everything in one file with
// no shared-chunk `import` that would break classic script loading.
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src/Admin'),
    },
  },
  build: {
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/Admin/main.tsx'),
      output: {
        inlineDynamicImports: true,
        entryFileNames: 'admin.js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.names?.[0] ?? '';
          if (name.endsWith('.css')) {
            return 'admin.css';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
