import { readFileSync } from 'node:fs';

export const browserDatabaseName = 'testing';

const localEnvironment = Object.fromEntries(
    readFileSync('.env', 'utf8')
        .split(/\r?\n/)
        .filter(line => line && !line.startsWith('#') && line.includes('='))
        .map(line => {
            const separator = line.indexOf('=');
            const key = line.slice(0, separator);
            const value = line.slice(separator + 1).replace(/^(['"])(.*)\1$/, '$2');

            return [key, value];
        }),
);

export const browserEnvironment = {
    ...process.env,
    APP_ENV: 'local',
    APP_URL: 'http://127.0.0.1:4173',
    CACHE_STORE: 'file',
    DB_CONNECTION: localEnvironment.DB_CONNECTION ?? 'mysql',
    DB_DATABASE: browserDatabaseName,
    DB_HOST: localEnvironment.DB_HOST ?? 'mysql',
    DB_PASSWORD: localEnvironment.DB_PASSWORD ?? '',
    DB_PORT: localEnvironment.DB_PORT ?? '3306',
    DB_USERNAME: localEnvironment.DB_USERNAME ?? 'sail',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    TWO_FACTOR_ENABLED: 'false',
    TWO_FACTOR_ENFORCED: 'false',
};
