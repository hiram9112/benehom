const { defineConfig } = require('@playwright/test');
const { testDatabaseEnvironment } = require('./tests/browser/helpers/test-environment');

module.exports = defineConfig({
    testDir: './tests/browser/specs',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: 'http://127.0.0.1:4173',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php -d variables_order=EGPCS -S 127.0.0.1:4173 -t public',
        url: 'http://127.0.0.1:4173/index.php?r=home/index',
        reuseExistingServer: false,
        timeout: 30_000,
        env: {
            ...process.env,
            APP_ENV: 'testing',
            NUMA_ENABLED: 'false',
            NUMA_PUBLIC_ENABLED: 'false',
            NUMA_PUBLIC_HASH_KEY: 'playwright-numa-testing-key',
            NUMA_PROVIDER: 'fake',
            NUMA_EMBEDDING_PROVIDER: 'fake',
            ...testDatabaseEnvironment,
        },
    },
});
