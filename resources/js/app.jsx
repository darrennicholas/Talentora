import React, { useCallback, useEffect, useRef, useState } from 'react';
import { hydrateRoot } from 'react-dom/client';
import { BrowserRouter, useLocation, useNavigate } from 'react-router-dom';
import { api as axios } from './api.ts';
import PageContent, { ActionPage } from './pages.jsx';
import { ArrowRight, BriefcaseBusiness, CalendarDays, ChartNoAxesCombined, ChevronDown, CircleUserRound, FileText, LayoutDashboard, LockKeyhole, LogOut, Mail, Plus, Search, Settings, Users, Workflow, RefreshCw, Menu } from 'lucide-react';
import { dateInZone, localToUtc, zonedDateInput } from './dates.js';
import { confirmMutation, showErrorAlert, showSuccessAlert } from './alerts.js';
import './bootstrap.js';

const nav = [['Dashboard', LayoutDashboard], ['Job Positions', BriefcaseBusiness], ['Candidates', Users], ['Applications', FileText], ['Hiring Pipeline', Workflow], ['Interview Schedule', CalendarDays], ['Reports & Analytics', ChartNoAxesCombined], ['Settings', Settings]];
const PAGE_PATHS = {
  Dashboard: '/dashboard',
  'Job Positions': '/job-positions',
  Candidates: '/candidates',
  Applications: '/applications',
  'Hiring Pipeline': '/hiring-pipeline',
  'Interview Schedule': '/interview-schedule',
  'Reports & Analytics': '/reports-analytics',
  Settings: '/settings',
};
const PATH_PAGES = Object.fromEntries(Object.entries(PAGE_PATHS).map(([page, path]) => [path, page]));
const descriptions = {
  Dashboard: 'Ringkasan posisi aktif, lamaran, dan jadwal interview.',
  'Job Positions': 'Kelola posisi kerja dan pantau lamaran yang masuk.',
  Candidates: 'Database kandidat, CV privat, dan riwayat lamaran.',
  Applications: 'Kelola lamaran, tahapan seleksi, dan riwayat kandidat.',
  'Hiring Pipeline': 'Pantau progres kandidat sesuai aturan workflow.',
  'Interview Schedule': 'Jadwalkan interview, simpan evaluasi, dan cek kalender.',
  'Reports & Analytics': 'Analisis cohort lamaran dan metrik rekrutmen.',
  Settings: 'Kelola profil, pengguna internal, dan pengaturan aplikasi.',
};
const createLabels = { 'Job Positions': 'Tambah Posisi', Candidates: 'Tambah Kandidat', Applications: 'Daftarkan Lamaran', 'Interview Schedule': 'Jadwalkan Interview' };
const pretty = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());

function App() {
  return <BrowserRouter><TalentoraApp /></BrowserRouter>;
}

function TalentoraApp() {
  const location = useLocation();
  const navigate = useNavigate();
  const [page, setPageState] = useState(PATH_PAGES[location.pathname] || 'Dashboard');
  const setPage = useCallback(nextPage => {
    setPageState(nextPage);
    const path = PAGE_PATHS[nextPage];
    if (path && location.pathname !== path) navigate(path);
  }, [location.pathname, navigate]);
  const previousPage = useRef(page);
  const [user, setUser] = useState(null);
  const [login, setLogin] = useState(false);
  const [authReady, setAuthReady] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const mutationPending = useRef(false);
  const activeSession = useRef(false);
  const responseCache = useRef(new Map());
  const requestSequence = useRef(0);
  const [pageResponse, setPageResponse] = useState({ key: null, value: null });
  const [search, setSearch] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [form, setForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [scheduleApplication, setScheduleApplication] = useState(null);
  const [reload, setReload] = useState(0);
  const [compact, setCompact] = useState(false);
  const [pageNum, setPageNum] = useState(1);
  const [filters, setFilters] = useState({});
  const timezone = user?.app_timezone || 'Asia/Jakarta';
  const listPage = ['Job Positions', 'Candidates', 'Applications', 'Hiring Pipeline', 'Interview Schedule'].includes(page);
  const requestKey = JSON.stringify([page, listPage ? searchQuery : '', pageNum, filters[page] || {}]);
  const activePageKey = useRef(requestKey);
  activePageKey.current = requestKey;
  const data = pageResponse.key === requestKey
    ? pageResponse.value
    : responseCache.current.get(requestKey) ?? null;

  useEffect(() => {
    const interceptor = axios.interceptors.response.use(response => response, requestError => {
      const status = requestError.response?.status;
      const path = requestError.config?.url || '';
      if (activeSession.current && [401, 403].includes(status) && path.startsWith('/api/') && !['/api/login', '/api/logout'].includes(path)) {
        activeSession.current = false;
        responseCache.current.clear();
        setUser(null);
        setLogin(true);
        setPageResponse({ key: null, value: null });
        setForm(false);
        setEditing(null);
        setScheduleApplication(null);
        setError('Sesi berakhir atau akun tidak lagi aktif. Silakan masuk kembali.');
      }
      return Promise.reject(requestError);
    });

    return () => axios.interceptors.response.eject(interceptor);
  }, []);

  const refresh = useCallback(async () => {
    if (!user) return;
    const requestId = ++requestSequence.current;
    const key = requestKey;
    setBusy(true);
    setError('');
    try {
      const path = { Dashboard: '/api/dashboard', 'Job Positions': '/api/job-positions', Candidates: '/api/candidates', Applications: '/api/applications', 'Hiring Pipeline': '/api/pipeline', 'Interview Schedule': '/api/interviews', 'Reports & Analytics': '/api/reports/summary', Settings: '/api/me' }[page];
      const params = {
        ...(listPage && searchQuery ? { q: searchQuery } : {}),
        ...(page === 'Hiring Pipeline' ? { limit_per_stage: 10 } : { page: pageNum, per_page: 10 }),
        ...(filters[page] || {}),
      };
      const response = await axios.get(path, { params });
      if (requestId !== requestSequence.current || key !== activePageKey.current) return;
      responseCache.current.set(key, response.data);
      while (responseCache.current.size > 30) responseCache.current.delete(responseCache.current.keys().next().value);
      setPageResponse({ key, value: response.data });
    } catch (e) {
      if (requestId !== requestSequence.current || key !== activePageKey.current) return;
      setError([401, 403].includes(e.response?.status)
        ? 'Sesi berakhir atau akun tidak lagi aktif. Silakan masuk kembali.'
        : e.response?.data?.message || 'Data gagal dimuat. Periksa koneksi, lalu coba lagi.');
    } finally {
      if (requestId === requestSequence.current && key === activePageKey.current) setBusy(false);
    }
  }, [page, searchQuery, pageNum, user, reload, filters, listPage, requestKey]);

  useEffect(() => {
    (async () => {
      try {
        await axios.get('/sanctum/csrf-cookie');
        const response = await axios.get('/api/me');
        const authenticatedUser = response.data.data;
        activeSession.current = true;
        setUser(authenticatedUser);
        try {
          if (window.location.pathname !== '/') return;
          const savedPage = localStorage.getItem(`talentora:last-page:${authenticatedUser.id}`);
          if (nav.some(([name]) => name === savedPage)) setPage(savedPage);
          else setPage('Dashboard');
        } catch {
          // Keep the dashboard fallback when browser storage is unavailable.
        }
      } catch {
        setLogin(true);
      } finally {
        setAuthReady(true);
      }
    })();
  }, []);
  useEffect(() => {
    const routedPage = PATH_PAGES[location.pathname];
    if (routedPage && routedPage !== page) setPageState(routedPage);
  }, [location.pathname, page]);
  useEffect(() => {
    const timeout = window.setTimeout(() => setSearchQuery(search.trim()), 250);
    return () => window.clearTimeout(timeout);
  }, [search]);
  useEffect(() => {
    if (user) {
      try { localStorage.setItem(`talentora:last-page:${user.id}`, page); } catch {
        // Page navigation still works when browser storage is unavailable.
      }
    }
  }, [page, user]);
  useEffect(() => {
    if (previousPage.current !== page) {
      previousPage.current = page;
      window.scrollTo({ top: 0, behavior: 'auto' });
    }
  }, [page]);
  useEffect(() => { refresh(); }, [refresh]);

  async function signIn(event) {
    event.preventDefault();
    const credentials = Object.fromEntries(new FormData(event.currentTarget));
    setBusy(true); setError('');
    try {
      await axios.get('/sanctum/csrf-cookie');
      const response = await axios.post('/api/login', credentials);
      await axios.get('/sanctum/csrf-cookie');
      activeSession.current = true;
      setUser(response.data.data); setLogin(false); setPage('Dashboard');
    } catch (e) {
      const status = e.response?.status;
      const emailError = e.response?.data?.errors?.email?.[0];
      setError(status === 422 && emailError === 'Invalid credentials.'
        ? 'Email atau password salah.'
        : emailError || (status === 419
          ? 'Sesi keamanan berakhir. Muat ulang halaman lalu coba lagi.'
          : status
            ? 'Login gagal karena server bermasalah. Coba lagi sebentar.'
            : 'Tidak dapat terhubung ke server. Pastikan Laravel berjalan lalu coba lagi.'));
    } finally { setBusy(false); }
  }

  async function signOut() {
    try { await axios.post('/api/logout'); } finally { activeSession.current = false; responseCache.current.clear(); setUser(null); setPageResponse({ key: null, value: null }); setLogin(true); }
  }

  async function mutate(method, path, body, success = 'Perubahan berhasil disimpan.', options = {}) {
    if (mutationPending.current) return false;
    mutationPending.current = true;
    let requestStarted = false;
    try {
      if (!(await confirmMutation(method, path, options.confirmation))) return false;
      setError(''); setFieldErrors({}); setBusy(true);
      requestStarted = true;
      await axios[method](path, body);
      if (path === '/api/me' || path === '/api/app-settings') {
        try { const response = await axios.get('/api/me'); setUser(response.data.data); } catch {
          // The completed mutation remains successful if profile refresh fails.
        }
      }
      setForm(false); setEditing(null); setScheduleApplication(null); setReload(value => value + 1); showSuccessAlert(success); return true;
    } catch (e) {
      const errors = e.response?.data?.errors || {};
      const fieldErrors = Object.values(errors).flat().join(' ');
      setFieldErrors(errors);
      const sessionInvalid = [401, 403].includes(e.response?.status);
      const message = sessionInvalid
        ? 'Sesi Anda berakhir. Silakan masuk kembali.'
        : [e.response?.data?.message, fieldErrors].filter(Boolean).join(' ') || (e.response
          ? 'Perubahan gagal disimpan. Periksa data lalu coba lagi.'
          : 'Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.');
      if (sessionInvalid) { activeSession.current = false; responseCache.current.clear(); setUser(null); setLogin(true); setPageResponse({ key: null, value: null }); }
      if (e.response?.status === 409) setReload(value => value + 1);
      await showErrorAlert(message);
      return false;
    } finally {
      if (requestStarted) setBusy(false);
      mutationPending.current = false;
    }
  }

  function openCreate() {
    const target = createLabels[page] ? page : 'Applications';
    if (target !== page) setPage(target);
    setEditing(null); setScheduleApplication(null); setError(''); setForm(true);
  }
  function openEdit(item) { setEditing(item); setScheduleApplication(null); setError(''); setForm(true); }
  function openSchedule(applicationId = null) {
    setPage('Interview Schedule'); setPageNum(1); setEditing(null); setScheduleApplication(applicationId); setError(''); setForm(true);
  }
  function viewPositionApplications(positionId) {
    setPage('Applications'); setPageNum(1); setForm(false); setEditing(null);
    setFilters(current => ({ ...current, Applications: { ...current.Applications, job_position_id: positionId } }));
  }
  function openDashboardMetric(targetPage, targetFilters) {
    setPage(targetPage); setSearch(''); setSearchQuery(''); setPageNum(1); setForm(false); setEditing(null); setScheduleApplication(null); setError('');
    setFilters(current => ({ ...current, [targetPage]: targetFilters }));
  }
  function setFilter(key, value, direction) {
    setFilters(current => {
      const previous = current[page] || {};
      const next = { ...previous, [key]: value || undefined };
      if (key === 'sort_by') next.sort_dir = previous.sort_by === value ? direction : 'desc';
      return { ...current, [page]: next };
    });
    setPageNum(1);
  }
  function submit(payload) {
    const endpoint = { 'Job Positions': 'job-positions', Candidates: 'candidates', Applications: 'applications', 'Interview Schedule': 'interviews' }[page];
    const method = editing ? 'patch' : 'post';
    const path = `/api/${endpoint}${editing ? `/${editing.id}` : ''}`;
    return mutate(method, path, payload, editing ? 'Perubahan berhasil diperbarui.' : 'Data berhasil ditambahkan.');
  }

  if (!authReady) return <BootShell page={location.pathname === '/' ? null : page} />;
  if (login) return <div className="login"><div className="loginlayout">
    <section className="loginintro" aria-label="Talentora Enterprise ATS">
      <div className="loginbrand"><div className="brandmark">T</div><div><strong>Talentora</strong><small>ENTERPRISE ATS</small></div></div>
      <div className="loginintrocopy"><h2>Kelola rekrutmen<br /><span>dengan lebih terarah.</span></h2><p>Lowongan, kandidat, dan proses seleksi dalam satu ruang kerja.</p></div>
      <div className="loginstatus"><i />Talentora Recruitment Workspace</div>
    </section>
    <main className="loginbox">
      <div className="loginheading"><h1>Selamat datang</h1><p>Masuk ke akun Talentora untuk melanjutkan pekerjaan rekrutmen Anda.</p></div>
      {error && <div className="notice error" role="alert">{error}</div>}
      <form onSubmit={signIn}>
        <label className="loginfield"><span>Email</span><span className="logininput"><Mail size={18} aria-hidden="true" /><input name="email" type="email" autoComplete="username" placeholder="nama@perusahaan.co.id" required autoFocus /></span></label>
        <label className="loginfield"><span>Password</span><span className="logininput"><LockKeyhole size={18} aria-hidden="true" /><input name="password" type="password" autoComplete="current-password" placeholder="Masukkan password" required /></span></label>
        <button className="primary wide" disabled={busy}>{busy ? 'Memproses…' : 'Masuk'}<ArrowRight size={18} aria-hidden="true" /></button>
      </form>
      <p className="loginhint">Akses untuk akun internal Talentora.</p>
    </main>
  </div></div>;

  return <div className={`shell ${compact ? 'compact' : ''}`}>
    <aside className="sidebar"><div className="brand"><div className="brandmark">T</div><div><b>Talentora</b></div></div><nav aria-label="Navigasi utama">{nav.map(([name, Icon]) => <button key={name} className={page === name ? 'active' : ''} aria-current={page === name ? 'page' : undefined} onClick={() => { setPage(name); setPageNum(1); setForm(false); setEditing(null); setError(''); }}><Icon size={18} /><span>{name}</span></button>)}</nav></aside>
    <main className="main"><header className="top"><button className="iconbtn mobilemenu" aria-label="Menu" onClick={() => setCompact(!compact)}><Menu size={18} /></button><div className="global-search"><Search size={16} /><input aria-label="Cari data" placeholder="Cari kandidat, lowongan..." value={search} onChange={event => { setSearch(event.target.value); setPageNum(1); }} /></div><button className="iconbtn" title="Muat ulang" aria-label="Muat ulang" onClick={() => setReload(value => value + 1)}><RefreshCw size={17} /></button><div className="userchip"><CircleUserRound size={27} /><div><b>{user?.name || 'Recruiter'}</b><small>{pretty(user?.role || 'admin')}</small></div></div><button className="iconbtn" title="Keluar" aria-label="Keluar" onClick={signOut}><LogOut size={17} /></button></header>
      <section className="content">{!form && <div className="pagehead"><div><div className="crumb">REKRUTMEN <span>›</span> {page.toUpperCase()}</div><h1 className={page === 'Dashboard' ? 'dashboardtitle' : page === 'Job Positions' ? 'jobpositionstitle' : undefined}>{page}</h1><p>{descriptions[page]}</p></div><div className="headactions">{createLabels[page] && <button className="primary" onClick={openCreate}><Plus size={17} />{createLabels[page]}</button>}</div></div>}
        {!form && error && (data !== null || ['Settings', 'Reports & Analytics'].includes(page)) && <div className="notice error" role="alert"><span>{error}</span><button type="button" className="secondary small" onClick={() => setReload(value => value + 1)}>Coba lagi</button></div>}{busy && <div className="loading" role="status"><i /> Memuat data terbaru…</div>}
        {form ? <CreateForm key={`${page}-${editing?.id || 'new'}`} page={page} item={editing} applicationId={scheduleApplication} timezone={timezone} busy={busy} fieldErrors={fieldErrors} formError={error} close={() => { setForm(false); setEditing(null); setScheduleApplication(null); setError(''); }} submit={submit} /> : <PageContent page={page} data={data} user={user} timezone={timezone} filters={filters[page] || {}} setFilter={setFilter} mutate={mutate} openCreate={openCreate} openSchedule={openSchedule} onEdit={openEdit} onViewApplications={viewPositionApplications} onDashboardMetric={openDashboardMetric} pageNum={pageNum} setPageNum={setPageNum} reload={reload} busy={busy} actionError={error} onRetry={() => setReload(value => value + 1)} onError={setError} clearError={() => setError('')} />}
        <footer>Talentora · Recruitment workspace <span>Data tersimpan di server</span></footer>
      </section>
    </main>
  </div>;
}

function BootShell({ page }) {
  const name = page || 'Talentora';
  return <div className="shell boot-shell" aria-busy="true">
    <aside className="sidebar"><div className="brand"><div className="brandmark">T</div><div><b>Talentora</b></div></div><nav aria-label="Navigasi utama">{nav.map(([label]) => <a key={label} className={page === label ? 'active' : ''} aria-current={page === label ? 'page' : undefined} href={PAGE_PATHS[label]}><span>{label}</span></a>)}</nav></aside>
    <main className="main"><header className="top"><div className="global-search"><span className="boot-search-icon" aria-hidden="true">⌕</span><span>Cari kandidat, lowongan...</span></div></header><section className="content"><div className="pagehead"><div><div className="crumb">REKRUTMEN <span>›</span> {page ? page.toUpperCase() : 'WORKSPACE'}</div><h1>{name}</h1><p>{page ? descriptions[page] : 'Menyiapkan halaman terakhir yang dibuka.'}</p></div></div><div className="panel request-state bootstate" role="status"><i className="bootspinner" /><span>{page ? `Menyiapkan ${page}…` : 'Memuat Talentora…'}</span></div></section></main>
  </div>;
}

function SelectOptions({ type, value }) {
  const [query, setQuery] = useState('');
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [retry, setRetry] = useState(0);
  const [selected, setSelected] = useState(value || '');
  useEffect(() => setSelected(value || ''), [value]);
  useEffect(() => {
    let active = true;
    setError('');
    const timer = setTimeout(() => {
      const requests = type === 'applications'
        ? ['screening', 'interview'].map(stage => axios.get('/api/applications', { params: { stage, q: query || undefined, per_page: 100 } }))
        : [axios.get(({ candidates: '/api/candidates', jobs: '/api/job-positions', interviewers: '/api/interviewers' })[type], { params: { q: query || undefined, per_page: 100, ...(type === 'jobs' ? { status: 'open' } : {}) } })];
      Promise.all(requests).then(responses => { if (active) setRows(responses.flatMap(response => response.data.data || [])); }).catch(error => {
        if (active) setError(error.response?.data?.message || 'Pilihan gagal dimuat. Coba lagi.');
      });
    }, 180);
    return () => { active = false; clearTimeout(timer); };
  }, [type, query, retry]);
  const name = { candidates: 'candidate_id', jobs: 'job_position_id', applications: 'application_id', interviewers: 'interviewer_id' }[type];
  return <><input aria-label={`Cari ${pretty(type)}`} placeholder={`Cari ${pretty(type).toLowerCase()}…`} value={query} onChange={event => setQuery(event.target.value)} /><select name={name} value={selected} onChange={event => setSelected(event.target.value)} required><option value="">Pilih…</option>{rows.map(row => <option value={row.id} key={row.id}>{row.candidate?.full_name || row.full_name || row.title || row.name}{row.application_code ? ` · ${row.application_code}` : ''}</option>)}</select>{error && <small className="option-error" role="alert">{error} <button type="button" className="link" onClick={() => setRetry(value => value + 1)}>Coba lagi</button></small>}</>;
}

function CreateForm({ page, item, applicationId, timezone, busy, fieldErrors, formError, close, submit }) {
  const fields = page === 'Job Positions'
    ? [['code', 'Kode', 'text'], ['title', 'Judul', 'text'], ['department', 'Departemen', 'text'], ['employment_type', 'Tipe', 'select:full_time,part_time,contract,internship'], ['location', 'Lokasi', 'text'], ['openings', 'Kuota', 'number'], ['description', 'Deskripsi', 'textarea'], ['requirements', 'Persyaratan', 'textarea'], ...(!item ? [['status', 'Status', 'select:draft,open,closed']] : [])]
    : page === 'Candidates'
      ? [['full_name', 'Nama lengkap', 'text'], ['email', 'Email', 'email'], ['phone', 'Telepon', 'text'], ['location', 'Lokasi', 'text'], ['education', 'Pendidikan', 'text'], ['years_experience', 'Pengalaman (tahun)', 'number'], ['skills', 'Keahlian', 'textarea'], ['experience_summary', 'Ringkasan pengalaman', 'textarea'], ['linkedin_url', 'LinkedIn', 'url'], ['portfolio_url', 'Portfolio', 'url']]
      : page === 'Applications'
        ? (item ? [['source', 'Sumber', 'select:manual,referral,job_portal,other'], ['applied_at', 'Tanggal melamar', 'date'], ['notes', 'Catatan', 'textarea']] : [['candidate_id', 'Kandidat', 'selectdata:candidates'], ['job_position_id', 'Posisi terbuka', 'selectdata:jobs'], ['source', 'Sumber', 'select:manual,referral,job_portal,other'], ['applied_at', 'Tanggal melamar', 'date'], ['notes', 'Catatan', 'textarea']])
        : (item ? [['interviewer_id', 'Interviewer', 'selectdata:interviewers'], ['start_at', 'Mulai', 'datetime-local'], ['end_at', 'Selesai', 'datetime-local'], ['mode', 'Mode', 'select:online,onsite'], ['meeting_link', 'Link meeting', 'url'], ['location', 'Lokasi', 'text'], ['notes', 'Catatan', 'textarea']] : [['application_id', 'Lamaran', 'selectdata:applications'], ['interviewer_id', 'Interviewer', 'selectdata:interviewers'], ['start_at', 'Mulai', 'datetime-local'], ['end_at', 'Selesai', 'datetime-local'], ['mode', 'Mode', 'select:online,onsite'], ['meeting_link', 'Link meeting', 'url'], ['location', 'Lokasi', 'text'], ['notes', 'Catatan', 'textarea']]);
  const [mode, setMode] = useState(item?.mode || 'online');
  const optional = new Set(['education', 'years_experience', 'skills', 'experience_summary', 'linkedin_url', 'portfolio_url', 'meeting_link', 'notes']);
  const [dateError, setDateError] = useState('');
  const title = item ? `Edit ${page === 'Job Positions' ? 'posisi' : page === 'Candidates' ? 'kandidat' : page === 'Applications' ? 'lamaran' : 'interview'}` : createLabels[page] || 'Tambah data';
  const Icon = { 'Job Positions': BriefcaseBusiness, Candidates: Users, Applications: FileText, 'Interview Schedule': CalendarDays }[page] || FileText;
  const descriptions = { 'Job Positions': 'Isi kebutuhan posisi, lokasi, dan persyaratan rekrutmen.', Candidates: 'Simpan profil kandidat. CV dapat diunggah melalui aksi CV setelah profil dibuat.', Applications: 'Hubungkan kandidat terdaftar dengan posisi yang sedang dibuka.', 'Interview Schedule': `Atur waktu, interviewer, dan mode interview. Zona waktu: ${timezone}.` };
  const initialValue = key => {
    if (key === 'application_id' && applicationId) return applicationId;
    if (key === 'applied_at' && !item) return dateInZone(new Date().toISOString(), timezone);
    if (['start_at', 'end_at'].includes(key) && item?.[key]) return zonedDateInput(item[key], timezone);
    return item?.[key] ?? '';
  };
  return <ActionPage title={title} description={descriptions[page]} icon={Icon} size="wide" close={close} busy={busy} error={formError}>
    <form className="modalform" onSubmit={event => { event.preventDefault(); setDateError(''); try { const values = Object.fromEntries(new FormData(event.currentTarget)); if (page === 'Interview Schedule') for (const key of ['start_at', 'end_at']) values[key] = localToUtc(values[key], timezone); submit(values); } catch (error) { setDateError(error.message); } }}>
      {formError && <div className="notice error" role="alert">{formError}</div>}{dateError && <div className="notice error" role="alert">{dateError}</div>}
      <div className="formgrid">{fields.map(([key, label, type]) => <label key={key} className={type === 'textarea' ? 'full' : ''}>{label}
        {type.startsWith('selectdata:') ? <SelectOptions type={type.slice(11)} value={initialValue(key)} />
          : type.startsWith('select:') ? <select name={key} defaultValue={initialValue(key)} required onChange={key === 'mode' ? event => setMode(event.target.value) : undefined}>{type.slice(7).split(',').map(value => <option key={value} value={value}>{pretty(value)}</option>)}</select>
            : type === 'textarea' ? <textarea name={key} defaultValue={initialValue(key)} required={['description', 'requirements'].includes(key)} maxLength={key === 'notes' ? 10000 : key === 'skills' ? 5000 : key === 'experience_summary' ? 10000 : undefined} />
            : <input name={key} type={type} defaultValue={initialValue(key)} required={key === 'meeting_link' ? page === 'Interview Schedule' && mode === 'online' : key === 'location' ? page === 'Job Positions' || (page === 'Interview Schedule' && mode === 'onsite') : !optional.has(key)} min={type === 'number' ? (key === 'openings' ? 1 : 0) : undefined} max={key === 'years_experience' ? 70 : key === 'applied_at' ? dateInZone(new Date().toISOString(), timezone) : undefined} minLength={{ code: 3, title: 3, department: 2, location: 2, phone: 6, full_name: 2 }[key]} maxLength={{ code: 50, title: 200, department: 150, location: 200, email: 255, phone: 30, full_name: 150 }[key] ?? (key.endsWith('_url') ? 500 : undefined)} />}
        {fieldErrors?.[key]?.map(message => <small className="field-error" key={message}>{message}</small>)}
      </label>)}</div><div className="formactions"><button type="button" className="secondary" onClick={close}>Batal</button><button className="primary" disabled={busy}>Simpan</button></div>
    </form>
  </ActionPage>;
}

hydrateRoot(document.getElementById('app'), <App />);
