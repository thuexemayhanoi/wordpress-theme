#!/usr/bin/env python3
"""Build the QA report of a CI run and decide whether a release artifact may be produced.

Inputs
  --needs      JSON of the GitHub "needs" context: {"job": {"result": "success|failure|cancelled|skipped"}}
  --required   comma separated job ids that must have succeeded
  --junit-dir  directory with PHPUnit JUnit XML files (one per run; the file name labels the run)
  --manifest   dist/manifest.json written by tools/package.sh (optional)
  --out        output directory for qa-report.md and qa-report.json

Exit status: 0 when every required job succeeded and no test failed, 1 otherwise. The report never says
more than the data shows: a job that did not run is reported as not run, never as passed.
"""

import argparse
import glob
import json
import os
import sys
import xml.etree.ElementTree as ET


def read_junit(directory):
    runs = []
    for path in sorted(glob.glob(os.path.join(directory, '**', '*.xml'), recursive=True)):
        try:
            root = ET.parse(path).getroot()
        except ET.ParseError:
            runs.append({'informational': False, 'run': os.path.basename(path), 'error': 'unreadable JUnit file'})
            continue
        suite = root if root.tag == 'testsuite' else root.find('testsuite')
        if suite is None:
            continue
        # Runs under a directory named "experimental" (forward-compatibility canaries) are shown, not gated on.
        informational = 'experimental' in os.path.relpath(path, directory).lower()
        runs.append({
            'informational': informational,
            'run': os.path.splitext(os.path.basename(path))[0] + (' (informational)' if informational else ''),
            'tests': int(suite.get('tests', 0)),
            'assertions': int(suite.get('assertions', 0)),
            'failures': int(suite.get('failures', 0)),
            'errors': int(suite.get('errors', 0)),
            'skipped': int(suite.get('skipped', 0)),
        })
    return runs


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--needs', required=True)
    parser.add_argument('--required', required=True)
    parser.add_argument('--junit-dir', default='')
    parser.add_argument('--manifest', default='')
    parser.add_argument('--out', required=True)
    args = parser.parse_args()

    with open(args.needs, encoding='utf-8') as handle:
        needs = json.load(handle)
    required = [name for name in args.required.split(',') if name]
    runs = read_junit(args.junit_dir) if args.junit_dir and os.path.isdir(args.junit_dir) else []
    manifest = {}
    if args.manifest and os.path.isfile(args.manifest):
        with open(args.manifest, encoding='utf-8') as handle:
            manifest = json.load(handle)

    gates = []
    for name in required:
        result = needs.get(name, {}).get('result', 'not run')
        gates.append({'job': name, 'result': result, 'passed': result == 'success'})

    gated = [r for r in runs if not r.get('informational')]
    failed_tests = sum(r.get('failures', 0) + r.get('errors', 0) for r in gated) + sum(1 for r in gated if r.get('error'))
    all_gates_passed = all(g['passed'] for g in gates)
    release_allowed = all_gates_passed and failed_tests == 0
    environment = {
        'repository': os.environ.get('GITHUB_REPOSITORY', ''),
        'commit': os.environ.get('GITHUB_SHA', '') or manifest.get('source', {}).get('commit', ''),
        'ref': os.environ.get('GITHUB_REF_NAME', ''),
        'run_url': '{}/{}/actions/runs/{}'.format(os.environ.get('GITHUB_SERVER_URL', 'https://github.com'), os.environ.get('GITHUB_REPOSITORY', ''), os.environ.get('GITHUB_RUN_ID', '')) if os.environ.get('GITHUB_RUN_ID') else '',
    }

    report = {
        'release_allowed': release_allowed,
        'environment': environment,
        'gates': gates,
        'phpunit_runs': runs,
        'totals': {
            'tests': sum(r.get('tests', 0) for r in gated),
            'assertions': sum(r.get('assertions', 0) for r in gated),
            'failures_and_errors': failed_tests,
            'skipped': sum(r.get('skipped', 0) for r in gated),
        },
        'artifacts': {key: manifest.get(key, {}) for key in ('theme', 'plugin') if manifest.get(key)},
    }

    lines = ['# Nguyen Ha Editorial: QA report', '']
    lines.append('**Verdict: {}**'.format('all required gates passed, release artifact allowed' if release_allowed else 'NOT RELEASABLE, at least one required gate failed or did not run'))
    lines.append('')
    if environment['commit']:
        lines.append('- Commit: `{}`{}'.format(environment['commit'], ' on `{}`'.format(environment['ref']) if environment['ref'] else ''))
    if environment['run_url']:
        lines.append('- Run: {}'.format(environment['run_url']))
    lines += ['', '## Required gates', '', '| Gate | Result |', '|---|---|']
    for gate in gates:
        lines.append('| {} | {} |'.format(gate['job'], gate['result']))

    if runs:
        lines += ['', '## PHPUnit and end-to-end runs', '', '| Run | Tests | Assertions | Failures | Errors | Skipped |', '|---|---|---|---|---|---|']
        for run in runs:
            if run.get('error'):
                lines.append('| {} | {} | | | | |'.format(run['run'], run['error']))
            else:
                lines.append('| {run} | {tests} | {assertions} | {failures} | {errors} | {skipped} |'.format(**run))
        totals = report['totals']
        lines.append('| **Total of the gated runs** | **{tests}** | **{assertions}** | **{failures_and_errors}** (failures + errors) | | **{skipped}** |'.format(**totals))
        lines += ['', 'Skipped tests are the multisite-only tests when a run is single-site and the single-site-only tests when it is multisite; they are not failures.']

    if manifest:
        lines += ['', '## Artifacts', '', '| Package | Version | Requires WordPress / PHP | SHA-256 |', '|---|---|---|---|']
        for key in ('theme', 'plugin'):
            item = manifest.get(key)
            if item:
                lines.append('| {} | {} | {} / {} | `{}` |'.format(item['zip'], item['version'], item['requires_wordpress'], item['requires_php'], item['sha256']))

    lines += [
        '',
        '## What these jobs cannot prove',
        '',
        '- Core Web Vitals in the field: only lab runs exist, and the hosting is not the production hosting.',
        '- The production Deployer for Git configuration and the live Yoast SEO / Rank Math settings: a staging check is required (docs/INSTALL-STAGING.md).',
        '- Content-specific facts of articles: no check verifies what an article claims.',
    ]

    os.makedirs(args.out, exist_ok=True)
    with open(os.path.join(args.out, 'qa-report.md'), 'w', encoding='utf-8') as out:
        out.write('\n'.join(lines) + '\n')
    with open(os.path.join(args.out, 'qa-report.json'), 'w', encoding='utf-8') as out:
        json.dump(report, out, indent=2, ensure_ascii=False)
        out.write('\n')

    print('\n'.join(lines))
    return 0 if release_allowed else 1


if __name__ == '__main__':
    sys.exit(main())
