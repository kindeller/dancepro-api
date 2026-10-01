import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('family downloads preserve request IDs for retries and disable after the third allowance', async ({ page }) => {
    await page.route('http://localhost/family-fixture', route => route.fulfill({ contentType: 'text/html', body: `
        <div data-family-viewer data-csrf="test-csrf"><article data-family-video>
        <p data-family-status role="status"></p><p data-download-count>0 of 3</p>
        <form data-family-download action="http://localhost/request-download"><input name="request_uuid" value="original-request"><button>Download video</button></form>
        </article></div>` }));
    const requests = [];
    let successful = 0;
    await page.route('http://localhost/request-download', route => {
        expect(route.request().headers()['x-csrf-token']).toBe('test-csrf');
        requests.push(route.request().postDataJSON().request_uuid);
        if (requests.length === 1) return route.fulfill({ status: 503, json: { message: 'Please retry.' } });
        successful++;
        return route.fulfill({ json: { url: 'http://localhost/ticket', remaining: 3 - successful } });
    });
    await page.goto('http://localhost/family-fixture');
    await page.evaluate(() => document.addEventListener('click', event => {
        if (event.target.closest('a')) event.preventDefault();
    }));
    await page.addScriptTag({ content: readFileSync('resources/js/concert-family.js', 'utf8'), type: 'module' });
    const button = page.getByRole('button', { name: 'Download video' });
    await button.click();
    await expect(page.getByRole('status')).toHaveText('Please retry.');
    await button.click();
    await expect(page.locator('[data-download-count]')).toContainText('1 of 3');
    expect(requests[0]).toBe(requests[1]);
    await button.click();
    await expect(page.locator('[data-download-count]')).toContainText('2 of 3');
    expect(requests[2]).not.toBe(requests[1]);
    await button.click();
    await expect(page.locator('[data-download-count]')).toContainText('3 of 3');
    await expect(button).toBeDisabled();
    await expect(page.getByRole('status')).toContainText('does not confirm the file has finished');
});
