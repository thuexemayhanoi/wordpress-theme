#!/usr/bin/env node
/**
 * Build the theme assets with esbuild (the only build dependency, dev-only).
 *
 *   critical.min.css  inlined in <head>  (tokens, base, layout, header, cards, article core, archive)
 *   main.min.css      loaded without blocking rendering (extras, footer, comments, blocks, print)
 *   theme.min.js      one deferred, dependency-free script
 *
 * Built files are committed so the theme works when deployed straight from Git (no build on the server).
 *
 *   node tools/build-assets.mjs           write the files
 *   node tools/build-assets.mjs --check   fail when committed output differs from the sources (CI)
 */
import { build } from 'esbuild';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const check = process.argv.includes('--check');

const cssTarget = ['chrome90', 'firefox90', 'safari14'];
const targets = [
  { entry: 'assets/src/css/critical.css', out: 'assets/css/critical.min.css', options: { loader: { '.css': 'css' }, target: cssTarget } },
  { entry: 'assets/src/css/main.css', out: 'assets/css/main.min.css', options: { loader: { '.css': 'css' }, target: cssTarget } },
  { entry: 'assets/src/js/main.js', out: 'assets/js/theme.min.js', options: { format: 'iife', target: ['es2018'] } },
];

let failed = false;
for (const t of targets) {
  const result = await build({
    entryPoints: [resolve(root, t.entry)],
    bundle: true,
    minify: true,
    write: false,
    legalComments: 'none',
    logLevel: 'warning',
    outfile: resolve(root, t.out),
    ...t.options,
  });
  const output = result.outputFiles.find((f) => f.path.endsWith(t.out.split('/').pop()));
  const text = output.text.endsWith('\n') ? output.text : `${output.text}\n`;
  const file = resolve(root, t.out);

  if (check) {
    const current = existsSync(file) ? await readFile(file, 'utf8') : '';
    if (current !== text) {
      console.error(`OUT OF DATE: ${t.out} (run "npm run build" and commit the result)`);
      failed = true;
    } else {
      console.log(`ok  ${t.out}`);
    }
  } else {
    await mkdir(dirname(file), { recursive: true });
    await writeFile(file, text);
    console.log(`built ${t.out} (${Buffer.byteLength(text)} bytes)`);
  }
}
process.exit(failed ? 1 : 0);
