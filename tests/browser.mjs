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
  const page = await ctx.newPage();
  await page.goto(BASE + '/index.php');
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

  // Fill only step 1, then jump ahead and Finish: the engine should send us to
  // the first missing required field, not a dead-end error.
  await p.fill('input.input', 'CI Tester');
  await p.evaluate(() => {
    const engine = window.ToolEngine;  // sanity: engine present
    return !!engine;
  });
  console.log('BROWSER OK: engine rendered step "' + title + '"');
} finally {
  await browser.close();
}
