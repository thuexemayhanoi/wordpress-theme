#!/usr/bin/env node
/**
 * Summarise a Playwright JSON report: one row per spec file (tests, passed, failed, skipped), totals per
 * project, and every failure with the first line of its error.
 *
 *   node tests/e2e/summarize.mjs                # reads tests/e2e/.artifacts/results.json
 *   node tests/e2e/summarize.mjs other.json
 *   node tests/e2e/summarize.mjs --markdown     # a Markdown table (for a CI job summary)
 *
 * Exit code 1 when anything failed, so it can also gate a CI step after the report was uploaded.
 */
import fs from 'node:fs';
import path from 'node:path';
import { ARTIFACTS_DIR } from './helpers/env.mjs';

const args = process.argv.slice(2);
const markdown = args.includes('--markdown');
const file = args.find((arg) => !arg.startsWith('--')) || path.join(ARTIFACTS_DIR, 'results.json');
const report = JSON.parse(fs.readFileSync(file, 'utf8'));

const strip = (text) => String(text).replace(/\u001b\[[0-9;]*m/g, '');
const specs = new Map();
const projects = new Map();
const failures = [];

function bump(map, key, status, duration) {
  const row = map.get(key) || { total: 0, passed: 0, failed: 0, skipped: 0, flaky: 0, ms: 0 };
  row.total += 1;
  row.ms += duration;
  if (status === 'expected') row.passed += 1;
  else if (status === 'skipped') row.skipped += 1;
  else if (status === 'flaky') row.flaky += 1;
  else row.failed += 1;
  map.set(key, row);
}

function walk(suite) {
  for (const spec of suite.specs || []) {
    for (const test of spec.tests) {
      const last = test.results[test.results.length - 1] || {};
      const duration = test.results.reduce((sum, result) => sum + (result.duration || 0), 0);
      bump(specs, spec.file, test.status, duration);
      bump(projects, test.projectName, test.status, duration);
      if (test.status === 'unexpected' || test.status === 'flaky') {
        const message = strip((last.error && last.error.message) || (last.errors && last.errors[0] && last.errors[0].message) || 'no message');
        failures.push({ file: spec.file, project: test.projectName, title: spec.title, status: test.status, message: message.split('\n').find((line) => line.trim()) || message });
      }
    }
  }
  (suite.suites || []).forEach(walk);
}
report.suites.forEach(walk);

const total = { total: 0, passed: 0, failed: 0, skipped: 0, flaky: 0, ms: 0 };
for (const row of specs.values()) {
  for (const key of Object.keys(total)) total[key] += row[key];
}

const seconds = (ms) => `${(ms / 1000).toFixed(1)}s`;
const columns = ['spec', 'tests', 'pass', 'fail', 'skipped', 'flaky', 'test time'];
const lines = [...specs.entries()]
  .sort(([a], [b]) => a.localeCompare(b))
  .map(([name, row]) => [name, row.total, row.passed, row.failed, row.skipped, row.flaky, seconds(row.ms)]);
lines.push(['TOTAL', total.total, total.passed, total.failed, total.skipped, total.flaky, seconds(total.ms)]);
const projectLines = [...projects.entries()].map(([name, row]) => [name, row.total, row.passed, row.failed, row.skipped, row.flaky, seconds(row.ms)]);

function render(head, rows) {
  if (markdown) {
    return [`| ${head.join(' | ')} |`, `| ${head.map(() => '---').join(' | ')} |`, ...rows.map((row) => `| ${row.join(' | ')} |`)].join('\n');
  }
  const widths = head.map((title, i) => Math.max(title.length, ...rows.map((row) => String(row[i]).length)));
  const fmt = (row) => row.map((cell, i) => (i === 0 ? String(cell).padEnd(widths[i]) : String(cell).padStart(widths[i]))).join('  ');
  return [fmt(head), widths.map((w) => '-'.repeat(w)).join('  '), ...rows.map(fmt)].join('\n');
}

console.log(render(columns, lines));
console.log(`\n${render(['project', 'tests', 'pass', 'fail', 'skipped', 'flaky', 'test time'], projectLines)}`);
const wall = report.stats && report.stats.duration ? `, wall clock ${seconds(report.stats.duration)}` : '';
console.log(`\n${total.total} tests: ${total.passed} passed, ${total.failed} failed, ${total.skipped} skipped, ${total.flaky} flaky${wall}`);

if (failures.length > 0) {
  console.log(`\nFailures (${failures.length}):`);
  for (const failure of failures) {
    console.log(`  [${failure.project}] ${failure.file} > ${failure.title}\n      ${failure.message.slice(0, 200)}`);
  }
}
process.exit(total.failed > 0 || total.flaky > 0 ? 1 : 0);
