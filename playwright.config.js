import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  globalSetup: './tests/e2e/global-setup.js',
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8010',
    browserName: 'chromium',
    channel: 'msedge',
    headless: true,
    timezoneId: 'Asia/Jakarta',
    viewport: { width: 1440, height: 1000 },
    trace: 'retain-on-failure',
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8010',
    url: 'http://127.0.0.1:8010',
    reuseExistingServer: false,
    timeout: 30_000,
    env: {
      APP_ENV: 'testing',
      SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:8010,127.0.0.1,localhost',
      DB_CONNECTION: 'mysql',
      DB_DATABASE: 'talentora_test',
      DB_URL: '',
      CACHE_STORE: 'array',
      SESSION_DRIVER: 'file',
      QUEUE_CONNECTION: 'sync',
    },
  },
});
