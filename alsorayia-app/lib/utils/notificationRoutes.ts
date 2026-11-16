/**
 * توحيد وجهات الإشعارات وتحويلها إلى مسارات صالحة داخل التطبيق.
 *
 * السبب: السيرفر (Laravel) قد يرسل `action_url` بصيغ مختلفة جداً
 * مثل `/walking-chat/12` أو `walking-chat/12` أو `https://alsorayia.com/walking-chat/12`
 * ولو تم تمريرها كما هي إلى `router.push` فلن يطابقها expo-router
 * (لأن المسارات الحقيقية داخل مجموعات `(app)` و `(tabs)`) ويظهر خطأ 404.
 */

export interface NotificationRouteResult {
  /** مسار داخلي صالح داخل تطبيق expo-router */
  route?: string;
  /** رابط خارجي (http/https) يُفتح بالمتصفح */
  url?: string;
  isExternal?: boolean;
}

export interface NotificationRouteOptions {
  /** عنوان الدردشة لعرضه في أعلى الشاشة */
  title?: string;
  /** معرّف مجموعة تحدي المشي (يتقدم على أي استخراج من المسار) */
  groupId?: string | null;
  /** معرّف سؤال المسابقة (يتقدم على أي استخراج من المسار) */
  chatId?: string | null;
  /** نوع الدردشة المرجوة عند الغموض */
  chatKind?: 'walking' | 'quiz';
}

/** المسارات الأساسية المتاحة داخل التطبيق */
export const APP_ROUTES = {
  notifications: '/(app)/notifications',
  walkingGroups: '/(tabs)/walking?tab=groups',
  myProfile: '/(app)/my-profile',
  articles: '/(app)/articles',
  gallery: '/(tabs)/gallery',
  quranCompetitions: '/(app)/quran-competitions',
  store: '/(app)/store',
  sila: '/(tabs)/sila',
} as const;

export function buildWalkingChatRoute(id: string, title?: string): string {
  return `/(app)/walking-chat/${id}${title ? `?title=${encodeURIComponent(title)}` : ''}`;
}

export function buildQuizChatRoute(id: string, title?: string): string {
  return `/(app)/quiz-chat/${id}${title ? `?title=${encodeURIComponent(title)}` : ''}`;
}

/**
 * استخراج المسار من أي رابط (مطلق أو نسبي) بدون الاعتماد على URL المدمج في RN
 */
function stripToPath(raw: string): string {
  const value = raw.trim();

  if (/^https?:\/\//i.test(value)) {
    const withoutScheme = value.replace(/^https?:\/\//i, '');
    const slashIndex = withoutScheme.indexOf('/');
    const path = slashIndex >= 0 ? withoutScheme.slice(slashIndex) : '/';
    return path.split(/[?#]/)[0] || '/';
  }

  return value.split(/[?#]/)[0] || '/';
}

/**
 * استخراج معاملات الاستعلام من الرابط
 */
function parseQuery(raw: string): Record<string, string> {
  const qIndex = raw.indexOf('?');
  if (qIndex < 0) return {};

  const query = raw.slice(qIndex + 1).split('#')[0];
  const out: Record<string, string> = {};

  query.split('&').forEach((pair) => {
    if (!pair) return;
    const eq = pair.indexOf('=');
    const key = eq >= 0 ? pair.slice(0, eq) : pair;
    const value = eq >= 0 ? pair.slice(eq + 1) : '';
    if (!key) return;
    try {
      out[decodeURIComponent(key).toLowerCase()] = decodeURIComponent(value);
    } catch {
      out[key.toLowerCase()] = value;
    }
  });

  return out;
}

/**
 * استخراج أول معرّف رقمي من المسار (مثال: /walking-chat/12 => 12)
 */
export function extractIdFromPath(path: string): string | null {
  const segments = path.split('/').filter(Boolean);

  for (let i = segments.length - 1; i >= 0; i--) {
    const segment = segments[i];
    if (/^\d+$/.test(segment)) return segment;
  }

  return null;
}

/**
 * استخراج معرّف مجموعة التحدي من حمولة الإشعار
 */
export function extractGroupId(data?: Record<string, unknown> | null): string | null {
  if (!data) return null;

  const candidates = [
    data.group_id,
    data.groupId,
    data.walking_group_id,
    data.walkingGroupId,
    data.challenge_id,
    data.challengeId,
  ];

  for (const candidate of candidates) {
    if (candidate === null || candidate === undefined) continue;
    const value = String(candidate).trim();
    if (value && !Number.isNaN(Number(value))) return value;
  }

  return null;
}

/**
 * استخراج معرّف سؤال المسابقة من حمولة الإشعار
 */
export function extractChatId(data?: Record<string, unknown> | null): string | null {
  if (!data) return null;

  const candidates = [
    data.question_id,
    data.questionId,
    data.quiz_id,
    data.quizId,
    data.chat_id,
    data.chatId,
  ];

  for (const candidate of candidates) {
    if (candidate === null || candidate === undefined) continue;
    const value = String(candidate).trim();
    if (value && !Number.isNaN(Number(value))) return value;
  }

  return null;
}

function buildInternalRoute(
  path: string,
  query: Record<string, string>,
  options: NotificationRouteOptions
): NotificationRouteResult {
  const lower = path.toLowerCase();

  const groupId =
    options.groupId ||
    query.group_id ||
    query.groupid ||
    query.walking_group_id ||
    extractIdFromPath(path);

  const chatId =
    options.chatId ||
    query.question_id ||
    query.questionid ||
    query.quiz_id ||
    extractIdFromPath(path);

  // 1) دردشة تحدي المشي
  if (
    lower.includes('walking-chat') ||
    lower.includes('group-chat') ||
    lower.includes('walking_chat') ||
    lower.includes('challenge-chat')
  ) {
    return groupId
      ? { route: buildWalkingChatRoute(groupId, options.title) }
      : { route: APP_ROUTES.notifications };
  }

  // 2) دردشة أسئلة المسابقة
  if (
    lower.includes('quiz-chat') ||
    lower.includes('question-chat') ||
    lower.includes('quiz_chat')
  ) {
    return chatId
      ? { route: buildQuizChatRoute(chatId, options.title) }
      : { route: APP_ROUTES.notifications };
  }

  // 3) دردشة عامة بدون تحديد النوع
  if (lower.includes('/chat') || lower.endsWith('chat')) {
    const id = options.chatKind === 'quiz' ? chatId : groupId || chatId;
    if (!id) return { route: APP_ROUTES.notifications };
    return options.chatKind === 'quiz'
      ? { route: buildQuizChatRoute(id, options.title) }
      : { route: buildWalkingChatRoute(id, options.title) };
  }

  // 4) مجموعات وتحديات المشي
  if (lower.includes('walking') || lower.includes('challenge') || lower.includes('group')) {
    return {
      route: groupId
        ? `/(tabs)/walking?tab=groups&groupId=${groupId}`
        : APP_ROUTES.walkingGroups,
    };
  }

  // 5) بقية المسارات المعروفة
  if (lower.includes('profile')) return { route: APP_ROUTES.myProfile };
  if (lower.includes('article')) return { route: APP_ROUTES.articles };
  if (lower.includes('gallery')) return { route: APP_ROUTES.gallery };
  if (lower.includes('quran')) return { route: APP_ROUTES.quranCompetitions };
  if (lower.includes('store')) return { route: APP_ROUTES.store };
  if (lower.includes('sila')) return { route: APP_ROUTES.sila };
  if (lower.includes('notification')) return { route: APP_ROUTES.notifications };

  return {};
}

/**
 * تحويل أي وجهة واردة من السيرفر إلى مسار داخلي صالح أو رابط خارجي.
 * لا تُرجع أبداً مساراً غير موجود في التطبيق (وإلا ظهر خطأ 404).
 */
export function normalizeNotificationRoute(
  raw: string | null | undefined,
  options: NotificationRouteOptions = {}
): NotificationRouteResult {
  if (!raw || typeof raw !== 'string') return {};

  const value = raw.trim();
  if (!value) return {};

  const query = parseQuery(value);
  const path = stripToPath(value);

  // رابط خارجي: نحاول_internal أولاً لو كان يشير لشاشة داخلية
  if (/^https?:\/\//i.test(value)) {
    const internal = buildInternalRoute(path, query, options);
    if (internal.route) return internal;
    return { url: value, isExternal: true };
  }

  // مسار مطلق
  if (value.startsWith('/')) {
    const internal = buildInternalRoute(path, query, options);
    if (internal.route) return internal;
    return { route: APP_ROUTES.notifications };
  }

  // مسار نسبي بدون شرطة مائلة
  const relative = buildInternalRoute('/' + value, query, options);
  if (relative.route) return relative;

  // أي شيء غير معروف: روابط خارجية أخرى أو fallback آمن
  if (/^www\./i.test(value) || value.includes('.')) {
    return { url: `https://${value}`, isExternal: true };
  }

  return { route: APP_ROUTES.notifications };
}
