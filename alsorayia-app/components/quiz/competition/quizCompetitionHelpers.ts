import { toEnglishDigits } from '@/lib/utils/numberFormat';

/**
 * Checks whether an HTML string from rich-text CMS fields contains real visible
 * text content rather than empty paragraph/line-break markup.
 */
export function isRealHtml(html?: string | null): boolean {
  if (!html) return false;
  const stripped = html.replace(/<[^>]*>/g, '').trim();
  if (stripped === '') return false;
  return !/^(\s*<p>\s*(<br\s*\/?>|&nbsp;|\s*)*<\/p>\s*)+$/i.test(html);
}

/**
 * Formats remaining vote-edit window milliseconds as HH:MM:SS in ASCII digits.
 */
export function formatVoteCountdown(ms: number): string {
  const totalSecs = Math.max(0, Math.floor(ms / 1000));
  const hours = Math.floor(totalSecs / 3600);
  const minutes = Math.floor((totalSecs % 3600) / 60);
  const seconds = totalSecs % 60;
  const pad = (n: number) => toEnglishDigits(String(n).padStart(2, '0'));

  return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
}

/**
 * Formats upcoming event countdown duration with Arabic day prefix when > 24h.
 */
export function formatDuration(ms: number): string {
  const totalSecs = Math.floor(ms / 1000);
  const days = Math.floor(totalSecs / 86400);
  const hours = Math.floor((totalSecs % 86400) / 3600);
  const minutes = Math.floor((totalSecs % 3600) / 60);
  const seconds = totalSecs % 60;

  const pad = (n: number) => toEnglishDigits(String(n).padStart(2, '0'));
  if (days > 0) {
    return `${toEnglishDigits(days)} يوم و ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
  }
  return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
}

/**
 * Validates a 10-digit Saudi mobile number starting with `05`.
 */
export function isValidSaudiPhone(phone: string): boolean {
  return /^05[0-9]{8}$/.test(phone.trim());
}
