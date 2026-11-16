function gregorianToHijri(dateStr: string): string {
  if (!dateStr) return '';
  try {
    const iso = String(dateStr).trim().replace(/\//g, '-');
    const date = new Date(iso + (iso.includes('T') ? '' : 'T12:00:00Z'));
    if (Number.isNaN(date.getTime())) return '';
    const fmt = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', {
      timeZone: 'UTC',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
    const parts = fmt.formatToParts(date);
    const d = parts.find((p) => p.type === 'day')?.value;
    const m = parts.find((p) => p.type === 'month')?.value;
    const y = parts.find((p) => p.type === 'year')?.value;
    if (d == null || m == null || y == null) return '';
    return `${d} ${m} ${y}هـ`;
  } catch {
    return '';
  }
}

const AR_MONTHS = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
] as const;

export function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '';
  try {
    const iso = String(dateStr).trim().replace(/\//g, '-');
    const d = new Date(iso + 'T12:00:00Z');
    if (Number.isNaN(d.getTime())) return dateStr;
    const greg = `${d.getUTCDate()} ${AR_MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
    const hijri = gregorianToHijri(dateStr);
    return hijri ? `${greg}\n${hijri}` : greg;
  } catch {
    return dateStr;
  }
}
