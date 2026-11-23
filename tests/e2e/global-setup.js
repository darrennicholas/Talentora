import { execFileSync } from 'node:child_process';

export default function globalSetup() {
  const email = process.env.E2E_ADMIN_EMAIL || 'admin@talentora.local';
  const password = process.env.E2E_ADMIN_PASSWORD || 'developer';

  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
    stdio: 'inherit',
    env: {
      ...process.env,
      APP_ENV: 'testing',
      DB_CONNECTION: 'mysql',
      DB_DATABASE: 'talentora_test',
      DB_URL: '',
      ADMIN_EMAIL: email,
      ADMIN_PASSWORD: password,
      ADMIN_NAME: process.env.E2E_ADMIN_NAME || 'Talentora E2E Admin',
    },
  });
}
