# Talentora MVP v1.1 — rencana implementasi

Status: **Sembilan gap audit yang ditugaskan sudah diperbaiki dan diuji; P10 masih pending untuk cakupan visual seluruh modul dan E2E yang tidak berlaku/tersisa**. Acuan fungsional: `Talentora_FSD_Fullstack_MVP_v1.1.md` (§1–§22). Dokumen ini mencatat scope, rencana dan progres; FSD tetap acuan domain dan urutan prioritas §22.3. Desain visual mengacu pada 8 gambar yang diberikan pemilik proyek.

**Progres 22 November 2026:** seluruh 8 menu, endpoint domain, workflow, dan Settings tersedia. Perbaikan audit sudah memusatkan sesi tidak aktif ke respons 401 dan login, memisahkan error kalender/laporan dari hasil kosong, menggabungkan agregat laporan posisi agar jumlah query tidak tumbuh per posisi, memecah controller per domain dan menggunakan API Resources, serta menaikkan target sentuh tabel menjadi 44×44 px. Pipeline memakai enam kolom sejajar pada desktop, dan gulir mendatar hanya di dalam board pada layar kecil.

**Bukti QA 23 November 2026:** `php artisan test` lulus **19 tes / 206 assertion** pada MySQL `talentora_test`; E2E browser lulus **2 alur** (E2E-43/44); tes E2E-33 melepas dua proses PHP serentak dan membuktikan hanya satu transisi menang; `npm run lint`, `npm run typecheck`, `npm run build` (2215 modul), serta `php -l` untuk 47 file lulus. Tes mencakup trend harian/mingguan/bulanan, update profil/password, dan invariant admin aktif terakhir. Browser desktop/mobile yang sudah tercatat mengukur Pipeline dan Job Positions pada 1440/390 px. Seluruh sembilan gap dari audit sebelumnya sudah ditangani. Penerimaan FSD penuh masih **pending**: matriks visual semua delapan modul belum dijalankan; pemeriksaan visual respons konflik E2E-45 juga belum dilakukan. E2E-38/39 dan bagian drag-and-drop E2E-45 tidak berlaku karena keputusan produk menggunakan satu role Admin dan tombol perpindahan stage.

**Perubahan 21 November 2026:** pemilik proyek memilih React JS menggantikan Vue/TypeScript yang disebut FSD. Seluruh aturan fungsional tetap mengacu pada FSD; rujukan Vue/TypeScript di bawah perlu dibaca sebagai React JS untuk implementasi ini.

**Setup Laragon 21 November 2026:** repository tetap satu folder Laravel; React berada di `resources/js` dan Vite membangun aset ke `public/build`. Laragon sudah memiliki virtual host `talentora.test` dengan document root `C:\laragon\www\talentora\public`, serta hosts Windows `127.0.0.1 talentora.test`. `.env` memakai `APP_URL=http://talentora.test` dan domain tersebut masuk allowlist Sanctum. Keempat migration sudah berstatus Ran pada DB `talentora` dan `talentora_test`. Apache tidak sedang berjalan pada saat setup ini, jadi akses browser aktif setelah Apache/MySQL dijalankan lewat **Start All** di Laragon.

## Batas produk

- Tepat **8 menu**: Dashboard, Job Positions, Candidates, Applications, Hiring Pipeline, Interview Schedule, Reports & Analytics, Settings. Login/logout di luar menu; Profile, User Management, Application History, evaluation, dan department adalah bagian dari menu terkait.
- Hanya satu role internal, `admin`, sesuai keputusan pemilik proyek yang mengesampingkan role `hr_staff` pada FSD. Tidak ada candidate login. Fitur di luar §2.2 dan §22.1 tidak dikerjakan.
- `Candidate` adalah orang; `Application` adalah lamaran kandidat untuk satu posisi. Kombinasi candidate + position unik seumur aplikasi, termasuk setelah rejected. Metrik recruitment menghitung application, kecuali Total Candidates.
- Aturan bisnis, otorisasi, validasi, transaksi, dan kontrol konkurensi berada di backend/DB. Frontend menangani pengalaman pengguna dan memantulkan state server.

## Kondisi awal dan setup P1

Pada 21 November 2026, folder `C:\laragon\www\talentora` kosong dan belum menjadi Git repository. Terpasang: PHP 8.3.31, Composer 2.4.1, Node 24.16.0, npm 11.13.0, MySQL CLI 8.0.30; ekstensi PHP `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`, `dom`, `xml` tersedia. Koneksi/service MySQL dan instalasi paket belum diuji. Laravel 13 mensyaratkan PHP 8.3+, sehingga versi PHP lokal memenuhi syarat minimum; kompatibilitas paket tetap diverifikasi saat scaffold.

1. Jadikan folder ini Git repository dan simpan salinan FSD tanpa mengubah isinya. Setup aktual: **Laravel 13 + React JS + Vite** satu repo; SPA di `resources/js`, REST di `routes/api.php`, CSS di Vite, dan Blade hanya shell. React dipilih sesuai arahan terbaru pemilik proyek, sementara requirement TypeScript FSD belum dipenuhi. Gunakan host Laravel yang sama untuk session Sanctum.
2. Buat database MySQL InnoDB lokal terpisah untuk development dan test. Isi `.env` lokal untuk koneksi, `APP_KEY`, `APP_URL`, timezone internal UTC, session/cookie, private filesystem, dan kredensial bootstrap admin dari environment. Jangan commit `.env` atau password nyata. Verifikasi migration dari DB kosong.
3. Siapkan Sanctum **first-party session/cookie** untuk SPA, CSRF, login/logout, `/api/me`, middleware inactive-user dan role, serta profil timezone/company read-only pada `/api/me`. Atur origin/stateful domain dan credentials sesuai host lokal. `POST /api/login` mengikuti kontrak FSD meski contoh default dokumentasi Sanctum menggunakan `/login`.
4. Buat 7 tabel domain: `users`, `job_positions`, `candidates`, `applications`, `application_stage_histories`, `interviews`, `app_settings`; tambah tabel framework session/cache bila konfigurasi memerlukan. FK RESTRICT, unique index, indeks filter, UTC datetime, seed admin aktif dan default `company_name`/`timezone`, factories untuk enam stage/tiga status interview/tiga status posisi. Implementasi memakai satu role `admin` sesuai override pemilik proyek. Demo fixture dipisahkan dari seed wajib.
5. Siapkan test Laravel yang memakai MySQL test DB untuk constraint/transaction/race. Siapkan typecheck, lint, build frontend, serta test UI integration yang menguji API nyata. Verifikasi `migrate`, seed, login/logout, session refresh, 401/403, dan inactive user sebelum fitur domain.

## Peta fase dan gate

Setiap baris mencakup backend, API, frontend, validasi, dan tes. ID dalam rentang berarti **semua** ID tersebut harus dicentang, bukan contoh saja. `G-01..12`, `API-01..10`, `FE-01..16`, `BE-01..14`, `DB-01..14`, dan `EDGE-01..25` dilacak lintas fase pada bagian akhir.

| Fase | Modul / requirement | Pekerjaan dan API utama | Bukti gate |
|---|---|---|---|
| P1 | Foundation: `AUTH-01..08`, `DB-01..14` bagian schema/seed, `SET-11..13` dasar | Schema, factories, seed, Sanctum session, role/active guard, `/api/login`, `/api/logout`, `/api/me` | Migrasi DB kosong; `E2E-01..03`, 40; auth/role test. Full Settings tetap P9. |
| P2 | **Job Positions** `JOB-01..14` | CRUD, status terpisah, filter/search/sort/pagination, code uppercase unik, delete hanya tanpa application. Enam route `/api/job-positions...` §13. | `AC-JOB-01..05`, `E2E-04..07`; gate yang memerlukan application diselesaikan ulang setelah P4. |
| P3 | **Candidates** `CAN-01..18` | CRUD, email lowercase unik, private CV PDF/DOC/DOCX ≤5 MiB, safe replace/delete, detail + application history. Delapan route `/api/candidates...` §13. | `AC-CAN-01..06`, `E2E-08..13`, `EDGE-01,17,18`; bagian application dituntaskan setelah P4. |
| P4 | **Applications** `APP-01..19` | Create pada posisi open, `APP-<ULID>`, unique candidate+position, initial `NULL→applied` history atomik, metadata terbatas, detail/history read-only. Enam route `/api/applications...` §13 termasuk shared stage route yang diisi pada P5. | `AC-APP-01..06`, `E2E-14..17`, `EDGE-02..04,23`; kasus terminal selesai setelah P5. Re-run gate P2/P3 yang tertunda. |
| P5 | **Hiring Pipeline** `PIPE-01..18` | `ApplicationWorkflowService`, transition matrix, `from_stage` stale check 409, reject + auto-cancel atomik, history append-only, enam grup dengan `limit_per_stage` 50/≤100 dan total akurat. `GET /api/pipeline`, shared `PATCH /api/applications/{id}/stage`. | `AC-PIPE-01..06`, `E2E-18..20,29..33`, `EDGE-05..07,16`; Offered gate dan auto Interview diuji penuh setelah P6. |
| P6 | **Interview Schedule** `INT-01..22` | List/detail/calendar, schedule/reschedule/cancel/complete, active interviewer, overlap `[start,end)`, lock interviewer + application, first schedule mengubah Screening→Interview lewat workflow service, evaluasi lengkap setelah end. Tujuh route `/api/interviews...` §13. | `AC-INT-01..08`, `E2E-21..28`, `EDGE-08..10,13..15,21,25`. Re-run full Pipeline/Applications acceptance. |
| P7 | **Dashboard** `DASH-01..12` | `/api/dashboard`: 6 KPI, semua enam stage termasuk 0, applications by position, maksimum 5 upcoming dan 5 recent, default 30 hari lokal inklusif. | `AC-DASH-01..05`, `E2E-34`, `EDGE-20,22`; angka cocok fixture DB. |
| P8 | **Reports & Analytics** `REP-01..11` | Lima route `/api/reports/{summary,stages,trends,positions,metrics}`; cohort `applied_at`, interview by `start_at`, current stage, filter konsisten, zero/null semantics, pagination positions. | `AC-REP-01..06`, `E2E-35..37`, `EDGE-19,20,22`; perhitungan diverifikasi manual pada fixtures. |
| P9 | **Settings** `SET-01..14` | Profile/password sendiri, admin-only users create/edit/status dan app settings whitelist, last active admin invariant atomik, UTC/display timezone. Sembilan route `/api/me...`, `/api/users...`, `/api/app-settings...` §13 (GET `/api/me` sudah P1). | `AC-SET-01..07`, `E2E-38..42`, `EDGE-11,12,24`; tes 403 dan sesi lama. |
| P10 | Integrasi & QA `G-01..12`, `API-01..10`, `FE-01..16`, `BE-01..14`, `DB-01..14`, `EDGE-01..25`, `E2E-01..45` | Uji alur lengkap hired/rejected, semua delapan menu, sinkronisasi state lintas menu, mobile/desktop, error/loading/empty, typecheck/lint/build, keamanan CV/password. | Seluruh AC dan E2E relevan lulus dengan bukti command serta known gaps per ID. |

### Dependensi gate lintas fase

- P2 `AC-JOB-03..04` dan P3 `AC-CAN-05..06` butuh Applications P4. P4 `AC-APP-05` butuh stage terminal P5. Jadi fase tersebut boleh lolos gate implementasi lokal, tetapi **belum boleh dinyatakan lengkap** sampai tes lintas fase lulus.
- P5 `interview→offered`, rejection dengan scheduled interview, dan auto transition belum dapat dibuktikan sebelum P6. P6 menyelesaikan gate penuh Pipeline dan Interview bersama.
- `E2E-45` berlaku bila UI menggunakan drag-and-drop; FSD mengizinkan pilihan stage tanpa drag-and-drop. Bila desain pengguna memilih pilihan stage, test UI 409/refresh tetap wajib, sementara tes drag-and-drop spesifik dicatat tidak berlaku.

## Aturan domain yang harus dipertahankan

```text
applied → screening → interview → offered → hired
     ↘ rejected  ↘ rejected  ↘ rejected  ↘ rejected
```

- `screening→interview` hanya otomatis dari schedule interview pertama yang berhasil; manual stage API menolak `to_stage=interview` dengan 422. `hired`/`rejected` terminal. Reject butuh alasan, mengisi `rejected_at`, dan membatalkan semua scheduled interview dalam transaksi yang sama. Offered butuh completed interview dengan rating/recommendation/feedback lengkap dan tidak ada scheduled interview. Recommendation hanya informasi, bukan keputusan otomatis.
- Schedule hanya untuk application `screening`/`interview`, interviewer aktif, waktu depan, `end_at>start_at`, maksimal satu scheduled per application, tidak overlap untuk interviewer sama. Edit/cancel/complete hanya pada scheduled; complete setelah `end_at` dengan evaluasi lengkap. Cancel manual mempertahankan application pada stage `interview`.
- Position `draft/closed` tidak menerima application baru, tetapi existing application pada position yang telah closed terus diproses. `openings` tidak membatasi create. Candidate/position yang telah punya application tidak dapat dihapus (409); tidak ada hard delete application/interview/user/history.
- CV private, path acak dari server, upload metadata terpisah, tidak pernah expose path; delete/replace aman terhadap rollback dan gagal IO. Auth serta role diperiksa pada download. Semua waktu event UTC; tanggal `applied_at` adalah date-only lokal aplikasi.
- Semua list server-side pagination (`per_page` default 10, maks 100), sort allowlist, search `q` maks 100, filter invalid 422. Respons mengikuti §13: snake_case, 200/201/204, 401/403/404/409/422; tidak mengirim hash password/private path. Mutasi hanya sukses setelah commit.

## API dan data checklist

- **Auth/Profile:** `POST /api/login`, `POST /api/logout`, `GET|PATCH /api/me`, `PATCH /api/me/password`; CSRF `/sanctum/csrf-cookie`.
- **Dashboard:** `GET /api/dashboard`.
- **Job Positions:** `GET|POST /api/job-positions`, `GET|PATCH|DELETE /api/job-positions/{id}`, `PATCH /api/job-positions/{id}/status`.
- **Candidates:** `GET|POST /api/candidates`, `GET|PATCH|DELETE /api/candidates/{id}`, `POST|GET|DELETE /api/candidates/{id}/cv`.
- **Applications/Pipeline:** `GET|POST /api/applications`, `GET|PATCH /api/applications/{id}`, `GET /api/applications/{id}/history`, `PATCH /api/applications/{id}/stage`, `GET /api/pipeline`.
- **Interviews:** `GET|POST /api/interviews`, `GET /api/interviews/calendar`, `GET|PATCH /api/interviews/{id}`, `PATCH /api/interviews/{id}/complete`, `PATCH /api/interviews/{id}/cancel`.
- **Reports:** `GET /api/reports/summary|stages|trends|positions|metrics`.
- **Settings admin:** `GET|POST /api/users`, `PATCH /api/users/{id}`, `PATCH /api/users/{id}/status`, `GET|PATCH /api/app-settings`.

Schema domain mengikuti tepat 7 tabel §12.3, seluruh FK dan unique index §12.1–12.4, serta seed/factory §12.5. Parameter lengkap tiap route, validation field, contoh payload, dan response shape tetap dirujuk langsung ke §4–§13 FSD; jangan menebak dari ringkasan ini.

## Desain dan UI

Pemilik proyek sudah memberikan delapan rancangan visual. Implementasi menggunakan shell sidebar/topbar gelap dengan aksen cyan, kartu KPI, tabel/detail drawer, kanban, filter laporan, dan kalender mingguan. Semua delapan menu terhubung ke API dan memakai state data server. Form create/edit, upload CV, evaluasi, penolakan, serta konfirmasi aksi memakai pola modal gelap bercahaya cyan dari referensi modal pemilik proyek; validasi, endpoint, dan transisi tetap mengikuti FSD. Rancangan memuat elemen yang sengaja tidak diimplementasikan karena tidak ada pada MVP FSD: multi-company, AI screening/matching, export, email/calendar integration, notifications, dan payroll. Pemeriksaan browser sudah mengukur pipeline dan Job Positions pada viewport 1440/390 px; pemeriksaan visual seluruh halaman dan skenario mutate lintas modul masih menjadi gate P10. Untuk Pipeline dipilih aksi stage tanpa drag-and-drop.

## Gap/keputusan sebelum dan saat implementasi

1. **Gate fase FSD §19 tidak sepenuhnya independen.** Dependensi P2–P6 di atas harus dicatat sebagai pending dan diuji ulang; ini masalah urutan tes, bukan perubahan requirement.
2. **Grouping `weekly` REP-03/AC-REP-06** tidak menyebut awal minggu dan label periode. Keputusan implementasi: gunakan minggu ISO Senin–Minggu dari MySQL (`%x-W%v`); keputusan ini perlu diverifikasi di acceptance fixture.
3. **`E2E-45` menyebut drag-and-drop**, sedangkan `PIPE-04` mengizinkan pilihan stage. Perlakukan 409 conflict UI sebagai wajib untuk kedua pola; tes drag spesifik hanya bila pola drag dipilih.
4. **React JS menggantikan Vue 3** sesuai arahan pemilik proyek. API client TypeScript tersedia di `resources/js/api.ts` dan dicakup `npm run typecheck`; komponen tetap React JSX, jadi requirement FE-01 (Vue 3 + TypeScript) dicatat sebagai pengecualian stack.
5. Tambahan `GET /api/interviewers` hanya lookup user internal aktif untuk pilihan interviewer pada form; fitur domain tetap mengikuti endpoint Interview Schedule dalam FSD.
6. **Role tunggal Admin menggantikan dua role FSD** sesuai keputusan pemilik proyek; E2E-38/39 tidak berlaku. Aksi tahap pada UI berupa tombol, bukan drag-and-drop; E2E-45 bagian drag tidak berlaku, tetapi respons stale `409` diuji pada API dan frontend menjadwalkan refresh setelah konflik.

## Hasil acceptance terkini

| Bukti | Hasil | Catatan |
|---|---|---|
| Laravel feature/unit suite | **Lulus — 19 tes, 206 assertion** | Meliputi auth aktif/nonaktif/logout; validasi posisi; kandidat dan CV PDF/DOC/DOCX batas 5 MiB, replace/delete dan privasi; keunikan aplikasi/riwayat; transisi stage, interview/overlap/evaluasi/offer/hire/reject; dashboard/laporan; agregasi query posisi; Settings, profil/password dan invariant admin terakhir. |
| PHP syntax | **Lulus — 47 file** | `php -l` untuk request, controller, middleware, service, migration, seeder, dan tes. |
| Frontend lint | **Lulus** | `npm run lint` memeriksa berkas `.js`/`.jsx` di `resources/js`. |
| API client typecheck | **Lulus** | `npm run typecheck`; typed Axios API client di `resources/js/api.ts`. |
| Vite production build | **Lulus** | `npm run build`, 2215 modul ditransformasi. |
| Browser E2E-43/44 | **Lulus — 2 alur UI** | Buat lowongan/kandidat/lamaran dan verifikasi Applied; jadwalkan/evaluasi interview lalu pindahkan lamaran yang sama ke Offered. Fixture test menggeser waktu interview pada database E2E saja agar aturan interview berakhir diuji tanpa menunggu waktu nyata. |
| Browser desktop | **Lulus terbatas** | Pipeline + daftar lowongan pada 1440 px; semua enam tahap pipeline terlihat dan lingkaran sejajar kolom. |
| Browser mobile | **Lulus terbatas** | Pipeline + daftar lowongan pada 390 px; tidak ada overflow pada dokumen, board/tabel memakai gulir internal, kontrol aksi 44×44 px. |
| Visual seluruh 8 modul | **Belum diuji penuh** | Browser mengukur dua modul pada dua ukuran viewport; belum ada screenshot matrix delapan halaman.
| Respons konflik E2E-45 | **Belum diuji visual** | UI menggunakan tombol, bukan drag-and-drop; respons 409 diuji berurutan dan refresh state ditangani frontend. |

### Penutupan 9 gap audit

| # | Status | Perubahan dan bukti |
|---|---|---|
| 1 | Selesai | Tes dua proses untuk transisi stage serentak, dua alur browser E2E-43/44, grouping laporan harian/mingguan/bulanan, update profil/password, dan guard admin aktif terakhir. |
| 2 | Selesai dengan pengecualian stack tercatat | React Router dan deep link untuk delapan modul; typed Axios client, `npm run typecheck`, dan `npm run lint`. Komponen memakai React JSX sesuai keputusan pemilik proyek. |
| 3 | Selesai | Lokasi lowongan diwajibkan pada form dan `JobPositionRequest`; lokasi kandidat tetap opsional. `FsdAcceptanceTest` memeriksa validasi backend. |
| 4 | Selesai | Error load dan retry dipisahkan dari empty state pada daftar, opsi form, serta Settings. |
| 5 | Selesai | Form upload CV tetap terbuka saat mutasi gagal; ditutup setelah upload berhasil. |
| 6 | Selesai | Validasi endpoint dipindahkan ke Form Requests; akses user dan pengaturan aplikasi memakai `EnsureAdmin`, guard sesi aktif tetap diterapkan. |
| 7 | Selesai | Migration forward mengubah `users.name` menjadi 150 karakter; validasi mengikuti batas yang sama. |
| 8 | Selesai | Kode lamaran demo memakai prefix `APP-` dan suffix ULID 26 karakter; relasi seeder interview mengikuti kode tersebut. |
| 9 | Selesai | Warna CSS dipusatkan ke custom properties global; literal warna tidak tersisa di rule komponen. |

### Pemetaan ID E2E

| ID FSD | Status | Bukti |
|---|---|---|
| E2E-01..03 | Covered | `AuthenticationTest::test_active_user_can_login_and_logout`; `AuthenticationTest::test_inactive_user_cannot_login_or_use_existing_session`. |
| E2E-04..07 | Covered | `RecruitmentFlowTest::test_job_candidate_application_and_first_interview_share_one_workflow`; `FsdAcceptanceTest::test_job_position_rules_and_case_insensitive_code_are_enforced`. |
| E2E-08..13 | Covered | `FsdAcceptanceTest::test_candidate_email_cv_privacy_replacement_and_delete_rules`; `FsdAcceptanceTest::test_candidate_cv_download_requires_an_authenticated_session`. |
| E2E-14..20 | Covered | `FsdAcceptanceTest::test_application_uniqueness_history_and_stage_transition_guards`. |
| E2E-21..32 | Covered | `FsdAcceptanceTest::test_interview_schedule_evaluation_offer_hire_and_rejection_rules`. |
| E2E-33 | Covered | `ConcurrentStageTransitionTest::test_simultaneous_stage_transitions_allow_only_one_winner`; dua worker PHP memanggil endpoint stage serentak, satu berhasil dan satu menerima `409`, hanya satu history tercatat. |
| E2E-34..37 | Covered | `FsdAcceptanceTest::test_dashboard_and_reports_match_fixture_totals_and_return_empty_values`; `test_position_report_query_count_does_not_grow_with_positions`. |
| E2E-38..39 | Not applicable by owner decision | FSD role HR Staff diganti role Admin tunggal; tes memastikan role HR Staff lama/baru ditolak. |
| E2E-40..42 | Covered | `AuthenticationTest::test_inactive_user_cannot_login_or_use_existing_session`; `FsdAcceptanceTest::test_admin_only_user_settings_and_inactive_session_rules`. |
| E2E-43..44 | Covered | `tests/e2e/recruitment-workflow.spec.js`: alur create application→Applied dan interview evaluation→Offered dijalankan lewat browser pada database uji. |
| E2E-45 | Not applicable as written | UI memakai tombol, tanpa drag-and-drop; API stale `409` diuji dan frontend memicu refresh + pesan konflik. Verifikasi visual respons konflik UI masih pending. |

## Format kerja setiap fase

Laksanakan satu fase per langkah. Untuk tiap fase catat: requirement ID selesai dan pending, route, migration/model/service/frontend/test files, command test dan hasil nyata, AC yang lulus, edge case yang diuji, blocker, dan fase berikutnya. Jangan centang ID hanya karena kode ada; centang setelah tes/acceptance terbukti. Tidak ada UI palsu atau data dummy setelah integrasi.

