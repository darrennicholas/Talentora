const pad = value => String(value).padStart(2, '0');

function partsAt(value, timeZone) {
  return Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(value).filter(part => part.type !== 'literal').map(part => [part.type, Number(part.value)]));
}

export function localToUtc(value, timeZone) {
  const [date, time] = value.split('T');
  const [year, month, day] = date.split('-').map(Number);
  const [hour, minute] = time.split(':').map(Number);
  const wanted = Date.UTC(year, month - 1, day, hour, minute);
  let utc = wanted;
  for (let i = 0; i < 2; i++) {
    const p = partsAt(new Date(utc), timeZone);
    utc += wanted - Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute);
  }
  const resolved = partsAt(new Date(utc), timeZone);
  if (Date.UTC(resolved.year, resolved.month - 1, resolved.day, resolved.hour, resolved.minute) !== wanted) {
    throw new RangeError('Waktu tersebut tidak ada di zona waktu aplikasi.');
  }
  return new Date(utc).toISOString();
}

export function zonedDateInput(value, timeZone) {
  const p = partsAt(new Date(value), timeZone);
  return `${p.year}-${pad(p.month)}-${pad(p.day)}T${pad(p.hour)}:${pad(p.minute)}`;
}

export function dateInZone(value, timeZone) {
  const p = partsAt(new Date(value), timeZone);
  return `${p.year}-${pad(p.month)}-${pad(p.day)}`;
}

export function addDays(value, count) {
  const date = new Date(`${value}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + count);
  return date.toISOString().slice(0, 10);
}

export function weekRange(anchor, timeZone) {
  const today = dateInZone(anchor.toISOString(), timeZone);
  const weekday = new Date(`${today}T00:00:00Z`).getUTCDay();
  const start = addDays(today, -((weekday + 6) % 7));
  return { start, end: addDays(start, 7) };
}

export function dateLabel(value, timeZone) {
  return new Date(`${value}T12:00:00Z`).toLocaleDateString('id-ID', { timeZone: 'UTC', weekday: 'short', day: '2-digit', month: 'short' });
}
