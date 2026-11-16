import type { ReportsGenerationsBreakdown, ReportsPersonRow } from '@/lib/api/reports';

/** عدد عناصر القوائم المبدئية ومقدار الزيادة لمنع طول الصفحة المفرط */
export const LIST_INITIAL = 5;
export const LIST_PAGE = 15;
export const NAMES_INITIAL = 8;
export const NAMES_PAGE = 8;
export const MODAL_PAGE = 15;

export const GENERATION_LABELS: Record<string, string> = {
  '1': 'الجيل الأول (الأبناء)',
  '2': 'الجيل الثاني (الأحفاد)',
  '3': 'الجيل الثالث',
  '4': 'الجيل الرابع',
  '5': 'الجيل الخامس',
  '6': 'الجيل السادس',
};

export function formatNumber(val: number | string | undefined | null): string {
  if (val === undefined || val === null || val === '') return '0';
  const num = typeof val === 'number' ? val : Number(val);
  return Number.isNaN(num) ? String(val) : num.toLocaleString('en-US');
}

/**
 * العمر بالسنوات (رقم صحيح).
 * الباك اند كان يرسل age كعدد عشري (مثال: 35.469004637206019...) لأن Carbon's diffInYears
 * يرجع float، فنتقرّب لأقرب سنة عشان لا يظهر رقم مزعج للمستخدم.
 */
export function formatAge(val: number | string | undefined | null): string {
  const num = typeof val === 'number' ? val : Number(val);
  if (val === undefined || val === null || val === '' || Number.isNaN(num)) return '0';
  return String(Math.round(num));
}

export function parseLocationIdFromUrl(url: string | null | undefined): number | null {
  if (!url) return null;
  const m = String(url).match(/\/reports\/location\/(\d+)\//);
  return m ? Number(m[1]) : null;
}

export function sortGenerationKeys(breakdown: ReportsGenerationsBreakdown | undefined): string[] {
  if (!breakdown) return [];
  return Object.keys(breakdown)
    .map((k) => Number(k))
    .filter((n) => !Number.isNaN(n))
    .sort((a, b) => a - b)
    .map(String);
}

export function formatLocationDisplay(location: unknown): string | null {
  if (!location) return null;
  if (typeof location === 'string') {
    const trimmed = location.trim();
    return trimmed.length > 0 ? trimmed : null;
  }
  if (typeof location === 'object' && location !== null) {
    const locObj = location as { name?: unknown; city?: unknown; country?: unknown; address?: unknown };
    if (typeof locObj.name === 'string' && locObj.name.trim().length > 0) {
      return locObj.name.trim();
    }
    if (typeof locObj.city === 'string' && locObj.city.trim().length > 0) {
      return locObj.city.trim();
    }
    if (typeof locObj.country === 'string' && locObj.country.trim().length > 0) {
      return locObj.country.trim();
    }
    if (typeof locObj.address === 'string' && locObj.address.trim().length > 0) {
      return locObj.address.trim();
    }
  }
  return null;
}

export type ListModalMode = 'location' | 'name';

export interface ListModalState {
  visible: boolean;
  title: string;
  mode: ListModalMode | null;
  loading: boolean;
  /** تعذّر جلب البيانات (خطأ شبكة/خادم) — يُعرض كتنبيه بدل "لا يوجد أشخاص" */
  error: boolean;
  persons: ReportsPersonRow[];
  total: number;
  males: number;
  females: number;
}

export interface StatsModalState {
  visible: boolean;
  loading: boolean;
  personName: string;
  totalDescendants: number;
  maleDescendants: number;
  femaleDescendants: number;
  breakdown: ReportsGenerationsBreakdown | null;
}
