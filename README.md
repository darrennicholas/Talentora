# Talentora

Talentora adalah aplikasi **Applicant Tracking System (ATS)** untuk mengelola lowongan, kandidat, lamaran, dan proses rekrutmen dalam satu tempat.

**Teknologi:** Laravel 13 (REST API), React 19, Vite, MySQL, dan Laravel Sanctum.

## Modul

1. **Dashboard** — Ringkasan kandidat, lowongan, lamaran, interview, dan metrik rekrutmen berdasarkan rentang tanggal.
2. **Job Positions** — Membuat dan mengelola lowongan, termasuk departemen, lokasi, kuota, tipe kerja, dan status.
3. **Candidates** — Menyimpan profil kandidat, informasi kontak, keahlian, dan CV.
4. **Applications** — Menghubungkan kandidat dengan lowongan serta mencatat sumber, tahap, dan riwayat lamaran.
5. **Hiring Pipeline** — Memantau dan memindahkan lamaran melalui tahap Applied, Screening, Interview, Offered, dan Hired; termasuk Rejected.
6. **Interview Schedule** — Menjadwalkan interview, melihat kalender, dan menyimpan hasil evaluasi.
7. **Reports & Analytics** — Melihat grafik distribusi tahap, tren lamaran, performa posisi, dan KPI rekrutmen.
8. **Settings** — Mengelola profil admin, akun internal, dan pengaturan aplikasi.

## Setup lokal

Prasyarat: PHP 8.3+, Composer, Node.js/npm, dan MySQL.

1. Clone repository dan masuk ke folder proyek:

   ```powershell
   git clone https://github.com/darrennicholas/Talentora.git
   cd Talentora
   ```

2. Pasang dependensi dan siapkan konfigurasi:

   ```powershell
   composer install
   npm ci
   Copy-Item .env.example .env
   ```

3. Buat database MySQL bernama `talentora`. Atur `DB_*`, `APP_URL=http://127.0.0.1:8000`, `ADMIN_EMAIL`, dan `ADMIN_PASSWORD` pada `.env`.

4. Buat application key, migrasikan database, dan isi data awal:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   ```

5. Untuk data contoh, setelah akun Admin aktif dibuat, jalankan:

   ```powershell
   php artisan db:seed --class="Database\Seeders\DemoDataSeeder"
   ```

6. Jalankan Laravel dan Vite pada dua terminal:

   ```powershell
   php artisan serve
   ```

   ```powershell
   npm run dev
   ```

   Buka `http://127.0.0.1:8000` di browser. Jangan bagikan atau commit file `.env`.
