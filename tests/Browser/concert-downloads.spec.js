import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

// Exercise the real download script without media transfers or a streaming player.
test('download-only concert supports pause, resume and reset without duplicate requests', async ({ page }) => {
    await page.clock.install();
    await page.setContent(`
        <a data-concert-download="https://example.test/one">One</a>
        <a data-concert-download="https://example.test/two">Two</a>
        <a data-concert-download="https://example.test/three">Three</a>
        <p id="download-status" role="status"></p>
        <button id="download-all">Start</button>
        <button id="download-pause" disabled>Pause</button>
        <button id="download-reset">Reset</button>
    `);
    await page.evaluate(() => {
        window.downloadRequests = [];
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (link) {
                event.preventDefault();
                window.downloadRequests.push(link.href);
            }
        });
    });
    page.on('dialog', dialog => dialog.accept());
    await page.addScriptTag({ content: readFileSync('resources/js/concert-player.js', 'utf8'), type: 'module' });
    await page.getByRole('button', { name: 'Start', exact: true }).click();
    await expect(page.locator('#download-all')).toBeDisabled();
    // Even programmatic double-dispatch must not start a second timer.
    await page.locator('#download-all').dispatchEvent('click');
    await page.getByRole('button', { name: 'Pause', exact: true }).click();
    await page.clock.fastForward(5000);
    expect(await page.evaluate(() => window.downloadRequests)).toEqual(['https://example.test/one']);
    await page.getByRole('button', { name: 'Resume', exact: true }).click();
    await page.clock.fastForward(1000);
    expect(await page.evaluate(() => window.downloadRequests)).toEqual([
        'https://example.test/one', 'https://example.test/two', 'https://example.test/three',
    ]);
    await expect(page.locator('#download-pause')).toBeDisabled();
    await expect(page.getByRole('status')).toContainText('All downloads have been requested');
    await page.getByRole('button', { name: 'Reset', exact: true }).click();
    await expect(page.locator('#download-all')).toBeEnabled();
    await page.getByRole('button', { name: 'Start', exact: true }).click();
    await page.getByRole('button', { name: 'Reset', exact: true }).click();
    await page.clock.fastForward(5000);
    expect(await page.evaluate(() => window.downloadRequests)).toHaveLength(4);
});
