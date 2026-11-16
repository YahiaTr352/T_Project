import { formatDateAr } from '../formatters';

describe('formatDateAr', () => {
  it('formats Gregorian date without weekday by default', () => {
    expect(formatDateAr('2026-03-15')).toBe('15 مارس 2026');
    expect(formatDateAr('2026-01-01')).toBe('1 يناير 2026');
    expect(formatDateAr('2026-12-31')).toBe('31 ديسمبر 2026');
  });

  it('formats Gregorian date with correct Arabic weekday when includeWeekday is true', () => {
    // 2026-03-15 is Sunday (الأحد)
    expect(formatDateAr('2026-03-15', { includeWeekday: true })).toBe('الأحد، 15 مارس 2026');
    // 2026-03-16 is Monday (الإثنين)
    expect(formatDateAr('2026-03-16', { includeWeekday: true })).toBe('الإثنين، 16 مارس 2026');
    // 2026-03-19 is Thursday (الخميس)
    expect(formatDateAr('2026-03-19', { includeWeekday: true })).toBe('الخميس، 19 مارس 2026');
    // 2026-03-20 is Friday (الجمعة)
    expect(formatDateAr('2026-03-20', { includeWeekday: true })).toBe('الجمعة، 20 مارس 2026');
  });

  it('handles ISO timestamps cleanly without timezone drift', () => {
    expect(formatDateAr('2026-03-15T00:00:00.000Z', { includeWeekday: true })).toBe('الأحد، 15 مارس 2026');
    expect(formatDateAr('2026-03-15 23:59:59', { includeWeekday: true })).toBe('الأحد، 15 مارس 2026');
  });

  it('gracefully handles empty, null or undefined input', () => {
    expect(formatDateAr(null)).toBe('');
    expect(formatDateAr(undefined)).toBe('');
    expect(formatDateAr('')).toBe('');
  });
});
