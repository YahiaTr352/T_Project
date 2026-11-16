import { getAgeFromBirthDate, isUnder20 } from '@/lib/utils/age';

export interface EssentialProfileLike {
  id?: number | null;
  first_name?: string | null;
  full_name?: string | null;
  birth_date?: string | null;
  gender?: string | null;
}

export const INVITATION_MIN_AGE = 20;

/**
 * الحقول الأساسية المطلوبة لإرسال الدعوات (وليست الفرعية):
 * - الاسم الأول (first_name) — لا يقل عن حرفين بعد التنظيف
 * - تاريخ الميلاد (birth_date) — صالح وقابل لحساب العمر
 * - النوع (gender) — فقط عندما يكون الحقل موجوداً في الكائن (ملف /v1/me الكامل).
 *   لا نطالب به من AuthPerson المختصر حتى لا نحجب من لم يجلبه الكاش بعد.
 */
export function getMissingEssentialFields(
  person?: EssentialProfileLike | null
): string[] {
  if (!person) return ['الهوية', 'الاسم', 'تاريخ الميلاد'];
  const missing: string[] = [];

  const firstName = (person.first_name ?? '').trim();
  if (firstName.length < 2) {
    // fallback على full_name إن وجد (بعض الحسابات القديمة)
    const full = (person.full_name ?? '').trim();
    if (full.length < 2) missing.push('الاسم');
  }

  const age = getAgeFromBirthDate(person.birth_date ?? null);
  if (age == null) missing.push('تاريخ الميلاد');

  // gender موجود فقط في البروفايل الكامل — إن وُجد وكان فارغاً نعتبره ناقصاً
  if (
    person &&
    Object.prototype.hasOwnProperty.call(person, 'gender') &&
    (person.gender ?? '').trim() === ''
  ) {
    // لا نحجب على الجنس وحده إن كان الاسم والميلاد سليمين؟
    // القرار: نعم نعتبره أساسياً لأن الشجرة والتنقيح تعتمد عليه.
    missing.push('النوع');
  }

  return missing;
}

export function isProfileCompleteForInvitations(
  person?: EssentialProfileLike | null
): boolean {
  return getMissingEssentialFields(person).length === 0;
}

export function missingFieldsLabel(missing: string[]): string {
  if (missing.length === 0) return '';
  // إخفاء عنصر 'الهوية' من العرض لأنه يُعالج ببوابة منفصلة
  const visible = missing.filter((m) => m !== 'الهوية');
  if (visible.length === 0) return '';
  return visible.join('، ');
}

export type InvitationEligibility =
  | { eligible: true; reason: 'ok'; missing: string[]; under20: false }
  | { eligible: false; reason: 'identity'; missing: string[]; under20: false }
  | { eligible: false; reason: 'under20'; missing: string[]; under20: true }
  | { eligible: false; reason: 'incomplete'; missing: string[]; under20: false };

/**
 * الترتيب المقصود للبوابات:
 * 1) الهوية (بصمة وجه / واتساب) — يشمل دخول رمز الموقع (112/passcode) لأنه بلا user.id
 * 2) العمر (أقل من 20)
 * 3) اكتمال البيانات الأساسية
 */
export function getInvitationEligibility(
  hasIdentity: boolean,
  person?: EssentialProfileLike | null
): InvitationEligibility {
  if (!hasIdentity || person?.id == null) {
    return { eligible: false, reason: 'identity', missing: [], under20: false };
  }
  if (isUnder20(person?.birth_date ?? null)) {
    return { eligible: false, reason: 'under20', missing: [], under20: true };
  }
  const missing = getMissingEssentialFields(person);
  if (missing.length > 0) {
    return { eligible: false, reason: 'incomplete', missing, under20: false };
  }
  return { eligible: true, reason: 'ok', missing: [], under20: false };
}

/**
 * هل يُعرض زر «الدعوات» في القوائم؟
 * - دخول رمز الموقع / زائر (بلا هوية) → مخفي — يحقق شرط «112 ميشوفش الزر»
 * - أقل من 20 → مخفي (السلوك الحالي)
 * - ناقص البيانات الأساسية → مرئي (حتى يرى سبب المنع ويكمل بياناته)،
 *   والمنع يتم داخل شاشة الدعوات وعند الإرسال.
 */
export function canViewInvitesButton(
  hasIdentity: boolean,
  person?: EssentialProfileLike | null
): boolean {
  if (!hasIdentity || person?.id == null) return false;
  if (isUnder20(person?.birth_date ?? null)) return false;
  return true;
}

export function canSendInvitations(
  hasIdentity: boolean,
  person?: EssentialProfileLike | null
): boolean {
  return getInvitationEligibility(hasIdentity, person).eligible;
}
