/**
 * Colour contrast audit that goes where axe cannot.
 *
 * axe-core cannot decide the contrast of text over a gradient, over an overlapping element or over an
 * image, and reports those as "needs review". This audit decides them:
 *
 *  1. every visible text element whose background resolves to flat colours is checked from the computed
 *     colours (WCAG relative luminance, 4.5:1, or 3:1 for large text: >= 24px, or >= 18.66px bold);
 *  2. every other element is measured on the rendered pixels: its text is made transparent, the element
 *     is screenshotted, and the WORST pixel behind the text (the one closest in luminance to the text
 *     colour) decides.
 *
 * Text that is clipped to nothing, hidden, or fully transparent is skipped, as WCAG does.
 */

const MIN_NORMAL = 4.5;
const MIN_LARGE = 3;

/** Runs in the page: returns { checked, failures, unresolved } for flat-colour backgrounds. */
function collectContrast({ rootSelector, minNormal, minLarge }) {
  const root = document.querySelector(rootSelector) || document.body;

  const parse = (css) => {
    const match = css.match(/rgba?\(([^)]+)\)/);
    if (!match) {
      return null;
    }
    const parts = match[1].split(/[ ,/]+/).filter(Boolean).map(Number);
    return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
  };
  const lum = ({ r, g, b }) => {
    const channel = (v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
  };
  const ratio = (a, b) => {
    const [hi, lo] = lum(a) >= lum(b) ? [a, b] : [b, a];
    return (lum(hi) + 0.05) / (lum(lo) + 0.05);
  };
  const over = (top, under) => ({
    r: top.r * top.a + under.r * (1 - top.a),
    g: top.g * top.a + under.g * (1 - top.a),
    b: top.b * top.a + under.b * (1 - top.a),
    a: 1,
  });
  const describe = (el) => {
    const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
    return `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${cls ? '.' + cls : ''}`;
  };

  /** Effective opacity from the element up to the root (opacity multiplies down the tree). */
  const effectiveOpacity = (el) => {
    let value = 1;
    for (let node = el; node && node.nodeType === 1; node = node.parentElement) {
      value *= parseFloat(getComputedStyle(node).opacity);
    }
    return value;
  };

  /** Flat background colour behind an element, or null when a gradient/image is involved. */
  const flatBackground = (el) => {
    const stack = [];
    for (let node = el; node && node.nodeType === 1; node = node.parentElement) {
      const cs = getComputedStyle(node);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        return null;
      }
      const bg = parse(cs.backgroundColor);
      if (bg && bg.a > 0) {
        stack.push(bg);
        if (bg.a >= 0.99) {
          break;
        }
      }
    }
    let base = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = stack.length - 1; i >= 0; i -= 1) {
      base = over(stack[i], base);
    }
    return base;
  };

  const failures = [];
  const unresolved = [];
  let checked = 0;
  let counter = 0;

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  while (walker.nextNode()) {
    const text = walker.currentNode;
    if (!text.textContent.trim()) {
      continue;
    }
    const el = text.parentElement;
    if (!el || seen.has(el) || el.closest('script,style,noscript,svg,[hidden]')) {
      continue;
    }
    seen.add(el);
    const cs = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    if (cs.visibility !== 'visible' || cs.display === 'none' || rect.width < 1 || rect.height < 1 || effectiveOpacity(el) < 0.05) {
      continue;
    }
    // Skip text that is clipped away (screen-reader-only text, clip-path inset, 1px boxes).
    if ((rect.width <= 2 && rect.height <= 2) || cs.clipPath === 'inset(50%)') {
      continue;
    }
    const fg = parse(cs.color);
    if (!fg || fg.a === 0) {
      continue;
    }
    const size = parseFloat(cs.fontSize);
    const weight = parseInt(cs.fontWeight, 10) || 400;
    const large = size >= 24 || (size >= 18.66 && weight >= 700);
    const required = large ? minLarge : minNormal;
    const bg = flatBackground(el);
    checked += 1;
    if (!bg) {
      el.setAttribute('data-e2e-contrast', String(counter));
      unresolved.push({ index: counter, element: describe(el), text: text.textContent.trim().slice(0, 40), required, color: cs.color, size, weight });
      counter += 1;
      continue;
    }
    const value = ratio(over(fg, bg), bg);
    if (value < required) {
      failures.push({
        element: describe(el),
        text: text.textContent.trim().slice(0, 40),
        ratio: Number(value.toFixed(2)),
        required,
        color: cs.color,
        background: `rgb(${Math.round(bg.r)}, ${Math.round(bg.g)}, ${Math.round(bg.b)})`,
        size,
      });
    }
  }
  return { checked, failures, unresolved };
}

/** Runs in the page: worst contrast of a text colour against the pixels of a PNG (data given as base64). */
async function worstContrastOnPixels({ base64, color, required }) {
  const parse = (css) => {
    const match = css.match(/rgba?\(([^)]+)\)/);
    const parts = match[1].split(/[ ,/]+/).filter(Boolean).map(Number);
    return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
  };
  const channel = (v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  const lum = (r, g, b) => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);

  const image = new Image();
  image.src = `data:image/png;base64,${base64}`;
  await image.decode();
  const canvas = document.createElement('canvas');
  canvas.width = image.width;
  canvas.height = image.height;
  const context = canvas.getContext('2d', { willReadFrequently: true });
  context.drawImage(image, 0, 0);
  const { data } = context.getImageData(0, 0, canvas.width, canvas.height);

  const fg = parse(color);
  let worst = Infinity;
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    // Text colour composited over this pixel when it is semi-transparent.
    const fr = fg.r * fg.a + r * (1 - fg.a);
    const fgG = fg.g * fg.a + g * (1 - fg.a);
    const fb = fg.b * fg.a + b * (1 - fg.a);
    const a = lum(fr, fgG, fb);
    const bgL = lum(r, g, b);
    const value = (Math.max(a, bgL) + 0.05) / (Math.min(a, bgL) + 0.05);
    if (value < worst) {
      worst = value;
    }
  }
  return { worst: Number(worst.toFixed(2)), pixels: data.length / 4, required };
}

/**
 * Audit the contrast of every visible text element under `root`.
 * @returns {Promise<{checked:number, failures:object[], measuredOnPixels:number}>}
 */
export async function auditContrast(page, { root = 'body' } = {}) {
  const result = await page.evaluate(collectContrast, { rootSelector: root, minNormal: MIN_NORMAL, minLarge: MIN_LARGE });
  const failures = [...result.failures];

  // Elements whose background is a gradient/overlap: measure the pixels that are really painted.
  let measured = 0;
  for (const item of result.unresolved) {
    const locator = page.locator(`[data-e2e-contrast="${item.index}"]`);
    if ((await locator.count()) !== 1) {
      continue;
    }
    await locator.scrollIntoViewIfNeeded();
    await locator.evaluate((el) => {
      el.dataset.e2eInline = el.getAttribute('style') || '';
      el.querySelectorAll('*').forEach((child) => {
        child.dataset.e2eInline = child.getAttribute('style') || '';
        child.style.setProperty('color', 'transparent', 'important');
        child.style.setProperty('text-shadow', 'none', 'important');
      });
      el.style.setProperty('color', 'transparent', 'important');
      el.style.setProperty('text-shadow', 'none', 'important');
    });
    const png = await locator.screenshot({ animations: 'disabled', caret: 'hide' });
    await locator.evaluate((el) => {
      const restore = (node) => {
        const original = node.dataset.e2eInline;
        if (original) {
          node.setAttribute('style', original);
        } else {
          node.removeAttribute('style');
        }
        delete node.dataset.e2eInline;
      };
      el.querySelectorAll('*').forEach(restore);
      restore(el);
    });
    const { worst } = await page.evaluate(worstContrastOnPixels, { base64: png.toString('base64'), color: item.color, required: item.required });
    measured += 1;
    if (worst < item.required) {
      failures.push({ element: item.element, text: item.text, ratio: worst, required: item.required, color: item.color, background: 'measured on rendered pixels', size: item.size });
    }
  }
  await page.evaluate(() => document.querySelectorAll('[data-e2e-contrast]').forEach((el) => el.removeAttribute('data-e2e-contrast')));

  return { checked: result.checked, failures, measuredOnPixels: measured };
}

export function formatContrastFailures(failures) {
  return failures
    .slice(0, 15)
    .map((f) => `  ${f.element} "${f.text}" ratio ${f.ratio} < ${f.required} (colour ${f.color} on ${f.background}, ${f.size}px)`)
    .join('\n');
}
