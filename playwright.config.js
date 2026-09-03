import { defineConfig, devices } from '@playwright/test';

import { browserEnvironment } from './tests/Browser/environment.js';

export default defineConfig({
    testDir: './tests/Browser',
    globalSetup: './tests/Browser/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 2 : 0,
    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
    use: {
        baseURL: 'http://127.0.0.1:4173',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium-desktop',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'chromium-mobile',
            use: { ...devices['Pixel 7'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=4173',
        env: browserEnvironment,
        reuseExistingServer: false,
        timeout: 120_000,
        url: 'http://127.0.0.1:4173/up',
    },
});
