// Phase 16 — headless-Chromium certification of key HaHireAI screens.
//
// Usage:
//   BASE_URL=http://127.0.0.1:8062 OWNER_EMAIL=... OWNER_PASSWORD=... \
//   SHOT_DIR=/path/to/shots node tests/browser/certify.mjs
//
// Uses the pre-installed Chromium (no browser download). See docs/RELEASE_CERTIFICATION.md.

import { chromium } from 'playwright-core';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8062';
const EMAIL = process.env.OWNER_EMAIL;
const PASSWORD = process.env.OWNER_PASSWORD || 'password123';
const SHOTS = process.env.SHOT_DIR || '/tmp/shots';
const EXE = process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

const log = (m) => console.log(`[browser-cert] ${m}`);
let failures = 0;

const browser = await chromium.launch({
  executablePath: EXE,
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  // 1) Login screen renders.
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: `${SHOTS}/01-login.png`, fullPage: true });
  log('captured login');

  // 2) Authenticate as the System Owner.
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('button[type="submit"]'),
  ]);
  log(`logged in as ${EMAIL}`);

  // 3) Platform Overview (System Owner context — System/Companies/Users/Diagnostics sidebar).
  await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: `${SHOTS}/02-platform-overview.png`, fullPage: true });
  const overviewOk = await page.locator('text=Platform Overview').count();
  log(`platform overview heading present: ${overviewOk > 0}`);
  if (!overviewOk) failures++;

  // 4) Diagnostics (health probes, errors, alerts, backups).
  await page.goto(`${BASE}/admin/diagnostics`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: `${SHOTS}/03-diagnostics.png`, fullPage: true });
  const diagOk = await page.locator('text=Health probes').count();
  log(`diagnostics page present: ${diagOk > 0}`);
  if (!diagOk) failures++;

  // 5) Sidebar is the platform context (no per-role sidebar).
  const sidebarLabels = await page.locator('aside nav a').allInnerTexts();
  log(`sidebar: ${sidebarLabels.map((s) => s.trim()).filter(Boolean).join(' · ')}`);
} catch (e) {
  console.error(`[browser-cert] ERROR: ${e.message}`);
  failures++;
} finally {
  await browser.close();
}

log(failures === 0 ? 'ALL BROWSER CHECKS PASSED' : `${failures} browser check(s) FAILED`);
process.exit(failures === 0 ? 0 : 1);
