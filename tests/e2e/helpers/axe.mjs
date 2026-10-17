/**
 * axe-core wrapper: the rule set the owner asked for, reporting that makes a failure actionable, and the
 * only place where an exclusion may live.
 *
 * Exclusions are NOT used to get green. An entry in KNOWN_FALSE_POSITIVES must name the rule, a narrow
 * selector and the evidence that the finding is wrong (not just inconvenient). It is empty on purpose.
 */
import AxeBuilder from '@axe-core/playwright';

/** WCAG 2.0/2.1/2.2 A and AA plus axe's best-practice rules. */
export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** @type {{rule: string, selector: string, reason: string}[]} */
export const KNOWN_FALSE_POSITIVES = [];

/**
 * @param {import('@playwright/test').Page} page
 * @param {{include?: string, exclude?: string[]}} [options]
 */
export async function runAxe(page, { include, exclude = [] } = {}) {
  let builder = new AxeBuilder({ page }).withTags(AXE_TAGS);
  if (include) {
    builder = builder.include(include);
  }
  for (const selector of exclude) {
    builder = builder.exclude(selector);
  }
  const results = await builder.analyze();

  const isKnownFalsePositive = (violation, node) =>
    KNOWN_FALSE_POSITIVES.some(
      (entry) => entry.rule === violation.id && node.target.some((target) => String(target).includes(entry.selector))
    );

  const violations = results.violations
    .map((violation) => ({ ...violation, nodes: violation.nodes.filter((node) => !isKnownFalsePositive(violation, node)) }))
    .filter((violation) => violation.nodes.length > 0);

  return { violations, incomplete: results.incomplete, passes: results.passes.length, url: results.url };
}

/** Readable multi-line description of violations for an assertion message. */
export function formatViolations(violations) {
  if (violations.length === 0) {
    return 'no violations';
  }
  return violations
    .map((violation) => {
      const nodes = violation.nodes
        .slice(0, 5)
        .map((node) => `      - ${node.target.join(' ')}\n        ${String(node.failureSummary || '').split('\n').slice(0, 3).join(' | ')}`)
        .join('\n');
      const more = violation.nodes.length > 5 ? `\n      ... and ${violation.nodes.length - 5} more` : '';
      return `  [${violation.impact}] ${violation.id}: ${violation.help} (${violation.nodes.length} node(s))\n    ${violation.helpUrl}\n${nodes}${more}`;
    })
    .join('\n');
}

/** Compact record of "needs review" results, attached to the report for a human to judge. */
export function summarizeIncomplete(incomplete) {
  return incomplete.map((item) => ({
    rule: item.id,
    impact: item.impact,
    nodes: item.nodes.length,
    sample: item.nodes.slice(0, 3).map((node) => ({ target: node.target.join(' '), message: node.any?.[0]?.message || node.all?.[0]?.message || '' })),
  }));
}
