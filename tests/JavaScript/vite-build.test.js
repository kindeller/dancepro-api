import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { validateBuild } from '../../scripts/validate-vite-build.mjs';

function fixture(run) {
    const root = mkdtempSync(join(tmpdir(), 'dancepro-vite-test-'));
    const manifest = Object.fromEntries(['resources/css/app.css', 'resources/js/app.js', 'resources/js/concert-player.js'].map((entry, index) => [entry, { isEntry: true, file: `assets/${index}.js` }]));
    mkdirSync(join(root, 'assets'));
    for (let i = 0; i < 3; i++) writeFileSync(join(root, `assets/${i}.js`), 'fixture');
    const save = () => writeFileSync(join(root, 'manifest.json'), JSON.stringify(manifest));
    save();
    try { run(root, manifest, save); } finally { rmSync(root, { recursive: true, force: true }); }
}
test('complete manifest validates and returns player path', () => fixture(root => assert.equal(validateBuild(root), 'assets/2.js')));
test('missing player entry fails', () => fixture((root, manifest, save) => {
    delete manifest['resources/js/concert-player.js']; save(); assert.throws(() => validateBuild(root));
}));
test('missing imported asset fails', () => fixture((root, manifest, save) => {
    manifest['resources/js/app.js'].css = ['assets/missing.css']; save(); assert.throws(() => validateBuild(root));
}));
test('missing imported chunk fails', () => fixture((root, manifest, save) => {
    manifest['resources/js/app.js'].imports = ['missing']; save(); assert.throws(() => validateBuild(root));
}));
test('traversal and symlinks fail', () => fixture((root, manifest, save) => {
    manifest['resources/js/app.js'].file = '../outside'; save(); assert.throws(() => validateBuild(root));
    symlinkSync('/etc/passwd', join(root, 'assets/link')); assert.throws(() => validateBuild(root));
}));

test('empty placeholder app entry is valid', () => fixture(root => {
    writeFileSync(join(root, 'assets/1.js'), ''); assert.equal(validateBuild(root), 'assets/2.js');
}));
