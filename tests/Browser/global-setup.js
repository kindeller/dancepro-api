import { execFileSync } from 'node:child_process';

import { browserEnvironment } from './environment.js';

export default function globalSetup() {
    const artisan = (...arguments_) => execFileSync('php', ['artisan', ...arguments_], {
        cwd: process.cwd(),
        env: browserEnvironment,
        stdio: 'inherit',
    });

    artisan('migrate:fresh', '--force');
    artisan('db:seed', '--class=Database\\Seeders\\LocalDevelopmentSeeder', '--force');
}
