import { readFileSync, realpathSync, lstatSync, readdirSync, existsSync } from 'node:fs';
import { resolve, relative, isAbsolute } from 'node:path';

export function validateBuild(directory) {
    const root = resolve(directory);
    function file(path) {
        if (typeof path !== 'string' || !path || path.includes('\\') || path.includes('?') || path.includes('#')) throw new Error(`Invalid asset path: ${path}`);
        const absolute = resolve(root, path);
        const rel = relative(root, absolute);
        if (isAbsolute(path) || rel.startsWith('../') || rel === '..' || !rel || realpathSync(absolute) !== absolute) throw new Error(`Unsafe asset path: ${path}`);
        const stat = lstatSync(absolute);
        if (!stat.isFile() || stat.nlink !== 1) throw new Error(`Missing or unsafe asset: ${path}`);
        return absolute;
    }
    function tree(directory) {
        if (!lstatSync(directory).isDirectory() || lstatSync(directory).isSymbolicLink()) throw new Error('Unsafe build directory');
        for (const entry of readdirSync(directory, { withFileTypes: true })) {
            const path = resolve(directory, entry.name);
            if (entry.isDirectory()) tree(path);
            else file(relative(root, path));
        }
    }
    tree(root);
    const manifest = JSON.parse(readFileSync(file('manifest.json'), 'utf8'));
    if (!manifest || Array.isArray(manifest) || typeof manifest !== 'object') throw new Error('Invalid Vite manifest');
    for (const entry of ['resources/css/app.css', 'resources/js/app.js', 'resources/js/concert-player.js']) {
        if (manifest[entry]?.isEntry !== true) throw new Error(`Missing Vite entry: ${entry}`);
    }
    for (const entry of Object.values(manifest)) {
        file(entry.file);
        if (entry.file !== manifest['resources/js/app.js'].file && lstatSync(file(entry.file)).size === 0) throw new Error(`Empty asset: ${entry.file}`);
        for (const field of ['css', 'assets']) {
            if (entry[field] !== undefined && !Array.isArray(entry[field])) throw new Error(`Invalid ${field}`);
            for (const path of entry[field] ?? []) file(path);
        }
        for (const field of ['imports', 'dynamicImports']) {
            if (entry[field] !== undefined && !Array.isArray(entry[field])) throw new Error(`Invalid ${field}`);
            for (const key of entry[field] ?? []) if (!Object.hasOwn(manifest, key)) throw new Error(`Missing imported chunk: ${key}`);
        }
    }
    if (existsSync(resolve(root, 'fonts-manifest.json'))) {
        const fonts = JSON.parse(readFileSync(file('fonts-manifest.json'), 'utf8'));
        function references(value) {
            if (!value || typeof value !== 'object') return;
            if (Object.hasOwn(value, 'file')) file(value.file);
            for (const nested of Object.values(value)) references(nested);
        }
        references(fonts);
    }
    const player = manifest['resources/js/concert-player.js'].file;
    if (!player.endsWith('.js')) throw new Error('Player entry must be JavaScript');
    return player;
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(new URL(import.meta.url).pathname)) {
    try {
        const player = validateBuild(process.argv[2] ?? 'public/build');
        console.log(process.argv[3] === '--player' ? player : 'Vite manifest and complete build validated.');
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
