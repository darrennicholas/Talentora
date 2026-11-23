import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const stamp = () => `${Date.now()}${Math.floor(Math.random() * 1000)}`;

async function signIn(page) {
  await page.goto('/');
  await page.getByLabel('Email').fill(process.env.E2E_ADMIN_EMAIL || 'admin@talentora.local');
  await page.getByLabel('Password').fill(process.env.E2E_ADMIN_PASSWORD || 'developer');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}

async function confirmSave(page, confirmation = 'Ya, tambahkan') {
  await page.getByRole('button', { name: 'Simpan', exact: true }).click();
  await page.getByRole('button', { name: confirmation, exact: true }).click();
}

async function createPosition(page, id) {
  const position = `E2E Analyst ${id}`;
  await page.getByRole('button', { name: 'Job Positions' }).click();
  await expect(page.getByRole('heading', { name: 'Job Positions' })).toBeVisible();
  await page.getByRole('button', { name: 'Tambah Posisi' }).click();
  await page.getByLabel('Kode').fill(`E2E-${id}`);
  await page.getByLabel('Judul').fill(position);
  await page.getByLabel('Departemen').fill('Engineering');
  await page.getByLabel('Tipe').selectOption('full_time');
  await page.getByLabel('Lokasi').fill('Jakarta');
  await page.getByLabel('Kuota').fill('1');
  await page.getByLabel('Deskripsi').fill('E2E workflow validation role.');
  await page.getByLabel('Persyaratan').fill('Relevant experience and communication skills.');
  await page.getByLabel('Status').selectOption('open');
  await confirmSave(page);
  await expect(page.getByRole('heading', { name: 'Job Positions' })).toBeVisible();
  return position;
}

async function createCandidate(page, id) {
  const name = `E2E Candidate ${id}`;
  await page.getByRole('button', { name: 'Candidates' }).click();
  await page.getByRole('button', { name: 'Tambah Kandidat' }).click();
  await page.getByLabel('Nama lengkap').fill(name);
  await page.getByLabel('Email').fill(`candidate.${id}@example.test`);
  await page.getByLabel('Telepon').fill('+62 812 0000 0000');
  await confirmSave(page);
  await expect(page.getByRole('heading', { name: 'Candidates' })).toBeVisible();
  return name;
}

async function createApplication(page, candidate, position) {
  await page.getByRole('button', { name: 'Applications' }).click();
  await page.getByRole('button', { name: 'Daftarkan Lamaran' }).click();
  await page.getByPlaceholder('Cari candidates…').fill(candidate);
  await page.getByPlaceholder('Cari jobs…').fill(position);
  const candidateSelect = page.locator('select[name="candidate_id"]');
  const positionSelect = page.locator('select[name="job_position_id"]');
  await expect(candidateSelect).toContainText(candidate);
  await expect(positionSelect).toContainText(position);
  await candidateSelect.selectOption({ label: candidate });
  await positionSelect.selectOption({ label: position });
  await page.getByLabel('Sumber').selectOption('manual');
  await confirmSave(page);
  await expect(page.getByRole('heading', { name: 'Applications' })).toBeVisible();
}

function jakartaDateTime(offsetMinutes) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(Date.now() + offsetMinutes * 60_000)).map(({ type, value }) => [type, value]));
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

function markInterviewEnded(applicationId) {
  execFileSync('php', ['tests/e2e/mark-interview-ended.php', String(applicationId)], {
    stdio: 'inherit',
    env: {
      ...process.env,
      APP_ENV: 'testing',
      DB_CONNECTION: 'mysql',
      DB_DATABASE: 'talentora_test',
      DB_URL: '',
    },
  });
}

test('E2E-43 creates an application in the UI and verifies it in Applied pipeline', async ({ page }) => {
  await signIn(page);
  const id = stamp();
  const position = await createPosition(page, id);
  const candidate = await createCandidate(page, id);
  await createApplication(page, candidate, position);

  await page.getByRole('button', { name: 'Hiring Pipeline' }).click();
  await expect(page.locator('.pipeline-step-applied')).toContainText(candidate);
  await expect(page.locator('.pipeline-step-applied')).toContainText(position);
});

test('E2E-44 completes an interview from the UI and advances the same application to Offered', async ({ page }) => {
  await signIn(page);
  const id = stamp();
  const position = await createPosition(page, id);
  const candidate = await createCandidate(page, id);
  await createApplication(page, candidate, position);

  await page.getByRole('button', { name: 'Hiring Pipeline' }).click();
  const applied = page.locator('.pipeline-step-applied');
  const appliedCard = applied.locator('.dealcard').filter({ hasText: candidate });
  await expect(appliedCard).toBeVisible();
  await appliedCard.getByRole('button', { name: 'Lanjut ke Screening' }).click();
  await page.getByRole('button', { name: 'Ya, pindahkan ke Screening' }).click();
  const screening = page.locator('.pipeline-step-screening');
  const screeningCard = screening.locator('.dealcard').filter({ hasText: candidate });
  await expect(screeningCard).toBeVisible();
  await screeningCard.getByRole('button', { name: 'Jadwalkan interview' }).click();

  const applicationSelect = page.locator('select[name="application_id"]');
  await expect(applicationSelect).toContainText(candidate);
  const applicationId = await applicationSelect.inputValue();
  const interviewer = page.locator('select[name="interviewer_id"]');
  await expect(interviewer.locator('option')).toHaveCount(2);
  await interviewer.selectOption({ index: 1 });
  await page.locator('input[name="start_at"]').fill(jakartaDateTime(5));
  await page.locator('input[name="end_at"]').fill(jakartaDateTime(6));
  await page.getByLabel('Link meeting').fill('https://meet.example.test/e2e-interview');
  await confirmSave(page);

  await expect(page.getByRole('heading', { name: 'Interview Schedule' })).toBeVisible();
  markInterviewEnded(applicationId);
  await page.getByRole('button', { name: 'Muat ulang' }).click();
  const interviewRow = page.getByRole('row').filter({ hasText: candidate });
  await expect(interviewRow).toBeVisible();
  await interviewRow.getByRole('button', { name: 'Evaluasi interview' }).click();
  await page.getByLabel('Feedback').fill('Clear communication and strong relevant experience.');
  await page.getByRole('button', { name: 'Simpan evaluasi' }).click();
  await page.getByRole('button', { name: 'Ya, simpan evaluasi' }).click();

  await page.getByRole('button', { name: 'Hiring Pipeline' }).click();
  const interviewStage = page.locator('.pipeline-step-interview');
  const interviewCard = interviewStage.locator('.dealcard').filter({ hasText: candidate });
  await expect(interviewCard).toBeVisible();
  await interviewCard.getByRole('button', { name: 'Kirim ke Offered' }).click();
  await page.getByRole('button', { name: 'Ya, pindahkan ke Offered' }).click();
  await expect(page.locator('.pipeline-step-offered').locator('.dealcard').filter({ hasText: candidate })).toBeVisible();
});
