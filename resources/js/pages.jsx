import React, { useEffect, useId, useRef, useState } from 'react';
import { api as axios } from './api.ts';
import { Ban, CalendarClock, CalendarDays, ChevronLeft, ChevronRight, ClipboardCheck, Download, Eye, FileText, FileUp, Pencil, ShieldCheck, Star, Trash2, UserRoundCheck, UserRoundX, Users, Workflow } from 'lucide-react';
import { addDays, dateInZone, dateLabel, localToUtc, weekRange } from './dates.js';

const STAGES = ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'];
const LINEAR_STAGES = STAGES.slice(0, 5);
const STAGE_DESCRIPTIONS = {
  applied: 'Lamaran baru diterima dan menunggu pemeriksaan administrasi awal.',
  screening: 'Kualifikasi kandidat sedang ditinjau. Interview pertama akan memindahkan lamaran ke tahap berikutnya.',
  interview: 'Tahap ini dimulai setelah interview berhasil dijadwalkan. Penilaian tersimpan pada riwayat lamaran.',
  offered: 'Penawaran kerja dikirim setelah evaluasi interview memenuhi persyaratan.',
  hired: 'Keputusan akhir diterima. Tahap Hired bersifat terminal dan tidak dapat diubah.',
  rejected: 'Lamaran ditolak dari tahap aktif. Alasan dan perubahan status tersimpan pada riwayat lamaran.',
};
const pretty = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
const uppercase = value => String(value ?? '').toLocaleUpperCase('id-ID');
const date = (value, timeZone) => value ? new Date(value.length === 10 ? `${value}T12:00:00Z` : value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: value.length === 10 ? 'UTC' : timeZone }) : '—';
const datetime = (value, timeZone) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone }) : '—';
const val = (row, path) => path.split('.').reduce((value, key) => value?.[key], row);

export default function PageContent(props) {
  const { page, data, user, timezone, filters, setFilter, mutate, openCreate, openSchedule, onEdit, onViewApplications, onDashboardMetric, pageNum, setPageNum, reload, busy = false, actionError, onRetry, onError, clearError } = props;
  const rows = data?.data || [];
  const [selected, setSelected] = useState(null);
  const [cvCandidate, setCvCandidate] = useState(null);
  useEffect(() => { setSelected(null); setCvCandidate(null); }, [page]);
  useEffect(() => {
    if (!selected || !['Job Positions', 'Candidates', 'Applications'].includes(page)) return;
    const route = page === 'Job Positions' ? 'job-positions' : page.toLowerCase();
    let active = true;
    axios.get(`/api/${route}/${selected.id}`).then(response => { if (active) setSelected(response.data.data); }).catch(error => { if (active) onError?.(error.response?.data?.message || 'Detail gagal dimuat.'); });
    return () => { active = false; };
  }, [page, reload, selected?.id, onError]);

  if (data == null && !['Reports & Analytics', 'Settings'].includes(page)) {
    return <div className={`panel request-state${actionError ? ' error-state' : ''}`} role={actionError ? 'alert' : 'status'}>
      <span>{actionError || 'Memuat data…'}</span>
      {actionError && <button type="button" className="secondary small" onClick={onRetry}>Coba lagi</button>}
    </div>;
  }

  if (page === 'Dashboard') return <Dashboard data={rows} filters={filters} setFilter={setFilter} timezone={timezone} onMetricClick={onDashboardMetric} />;
  if (['Job Positions', 'Candidates', 'Applications'].includes(page)) {
    const configs = {
      'Job Positions': { route: 'job-positions', cols: [['code', 'Kode'], ['title', 'Posisi'], ['department', 'Departemen'], ['employment_type', 'Tipe'], ['location', 'Lokasi'], ['openings', 'Kuota'], ['applicants_count', 'Lamaran'], ['status', 'Status'], ['created_at', 'Dibuat']], sorts: ['created_at', 'title', 'code'] },
      Candidates: { route: 'candidates', cols: [['full_name', 'Kandidat'], ['email', 'Email'], ['phone', 'Telepon'], ['location', 'Lokasi'], ['skills', 'Keahlian'], ['applications_count', 'Lamaran'], ['created_at', 'Dibuat']], sorts: ['created_at', 'full_name'] },
      Applications: { route: 'applications', cols: [['application_code', 'Kode'], ['candidate.full_name', 'Kandidat'], ['job_position.title', 'Posisi'], ['job_position.department', 'Departemen'], ['source', 'Sumber'], ['applied_at', 'Tanggal'], ['stage', 'Tahap'], ['updated_at', 'Diperbarui']], sorts: ['applied_at', 'created_at', 'updated_at'] },
    };
    const config = configs[page];
    const loadDetail = async row => { try { setSelected((await axios.get(`/api/${config.route}/${row.id}`)).data.data); } catch (error) { onError?.(error.response?.data?.message || 'Detail gagal dimuat.'); } };
    if (cvCandidate) return <CvUploadPage candidate={cvCandidate} busy={busy} close={() => setCvCandidate(null)} upload={file => { const body = new FormData(); body.append('cv', file); return mutate('post', `/api/candidates/${cvCandidate.id}/cv`, body, 'CV berhasil diunggah.'); }} />;
    if (selected) return <DetailPanel title={selected.full_name || selected.title || selected.application_code} close={() => setSelected(null)}>
      <DetailData data={selected} timeZone={timezone} />
      {page === 'Candidates' && <>
        <div className="detailhistory"><h3>Riwayat lamaran</h3>{(selected.applications || []).map(application => <article key={application.id}><b>{application.application_code} · {application.job_position?.title}</b><small>{pretty(application.stage)} · {date(application.applied_at, timezone)}</small></article>)}{!selected.applications?.length && <p className="empty">Kandidat belum memiliki lamaran.</p>}</div>
        <div className="toolbar">{selected.has_cv ? <><a className="secondary" href={`/api/candidates/${selected.id}/cv`}><Download size={15} />Unduh CV</a><button className="secondary" onClick={() => { clearError?.(); setCvCandidate(selected); }}><FileUp size={15} />Ganti CV</button><button className="link danger" onClick={() => mutate('delete', `/api/candidates/${selected.id}/cv`, undefined, 'CV kandidat berhasil dihapus.', { confirmation: { title: 'Hapus CV kandidat?', text: `Berkas CV milik ${selected.full_name} akan dihapus.`, confirmButtonText: 'Ya, hapus CV', danger: true } })}>Hapus CV</button></> : <button className="secondary" onClick={() => { clearError?.(); setCvCandidate(selected); }}><FileUp size={15} />Unggah CV</button>}</div>
      </>}
      {page === 'Applications' && <ApplicationHistory application={selected} timeZone={timezone} />}
      {page === 'Job Positions' && <div className="toolbar"><button className="secondary" onClick={() => { setSelected(null); onEdit(selected); }}>Edit posisi</button><button className="secondary" onClick={() => { const id = selected.id; setSelected(null); onViewApplications(id); }}>Lihat lamaran terkait ({selected.applicants_count || 0})</button></div>}
    </DetailPanel>;
    return <>
      <ListFilters page={page} filters={filters} setFilter={setFilter} />
      <Table rows={rows} cols={config.cols} total={data?.meta?.total} meta={data?.meta} onPage={setPageNum} sort={filters.sort_by} sortDir={filters.sort_dir} onSort={field => setFilter('sort_by', field, filters.sort_dir === 'asc' ? 'desc' : 'asc')} sortFields={config.sorts} timeZone={timezone} actions={row => <>
        <TableAction label={`Lihat detail ${page === 'Candidates' ? row.full_name : page === 'Job Positions' ? row.title : row.application_code}`} icon={Eye} onClick={() => loadDetail(row)} />
        {page !== 'Applications' && <TableAction label={`Edit ${page === 'Candidates' ? 'kandidat' : 'posisi'}`} icon={Pencil} onClick={() => onEdit(row)} />}
        {page === 'Applications' && !['hired', 'rejected'].includes(row.stage) && <TableAction label="Edit metadata lamaran" icon={Pencil} onClick={() => onEdit(row)} />}
        {page === 'Job Positions' && <><label className="inline-label">Status<select aria-label={`Status ${row.code}`} value={row.status} onChange={event => { const status = event.target.value; mutate('patch', `/api/job-positions/${row.id}/status`, { status }, 'Status posisi diperbarui.', { confirmation: { title: 'Ubah status posisi?', text: `Status ${row.title} akan diubah menjadi ${pretty(status)}.`, confirmButtonText: 'Simpan status' } }); }}><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select></label><TableAction label="Hapus posisi" icon={Trash2} tone="danger" onClick={() => mutate('delete', `/api/job-positions/${row.id}`, undefined, 'Posisi berhasil dihapus.', { confirmation: { title: 'Hapus posisi ini?', text: 'Posisi yang sudah memiliki lamaran tidak dapat dihapus.', confirmButtonText: 'Ya, hapus posisi', danger: true } })} /></>}
        {page === 'Candidates' && <><TableAction label="Unggah atau ganti CV" icon={FileUp} onClick={() => { clearError?.(); setCvCandidate(row); }} />{row.has_cv && <><TableAction label="Unduh CV" icon={Download} href={`/api/candidates/${row.id}/cv`} /><TableAction label="Hapus CV" icon={Trash2} tone="danger" onClick={() => mutate('delete', `/api/candidates/${row.id}/cv`, undefined, 'CV kandidat berhasil dihapus.', { confirmation: { title: 'Hapus CV kandidat?', text: `Berkas CV milik ${row.full_name} akan dihapus.`, confirmButtonText: 'Ya, hapus CV', danger: true } })} /></>}<TableAction label="Hapus kandidat" icon={Trash2} tone="danger" onClick={() => mutate('delete', `/api/candidates/${row.id}`, undefined, 'Kandidat berhasil dihapus.', { confirmation: { title: 'Hapus kandidat?', text: 'Profil kandidat akan dihapus jika belum memiliki lamaran.', confirmButtonText: 'Ya, hapus kandidat', danger: true } })} /></>}
      </>} />
    </>;
  }
  if (page === 'Hiring Pipeline') return <Pipeline groups={rows} filters={filters} setFilter={setFilter} mutate={mutate} openSchedule={openSchedule} timezone={timezone} busy={busy} actionError={actionError} clearError={clearError} reload={reload} />;
  if (page === 'Interview Schedule') return <InterviewPage rows={rows} meta={data?.meta} setPageNum={setPageNum} filters={filters} setFilter={setFilter} timezone={timezone} mutate={mutate} openCreate={openCreate} onEdit={onEdit} busy={busy} actionError={actionError} clearError={clearError} />;
  if (page === 'Reports & Analytics') return <Reports reload={reload} timezone={timezone} />;
  if (page === 'Settings') return <SettingsPage user={user} timezone={timezone} mutate={mutate} reload={reload} busy={busy} actionError={actionError} clearError={clearError} />;
  return null;
}

function Dashboard({ data, filters, setFilter, timezone, onMetricClick }) {
  const [start, setStart] = useState(data.period?.start_date || '');
  const [end, setEnd] = useState(data.period?.end_date || '');
  const [periodError, setPeriodError] = useState('');
  useEffect(() => { if (!filters.start_date && !filters.end_date && data.period) { setStart(data.period.start_date); setEnd(data.period.end_date); } }, [data.period?.start_date, data.period?.end_date]);
  function apply() {
    if (Boolean(start) !== Boolean(end)) { setPeriodError('Isi kedua tanggal periode atau kosongkan keduanya.'); return; }
    if (start && end && start > end) { setPeriodError('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.'); return; }
    setPeriodError(''); setFilter('start_date', start || undefined); setFilter('end_date', end || undefined);
  }
  const periodFilters = data.period?.start_date && data.period?.end_date ? { start_date: data.period.start_date, end_date: data.period.end_date } : {};
  const metrics = [
    { label: 'Total kandidat', value: data.total_candidates, scope: 'Seluruh kandidat', page: 'Candidates', filters: {} },
    { label: 'Posisi terbuka', value: data.active_job_positions, scope: 'Status lowongan: open', page: 'Job Positions', filters: { status: 'open' } },
    { label: 'Total lamaran', value: data.total_applications, scope: 'Periode yang dipilih', page: 'Applications', filters: periodFilters },
    { label: 'Interview mendatang', value: data.upcoming_scheduled_interviews, scope: 'Jadwal 14 hari ke depan', page: 'Interview Schedule', filters: { status: 'scheduled', upcoming: 1 } },
    { label: 'Lamaran diterima', value: data.hired_applications, scope: 'Tahap hired · periode dipilih', page: 'Applications', filters: { ...periodFilters, stage: 'hired' } },
    { label: 'Lamaran ditolak', value: data.rejected_applications, scope: 'Tahap rejected · periode dipilih', page: 'Applications', filters: { ...periodFilters, stage: 'rejected' } },
  ];
  return <>
    <div className="toolbar dashboardfilters"><div className="dashboardrange"><CalendarDays size={20} aria-hidden="true" /><div><strong>Rentang tanggal</strong><span>Sesuaikan ringkasan dashboard</span></div></div><label>Dari<input type="date" value={start} onChange={event => setStart(event.target.value)} /></label><label>Sampai<input type="date" value={end} onChange={event => setEnd(event.target.value)} /></label><div className="filter-actions"><button className="primary" onClick={apply}>Terapkan</button><button type="button" className="secondary small filter-reset" onClick={() => { setStart(data.period?.start_date || ''); setEnd(data.period?.end_date || ''); setPeriodError(''); setFilter('start_date', undefined); setFilter('end_date', undefined); }}>Reset</button></div>{periodError && <small className="field-error dashboardfiltererror">{periodError}</small>}</div>
    <div className="dashboardmetrics">{metrics.map(metric => <DashboardMetric key={metric.label} {...metric} onClick={() => onMetricClick(metric.page, metric.filters)} />)}</div>
    <div className="dashboardgrid"><section className="panel"><div className="sectionhead"><div><h2>Pipeline rekrutmen</h2><p>Distribusi lamaran pada periode terpilih</p></div><span className="statuspill">{data.total_applications || 0} lamaran</span></div><StageChart rows={data.stage_distribution || []} /><h2 className="subhead">Lamaran per posisi</h2><SimpleTable rows={data.applications_by_position || []} cols={['title', 'total']} labels={['Posisi', 'Lamaran']} emptyText="Belum ada lamaran per posisi pada rentang tanggal ini." /></section>
      <section className="panel"><div className="sectionhead"><div><h2>Interview mendatang</h2><p>Jadwal terdekat dalam 14 hari ke depan</p></div><span className="countpill">{data.upcoming_interviews?.length || 0}</span></div>{(data.upcoming_interviews || []).map(interview => <article className="scheduleitem" key={interview.id}><span className="schedule-time">{datetime(interview.start_at, timezone)}</span><b>{interview.application?.candidate?.full_name || 'Kandidat'}</b><small>{interview.application?.job_position?.title} · {interview.interviewer?.name}</small><span className="badge">{pretty(interview.mode)}</span></article>)}{!data.upcoming_interviews?.length && <p className="empty">Belum ada interview terjadwal dalam 14 hari ke depan.</p>}</section>
      <section className="panel widepanel"><div className="sectionhead"><div><h2>Lamaran terbaru</h2><p>Lamaran pada periode yang dipilih</p></div></div><SimpleTable rows={data.recent_applications || []} cols={['application_code', 'candidate.full_name', 'job_position.title', 'stage', 'applied_at']} labels={['Kode', 'Kandidat', 'Posisi', 'Tahap', 'Tanggal']} timeZone={timezone} emptyText="Belum ada lamaran baru pada rentang tanggal ini." /></section>
    </div>
  </>;
}

function ListFilters({ page, filters, setFilter }) {
  const positions = useOptions('/api/job-positions', { per_page: 100 }, ['Applications', 'Interview Schedule'].includes(page));
  const users = useOptions('/api/interviewers', {}, page === 'Interview Schedule');
  const label = page === 'Job Positions' ? 'Filter lowongan' : page === 'Candidates' ? 'Filter kandidat' : page === 'Applications' ? 'Filter lamaran' : 'Filter interview';
  const resetFilters = () => ['department', 'employment_type', 'status', 'location', 'job_position_id', 'stage', 'interviewer_id', 'start_date', 'end_date'].forEach(key => setFilter(key, undefined));
  return <div className={`filterbar${page === 'Applications' ? ' applicationfilters' : page === 'Interview Schedule' ? ' interviewfilters' : ''}`}><strong>{label}</strong>
    {page === 'Job Positions' && <><input aria-label="Departemen" placeholder="Departemen" value={filters.department || ''} onChange={event => setFilter('department', event.target.value)} /><select aria-label="Tipe pekerjaan" value={filters.employment_type || ''} onChange={event => setFilter('employment_type', event.target.value)}><option value="">Semua tipe</option>{['full_time', 'part_time', 'contract', 'internship'].map(type => <option key={type} value={type}>{pretty(type)}</option>)}</select><select aria-label="Status posisi" value={filters.status || ''} onChange={event => setFilter('status', event.target.value)}><option value="">Semua status</option>{['open', 'draft', 'closed'].map(status => <option key={status}>{status}</option>)}</select></>}
    {page === 'Candidates' && <input aria-label="Lokasi" placeholder="Lokasi kandidat" value={filters.location || ''} onChange={event => setFilter('location', event.target.value)} />}
    {page === 'Applications' && <><select aria-label="Posisi" value={filters.job_position_id || ''} onChange={event => setFilter('job_position_id', event.target.value)}><option value="">Semua posisi</option>{positions.rows.map(position => <option key={position.id} value={position.id}>{position.title}</option>)}</select><select aria-label="Tahap" value={filters.stage || ''} onChange={event => setFilter('stage', event.target.value)}><option value="">Semua tahap</option>{STAGES.map(stage => <option key={stage} value={stage}>{pretty(stage)}</option>)}</select><DateFilters filters={filters} setFilter={setFilter} onReset={resetFilters} /></>}
    {page === 'Interview Schedule' && <><select aria-label="Posisi" value={filters.job_position_id || ''} onChange={event => setFilter('job_position_id', event.target.value)}><option value="">Semua posisi</option>{positions.rows.map(position => <option key={position.id} value={position.id}>{position.title}</option>)}</select><select aria-label="Interviewer" value={filters.interviewer_id || ''} onChange={event => setFilter('interviewer_id', event.target.value)}><option value="">Semua interviewer</option>{users.rows.map(person => <option key={person.id} value={person.id}>{person.name}</option>)}</select><select aria-label="Status interview" value={filters.status || ''} onChange={event => setFilter('status', event.target.value)}><option value="">Semua status</option>{['scheduled', 'completed', 'cancelled'].map(status => <option key={status} value={status}>{pretty(status)}</option>)}</select><DateFilters filters={filters} setFilter={setFilter} onReset={resetFilters} /></>}
    {(positions.error || users.error) && <div className="option-error" role="alert">{positions.error || users.error}<button type="button" className="link" onClick={positions.error ? positions.retry : users.retry}>Coba lagi</button></div>}
    {!['Applications', 'Interview Schedule'].includes(page) && <button type="button" className="secondary small filter-reset" onClick={resetFilters}>Reset</button>}
  </div>;
}

function DateFilters({ filters, setFilter, onReset }) {
  const [start, setStart] = useState(filters.start_date || '');
  const [end, setEnd] = useState(filters.end_date || '');
  useEffect(() => { setStart(filters.start_date || ''); setEnd(filters.end_date || ''); }, [filters.start_date, filters.end_date]);
  const valid = Boolean(start) === Boolean(end) && (!start || start <= end);
  return <><label className="compactfield">Dari<input aria-label="Tanggal mulai" type="date" value={start} onChange={event => setStart(event.target.value)} /></label><label className="compactfield">Sampai<input aria-label="Tanggal akhir" type="date" value={end} onChange={event => setEnd(event.target.value)} /></label><div className="filter-actions"><button className="primary small filter-apply" disabled={!valid} onClick={() => { setFilter('start_date', start || undefined); setFilter('end_date', end || undefined); }}>Terapkan</button><button type="button" className="secondary small filter-reset" onClick={onReset}>Reset</button></div></>;
}

function Pipeline({ groups, filters, setFilter, mutate, openSchedule, timezone, busy, actionError, clearError, reload }) {
  const positions = useOptions('/api/job-positions', { per_page: 100 }, true);
  const [rejecting, setRejecting] = useState(null);
  const [reason, setReason] = useState('');
  const [rejectError, setRejectError] = useState('');
  const [stageDetail, setStageDetail] = useState(null);
  const [stagePage, setStagePage] = useState(1);
  const [stageTransactions, setStageTransactions] = useState({ rows: [], meta: null });
  const [stageLoading, setStageLoading] = useState(false);
  const [stageError, setStageError] = useState('');
  const [transaction, setTransaction] = useState(null);
  const transactionRequest = useRef(0);

  useEffect(() => {
    if (!stageDetail) return;
    let active = true;
    setStageLoading(true);
    setStageError('');
    axios.get('/api/applications', { params: { stage: stageDetail, job_position_id: filters.job_position_id || undefined, q: filters.q || undefined, page: stagePage, per_page: 10, sort_by: 'updated_at', sort_dir: 'desc' } })
      .then(response => { if (active) setStageTransactions({ rows: response.data.data || [], meta: response.data.meta || null }); })
      .catch(error => { if (active) { setStageTransactions({ rows: [], meta: null }); setStageError(error.response?.data?.message || 'Transaksi tahap ini gagal dimuat.'); } })
      .finally(() => { if (active) setStageLoading(false); });
    return () => { active = false; };
  }, [stageDetail, stagePage, filters.job_position_id, filters.q, reload]);

  const confirmTransition = (application, toStage) => {
    const text = toStage === 'offered'
      ? 'Tahap ini membutuhkan evaluasi interview lengkap dan tidak boleh ada jadwal interview aktif.'
      : toStage === 'hired'
        ? 'Lamaran akan menjadi tahap terminal. Interview terjadwal harus diselesaikan atau dibatalkan terlebih dahulu.'
        : 'Perubahan tahap akan dicatat pada riwayat lamaran.';
    mutate('patch', `/api/applications/${application.id}/stage`, { from_stage: application.stage, to_stage: toStage }, `Lamaran dipindahkan ke ${pretty(toStage)}.`, {
      confirmation: { title: `Pindahkan ke ${pretty(toStage)}?`, text, confirmButtonText: `Ya, pindahkan ke ${pretty(toStage)}` },
    });
  };
  const openStage = stage => { setStageDetail(stage); setStagePage(1); setTransaction(null); };
  async function openTransaction(application) {
    const request = ++transactionRequest.current;
    setTransaction({ row: application, data: null, loading: true, error: '' });
    try {
      const response = await axios.get(`/api/applications/${application.id}`);
      if (request === transactionRequest.current) setTransaction({ row: application, data: response.data.data, loading: false, error: '' });
    } catch (error) {
      if (request === transactionRequest.current) setTransaction({ row: application, data: null, loading: false, error: error.response?.data?.message || 'Detail transaksi gagal dimuat.' });
    }
  }
  const closeTransaction = () => { transactionRequest.current += 1; setTransaction(null); };

  if (rejecting) return <ActionPage title="Tolak lamaran" description="Catatan penolakan disimpan bersama riwayat tahapan." icon={ShieldCheck} close={() => setRejecting(null)} busy={busy} error={actionError}><p>Alasan penolakan wajib diisi sesuai aturan workflow.</p>{rejectError && <div className="notice error">{rejectError}</div>}<form onSubmit={async event => { event.preventDefault(); if (!reason.trim()) { setRejectError('Alasan penolakan wajib diisi.'); return; } const ok = await mutate('patch', `/api/applications/${rejecting.id}/stage`, { from_stage: rejecting.stage, to_stage: 'rejected', note: reason.trim() }, 'Lamaran ditolak dan riwayatnya diperbarui.', { confirmation: { title: 'Tolak lamaran ini?', text: `Lamaran ${rejecting.application_code} akan dipindahkan ke tahap Rejected.`, confirmButtonText: 'Ya, tolak lamaran', danger: true } }); if (ok) setRejecting(null); }}><label>Alasan penolakan<textarea autoFocus required value={reason} onChange={event => setReason(event.target.value)} maxLength={10000} /></label><div className="formactions"><button className="secondary" type="button" disabled={busy} onClick={() => setRejecting(null)}>Batal</button><button className="primary dangerbutton" disabled={busy}>Konfirmasi penolakan</button></div></form></ActionPage>;
  if (transaction) return <PipelineTransactionPage transaction={transaction} stage={stageDetail} timezone={timezone} onBack={closeTransaction} onRetry={() => openTransaction(transaction.row)} />;
  if (stageDetail) return <PipelineStagePage stage={stageDetail} rows={stageTransactions.rows} meta={stageTransactions.meta} loading={stageLoading} error={stageError} onPage={setStagePage} onBack={() => setStageDetail(null)} onOpenTransaction={openTransaction} />;

  return <>
    <div className="filterbar pipelinefilters"><strong><Workflow size={17} aria-hidden="true" /> Pipeline kandidat</strong><select aria-label="Filter posisi pipeline" value={filters.job_position_id || ''} onChange={event => setFilter('job_position_id', event.target.value)}><option value="">Semua posisi</option>{positions.rows.map(position => <option key={position.id} value={position.id}>{position.title}</option>)}</select><input type="search" aria-label="Cari kandidat atau kode lamaran" placeholder="Cari nama kandidat atau kode lamaran" value={filters.q || ''} onChange={event => setFilter('q', event.target.value)} /><span>Maks. 10 kartu per tahap · klik tahap untuk melihat semua</span><button type="button" className="secondary small filter-reset" onClick={() => { setFilter('job_position_id', undefined); setFilter('q', undefined); }}>Reset</button>{positions.error && <div className="option-error" role="alert">{positions.error}<button type="button" className="link" onClick={positions.retry}>Coba lagi</button></div>}</div>
    <div className="notice pipeline-notice"><ShieldCheck size={17} aria-hidden="true" /><span>Perpindahan mengikuti aturan workflow. Interview dimulai setelah jadwal berhasil dibuat; Hired dan Rejected adalah tahap terminal.</span></div>
    <div className="pipeline-track" role="region" aria-label="Tahapan hiring pipeline dan lamaran terkait" tabIndex={0}>
      <ol className="pipelineboard" aria-label="Tahapan dan lamaran pipeline">{STAGES.map(stage => <li className={`pipeline-step pipeline-step-${stage}`} key={stage}>
        <button type="button" className="pipeline-step-button" onClick={() => openStage(stage)} aria-label={`Buka detail tahap ${pretty(stage)}, ${groups[stage]?.total || 0} lamaran`}>
          <span className="stagecount">{stage === 'rejected' ? 'R' : LINEAR_STAGES.indexOf(stage) + 1}</span><b>{pretty(stage)}</b><small>{stage === 'rejected' ? 'Alternatif terminal · ' : ''}{(groups[stage]?.total || 0).toLocaleString('id-ID')} lamaran</small>
        </button>
        <section className={`kanbancol kanbancol-${stage}`} aria-label={`${pretty(stage)}: ${groups[stage]?.total || 0} lamaran`}>
        {(groups[stage]?.applications || []).map(application => <article className="candidatecard dealcard" key={application.id}><div className="dealhead"><b>{application.candidate?.full_name || 'Kandidat'}</b><span className="dealcode">{application.application_code}</span></div><p className="dealposition">{application.jobPosition?.title || application.job_position?.title || 'Posisi belum tersedia'}</p><div className="dealmeta"><span><small>Tanggal masuk</small>{date(application.applied_at, timezone)}</span><span><small>Terakhir diperbarui</small>{datetime(application.stage_updated_at, timezone)}</span></div><div className="dealactions">
          {stage === 'applied' && <button className="primary small" disabled={busy} onClick={() => confirmTransition(application, 'screening')}>Lanjut ke Screening</button>}
          {stage === 'screening' && <button className="primary small" disabled={busy} onClick={() => openSchedule(application.id)}>Jadwalkan interview</button>}
          {stage === 'interview' && <button className="primary small" disabled={busy} onClick={() => confirmTransition(application, 'offered')}>Kirim ke Offered</button>}
          {stage === 'offered' && <button className="primary small" disabled={busy} onClick={() => confirmTransition(application, 'hired')}>Tandai Hired</button>}
          {!['hired', 'rejected'].includes(stage) && <button type="button" className="secondary small dealreject" disabled={busy} onClick={() => { clearError?.(); setRejecting(application); setReason(''); setRejectError(''); }}><Ban size={14} aria-hidden="true" />Reject kandidat</button>}
        </div></article>)}{!groups[stage]?.applications?.length && <div className="pipeline-empty"><Users size={18} aria-hidden="true" /><span>Belum ada lamaran di tahap ini.</span></div>}
        </section>
      </li>)}</ol>
    </div>
  </>;
}

function PipelineStagePage({ stage, rows, meta, loading, error, onPage, onBack, onOpenTransaction }) {
  const stageIndex = LINEAR_STAGES.indexOf(stage);
  return <>
    <div className="pipeline-detailback"><button type="button" className="secondary" onClick={onBack}><ChevronLeft size={16} aria-hidden="true" />Kembali ke Pipeline</button></div>
    <header className="pagehead pipeline-detailhead"><div><div className="crumb">HIRING PIPELINE <span>›</span> DETAIL TAHAP</div><h1>{pretty(stage)}</h1><p>{STAGE_DESCRIPTIONS[stage]}</p></div><span className={`badge ${stage} pipeline-stage-badge`}>{stageIndex >= 0 ? `TAHAP ${stageIndex + 1} DARI 5` : 'TAHAP TERMINAL ALTERNATIF'}</span></header>
    <section className="panel pipeline-transactions"><div className="sectionhead"><div><h2>Transaksi pada tahap {pretty(stage)}</h2><p>Lamaran yang saat ini berada di tahap ini beserta riwayat perpindahannya.</p></div><span className="countpill">{(meta?.total || 0).toLocaleString('id-ID')} lamaran</span></div>
      {loading ? <p className="loading" role="status">Memuat transaksi tahap...</p> : error ? <div className="notice error" role="alert">{error}</div> : <Table rows={rows} total={meta?.total || 0} meta={meta} onPage={onPage} cols={[
        ['application_code', 'Kode lamaran'], ['candidate.full_name', 'Kandidat'], ['job_position.title', 'Posisi'], ['applied_at', 'Tanggal masuk'], ['stage_updated_at', 'Terakhir diperbarui'],
      ]} emptyText={`Belum ada lamaran pada tahap ${pretty(stage)}.`} actions={application => <TableAction label="Lihat detail transaksi" icon={Eye} onClick={() => onOpenTransaction(application)} />} />}
    </section>
  </>;
}

function PipelineTransactionPage({ transaction, stage, timezone, onBack, onRetry }) {
  const application = transaction.data || transaction.row;
  const candidate = application.candidate;
  const position = application.jobPosition || application.job_position;
  return <>
    <div className="pipeline-detailback"><button type="button" className="secondary" onClick={onBack}><ChevronLeft size={16} aria-hidden="true" />Kembali ke {pretty(stage)}</button></div>
    <header className="pagehead pipeline-detailhead"><div><div className="crumb">HIRING PIPELINE <span>›</span> TRANSAKSI LAMARAN</div><h1>{application.application_code}</h1><p>{candidate?.full_name || 'Kandidat'} · {position?.title || 'Posisi tidak tersedia'}</p></div><span className={`badge ${application.stage}`}>{pretty(application.stage)}</span></header>
    <div className="pipeline-detailgrid">
      <section className="panel"><div className="sectionhead"><div><h2>Ringkasan transaksi</h2><p>Identitas lamaran dan tahap aktif.</p></div></div><dl className="detaildata"><dt>Kandidat</dt><dd>{candidate?.full_name || '—'}</dd><dt>Email</dt><dd>{candidate?.email || '—'}</dd><dt>Posisi</dt><dd>{position?.title || '—'}</dd><dt>Departemen</dt><dd>{position?.department || '—'}</dd><dt>Sumber</dt><dd>{pretty(application.source)}</dd><dt>Tanggal masuk</dt><dd>{date(application.applied_at, timezone)}</dd><dt>Tahap aktif</dt><dd>{pretty(application.stage)}</dd><dt>Diperbarui</dt><dd>{datetime(application.stage_updated_at, timezone)}</dd></dl></section>
      <section className="panel"><div className="sectionhead"><div><h2>Riwayat transaksi tahap</h2><p>Catatan perpindahan tahap dan evaluasi interview.</p></div></div>{transaction.loading ? <p className="loading" role="status">Memuat riwayat transaksi...</p> : transaction.error ? <div className="notice error" role="alert">{transaction.error}<button type="button" className="secondary small" onClick={onRetry}>Coba lagi</button></div> : <ApplicationHistory application={application} timeZone={timezone} />}</section>
    </div>
  </>;
}

function InterviewPage({ rows, meta, setPageNum, filters, setFilter, timezone, mutate, openCreate, onEdit, busy, actionError, clearError }) {
  const [view, setView] = useState('list');
  const [week, setWeek] = useState(new Date());
  const [events, setEvents] = useState([]);
  const [eventsLoading, setEventsLoading] = useState(false);
  const [eventsError, setEventsError] = useState('');
  const [calendarReload, setCalendarReload] = useState(0);
  const [selected, setSelected] = useState(null);
  const [action, setAction] = useState(null);
  const [evaluation, setEvaluation] = useState({ rating: '5', recommendation: 'recommended', feedback: '' });
  const [cancelReason, setCancelReason] = useState('');
  const range = weekRange(week, timezone);
  useEffect(() => {
    if (view !== 'calendar') return;
    let active = true;
    setEventsLoading(true);
    setEventsError('');
    Promise.all([localToUtc(`${range.start}T00:00`, timezone), localToUtc(`${range.end}T00:00`, timezone)])
      .then(([start_at, end_at]) => axios.get('/api/interviews/calendar', { params: { start_at, end_at } }))
      .then(response => { if (active) setEvents(response.data.data || []); })
      .catch(error => {
        if (!active) return;
        setEvents([]);
        setEventsError(error.response?.data?.message || 'Kalender gagal dimuat. Periksa koneksi lalu coba lagi.');
      })
      .finally(() => { if (active) setEventsLoading(false); });
    return () => { active = false; };
  }, [view, week, timezone, range.start, range.end, calendarReload]);
  const columns = [['application.candidate.full_name', 'Kandidat'], ['application.job_position.title', 'Posisi'], ['interviewer.name', 'Interviewer'], ['start_at', 'Mulai'], ['end_at', 'Selesai'], ['mode', 'Mode'], ['status', 'Status']];
  async function openDetail(interview) { try { setSelected((await axios.get(`/api/interviews/${interview.id}`)).data.data); } catch { setSelected(interview); } }
  if (action?.type === 'complete') return <ActionPage title="Evaluasi interview" description="Isi penilaian sebelum memperbarui keputusan pada pipeline." icon={Star} close={() => setAction(null)} busy={busy} error={actionError}><form onSubmit={async event => { event.preventDefault(); const ok = await mutate('patch', `/api/interviews/${action.interview.id}/complete`, { ...evaluation, rating: Number(evaluation.rating), feedback: evaluation.feedback.trim() }, 'Evaluasi interview berhasil disimpan.', { confirmation: { title: 'Simpan evaluasi interview?', text: 'Nilai, rekomendasi, dan feedback akan disimpan pada riwayat lamaran.', confirmButtonText: 'Ya, simpan evaluasi' } }); if (ok) setAction(null); }}><label>Rating<select value={evaluation.rating} onChange={event => setEvaluation({ ...evaluation, rating: event.target.value })}>{[1, 2, 3, 4, 5].map(rating => <option key={rating} value={rating}>{rating} / 5</option>)}</select></label><label>Rekomendasi<select value={evaluation.recommendation} onChange={event => setEvaluation({ ...evaluation, recommendation: event.target.value })}>{['recommended', 'not_recommended', 'hold'].map(value => <option key={value} value={value}>{pretty(value)}</option>)}</select></label><label>Feedback<textarea required minLength={1} maxLength={10000} value={evaluation.feedback} onChange={event => setEvaluation({ ...evaluation, feedback: event.target.value })} /></label><div className="formactions"><button type="button" className="secondary" disabled={busy} onClick={() => setAction(null)}>Batal</button><button className="primary" disabled={busy}>Simpan evaluasi</button></div></form></ActionPage>;
  if (action?.type === 'cancel') return <ActionPage title="Batalkan interview" description="Jadwal akan ditandai dibatalkan dan alasan dicatat bila diisi." icon={CalendarDays} close={() => setAction(null)} busy={busy} error={actionError}><form onSubmit={async event => { event.preventDefault(); const ok = await mutate('patch', `/api/interviews/${action.interview.id}/cancel`, { reason: cancelReason.trim() || undefined }, 'Jadwal interview berhasil dibatalkan.', { confirmation: { title: 'Batalkan jadwal interview?', text: 'Jadwal tidak dapat digunakan lagi. Riwayat lamaran akan tetap tersimpan.', confirmButtonText: 'Ya, batalkan jadwal', danger: true } }); if (ok) setAction(null); }}><p>Status lamaran tetap berada pada tahap Interview.</p><label>Alasan (opsional)<textarea maxLength={2000} value={cancelReason} onChange={event => setCancelReason(event.target.value)} /></label><div className="formactions"><button type="button" className="secondary" disabled={busy} onClick={() => setAction(null)}>Kembali</button><button className="primary dangerbutton" disabled={busy}>Batalkan jadwal</button></div></form></ActionPage>;
  if (selected) return <DetailPanel title={selected.application?.candidate?.full_name || 'Detail interview'} close={() => setSelected(null)}><DetailData data={selected} timeZone={timezone} /><h3>{selected.application?.job_position?.title}</h3><p>Interviewer: {selected.interviewer?.name}</p>{selected.feedback && <div className="evaluation"><b><Star size={15} /> {selected.rating}/5 · {pretty(selected.recommendation)}</b><p>{selected.feedback}</p></div>}</DetailPanel>;
  return <>
    <ListFilters page="Interview Schedule" filters={filters} setFilter={setFilter} />
    <div className="toolbar interviewtoolbar"><div className="interviewviewtoggle" role="group" aria-label="Tampilan jadwal interview"><button type="button" className={view === 'list' ? 'secondary selected' : 'secondary'} aria-pressed={view === 'list'} onClick={() => setView('list')}>Daftar</button><button type="button" className={view === 'calendar' ? 'secondary selected' : 'secondary'} aria-pressed={view === 'calendar'} onClick={() => setView('calendar')}>Kalender</button></div>{view === 'calendar' && <><button className="iconbtn" aria-label="Minggu sebelumnya" onClick={() => setWeek(new Date(week.getTime() - 7 * 86400000))}><ChevronLeft size={16} /></button><span>{date(range.start, timezone)} – {date(addDays(range.end, -1), timezone)}</span><button className="iconbtn" aria-label="Minggu berikutnya" onClick={() => setWeek(new Date(week.getTime() + 7 * 86400000))}><ChevronRight size={16} /></button></>}<span className="interviewtimezone">Waktu: {timezone}</span></div>
    {view === 'list' ? (
      <Table rows={rows} meta={meta} total={meta?.total} onPage={setPageNum} cols={columns} timeZone={timezone} sort={filters.sort_by} sortDir={filters.sort_dir} onSort={field => setFilter('sort_by', field, filters.sort_dir === 'asc' ? 'desc' : 'asc')} sortFields={['start_at', 'created_at']} actions={interview => <>
        <TableAction label="Lihat detail interview" icon={Eye} onClick={() => openDetail(interview)} />
        {interview.status === 'scheduled' && <><TableAction label="Jadwalkan ulang interview" icon={CalendarClock} onClick={() => onEdit(interview)} />{new Date(interview.end_at) <= new Date() && <TableAction label="Evaluasi interview" icon={ClipboardCheck} onClick={() => { clearError?.(); setAction({ type: 'complete', interview }); setEvaluation({ rating: '5', recommendation: 'recommended', feedback: '' }); }} />}<TableAction label="Batalkan interview" icon={Ban} tone="danger" onClick={() => { clearError?.(); setAction({ type: 'cancel', interview }); setCancelReason(''); }} /></>}
      </>} />
    ) : (
      eventsError ? <div className="panel request-state error-state" role="alert"><span>{eventsError}</span><button type="button" className="secondary small" onClick={() => setCalendarReload(value => value + 1)}>Coba lagi</button></div>
        : eventsLoading ? <div className="panel request-state" role="status">Memuat jadwal interview…</div>
          : <div className="weekgrid">{Array.from({ length: 7 }, (_, index) => { const day = addDays(range.start, index); const dayEvents = events.filter(event => dateInZone(event.start_at, timezone) === day); return <section key={day}><header>{dateLabel(day, timezone)}</header>{dayEvents.map(interview => <button className={`calendarevent ${interview.status}`} key={interview.id} onClick={() => openDetail(interview)}><b>{new Date(interview.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: timezone })}</b><span>{interview.application?.candidate?.full_name}</span><small>{interview.application?.job_position?.title} · {pretty(interview.status)}</small></button>)}{!dayEvents.length && <small className="calempty">Tidak ada jadwal</small>}</section>; })}</div>
    )}
  </>;
}

function Reports({ reload, timezone }) {
  const [tab, setTab] = useState('summary');
  const [report, setReport] = useState(null);
  const [positionMeta, setPositionMeta] = useState(null);
  const [positionsPage, setPositionsPage] = useState(1);
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [position, setPosition] = useState('');
  const [department, setDepartment] = useState('');
  const [group, setGroup] = useState('weekly');
  const [applied, setApplied] = useState({});
  const [filterError, setFilterError] = useState('');
  const [reportLoading, setReportLoading] = useState(true);
  const [reportError, setReportError] = useState('');
  const [retry, setRetry] = useState(0);
  const positionOptions = useOptions('/api/job-positions', { per_page: 100 });
  useEffect(() => {
    let active = true;
    setReportLoading(true);
    setReportError('');
    const params = { ...applied };
    const requests = tab === 'summary' ? Promise.all([
      axios.get('/api/reports/summary', { params }),
      axios.get('/api/reports/metrics', { params }),
      axios.get('/api/reports/stages', { params }),
      axios.get('/api/reports/positions', { params: { ...params, page: 1, per_page: 10 } }),
      axios.get('/api/reports/trends', { params: { ...params, group_by: group } }),
    ]).then(([summary, metrics, stages, positions, trends]) => ({
      ...summary.data.data,
      ...metrics.data.data,
      stages: stages.data.data || [],
      positions: positions.data.data || [],
      trends: trends.data.data || [],
      positionMeta: positions.data.meta || null,
    })) : tab === 'metrics' ? Promise.all([
      axios.get('/api/reports/summary', { params }),
      axios.get('/api/reports/metrics', { params }),
      axios.get('/api/reports/stages', { params }),
    ]).then(([summary, metrics, stages]) => ({ ...summary.data.data, ...metrics.data.data, stages: stages.data.data || [] })) : axios.get(`/api/reports/${tab}`, {
      params: { ...params, ...(tab === 'trends' ? { group_by: group } : {}), ...(tab === 'positions' ? { page: positionsPage, per_page: 10 } : {}) },
    }).then(response => tab === 'positions' ? { positions: response.data.data || [], meta: response.data.meta || null } : response.data.data);
    requests.then(data => {
      if (!active) return;
      setReport(data?.data ?? data);
      setPositionMeta(data?.meta || data?.positionMeta || null);
    }).catch(error => {
      if (!active) return;
      setReport(null);
      setPositionMeta(null);
      setReportError(error.response?.data?.message || 'Laporan gagal dimuat. Periksa koneksi lalu coba lagi.');
    }).finally(() => { if (active) setReportLoading(false); });
    return () => { active = false; };
  }, [tab, applied, group, positionsPage, reload, retry]);
  function applyFilters() {
    if (Boolean(start) !== Boolean(end)) { setFilterError('Tanggal mulai dan tanggal akhir harus diisi bersama.'); return; }
    if (start && end && start > end) { setFilterError('Tanggal mulai harus sebelum tanggal akhir.'); return; }
    setFilterError(''); setPositionsPage(1); setApplied({ ...(start ? { start_date: start, end_date: end } : {}), ...(position ? { job_position_id: position } : {}), ...(department ? { department } : {}) });
  }
  function resetFilters() {
    setStart(''); setEnd(''); setPosition(''); setDepartment(''); setGroup('weekly'); setFilterError(''); setPositionsPage(1); setApplied({});
  }
  const summaryMetrics = [
    { label: 'Total lamaran', value: report?.total_applications, scope: 'Kohort periode · lihat tahapan', target: 'stages' },
    { label: 'Diterima', value: report?.hired_applications, scope: 'Tahap hired · lihat tahapan', target: 'stages' },
    { label: 'Ditolak', value: report?.rejected_applications, scope: 'Tahap rejected · lihat tahapan', target: 'stages' },
    { label: 'Interview selesai', value: report?.completed_interviews, scope: 'Lihat ringkasan KPI', target: 'metrics' },
    { label: 'Posisi aktif', value: report?.active_job_positions, scope: 'Status saat ini · lihat posisi', target: 'positions' },
  ];
  const positionRows = report?.positions || [];
  const stageRows = report?.stages || [];
  return <>
    <div className="panel reportfilters"><label>Mulai<input type="date" value={start} onChange={event => setStart(event.target.value)} /></label><label>Sampai<input type="date" value={end} onChange={event => setEnd(event.target.value)} /></label><label>Posisi<select value={position} onChange={event => setPosition(event.target.value)}><option value="">Semua posisi</option>{positionOptions.rows.map(row => <option key={row.id} value={row.id}>{row.title}</option>)}</select></label><label>Departemen<input value={department} onChange={event => setDepartment(event.target.value)} placeholder="Semua departemen" /></label>{tab === 'trends' && <label>Kelompok<select value={group} onChange={event => setGroup(event.target.value)}><option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option></select></label>}<div className="filter-actions"><button className="primary" onClick={applyFilters}>Terapkan</button><button type="button" className="secondary small filter-reset" onClick={resetFilters}>Reset</button></div>{filterError && <small className="field-error">{filterError}</small>}{positionOptions.error && <div className="option-error" role="alert">{positionOptions.error}<button type="button" className="link" onClick={positionOptions.retry}>Coba lagi</button></div>}</div>
    <div className="tabs reporttabs" role="group" aria-label="Pilih laporan">{[['summary', 'Ringkasan'], ['stages', 'Tahapan'], ['trends', 'Tren'], ['positions', 'Performa posisi'], ['metrics', 'KPI']].map(([id, label]) => <button type="button" aria-pressed={tab === id} key={id} className={tab === id ? 'selected' : ''} onClick={() => setTab(id)}>{label}</button>)}</div>
    {reportError ? <div className="panel request-state error-state" role="alert"><span>{reportError}</span><button type="button" className="secondary small" onClick={() => setRetry(value => value + 1)}>Coba lagi</button></div>
      : reportLoading ? <div className="panel request-state" role="status">Memuat laporan…</div>
        : <>
    {tab === 'summary' && <>
      <div className="dashboardmetrics reportmetrics">{summaryMetrics.map(metric => <DashboardMetric key={metric.label} {...metric} onClick={() => setTab(metric.target)} />)}</div>
      <div className="reportchartgrid">
        <section className="panel reportchart reportchart-wide"><div className="charthead"><div><h2>Distribusi tahapan</h2><p>Enam tahap lamaran dalam kohort terpilih</p></div></div><div className="reportstagegrid"><StageDonut rows={stageRows} /><StageChart rows={stageRows} /></div></section>
        <section className="panel reportchart"><div className="charthead"><div><h2>Tren lamaran</h2><p>Per kelompok {pretty(group)} · berdasarkan tanggal melamar</p></div><button type="button" className="link" onClick={() => setTab('trends')}>Atur tren</button></div><TrendPlot rows={report?.trends || []} /></section>
        <section className="panel reportchart"><div className="charthead"><div><h2>Performa posisi</h2><p>Lamaran aktif, hired, dan rejected</p></div><button type="button" className="link" onClick={() => setTab('positions')}>Lihat posisi</button></div><PositionChart rows={positionRows} /></section>
        <section className="panel reportchart reportchart-wide"><div className="charthead"><div><h2>Hiring KPI</h2><p>Rasio hired dan rata-rata waktu pemenuhan</p></div><button type="button" className="link" onClick={() => setTab('metrics')}>Lihat KPI</button></div><ReportKpiCharts report={report || {}} /></section>
      </div>
    </>}
    {tab === 'stages' && <div className="reportchartgrid"><section className="panel reportchart"><div className="charthead"><div><h2>Distribusi tahapan lamaran</h2><p>Jumlah lamaran pada setiap tahap saat ini</p></div></div><StageChart rows={stageRows.length ? stageRows : report || []} /></section><section className="panel reportchart"><div className="charthead"><div><h2>Komposisi pipeline</h2><p>Proporsi tiap tahap dari total kohort</p></div></div><StageDonut rows={stageRows.length ? stageRows : report || []} /></section></div>}
    {tab === 'trends' && <section className="panel reportchart"><div className="charthead"><div><h2>Tren lamaran</h2><p>Per kelompok {pretty(group)} · berdasarkan tanggal melamar</p></div></div><TrendPlot rows={Array.isArray(report) ? report : []} /></section>}
    {tab === 'positions' && <section className="panel reportchart"><div className="charthead"><div><h2>Performa posisi</h2><p>Jumlah lamaran dan distribusi tahap untuk setiap posisi</p></div></div><PositionChart rows={positionRows} />{positionMeta?.last_page > 1 && <div className="chartpager"><button type="button" className="secondary small" disabled={positionMeta.current_page <= 1} onClick={() => setPositionsPage(positionsPage - 1)}>Sebelumnya</button><span>{positionMeta.current_page} / {positionMeta.last_page}</span><button type="button" className="secondary small" disabled={positionMeta.current_page >= positionMeta.last_page} onClick={() => setPositionsPage(positionsPage + 1)}>Berikutnya</button></div>}</section>}
    {tab === 'metrics' && <><div className="dashboardmetrics reportmetrics reportkpimetrics">{[
      { label: 'Hiring rate', value: `${report?.hiring_rate_percent ?? 0}%`, scope: 'Hired / total lamaran', target: 'stages' },
      { label: 'Rata-rata waktu ke hired', value: report?.average_time_to_hire_days == null ? '—' : `${report.average_time_to_hire_days} hari`, scope: 'Rerata untuk lamaran hired', target: 'trends' },
    ].map(metric => <DashboardMetric key={metric.label} {...metric} onClick={() => setTab(metric.target)} />)}</div><ReportKpiCharts report={report || {}} /></>}
        </>}
  </>;
}

function SettingsPage({ user, timezone, mutate, reload, busy, actionError, clearError }) {
  const [tab, setTab] = useState('profile');
  const [users, setUsers] = useState([]);
  const [userMeta, setUserMeta] = useState(null);
  const [userPage, setUserPage] = useState(1);
  const [userSort, setUserSort] = useState({ sort_by: 'created_at', sort_dir: 'desc' });
  const [settings, setSettings] = useState({ company_name: user?.company_name || '', timezone });
  const [settingsErrors, setSettingsErrors] = useState({ users: '', app: '' });
  const [settingsRetry, setSettingsRetry] = useState(0);
  const [editUser, setEditUser] = useState(null);
  useEffect(() => {
    if (user?.role !== 'admin') return;
    let active = true;
    setSettingsErrors({ users: '', app: '' });
    axios.get('/api/users', { params: { page: userPage, per_page: 10, ...userSort } }).then(response => { if (active) { setUsers(response.data.data || []); setUserMeta(response.data.meta || null); } }).catch(error => { if (active) setSettingsErrors(current => ({ ...current, users: error.response?.data?.message || 'Daftar pengguna gagal dimuat.' })); });
    axios.get('/api/app-settings').then(response => { if (active) setSettings(response.data.data || {}); }).catch(error => { if (active) setSettingsErrors(current => ({ ...current, app: error.response?.data?.message || 'Pengaturan aplikasi gagal dimuat.' })); });
    return () => { active = false; };
  }, [user, reload, userPage, userSort, settingsRetry]);
  if (editUser) return <ActionPage title={editUser.id ? 'Edit pengguna' : 'Tambah pengguna'} description="Setiap akun Talentora menggunakan role Admin." icon={Users} close={() => setEditUser(null)} busy={busy} error={actionError}><form onSubmit={async event => { event.preventDefault(); const payload = Object.fromEntries(new FormData(event.currentTarget)); const ok = await mutate(editUser.id ? 'patch' : 'post', `/api/users${editUser.id ? `/${editUser.id}` : ''}`, payload, editUser.id ? 'Pengguna diperbarui.' : 'Pengguna ditambahkan.'); if (ok) setEditUser(null); }}><div className="formgrid"><label>Nama<input name="name" defaultValue={editUser.name || ''} required minLength={2} maxLength={150} /></label><label>Email<input name="email" type="email" defaultValue={editUser.email || ''} required maxLength={255} /></label>{!editUser.id && <label>Password awal<input name="password" type="password" required minLength={8} /></label>}</div><div className="formactions"><button type="button" className="secondary" disabled={busy} onClick={() => setEditUser(null)}>Batal</button><button className="primary" disabled={busy}>Simpan pengguna</button></div></form></ActionPage>;
  return <>
    <div className="tabs settings-tabs">{[['profile', 'Profil Saya'], ...(user?.role === 'admin' ? [['users', 'Manajemen Pengguna'], ['app', 'Pengaturan Aplikasi']] : [])].map(([id, label]) => <button key={id} className={tab === id ? 'selected' : ''} onClick={() => setTab(id)}>{label}</button>)}</div>
    {tab === 'profile' && <div className="settingscolumns"><section className="panel settingsform"><h2>Informasi profil</h2><p>{pretty(user?.role)} · Zona waktu aplikasi: {timezone}</p><form onSubmit={event => { event.preventDefault(); mutate('patch', '/api/me', Object.fromEntries(new FormData(event.currentTarget)), 'Profil berhasil diperbarui.'); }}><label>Nama lengkap<input name="name" defaultValue={user?.name} required minLength={2} maxLength={150} /></label><label>Email<input name="email" type="email" defaultValue={user?.email} required maxLength={255} /></label><button className="primary">Simpan profil</button></form><h3>Ubah password</h3><form onSubmit={async event => { event.preventDefault(); const form = event.currentTarget; const ok = await mutate('patch', '/api/me/password', Object.fromEntries(new FormData(form)), 'Password berhasil diperbarui.'); if (ok) form.reset(); }}><label>Password saat ini<input name="current_password" type="password" required autoComplete="current-password" /></label><label>Password baru<input name="new_password" type="password" required minLength={8} autoComplete="new-password" /></label><label>Konfirmasi password<input name="new_password_confirmation" type="password" required minLength={8} autoComplete="new-password" /></label><button className="primary">Perbarui password</button></form></section><aside className="panel settingsaside"><h2>Akun dan akses</h2><p><b>{user?.name}</b></p><p>{user?.email}</p><span className="statuspill">Aktif · {pretty(user?.role)}</span><p>Pengaturan waktu mengikuti zona <b>{timezone}</b>.</p></aside></div>}
    {tab === 'users' && <>{settingsErrors.users ? <div className="panel request-state error-state" role="alert"><span>{settingsErrors.users}</span><button type="button" className="secondary small" onClick={() => setSettingsRetry(value => value + 1)}>Coba lagi</button></div> : <><div className="toolbar"><span>Semua akun memakai role Admin.</span><button className="primary" onClick={() => { clearError?.(); setEditUser({}); }}>Tambah pengguna</button></div><Table rows={users} cols={ [['name', 'Nama'], ['email', 'Email'], ['is_active', 'Status'], ['created_at', 'Dibuat']] } meta={userMeta} total={userMeta?.total} onPage={setUserPage} timeZone={timezone} sort={userSort.sort_by} sortDir={userSort.sort_dir} sortFields={['name', 'email', 'created_at']} onSort={field => setUserSort(current => ({ sort_by: field, sort_dir: current.sort_by === field && current.sort_dir === 'asc' ? 'desc' : 'asc' }))} actions={row => <><TableAction label="Edit pengguna" icon={Pencil} onClick={() => { clearError?.(); setEditUser(row); }} /><TableAction label={row.id === user.id ? 'Anda tidak dapat menonaktifkan akun sendiri' : row.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'} icon={row.is_active ? UserRoundX : UserRoundCheck} tone={row.is_active ? 'danger' : 'positive'} disabled={row.id === user.id || busy} onClick={() => mutate('patch', `/api/users/${row.id}/status`, { is_active: !row.is_active }, 'Status akun diperbarui.', { confirmation: { title: `${row.is_active ? 'Nonaktifkan' : 'Aktifkan'} akun?`, text: `Akun ${row.name} akan ${row.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`, confirmButtonText: row.is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan', danger: row.is_active } })} /></>} /></>}
    </>}
    {tab === 'app' && <div className="panel settingsform">{settingsErrors.app && <div className="request-state error-state" role="alert"><span>{settingsErrors.app}</span><button type="button" className="secondary small" onClick={() => setSettingsRetry(value => value + 1)}>Coba lagi</button></div>}<h2>Pengaturan aplikasi</h2><p>Perubahan zona waktu hanya mengubah cara tanggal dan waktu ditampilkan; event disimpan dalam UTC.</p><label>Nama perusahaan<input value={settings.company_name || ''} minLength={2} maxLength={200} onChange={event => setSettings({ ...settings, company_name: event.target.value })} required /></label><label>Timezone IANA<input value={settings.timezone || ''} onChange={event => setSettings({ ...settings, timezone: event.target.value })} required /></label><button className="primary" onClick={() => mutate('patch', '/api/app-settings', settings, 'Pengaturan aplikasi berhasil disimpan.')}>Simpan pengaturan</button></div>}
  </>;
}

function useOptions(path, params, enabled = true) {
  const [result, setResult] = useState({ rows: [], error: '', loading: false });
  const [retryCount, setRetryCount] = useState(0);
  const key = JSON.stringify(params);
  useEffect(() => {
    if (!enabled) { setResult({ rows: [], error: '', loading: false }); return; }
    let active = true;
    setResult(current => ({ ...current, error: '', loading: true }));
    axios.get(path, { params }).then(response => {
      if (active) setResult({ rows: response.data.data || [], error: '', loading: false });
    }).catch(error => {
      if (active) setResult(current => ({ ...current, error: error.response?.data?.message || 'Pilihan gagal dimuat.', loading: false }));
    });
    return () => { active = false; };
  }, [path, key, enabled, retryCount]);
  return { ...result, retry: () => setRetryCount(value => value + 1) };
}

function DashboardMetric({ label, value, scope, onClick }) {
  const formatted = typeof value === 'number' ? value.toLocaleString('id-ID') : value ?? '0';
  return <button type="button" className="dashboardmetric" onClick={onClick} aria-label={`${label}: ${formatted}. Lihat data`}>
    <span className="dashboardmetric-label">{label}</span>
    <strong>{formatted}</strong>
    <span className="dashboardmetric-footer"><span>{scope}</span><span className="dashboardmetric-action">Lihat data <ChevronRight size={15} aria-hidden="true" /></span></span>
  </button>;
}
function StageChart({ rows = [] }) { const max = Math.max(1, ...rows.map(row => Number(row.total || 0))); return <div className="reportbars">{rows.map(row => <div key={row.stage}><span>{pretty(row.stage)}</span><i><b style={{ width: `${Number(row.total || 0) / max * 100}%` }} /></i><strong>{Number(row.total || 0).toLocaleString('id-ID')}</strong></div>)}</div>; }
const REPORT_STAGE_COLORS = { applied: '#77d9fa', screening: '#91b6c9', interview: '#c69cf7', offered: '#ced9e8', hired: '#75dfbd', rejected: '#fb8496' };
function StageDonut({ rows = [] }) {
  const total = rows.reduce((sum, row) => sum + Number(row.total || 0), 0);
  let offset = 0;
  const stops = rows.map(row => { const start = offset; offset += total ? Number(row.total || 0) / total * 100 : 0; return `${REPORT_STAGE_COLORS[row.stage] || '#64748b'} ${start}% ${offset}%`; });
  const background = total ? `conic-gradient(${stops.join(',')})` : 'conic-gradient(#26364c 0 100%)';
  return <div className="stagevisual"><div className="stagedonut" role="img" aria-label={`Komposisi ${total.toLocaleString('id-ID')} lamaran`} style={{ background }}><div className="donutcenter"><strong>{total.toLocaleString('id-ID')}</strong><span>Lamaran</span></div></div><ul className="stagelegend">{rows.map(row => <li key={row.stage}><i style={{ background: REPORT_STAGE_COLORS[row.stage] || '#64748b' }} /><span>{pretty(row.stage)}</span><strong>{Number(row.total || 0).toLocaleString('id-ID')}</strong></li>)}</ul></div>;
}
function TrendPlot({ rows = [] }) {
  const max = Math.max(1, ...rows.map(row => Number(row.total || 0)));
  const x = index => rows.length < 2 ? 50 : index * 100 / (rows.length - 1);
  const y = row => 92 - Number(row.total || 0) / max * 78;
  const points = rows.map((row, index) => `${x(index)},${y(row)}`).join(' ');
  const labelStep = Math.max(1, Math.ceil(rows.length / 6));
  const labels = rows.filter((_, index) => index % labelStep === 0 || index === rows.length - 1);
  return <div className={`trendplot${rows.length ? '' : ' trendplot-empty'}`}><svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label={rows.length ? 'Grafik tren lamaran per periode' : 'Grafik tren lamaran, belum ada data'}><polygon className="trendarea" points={rows.length ? `0,100 ${points} 100,100` : ''} /><polyline points={points} />{rows.map((row, index) => <circle key={row.period} cx={x(index)} cy={y(row)} r="1.3"><title>{`${row.period}: ${Number(row.total || 0).toLocaleString('id-ID')} lamaran`}</title></circle>)}</svg>{rows.length ? <div className="trendlabels">{labels.map(row => <span key={row.period}>{row.period}<b>{Number(row.total || 0).toLocaleString('id-ID')}</b></span>)}</div> : <span className="chartempty">Belum ada data pada periode ini</span>}</div>;
}
function PositionChart({ rows = [] }) {
  const max = Math.max(1, ...rows.map(row => Number(row.total || 0)));
  return rows.length ? <><div className="positionlegend"><span><i className="position-inprogress" />Dalam proses</span><span><i className="position-hired" />Hired</span><span><i className="position-rejected" />Rejected</span></div><div className="positionchart">{rows.map(row => <div className="positionrow" key={row.job_position_id}><div className="positionrowhead"><span title={row.title}>{row.title}</span><strong>{Number(row.total || 0).toLocaleString('id-ID')}</strong></div><div className="positionstack" role="img" aria-label={`${row.title}: ${Number(row.total || 0)} lamaran, ${Number(row.in_progress || 0)} dalam proses, ${Number(row.hired || 0)} hired, ${Number(row.rejected || 0)} rejected`}><i className="position-inprogress" title={`Dalam proses: ${Number(row.in_progress || 0)}`} style={{ width: `${Number(row.in_progress || 0) / max * 100}%` }} /><i className="position-hired" title={`Hired: ${Number(row.hired || 0)}`} style={{ width: `${Number(row.hired || 0) / max * 100}%` }} /><i className="position-rejected" title={`Rejected: ${Number(row.rejected || 0)}`} style={{ width: `${Number(row.rejected || 0) / max * 100}%` }} /></div></div>)}</div></> : <div className="chartempty">Belum ada posisi pada filter ini</div>;
}
function ReportKpiCharts({ report }) {
  const total = Number(report.total_applications || 0);
  const hired = Number(report.hired_applications || 0);
  const rate = Math.min(100, Math.max(0, Number(report.hiring_rate_percent || 0)));
  const days = report.average_time_to_hire_days == null ? null : Number(report.average_time_to_hire_days);
  const scale = days == null ? 10 : Math.max(10, Math.ceil(days / 10) * 10);
  return <div className="kpichartgrid"><section className="kpichart"><h3>Hiring rate</h3><div className="kpirate"><div className="kpiratedonut" role="img" aria-label={`Hiring rate ${rate}%`} style={{ background: `conic-gradient(#75dfbd 0 ${rate}%, #26364c ${rate}% 100%)` }}><span>{rate.toLocaleString('id-ID')}%</span></div><div className="kpilegend"><span><i className="position-hired" />Hired <b>{hired.toLocaleString('id-ID')}</b></span><span><i className="position-inprogress" />Tahap lain <b>{Math.max(0, total - hired).toLocaleString('id-ID')}</b></span></div></div></section><section className="kpichart"><h3>Rata-rata waktu ke hired</h3><div className="daychart" role="img" aria-label={days == null ? 'Belum ada waktu ke hired pada kohort' : `Rata-rata waktu ke hired ${days} hari, skala 0 sampai ${scale} hari`}><strong>{days == null ? '—' : days.toLocaleString('id-ID')}<small>hari</small></strong><div className="dayaxis"><i style={{ width: `${days == null ? 0 : Math.min(100, days / scale * 100)}%` }} /></div><div className="dayticks"><span>0</span><span>{Math.round(scale / 2)}</span><span>{scale} hari</span></div>{days == null && <span className="chartempty">Belum ada lamaran hired pada kohort ini</span>}</div></section></div>;
}
function SimpleTable(props) { return <Table {...props} cols={props.cols.map((column, index) => [column, props.labels[index]])} />; }

function TableAction({ label, icon: Icon, tone = '', href, onClick, disabled = false }) {
  const className = `table-action${tone ? ` ${tone}` : ''}`;
  return href
    ? <a className={className} href={href} title={label} aria-label={label}><Icon size={15} strokeWidth={2} aria-hidden="true" /></a>
    : <button type="button" className={className} title={label} aria-label={label} disabled={disabled} onClick={onClick}><Icon size={15} strokeWidth={2} aria-hidden="true" /></button>;
}

function Table({ rows = [], cols, total, actions, meta, onPage, sort, sortDir, onSort, sortFields = [], timeZone = 'Asia/Jakarta', emptyText = 'Belum ada data yang cocok.' }) {
  return <section className="panel tablepanel"><div className="tablewrap"><table><thead><tr>{cols.map(([key, label]) => <th key={key}>{sortFields.includes(key) && onSort ? <button className="sortbutton" onClick={() => onSort(key)}>{uppercase(label)}{sort === key ? (sortDir === 'asc' ? ' ↑' : ' ↓') : ''}</button> : uppercase(label)}</th>)}{actions && <th>AKSI</th>}</tr></thead><tbody>{rows.map(row => <tr key={row.id || row.job_position_id || row.period}>{cols.map(([key]) => { const value = val(row, key); const isDate = ['applied_at'].includes(key); const isDateTime = ['created_at', 'updated_at', 'stage_updated_at', 'start_at', 'end_at', 'hired_at', 'rejected_at'].includes(key); const cell = value == null || value === '' ? '—' : Array.isArray(value) ? value.join(', ') : typeof value === 'boolean' ? (value ? 'Aktif' : 'Nonaktif') : isDate ? date(String(value), timeZone) : isDateTime ? datetime(value, timeZone) : ['stage', 'status'].includes(key) ? <span className={`badge ${value}`}>{pretty(value)}</span> : value; return <td key={key}>{cell}</td>; })}{actions && <td><div className="rowactions">{actions(row)}</div></td>}</tr>)}{!rows.length && <tr><td colSpan={cols.length + (actions ? 1 : 0)} className="empty">{emptyText}</td></tr>}</tbody></table></div><div className="tablefooter">{total ?? meta?.total ?? rows.length} data {meta && meta.last_page > 1 && <span className="pager"><button disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Sebelumnya</button>Halaman {meta.current_page} dari {meta.last_page}<button disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Berikutnya</button></span>}</div></section>;
}

function DetailData({ data, timeZone }) { return <dl className="detaildata">{Object.entries(data || {}).filter(([key, value]) => !['id', 'created_by', 'cv_path', 'histories', 'interviews', 'applications', 'jobPosition', 'job_position', 'candidate', 'interviewer', 'has_cv', 'created_at', 'updated_at', 'password'].includes(key) && value !== null && typeof value !== 'object').map(([key, value]) => <React.Fragment key={key}><dt>{pretty(key)}</dt><dd>{key.endsWith('_at') ? datetime(value, timeZone) : String(value)}</dd></React.Fragment>)}</dl>; }
function ApplicationHistory({ application, timeZone }) { return <div className="detailhistory"><h3>Riwayat tahapan</h3>{(application.histories || []).map(history => { const actor = history.changedBy?.name || history.changed_by?.name; return <article key={history.id}><b>{history.from_stage ? pretty(history.from_stage) : 'Awal'} → {pretty(history.to_stage)}</b><small>{history.note || '—'} · {datetime(history.changed_at, timeZone)}{actor ? ` · ${actor}` : ''}</small></article>; })}<h3>Riwayat interview</h3>{(application.interviews || []).map(interview => <article key={interview.id}><b>{datetime(interview.start_at, timeZone)} · {pretty(interview.status)}</b><small>{interview.interviewer?.name} · {interview.rating ? `${interview.rating}/5` : 'Belum dievaluasi'}{interview.feedback ? ` · ${interview.feedback}` : ''}</small></article>)}</div>; }
function CvUploadPage({ candidate, close, busy, upload }) {
  const [file, setFile] = useState(null);
  const [error, setError] = useState('');
  function selectFile(event) {
    const next = event.target.files?.[0] || null;
    const extension = next?.name.split('.').pop()?.toLowerCase();
    setError(next && !['pdf', 'doc', 'docx'].includes(extension) ? 'Pilih berkas PDF, DOC, atau DOCX.' : next?.size > 5 * 1024 * 1024 ? 'Ukuran berkas maksimal 5 MiB.' : '');
    setFile(next);
  }
  return <ActionPage title={candidate.has_cv ? 'Ganti CV kandidat' : 'Unggah CV kandidat'} description={`Pilih CV privat untuk ${candidate.full_name}. Berkas PDF, DOC, atau DOCX hingga 5 MiB.`} icon={FileUp} close={close} busy={busy}>
    <form onSubmit={async event => { event.preventDefault(); if (!file || error) return; if (await upload(file)) close(); }}>
      <label className="filepick">Berkas CV<input type="file" accept=".pdf,.doc,.docx" required onChange={selectFile} />{file && <span className="filehint">{file.name} · {(file.size / 1024 / 1024).toFixed(2)} MiB</span>}</label>
      {error && <small className="field-error" role="alert">{error}</small>}
      <p className="filehint">Berkas disimpan pada penyimpanan privat dan hanya dapat diakses melalui endpoint terautentikasi.</p>
      <div className="formactions"><button type="button" className="secondary" disabled={busy} onClick={close}>Batal</button><button className="primary" disabled={busy || !file || Boolean(error)}><FileUp size={15} />Simpan CV</button></div>
    </form>
  </ActionPage>;
}
function DetailPanel({ title, close, children }) {
  const pageRef = useRef(null);
  useEffect(() => { window.scrollTo(0, 0); pageRef.current?.focus({ preventScroll: true }); }, []);
  return <section ref={pageRef} tabIndex={-1} className="detailpage" aria-label={`Detail ${title}`}><div className="detailpagehead"><button className="secondary" onClick={close}><ChevronLeft size={16} />Kembali</button><h2>{title}</h2></div><div className="detailpagebody">{children}</div></section>;
}
export function ActionPage({ title, close, children, description, icon: Icon = FileText, size = 'regular', busy = false, error }) {
  const titleId = useId();
  const pageRef = useRef(null);
  useEffect(() => { window.scrollTo(0, 0); pageRef.current?.focus({ preventScroll: true }); }, []);
  return <section ref={pageRef} tabIndex={-1} className={`actionpage ${size === 'wide' ? 'actionpage-wide' : ''}`} aria-labelledby={titleId}>
    <header className="actionpagehead"><button className="secondary actionback" type="button" disabled={busy} onClick={close}><ChevronLeft size={16} />Kembali</button><div className="actionpage-title"><span className="actionpage-icon"><Icon size={21} aria-hidden="true" /></span><div><h2 id={titleId}>{title}</h2>{description && <p>{description}</p>}</div></div></header>
    <div className="actionpagebody">{error && <div className="notice error" role="alert">{error}</div>}{children}</div>
  </section>;
}
