import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

function boot(filename, dataset, accepted = true) {
    const requests = [];
    const warnings = [];
    const handlers = {};
    const collection = { dataset: { collection: 'collection-uuid' } };
    const button = {
        dataset, disabled: false,
        closest: selector => selector === '[data-collection]' ? collection : null,
        matches: () => false,
        addEventListener: (name, handler) => { handlers[name] = handler; },
    };
    const root = {
        contains: () => true,
        addEventListener: (name, handler) => { handlers[name] = () => handler({ target: { closest: () => button } }); },
    };
    const status = {};
    let reloads = 0;
    const config = { base: '/admin/media-api', csrf: 'csrf-value' };
    runInNewContext(readFileSync(new URL(`../../public/js/${filename}`, import.meta.url), 'utf8'), {
        document: {
            querySelector: selector => {
                if (selector.endsWith('-config')) return { textContent: JSON.stringify(config) };
                if (selector === '#concert-media-management') return root;
                if (selector === '#new-media-collection') return null;
                return status;
            },
            querySelectorAll: selector => selector === '[data-collection-status]' ? [button] : [],
        },
        confirm: warning => { warnings.push(warning); return accepted; },
        crypto: { randomUUID: () => 'request-uuid' },
        fetch: async (url, options) => {
            requests.push({ url, method: options.method, data: JSON.parse(options.body) });
            return { ok: true, json: async () => ({ data: {} }) };
        },
        location: { reload: () => { reloads++; } },
    });
    return { click: () => handlers.click(), requests, warnings, button, reloads: () => reloads };
}

for (const filename of ['admin-concert-media-management.js', 'admin-concert-uploader.js']) {
    test(`${filename}: accepting the warning shows all verified videos and publishes in one request`, async () => {
        const page = boot(filename, { collectionStatus: 'published', showAllVerified: '1', publishWarning: 'Show hidden videos?' });
        await page.click();
        assert.deepEqual(page.warnings, ['Show hidden videos?']);
        assert.deepEqual(page.requests, [{
            url: '/admin/media-api/collections/collection-uuid', method: 'PATCH',
            data: { status: 'published', show_all_verified: true },
        }]);
        assert.equal(page.reloads(), 1);
    });

    test(`${filename}: cancelling leaves the collection and visibility unchanged`, async () => {
        const page = boot(filename, { collectionStatus: 'published', showAllVerified: '1', publishWarning: 'Show hidden videos?' }, false);
        await page.click();
        assert.equal(page.requests.length, 0);
        assert.equal(page.button.disabled, false);
        assert.equal(page.reloads(), 0);
    });

    test(`${filename}: unpublishing preserves existing visibility`, async () => {
        const page = boot(filename, { collectionStatus: 'draft' });
        await page.click();
        assert.equal(page.warnings.length, 0);
        assert.deepEqual(page.requests[0].data, { status: 'draft' });
    });
}
