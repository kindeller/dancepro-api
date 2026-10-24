import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../resources/js/concert-player.js', import.meta.url), 'utf8')
    .replace("import Hls from 'hls.js';", '');
const mp4 = { format: 'progressive', url: 'https://media.example.test/fallback.mp4?Signature=test' };
const adaptive = { format: 'hls', url: 'https://media.example.test/master.m3u8', fallback_url: '/concerts/example/media/example?fallback=1' };

async function boot(source, { nativeHls = false, supported = true } = {}) {
    const nodes = new Map();
    function element() {
        return {
            crossOrigin: null, events: {}, dataset: {}, classList: { toggle() {} },
            addEventListener(name, handler) { this.events[name] = handler; },
            replaceChildren() {}, add() {}, load() {}, play: async () => {},
            removeAttribute(name) {
                if (name === 'crossorigin') this.crossOrigin = null;
            }, canPlayType: () => nativeHls ? 'probably' : '',
        };
    }
    const player = element();
    const assignments = [];
    Object.defineProperty(player, 'src', {
        set(url) { assignments.push({ url, mode: this.crossOrigin }); },
    });
    nodes.set('#concert-player', player);
    const item = element();
    item.dataset = { playback: '/playback', title: 'Concert', download: '/download' };
    const instances = [];
    class Hls {
        static Events = { MANIFEST_PARSED: 'manifest', ERROR: 'error' };
        static isSupported() { return supported; }
        constructor(config) { this.config = config; this.events = {}; instances.push(this); }
        loadSource(url) { this.url = url; }
        attachMedia() {}
        on(name, handler) { this.events[name] = handler; }
        destroy() { this.destroyed = true; }
    }
    const requests = [];
    let currentSource = source;
    runInNewContext(script, {
        Hls,
        document: {
            querySelector(selector) {
                if (!nodes.has(selector)) nodes.set(selector, element());
                return nodes.get(selector);
            },
            querySelectorAll: () => [item],
        },
        fetch: async (url, options) => {
            requests.push({ url, options });
            return { ok: true, json: async () => ({ data: currentSource }) };
        },
        Option: class {}, setTimeout, clearTimeout,
    });
    await new Promise(setImmediate);
    return {
        player, assignments, instances, requests,
        async select(nextSource) {
            currentSource = nextSource;
            await item.events.click();
        },
    };
}

test('signed MP4 omits CORS mode before source assignment and retains Laravel session fetch', async () => {
    const { assignments, requests } = await boot(mp4);
    assert.deepEqual(assignments, [{ url: mp4.url, mode: null }]);
    assert.equal(requests[0].options.credentials, 'same-origin');
});

test('native HLS retains credentialed video requests', async () => {
    const { assignments } = await boot(adaptive, { nativeHls: true });
    assert.deepEqual(assignments, [{ url: adaptive.url, mode: 'use-credentials' }]);
});

test('hls.js retains cookie requests and switches fatal errors to MP4 without CORS mode', async () => {
    const { player, assignments, instances } = await boot(adaptive);
    assert.equal(player.crossOrigin, 'use-credentials');
    assert.equal(instances[0].url, adaptive.url);
    const xhr = {};
    instances[0].config.xhrSetup(xhr);
    assert.equal(xhr.withCredentials, true);
    instances[0].events.error(null, { fatal: true });
    assert.equal(instances[0].destroyed, true);
    assert.deepEqual(assignments, [{ url: adaptive.fallback_url, mode: null }]);
});

test('unsupported HLS uses MP4 without CORS mode fallback', async () => {
    const { assignments } = await boot(adaptive, { supported: false });
    assert.deepEqual(assignments, [{ url: adaptive.fallback_url, mode: null }]);
});

test('native HLS video error uses MP4 without CORS mode fallback', async () => {
    const { player, assignments } = await boot(adaptive, { nativeHls: true });
    player.events.error();
    assert.deepEqual(assignments.at(-1), { url: adaptive.fallback_url, mode: null });
});

test('switching HLS to MP4 resets credentials before assigning the next source', async () => {
    const app = await boot(adaptive);
    await app.select(mp4);
    assert.equal(app.instances[0].destroyed, true);
    assert.deepEqual(app.assignments.at(-1), { url: mp4.url, mode: null });
});

test('switching MP4 to native HLS restores credentialed requests', async () => {
    const app = await boot(mp4, { nativeHls: true });
    await app.select(adaptive);
    assert.deepEqual(app.assignments.at(-1), { url: adaptive.url, mode: 'use-credentials' });
});
