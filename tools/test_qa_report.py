#!/usr/bin/env python3
"""Tests of tools/qa-report.py, the script that decides whether a release artifact may exist.

Run: python3 -m unittest discover -s tools -p 'test_*.py'
"""

import json
import os
import subprocess
import sys
import tempfile
import unittest

SCRIPT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'qa-report.py')

PHPUNIT_OK = '<?xml version="1.0"?><testsuites><testsuite name="theme" tests="172" assertions="7712" errors="0" failures="0" skipped="1" time="1"><testsuite name="NH_Test" tests="172" assertions="7712" errors="0" failures="0" skipped="1" time="1"/></testsuite></testsuites>'
PHPUNIT_FAILING = '<?xml version="1.0"?><testsuites><testsuite name="plugin" tests="320" assertions="3556" errors="1" failures="2" skipped="0" time="1"/></testsuites>'
# Playwright puts the counters on <testsuites> and writes one <testsuite> per spec file.
PLAYWRIGHT = '<?xml version="1.0"?><testsuites id="" name="" tests="10" failures="0" skipped="0" errors="0" time="2"><testsuite name="a.spec.mjs" tests="6" failures="0" skipped="0" errors="0"/><testsuite name="b.spec.mjs" tests="4" failures="0" skipped="0" errors="0"/></testsuites>'
PLAYWRIGHT_FAILING = PLAYWRIGHT.replace('tests="10" failures="0"', 'tests="10" failures="1"')


def success(*jobs):
    return {job: {'result': 'success'} for job in jobs}


class QaReportTest(unittest.TestCase):

    def run_report(self, needs, required, junit=None, manifest=None):
        """Run the script; return (exit status, parsed qa-report.json, markdown)."""
        with tempfile.TemporaryDirectory() as tmp:
            needs_file = os.path.join(tmp, 'needs.json')
            with open(needs_file, 'w', encoding='utf-8') as handle:
                json.dump(needs, handle)
            extra = []
            if manifest is not None:
                manifest_file = os.path.join(tmp, 'manifest.json')
                with open(manifest_file, 'w', encoding='utf-8') as handle:
                    json.dump(manifest, handle)
                extra = ['--manifest', manifest_file]
            junit_dir = os.path.join(tmp, 'junit')
            os.makedirs(junit_dir)
            for name, xml in (junit or {}).items():
                path = os.path.join(junit_dir, name)
                os.makedirs(os.path.dirname(path), exist_ok=True)
                with open(path, 'w', encoding='utf-8') as handle:
                    handle.write(xml)
            out = os.path.join(tmp, 'out')
            result = subprocess.run(
                [sys.executable, '-I', SCRIPT, '--needs', needs_file, '--required', ','.join(required), '--junit-dir', junit_dir, '--out', out] + extra,
                capture_output=True, text=True, check=False,
            )
            with open(os.path.join(out, 'qa-report.json'), encoding='utf-8') as handle:
                report = json.load(handle)
            with open(os.path.join(out, 'qa-report.md'), encoding='utf-8') as handle:
                markdown = handle.read()
            return result.returncode, report, markdown

    def test_every_gate_green_and_no_failed_test_is_releasable(self):
        status, report, markdown = self.run_report(success('static', 'phpunit'), ['static', 'phpunit'], {'theme.xml': PHPUNIT_OK})
        self.assertEqual(0, status)
        self.assertTrue(report['release_allowed'])
        self.assertEqual(172, report['totals']['tests'])
        self.assertEqual(1, report['totals']['skipped'])
        self.assertIn('release artifact allowed', markdown)

    def test_a_failed_required_job_blocks_the_release(self):
        needs = success('static')
        needs['phpunit'] = {'result': 'failure'}
        status, report, markdown = self.run_report(needs, ['static', 'phpunit'], {'theme.xml': PHPUNIT_OK})
        self.assertEqual(1, status)
        self.assertFalse(report['release_allowed'])
        self.assertIn('NOT RELEASABLE', markdown)

    def test_a_job_that_did_not_run_is_never_reported_as_passed(self):
        status, report, _ = self.run_report(success('static'), ['static', 'phpunit'])
        self.assertEqual(1, status)
        self.assertEqual('not run', next(g for g in report['gates'] if g['job'] == 'phpunit')['result'])

    def test_a_skipped_or_cancelled_required_job_blocks_the_release(self):
        for result in ('skipped', 'cancelled'):
            needs = success('static')
            needs['e2e'] = {'result': result}
            status, report, _ = self.run_report(needs, ['static', 'e2e'])
            self.assertEqual(1, status, result)
            self.assertFalse(report['release_allowed'], result)

    def test_failing_tests_block_the_release_even_when_every_job_is_green(self):
        status, report, _ = self.run_report(success('phpunit'), ['phpunit'], {'plugin.xml': PHPUNIT_FAILING})
        self.assertEqual(1, status)
        self.assertEqual(3, report['totals']['failures_and_errors'])

    def test_an_unreadable_junit_file_blocks_the_release(self):
        status, report, _ = self.run_report(success('phpunit'), ['phpunit'], {'broken.xml': '<testsuites><oops'})
        self.assertEqual(1, status)
        self.assertFalse(report['release_allowed'])

    def test_the_forward_compatibility_canary_is_reported_but_never_gates(self):
        junit = {'theme.xml': PHPUNIT_OK, 'experimental/theme-latest.xml': PHPUNIT_FAILING}
        status, report, markdown = self.run_report(success('phpunit'), ['phpunit'], junit)
        self.assertEqual(0, status)
        self.assertEqual(172, report['totals']['tests'], 'the canary is not part of the gated totals')
        self.assertIn('(informational)', markdown)

    def test_playwright_counters_on_the_testsuites_element_are_used(self):
        status, report, _ = self.run_report(success('e2e'), ['e2e'], {'e2e.xml': PLAYWRIGHT})
        self.assertEqual(0, status)
        self.assertEqual(10, report['totals']['tests'], 'both spec files are counted, not only the first')

    def test_a_failing_browser_test_blocks_the_release(self):
        status, report, _ = self.run_report(success('e2e'), ['e2e'], {'e2e.xml': PLAYWRIGHT_FAILING})
        self.assertEqual(1, status)
        self.assertEqual(1, report['totals']['failures_and_errors'])

    def test_the_artifact_table_lists_the_theme_and_both_plugins(self):
        manifest = {key: {'zip': key + '.zip', 'version': '1.0.0', 'requires_wordpress': '6.3', 'requires_php': '7.4', 'sha256': 'abc'} for key in ('theme', 'plugin', 'vi_en_plugin')}
        status, report, markdown = self.run_report(success('static'), ['static'], manifest=manifest)
        self.assertEqual(0, status)
        self.assertEqual(['theme', 'plugin', 'vi_en_plugin'], list(report['artifacts']))
        for key in manifest:
            self.assertIn(key + '.zip', markdown)

    def test_phpunit_and_browser_results_add_up(self):
        status, report, _ = self.run_report(success('phpunit', 'e2e'), ['phpunit', 'e2e'], {'theme.xml': PHPUNIT_OK, 'e2e.xml': PLAYWRIGHT})
        self.assertEqual(0, status)
        self.assertEqual(182, report['totals']['tests'])


if __name__ == '__main__':
    unittest.main()
