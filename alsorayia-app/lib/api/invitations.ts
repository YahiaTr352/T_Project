import { api, apiUpload, WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import { isAxiosError } from 'axios';

export const IDENTITY_REQUIRED_CODE = 'IDENTITY_REQUIRED_FOR_INVITATIONS';
export const PROFILE_INCOMPLETE_CODE = 'PROFILE_INCOMPLETE_FOR_INVITATIONS';
export const UNDERAGE_CODE = 'UNDERAGE_FOR_INVITATIONS';

export type InvitationChannel = 'whatsapp' | 'push';
export type InvitationRecipientType = 'persons' | 'group' | 'external';

export interface InvitationsApiResponse<T = unknown> {
  success: boolean;
  message?: string;
  code?: string;
  data?: T;
  errors?: Record<string, string[]> | null;
}

export interface InvitationPersonFlags {
  has_whatsapp: boolean;
  selectable: boolean;
  is_deceased: boolean;
}

export interface InvitationPersonBrief {
  id: number;
  first_name: string;
  photo_url?: string | null;
  // مفاتيح بديلة قد يرجعها الباك إند في بعض المسارات
  photo?: string | null;
  avatar?: string | null;
  image?: string | null;
  full_name?: string | null;
  name?: string | null;
}

export interface InvitationTreeNode extends InvitationPersonFlags {
  id: number;
  first_name: string;
  photo_url: string | null;
  gender?: string | null;
  whatsapp_descendant_count: number;
  expandable: boolean;
  children: InvitationTreeNode[];
}

export interface InvitationSearchPerson extends InvitationPersonFlags {
  id: number;
  first_name: string;
  full_name?: string;
  photo_url: string | null;
  gender?: string | null;
}

export interface InvitationTreePickerData {
  tree: InvitationTreeNode[];
  flags?: Record<
    string,
    {
      has_whatsapp: boolean;
      selectable: boolean;
      whatsapp_descendant_count: number;
      expandable: boolean;
    }
  >;
}

export interface InvitationTemplate {
  key: string;
  name: string;
  title: string;
  body: string;
}

export interface InvitationGroup {
  id: number;
  name: string;
  description: string | null;
  persons_count: number;
  persons: InvitationPersonBrief[];
}

export interface InvitationSendResult {
  id: number;
  status: string;
  whatsapp_jobs: number;
  push_jobs: number;
}

export interface InvitationLogRecipient {
  id: number;
  person_id?: number | null;
  recipient_name?: string | null;
  recipient_phone?: string | null;
  person?: InvitationPersonBrief | null;
  // قد يرجع التفصيل الصورة أو حالة التسليم على مستوى المستلم مباشرة
  photo_url?: string | null;
  photo?: string | null;
  avatar?: string | null;
  status?: string | null;
  delivered_at?: string | null;
  sent_at?: string | null;
}

export type InvitationLogDirection = 'outgoing' | 'incoming';

export interface InvitationLog {
  id: number;
  title: string | null;
  body: string;
  channels: InvitationChannel[] | string[] | null;
  status: string;
  created_at: string;
  sent_at?: string | null;
  sender_name?: string | null;
  targeting_filters?: { requires_approval?: boolean } | null;
  recipients?: InvitationLogRecipient[];
  recipients_count?: number | null;
  delivered_count?: number | null;
  sent_count?: number | null;
}

export interface InvitationLogsPage {
  data: InvitationLog[];
  current_page: number;
  last_page: number;
  total?: number;
  per_page?: number;
}

export interface PreviewInvitationInput {
  person_id?: number;
  external_name?: string;
  external_phone?: string;
  external_country_code?: string;
  title?: string;
  background_id?: number;
  creative_id?: number;
  body?: string;
  template_key?: string;
  template_vars?: Record<string, string>;
}

export interface InvitationBackground {
  id: number;
  name?: string | null;
  image_url?: string | null;
  is_template?: boolean;
}

export interface MyInvitationBackground extends InvitationBackground {
  status: string;
  status_label?: string | null;
  rejection_reason?: string | null;
  created_at?: string;
}

export interface CreativeTemplate {
  id: number;
  name?: string | null;
  image_url?: string | null;
}

export interface MyCreativeTemplate extends CreativeTemplate {
  status: string;
  status_label?: string | null;
  rejection_reason?: string | null;
  created_at?: string;
}

export interface SendInvitationInput {
  channels: InvitationChannel[];
  recipient_type: InvitationRecipientType;
  person_ids?: number[];
  group_id?: number | null;
  external_name?: string;
  external_phone?: string;
  external_country_code?: string;
  title?: string | null;
  background_id?: number | null;
  creative_id?: number | null;
  body?: string;
  template_key?: string | null;
  template_vars?: Record<string, string>;
  exclude_deceased?: boolean;
}

export interface CreateGroupInput {
  name: string;
  description?: string | null;
  person_ids?: number[];
}

export interface UpdateGroupInput {
  name?: string;
  description?: string | null;
  person_ids?: number[];
}

const skipHandler = { skipGlobalErrorHandler: true } as const;

function asApiResponse<T>(data: unknown, fallbackMessage: string): InvitationsApiResponse<T> {
  return (data ?? { success: false, message: fallbackMessage }) as InvitationsApiResponse<T>;
}

async function request<T>(
  fallback: string,
  fn: () => Promise<unknown>
): Promise<InvitationsApiResponse<T>> {
  try {
    const data = await fn();
    // apiUpload قد يرجع null عند رد غير JSON — نعتبره فشل اتصال
    if (!data) return { success: false, message: 'تعذّر الاتصال بالخادم.' };
    return asApiResponse<T>(data, fallback);
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as InvitationsApiResponse<T>;
    }
    if (isAxiosError(err) && (err.code === 'ECONNABORTED' || err.message?.includes('timeout'))) {
      return { success: false, message: 'انتهت مهلة الاتصال بالخادم (قد يكون يعالج الدعوة). تحقق من سجل الإرسال قبل إعادة المحاولة حتى لا تتكرر الدعوة.' };
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم. تحقق من الإنترنت ثم أعد المحاولة.' };
  }
}

export function extractInvitationError(res: InvitationsApiResponse<unknown> | null): string {
  if (!res) return 'تعذّر الاتصال بالخادم.';
  if (res.message) return res.message;
  if (res.errors) {
    const firstKey = Object.keys(res.errors)[0];
    if (firstKey && res.errors[firstKey]?.[0]) return res.errors[firstKey][0];
  }
  return 'حدث خطأ غير متوقع.';
}

export function isIdentityRequired(res: InvitationsApiResponse<unknown> | null): boolean {
  return res?.code === IDENTITY_REQUIRED_CODE;
}

export function isProfileIncomplete(res: InvitationsApiResponse<unknown> | null): boolean {
  return res?.code === PROFILE_INCOMPLETE_CODE;
}

export function isUnderageBlocked(res: InvitationsApiResponse<unknown> | null): boolean {
  return res?.code === UNDERAGE_CODE;
}

/** أي منع من الباك إند (هوية / بيانات ناقصة / عمر) يستوجب إظهار بوابة بدل الكومبوزر */
export function isInvitationBlocked(res: InvitationsApiResponse<unknown> | null): boolean {
  return isIdentityRequired(res) || isProfileIncomplete(res) || isUnderageBlocked(res);
}

export function resolveInvitationPhotoUrl(raw?: string | null): string | null {
  const u = raw?.trim();
  if (!u) return null;
  if (/^https?:\/\//i.test(u)) {
    if (u.includes('localhost') || u.includes('127.0.0.1')) {
      const base = WEB_BASE_URL.replace(/\/$/, '');
      return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, base);
    }
    return u;
  }
  const base = WEB_BASE_URL.replace(/\/$/, '');
  return `${base}/${u.replace(/^\//, '')}`;
}

/** يستخرج صورة الشخص مهما اختلف اسم المفتاح بين المسارات */
export function resolvePersonPhoto(person?: InvitationPersonBrief | null): string | null {
  if (!person) return null;
  const raw =
    person.photo_url ?? person.photo ?? person.avatar ?? person.image ?? null;
  return resolveInvitationPhotoUrl(raw);
}

/** صورة المستلم: من الكائن نفسه أولاً ثم من الشخص المرتبط */
export function recipientPhotoUrl(r?: InvitationLogRecipient | null): string | null {
  if (!r) return null;
  const direct = resolveInvitationPhotoUrl(
    (r as any).photo_url ?? (r as any).photo ?? (r as any).avatar ?? null
  );
  if (direct) return direct;
  return resolvePersonPhoto(r.person);
}

/** اسم العرض للمستلم */
export function recipientDisplayName(r?: InvitationLogRecipient | null): string {
  if (!r) return 'ضيف';
  return (
    r.person?.first_name ||
    (r.person as any)?.full_name ||
    (r.person as any)?.name ||
    r.recipient_name ||
    (r.recipient_phone ? `ضيف (${r.recipient_phone})` : 'ضيف خارجي')
  );
}

const COMPLETED_STATUSES = new Set(['sent', 'completed', 'delivered', 'done']);

/** هل الدعوة مكتملة فعلاً؟ sent_at هو مصدر الحقيقة لو تأخرت حالة الخادم */
export function isLogCompleted(log?: InvitationLog | null): boolean {
  if (!log) return false;
  if (log.sent_at) return true;
  return COMPLETED_STATUSES.has((log.status || '').toLowerCase().trim());
}

/** الحالة الفعالة للعرض: لو اكتملت بتاريخ نعتبرها completed حتى لو النص متأخر */
export function effectiveLogStatus(log?: InvitationLog | null): string {
  if (!log) return '';
  if (isLogCompleted(log)) return 'completed';
  return log.status || '';
}

const BUILTIN_PLACEHOLDERS = new Set(['name', 'first_name', 'full_name', 'sender_name']);

export function extractTemplateVarKeys(body: string): string[] {
  const seen = new Set<string>();
  const keys: string[] = [];
  const re = /\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g;
  let match: RegExpExecArray | null;
  while ((match = re.exec(body)) !== null) {
    const key = match[1];
    if (BUILTIN_PLACEHOLDERS.has(key) || seen.has(key)) continue;
    seen.add(key);
    keys.push(key);
  }
  return keys;
}

export const TEMPLATE_VAR_LABELS: Record<string, string> = {
  event_name: 'اسم المناسبة',
  date: 'التاريخ',
  time: 'الوقت',
  location: 'المكان',
  occasion: 'المناسبة',
  announcement_title: 'عنوان الإعلان',
  announcement_details: 'تفاصيل الإعلان',
  topic: 'الموضوع',
  deceased_name: 'اسم الفقيد',
  fund_name: 'اسم الصندوق',
  amount: 'المبلغ',
  urgent_matter: 'الأمر العاجل',
};

export function templateVarLabel(key: string): string {
  return TEMPLATE_VAR_LABELS[key] ?? key.replace(/_/g, ' ');
}

export async function fetchInvitationTemplates(): Promise<
  InvitationsApiResponse<{ templates: InvitationTemplate[] }>
> {
  return request('تعذّر جلب القوالب.', () => api.get('/v1/invitations/templates', skipHandler as any));
}

export async function fetchInvitationTreePicker(): Promise<
  InvitationsApiResponse<InvitationTreePickerData>
> {
  return request('تعذّر جلب شجرة العائلة.', () =>
    api.get('/v1/invitations/tree-picker', skipHandler as any)
  );
}

export async function searchInvitationPersons(
  q: string
): Promise<InvitationsApiResponse<{ persons: InvitationSearchPerson[] }>> {
  return request('تعذّر البحث عن الأشخاص.', () =>
    api.get('/v1/invitations/persons', { params: { q }, ...skipHandler } as any)
  );
}

export async function fetchInvitationGroups(): Promise<
  InvitationsApiResponse<{ groups: InvitationGroup[] }>
> {
  return request('تعذّر جلب المجموعات.', () => api.get('/v1/invitations/groups', skipHandler as any));
}

export async function createInvitationGroup(
  input: CreateGroupInput
): Promise<InvitationsApiResponse<{ group: InvitationGroup }>> {
  return request('تعذّر إنشاء المجموعة.', () =>
    api.post('/v1/invitations/groups', input, skipHandler as any)
  );
}

export async function fetchInvitationGroup(
  id: number
): Promise<InvitationsApiResponse<{ group: InvitationGroup }>> {
  return request('تعذّر جلب المجموعة.', () =>
    api.get(`/v1/invitations/groups/${id}`, skipHandler as any)
  );
}

export async function updateInvitationGroup(
  id: number,
  input: UpdateGroupInput
): Promise<InvitationsApiResponse<{ group: InvitationGroup }>> {
  return request('تعذّر تحديث المجموعة.', () =>
    api.put(`/v1/invitations/groups/${id}`, input, skipHandler as any)
  );
}

export async function deleteInvitationGroup(id: number): Promise<InvitationsApiResponse<null>> {
  return request('تعذّر حذف المجموعة.', () =>
    api.delete(`/v1/invitations/groups/${id}`, skipHandler as any)
  );
}

export async function attachPersonToGroup(
  groupId: number,
  personId: number
): Promise<InvitationsApiResponse<{ group: InvitationGroup }>> {
  return request('تعذّر إضافة الشخص للمجموعة.', () =>
    api.post(`/v1/invitations/groups/${groupId}/persons`, { person_id: personId }, skipHandler as any)
  );
}

export async function detachPersonFromGroup(
  groupId: number,
  personId: number
): Promise<InvitationsApiResponse<null>> {
  return request('تعذّر إزالة الشخص من المجموعة.', () =>
    api.delete(`/v1/invitations/groups/${groupId}/persons/${personId}`, skipHandler as any)
  );
}

export async function previewInvitation(
  input: PreviewInvitationInput
): Promise<InvitationsApiResponse<{ preview: string; background?: InvitationBackground | null; creative?: CreativeTemplate | null; card_image_url?: string | null }>> {
  return request('تعذّرت معاينة الرسالة.', () =>
    api.post('/v1/invitations/preview', input, { timeout: 30000, ...skipHandler } as any)
  );
}

export async function sendInvitation(
  input: SendInvitationInput
): Promise<InvitationsApiResponse<{ notification: InvitationSendResult }>> {
  // مهم: المهلة 60ث لأن السيرفر يولّد البطاقة ومهام الواتساب، ومنع إعادة المحاولة التلقائية حتى لا تتكرر الدعوة
  return request('تعذّر إرسال الدعوة.', () =>
    api.post('/v1/invitations/send', input, { timeout: 60000, skipRetry: true, ...skipHandler } as any)
  );
}

export async function fetchInvitationLogs(
  page = 1,
  direction: InvitationLogDirection = 'outgoing'
): Promise<InvitationsApiResponse<{ notifications: InvitationLogsPage }>> {
  return request('تعذّر جلب سجل الدعوات.', () =>
    api.get('/v1/invitations/logs', { params: { page, direction }, ...skipHandler } as any)
  );
}

export async function fetchInvitationLogDetail(
  id: number,
  direction: InvitationLogDirection = 'outgoing'
): Promise<InvitationsApiResponse<{ notification: InvitationLog }>> {
  return request('تعذّر جلب تفاصيل الدعوة.', () =>
    api.get(`/v1/invitations/logs/${id}`, { params: { direction }, ...skipHandler } as any)
  );
}

export async function fetchBackgrounds(): Promise<
  InvitationsApiResponse<{ backgrounds: InvitationBackground[] }>
> {
  return request('تعذر جلب الخلفيات.', () =>
    api.get('/v1/invitations/backgrounds', skipHandler as any)
  );
}

export async function fetchMyBackgrounds(): Promise<
  InvitationsApiResponse<{ backgrounds: MyInvitationBackground[] }>
> {
  return request('تعذر جلب خلفياتك.', () =>
    api.get('/v1/invitations/backgrounds/mine', skipHandler as any)
  );
}

export async function uploadBackground(
  imageUri: string,
  name?: string
): Promise<InvitationsApiResponse<{ background: MyInvitationBackground }>> {
  const form = new FormData();
  form.append('image', {
    uri: imageUri,
    name: `background-${Date.now()}.jpg`,
    type: 'image/jpeg',
  } as any);
  if (name?.trim()) form.append('name', name.trim());
  return request('تعذر رفع الخلفية.', () => apiUpload('/v1/invitations/backgrounds', form));
}

export async function deleteMyBackground(id: number): Promise<InvitationsApiResponse<null>> {
  return request('تعذر حذف الخلفية.', () =>
    api.delete(`/v1/invitations/backgrounds/${id}`, skipHandler as any)
  );
}

export async function fetchCreatives(): Promise<
  InvitationsApiResponse<{ templates: CreativeTemplate[] }>
> {
  return request('تعذر جلب القوالب.', () =>
    api.get('/v1/invitations/creatives', skipHandler as any)
  );
}

export async function fetchMyCreatives(): Promise<
  InvitationsApiResponse<{ templates: MyCreativeTemplate[] }>
> {
  return request('تعذر جلب تصاميمك.', () =>
    api.get('/v1/invitations/creatives/mine', skipHandler as any)
  );
}

export async function uploadCreative(
  imageUri: string,
  name?: string
): Promise<InvitationsApiResponse<{ template: MyCreativeTemplate }>> {
  const form = new FormData();
  form.append('image', {
    uri: imageUri,
    name: `creative-${Date.now()}.jpg`,
    type: 'image/jpeg',
  } as any);
  if (name?.trim()) form.append('name', name.trim());
  return request('تعذر رفع التصميم.', () => apiUpload('/v1/invitations/creatives', form));
}

export async function deleteMyCreative(id: number): Promise<InvitationsApiResponse<null>> {
  return request('تعذر حذف التصميم.', () =>
    api.delete(`/v1/invitations/creatives/${id}`, skipHandler as any)
  );
}

export async function sendInvitationWithMedia(
  input: SendInvitationInput,
  imageUri: string
): Promise<InvitationsApiResponse<{ notification: InvitationSendResult }>> {
  const form = new FormData();
  input.channels.forEach((c) => form.append('channels[]', c));
  form.append('recipient_type', input.recipient_type);
  (input.person_ids ?? []).forEach((id) => form.append('person_ids[]', String(id)));
  if (input.group_id) form.append('group_id', String(input.group_id));
  if (input.external_name) form.append('external_name', input.external_name);
  if (input.external_phone) form.append('external_phone', input.external_phone);
  if (input.external_country_code) form.append('external_country_code', input.external_country_code);
  if (input.title) form.append('title', input.title);
  // مساحة الإبداع والصورة المرفقة وضعان متنافيان، لكن نمرر المعرفات إن وجدت للتوافق
  if (input.background_id) form.append('background_id', String(input.background_id));
  if (input.creative_id) form.append('creative_id', String(input.creative_id));
  form.append('body', input.body ?? '');
  if (input.template_key) form.append('template_key', input.template_key);
  Object.entries(input.template_vars ?? {}).forEach(([k, v]) => form.append(`template_vars[${k}]`, v));
  form.append('media', {
    uri: imageUri,
    name: `invite-${Date.now()}.jpg`,
    type: 'image/jpeg',
  } as any);
  // مهم: الرفع عبر XMLHttpRequest (apiUpload) لأن axios/FormData يفشل في RN/Hermes، وبدون إعادة محاولة لمنع تكرار الإرسال
  return request('تعذر إرسال الدعوة بالمرفق.', () => apiUpload('/v1/invitations/send', form, 90000));
}
