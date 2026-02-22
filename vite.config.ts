import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

const srcDir = resolve(__dirname, 'src/web/assets/chat/src');
const outDir = resolve(__dirname, 'src/web/assets/chat/dist');

// Two separate IIFE builds — Vite lib mode doesn't support multi-entry IIFE,
// so we use two rollup outputs via a custom build approach.
export default defineConfig(() => {
  // Determine which entry to build based on ENTRY env var
  const entry = process.env.ENTRY || 'main';
  const isSlideout = entry === 'slideout';

  return {
    plugins: [vue()],
    define: {
      'process.env.NODE_ENV': JSON.stringify('production'),
    },
    resolve: {
      alias: {
        '@': srcDir,
      },
    },
    build: {
      outDir,
      emptyOutDir: !isSlideout, // Only empty on first build
      lib: {
        entry: isSlideout
          ? resolve(srcDir, 'main-slideout.ts')
          : resolve(srcDir, 'main.ts'),
        formats: ['iife'],
        name: isSlideout ? 'CoPilotSlideout' : 'CoPilotChat',
        fileName: () => isSlideout ? 'copilot-slideout.js' : 'copilot-chat.js',
      },
      rollupOptions: {
        output: {
          assetFileNames: 'copilot-chat[extname]',
        },
      },
      cssCodeSplit: false,
    },
  };
});
