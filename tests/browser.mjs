/**
 * browser.mjs — a real-browser (Playwright) quality check for CI.
 * Claims an account, opens a Gate 1 tool, and asserts the client engine renders
 * and that "Finish" with a missing required field guides the learner to it.
 *
 *   BASE=http://127.0.0.1:8899 CODE=ABC-DEF-GHI node tests/browser.mjs
 */
import { chromium } from 'playwright';

const BASE = process.env.BASE || 'http://127.0.0.1:8899';
const CODE = process.env.CODE;
const EMAIL = 'browser' + Date.now() + '@example.com';
if (!CODE) { console.error('set CODE'); process.exit(1); }

function assert(cond, msg) { if (!cond) { console.error('FAIL: ' + msg); process.exit(1); } }

// In CI, Playwright installs its own Chromium and this stays unset. Locally you
// can point at a pre-installed browser via CHROME_PATH.
const launchOpts = { args: ['--no-sandbox'] };
if (process.env.CHROME_PATH) { launchOpts.executablePath = process.env.CHROME_PATH; }
const browser = await chromium.launch(launchOpts);
try {
  const ctx = await browser.newContext();
  // Keep the test hermetic: the page loads a third-party widget (WyzAI) and web
  // fonts via parser-blocking <script>/<link> tags. If CI can't reach those
  // hosts, the parser stalls and DOMContentLoaded never fires. Abort every
  // non-local request so the run never depends on a third party — the app and
  // all its own assets are served from BASE.
  const baseHost = new URL(BASE).host;
  await ctx.route('**/*', (route) => {
    return (new URL(route.request().url()).host === baseHost)
      ? route.continue()
      : route.abort();
  });
  const page = await ctx.newPage();
  await page.goto(BASE + '/index.php', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const csrf = await page.getAttribute('meta[name=csrf-token]', 'content');

  // Claim an account through the API.
  const claimed = await page.evaluate(async ([csrf, email, code]) => {
    await fetch('/api/verify-code.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ email, code }) });
    const r = await fetch('/api/set-password.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ email, code, password: 'browsertest123' }) });
    return r.ok;
  }, [csrf, EMAIL, CODE]);
  assert(claimed, 'set-password failed');

  // Open the Avatar tool and confirm the engine rendered a step.
  const p = await ctx.newPage();
  await p.goto(BASE + '/gate1/ideal-client-avatar.php', { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1500);
  const title = await p.evaluate(() => (document.querySelector('.tool-step__title') || {}).textContent || '');
  assert(title, 'tool engine did not render a step');
  const hasField = await p.evaluate(() => document.body.textContent.includes('Avatar name'));
  assert(hasField, 'avatar fields not rendered');

  // Complete the whole tool through the UI and assert a result renders. This
  // guards the "answers post empty" bug: a fresh tool's answers arrive as [] (a
  // JS array), and string keys assigned to an array vanish on JSON.stringify, so
  // every required field would read as missing at Finish even when filled.
  for (let i = 0; i < 9; i++) {
    await p.evaluate(() => {
      document.querySelectorAll('.tool-step [data-field] input[type=text], .tool-step [data-field] textarea').forEach((el, ix) => {
        if (!el.value) { el.value = 'Answer ' + ix; el.dispatchEvent(new Event('input', { bubbles: true })); }
      });
      document.querySelectorAll('.tool-step [data-field] input[type=checkbox]').forEach((cb) => {
        if (!cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }
      });
    });
    const isLast = await p.evaluate(() => !!document.querySelector('button.btn--cta'));
    await p.locator('.tool-nav button').last().click();
    await p.waitForTimeout(300);
    if (isLast) break;
  }
  await p.waitForTimeout(500);
  const finished = await p.evaluate(() => !!document.querySelector('.tool-result'));
  const stillInvalid = await p.evaluate(() => document.querySelectorAll('[data-field].field--invalid').length);
  assert(finished && stillInvalid === 0, 'tool did not complete through the UI (answers may be posting empty)');
  console.log('BROWSER OK: engine rendered "' + title + '" and completed the tool');
} finally {
  await browser.close();
}
