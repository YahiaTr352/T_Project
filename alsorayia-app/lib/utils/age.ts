/**
 * حساب العمر من تاريخ الميلاد.
 * يقبل صيغ YYYY-MM-DD أو ISO مع جزء وقت، ويفصل القيم المائلة أيضاً.
 * يعيد null إن كان التاريخ مفقوداً أو غير صالح أو خارج النطاق المنطقي.
 */
export function getAgeFromBirthDate(birthDate?: string | null): number | null {
  if (!birthDate) return null;

  const clean = String(birthDate)
    .trim()
    .replace(/\//g, '-')
    .split(/[ T]/)[0];

  if (!clean) return null;

  const birth = new Date(`${clean}T00:00:00Z`);
  if (Number.isNaN(birth.getTime())) return null;

  const now = new Date();
  let age = now.getUTCFullYear() - birth.getUTCFullYear();
  const monthDiff = now.getUTCMonth() - birth.getUTCMonth();
  const dayDiff = now.getUTCDate() - birth.getUTCDate();

  if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
    age -= 1;
  }

  // قيم غير منطقية (مستقبل أو عمر خيالي) تُعتبر مجهولة.
  if (age < 0 || age > 150) return null;

  return age;
}

/**
 * هل المستخدم أقل من 20 سنة؟
 * عندما يكون تاريخ الميلاد مفقوداً أو غير صالح نعيد false حتى لا نمنع
 * ميزات عن مستخدمين لم يكمّلوا بياناتهم بعد.
 */
export function isUnder20(birthDate?: string | null): boolean {
  const age = getAgeFromBirthDate(birthDate);
  return age != null && age < 20;
}
