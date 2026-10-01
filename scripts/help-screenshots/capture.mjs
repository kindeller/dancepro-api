// Run after render.php, with the local site running. No forms are submitted.
import { chromium } from '@playwright/test';
import { readFileSync, mkdirSync } from 'node:fs';
const base = process.env.HELP_BASE_URL || 'http://localhost';
if (!['localhost', '127.0.0.1'].includes(new URL(base).hostname)) throw new Error('Use a local site for Help captures.');
const browser = await chromium.launch({ ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}), headless: true });
const page = await browser.newPage({ viewport: {width: 900, height: 1100}, deviceScaleFactor: 1, javaScriptEnabled: false });
await page.route('**/*', async route => {
 const request = route.request();
 if (request.method() !== 'GET' || new URL(request.url()).origin !== base) return route.abort();
 const match = new URL(request.url()).pathname.match(/^\/help-capture\/([a-z-]+)$/);
 if (match) return route.fulfill({contentType: 'text/html', body: readFileSync(`tmp/help-screenshots/${match[1]}.html`, 'utf8')});
 return route.continue();
});
mkdirSync('public/images/help', {recursive:true});
const shots = {};
async function capture(name, locator) {
 await locator.scrollIntoViewIfNeeded();
 const png = await locator.screenshot({path:`public/images/help/${name}.png`});
 shots[name] = {width:png.readUInt32BE(16), height:png.readUInt32BE(20)};
}
await page.goto(`${base}/customer/register`);
await capture('create-account', page.locator('form.card'));
await page.goto(`${base}/help-capture/media`);
await capture('media-tabs', page.locator('.section-tabs'));
await capture('add-media', page.locator('section[aria-labelledby="add-media-heading"]'));
await page.goto(`${base}/help-capture/purchases`);
await capture('purchases-filter', page.locator('.media-section-head'));
for (const tab of ['studios','dancers','preferences']) {
 await page.goto(`${base}/help-capture/profile-${tab}`);
 await capture(`profile-${tab}`, page.locator(`#${tab}`));
}
await page.goto(`${base}/help-capture/message`);
await capture('new-message', page.locator('.card').filter({has: page.locator('.message-form')}));
await page.locator('.message-advanced summary').click();
await capture('message-options', page.locator('.message-advanced'));
await page.goto(`${base}/help-capture/family`);
await capture('family-invite', page.locator('form.family-card'));
await page.setViewportSize({width:600,height:1100});
await page.goto(`${base}/help-capture/concert`);
await capture('download-manager', page.locator('article.utility').filter({has:page.locator('#download-all')}));
await page.setViewportSize({width:800,height:1100});
await page.goto(`${base}/help-capture/review`);
await capture('review-corrections', page.locator('form').filter({has:page.locator('textarea[name="feedback"]')}));
await capture('review-release', page.locator('form').filter({has:page.locator('input[name="confirm_release"]')}));
console.log(JSON.stringify(shots,null,2));
await browser.close();
