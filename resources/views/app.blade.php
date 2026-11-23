<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talentora</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    @php
        $bootPages = [
            'Dashboard' => ['path' => 'dashboard', 'description' => 'Ringkasan posisi aktif, lamaran, dan jadwal interview.'],
            'Job Positions' => ['path' => 'job-positions', 'description' => 'Kelola posisi kerja dan pantau lamaran yang masuk.'],
            'Candidates' => ['path' => 'candidates', 'description' => 'Database kandidat, CV privat, dan riwayat lamaran.'],
            'Applications' => ['path' => 'applications', 'description' => 'Kelola lamaran, tahapan seleksi, dan riwayat kandidat.'],
            'Hiring Pipeline' => ['path' => 'hiring-pipeline', 'description' => 'Pantau progres kandidat sesuai aturan workflow.'],
            'Interview Schedule' => ['path' => 'interview-schedule', 'description' => 'Jadwalkan interview, simpan evaluasi, dan cek kalender.'],
            'Reports & Analytics' => ['path' => 'reports-analytics', 'description' => 'Analisis cohort lamaran dan metrik rekrutmen.'],
            'Settings' => ['path' => 'settings', 'description' => 'Kelola profil, pengguna internal, dan pengaturan aplikasi.'],
        ];
        $currentPath = trim(request()->path(), '/');
        $bootPage = null;
        foreach ($bootPages as $name => $route) {
            if ($currentPath === $route['path']) {
                $bootPage = $name;
                break;
            }
        }
    @endphp
    <div id="app"><div class="shell boot-shell" aria-busy="true"><aside class="sidebar"><div class="brand"><div class="brandmark">T</div><div><b>Talentora</b></div></div><nav aria-label="Navigasi utama">@foreach ($bootPages as $label => $route)<a class="{{ $bootPage === $label ? 'active' : '' }}" @if ($bootPage === $label) aria-current="page" @endif href="/{{ $route['path'] }}"><span>{{ $label }}</span></a>@endforeach</nav></aside><main class="main"><header class="top"><div class="global-search"><span class="boot-search-icon" aria-hidden="true">⌕</span><span>Cari kandidat, lowongan...</span></div></header><section class="content"><div class="pagehead"><div><div class="crumb">REKRUTMEN <span>›</span> {{ $bootPage ? mb_strtoupper($bootPage) : 'WORKSPACE' }}</div><h1>{{ $bootPage ?: 'Talentora' }}</h1><p>{{ $bootPage ? $bootPages[$bootPage]['description'] : 'Menyiapkan halaman terakhir yang dibuka.' }}</p></div></div><div class="panel request-state bootstate" role="status"><i class="bootspinner"></i><span>{{ $bootPage ? "Menyiapkan {$bootPage}…" : 'Memuat Talentora…' }}</span></div></section></main></div></div>
</body>
</html>
