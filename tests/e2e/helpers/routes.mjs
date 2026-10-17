/**
 * The page types the suite covers, and access to their discovered URLs.
 *
 * PAGE_TYPES is static on purpose: test titles are generated from it at collection time, before any
 * network access. The URLs are resolved at run time (global setup writes routes.json; a worker falls
 * back to discovering them itself when the file is missing).
 */
import fs from 'node:fs';
import { ARTIFACTS_DIR, ROUTES_FILE } from './env.mjs';

/**
 * @typedef {object} PageType
 * @property {string} key       Key in routes.json.
 * @property {string} label     Human readable name used in test titles and screenshot file names.
 * @property {number} [status]  Expected HTTP status (default 200).
 * @property {boolean} [core]   Core pages must be discoverable: a missing one fails the run.
 */

/** @type {PageType[]} */
export const PAGE_TYPES = [
  { key: 'home', label: 'home', core: true },
  { key: 'homePage2', label: 'home page 2' },
  { key: 'singleRich', label: 'single rich', core: true },
  { key: 'singlePlain', label: 'single plain' },
  { key: 'singleComments', label: 'single with comments' },
  { key: 'passwordPost', label: 'password post' },
  { key: 'categoryHub', label: 'category hub', core: true },
  { key: 'categoryChild', label: 'category child' },
  { key: 'categoryVi', label: 'category vietnamese' },
  { key: 'categoryPage2', label: 'category page 2' },
  { key: 'search', label: 'search results', core: true },
  { key: 'searchEmpty', label: 'search no results' },
  { key: 'notFound', label: '404', status: 404, core: true },
  { key: 'pillar', label: 'pillar page' },
  { key: 'author', label: 'author archive' },
  { key: 'page', label: 'static page' },
  { key: 'tag', label: 'tag archive' },
];

/** File-name friendly version of a label. */
export const slug = (text) => text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

let cached = null;

/** @returns {Promise<Record<string, string>>} */
export async function loadRoutes() {
  if (cached) {
    return cached;
  }
  if (fs.existsSync(ROUTES_FILE)) {
    cached = JSON.parse(fs.readFileSync(ROUTES_FILE, 'utf8')).routes;
    return cached;
  }
  const { discoverRoutes } = await import('./discover.mjs');
  const { routes } = await discoverRoutes();
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  cached = routes;
  return cached;
}
