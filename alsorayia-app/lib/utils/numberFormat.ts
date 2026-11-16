/**
 * دوال معالجة وتوحيد صيغ الأرقام لتظهر دائماً بالإنجليزية (0-9)
 * عبر كافة الأجهزة وأنظمة التشغيل (iOS / Android) بغض النظر عن لغة النظام المحلية.
 */

/**
 * تحويل أي أرقام مشرقية (عربية هندية أو فارسية ٠-٩ / ۰-۹) إلى أرقام إنجليزية قياسية (0-9).
 * يحافظ على النصوص المحيطة والعلامات كما هي.
 */
export function toEnglishDigits(value: string | number | null | undefined): string {
  if (value == null) return '';
  return String(value)
    .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 1632))
    .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 1776));
}

/**
 * تنسيق الأرقام باللغة الإنجليزية دائماً باستخدام locale 'en-US'
 * لضمان ظهور الأرقام بصيغة لاتينية (0-9) وفواصل الآلاف المعتادة (,)
 */
export function formatEnglishNumber(
  value: number | string | null | undefined,
  options?: Intl.NumberFormatOptions
): string {
  if (value == null || value === '') return '0';
  const num = typeof value === 'number' ? value : parseFloat(toEnglishDigits(value));
  if (Number.isNaN(num)) return '0';
  return new Intl.NumberFormat('en-US', options).format(num);
}

/**
 * تنسيق أعداد الخطوات لإنتاج رقم إنجليزي متماسك بدون فجوات (مثل 4,058 أو 12,500)
 */
export function formatSteps(n: number | null | undefined): string {
  if (n == null || Number.isNaN(n)) return '0';
  const rounded = Math.round(Number(n));
  return new Intl.NumberFormat('en-US').format(rounded);
}
