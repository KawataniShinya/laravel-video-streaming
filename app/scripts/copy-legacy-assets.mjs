import { copyFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = new URL('../', import.meta.url);
mkdirSync(new URL('public/legacy/', root), { recursive: true });
copyFileSync(fileURLToPath(new URL('node_modules/hls.js/dist/hls.min.js', root)), fileURLToPath(new URL('public/legacy/hls.min.js', root)));
