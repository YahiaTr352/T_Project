import { api } from '@/lib/api/alsorayiaClient';
import { ksaDateKey } from '@/lib/walking/ledger';

// ==========================================
// 1. الأنواع ونماذج البيانات (Types & DTOs)
// ==========================================

export type WalkingSource = 'apple_health' | 'health_connect' | 'google_fit' | 'device' | 'manual';
export type LeaderboardPeriod = 'today' | 'weekly' | 'monthly' | 'all_time';
export type WalkingPrivacyMode = 'visible' | 'anonymous' | 'hidden';

export interface WalkingClub {
  id: number;
  key: string;
  slug: string;
  name: string;
  short_name: string;
  label: string;
  short_label: string;
  sub_label: string;
  min_steps: number;
  max_steps?: number | null;
  calculation_start_date?: string | null;
  icon: string;
  badge_color: string;
  badge_dark: string;
  accent_light: string;
  accent_dark: string;
  display_order: number;
  is_active: boolean;
}

export interface LeaderboardViewerPrivacy {
  leaderboard_mode: WalkingPrivacyMode;
}

export interface SyncStepsPayload {
  date: string; // YYYY-MM-DD
  steps: number;
  distance_km?: number;
  duration_minutes?: number;
  calories?: number;
  source?: WalkingSource;
  device_info?: string;
  notes?: string | null;
  is_authoritative?: boolean;
}

export interface WalkingRecord {
  id: number;
  date: string;
  steps: number;
  distance_km: number | null;
  duration_minutes: number | null;
  calories: number | null;
  source: WalkingSource;
  notes: string | null;
  person_id: number;
  user_id: number | null;
  created_at?: string;
  updated_at?: string;
}

export interface BatchSyncPayload {
  records: {
    date: string;
    steps: number;
    distance_km?: number;
    duration_minutes?: number;
    calories?: number;
    source?: WalkingSource;
  }[];
}

export interface BatchSyncResponseData {
  synced: number;
  skipped: number;
}

export interface LeaderboardPerson {
  id: number;
  name: string;
  avatar: string | null;
  gender?: string | null;
}

export interface LeaderboardEntry {
  rank: number;
  person_id: number | null;
  membership_id?: number | null;
  total_steps: number;
  /** عدد الأيام المتتالية في استمرارية النادي */
  streak_days?: number;
  /** سلسلة الاستمرارية: عدد الأيام المتتالية التي تجاوز فيها 10 آلاف خطوة (توافقية) */
  streak_10k?: number;
  is_today_completed?: boolean;
  in_top?: boolean;
  is_anonymous?: boolean;
  is_hidden?: boolean;
  is_self?: boolean;
  completed_at?: string | null;
  is_goal_reached?: boolean;
  completion_duration_text?: string | null;
  /** مؤشرات التأهل في نوادي الخطوات (5k, 10k, 20k) */
  is_qualified?: boolean;
  steps_needed?: number;
  min_steps?: number;
  max_steps?: number | null;
  active_club?: string | null;
  person: LeaderboardPerson;
}

export interface LeaderboardPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
}

export interface LeaderboardGenderVisibility {
  viewer_gender: 'male' | 'female' | 'guest';
  active_gender_filter: 'male' | 'female';
  allowed_gender_filters: string[];
  can_toggle_gender: boolean;
}

export interface LeaderboardData {
  type?: 'steps' | 'streak';
  period: LeaderboardPeriod;
  min_steps?: number | null;
  max_steps?: number | null;
  date_range?: {
    from: string;
    to: string;
  };
  gender_visibility?: LeaderboardGenderVisibility;
  viewer_privacy?: LeaderboardViewerPrivacy;
  top: LeaderboardEntry[];
  viewer_rank: LeaderboardEntry | null;
  pagination?: LeaderboardPagination;
}

export interface PersonalStats {
  date?: string; // YYYY-MM-DD
  server_time?: string;
  reset_id?: number | null;
  today_steps: number;
  weekly_steps: number;
  monthly_steps: number;
  all_time_steps: number;
  current_streak: number;
  longest_streak: number;
  streak_5k?: number;
  streak_10k?: number;
  streak_20k?: number;
  club_streaks?: Record<string, number>;
  best_day_steps: number;
  best_day_date?: string | null;
  active_days_count?: number;
  avg_daily_steps_30d?: number;
  avg_daily_steps: number;
  total_days_active: number;
  today_rank?: number | null;
  today_total_participants?: number;
}

export interface WalkingGroupItem {
  id: number;
  name: string;
  description: string | null;
  cover_image: string | null;
  target_steps: number | null;
  start_date: string | null;
  end_date: string | null;
  is_public: boolean;
  status: 'upcoming' | 'active' | 'completed' | 'cancelled' | 'archived';
  archived_at?: string | null;
  archived_reason?: string | null;
  invite_code: string;
  member_count: number;
  is_admin?: boolean;
  is_member?: boolean;
  is_creator?: boolean;
  current_member_settings?: MemberChallengeSettings | null;
  created_at: string;
}

export interface MemberChallengeSettings {
  is_anonymous: boolean;
  is_inherited?: boolean;
  share_details: boolean;
  notifications_enabled: boolean;
  apply_to_all?: boolean;
}

export interface WalkingGroupMember {
  id: number;
  membership_id?: number | null;
  person_id: number | null;
  role: 'admin' | 'member';
  status?: 'accepted' | 'invited' | 'pending';
  is_anonymous?: boolean;
  is_inherited?: boolean;
  is_self?: boolean;
  share_details?: boolean;
  notifications_enabled?: boolean;
  joined_at: string;
  completed_at?: string | null;
  is_goal_reached?: boolean;
  completion_duration_text?: string | null;
  total_steps_in_challenge?: number;
  total_points_in_challenge?: number;
  person: LeaderboardPerson | null;
}

export interface WalkingGroupDetail extends WalkingGroupItem {
  members: WalkingGroupMember[];
}

export interface CreateGroupPayload {
  name: string;
  description?: string | null;
  target_steps?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  is_public?: boolean;
  cover_image?: string | null;
  member_person_ids?: number[];
}

export interface UpdateGroupPayload {
  name?: string;
  description?: string | null;
  target_steps?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  is_public?: boolean;
  cover_image?: string | null;
  status?: 'upcoming' | 'active' | 'completed' | 'cancelled';
  member_person_ids?: number[];
}

export interface PaginationResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  server?: {
    date_today: string;
    server_time: string;
    reset_id: number | null;
  } | null;
  errors?: Record<string, string[]> | null;
}

// ==========================================
// 2. دوال الاتصال بالـ API (API Service Methods)
// ==========================================

/**
 * تاريخ اليوم بتقويم جهاز المستخدم (YYYY-MM-DD) مع المنطقة الزمنية.
 * يُرسل مع طلبات اللوحات حتى يحسب السيرفر "اليوم" على يوم المستخدم
 * (Asia/Riyadh غالباً) وليس على توقيت السيرفر — وإلا ظهرت خطوات الأمس
 * في سباق اليوم عند الفجر (مثل 4 صباحاً).
 */
function getClientDateContext(): { date: string; timezone: string } {
  const ksa = ksaDateKey();
  let timezone = 'Asia/Riyadh';
  try {
    const detected = 'Asia/Riyadh';
    if (detected) timezone = detected;
  } catch {
    // fallback kept
  }
  return { date: ksa, timezone };
}

/**
 * 1️⃣ مزامنة خطوات يوم واحد (POST /api/v1/walking/sync)
 */
export async function syncTodaySteps(payload: SyncStepsPayload): Promise<ApiResponse<WalkingRecord> | null> {
  try {
    const res = await api.post<ApiResponse<WalkingRecord>>('/v1/walking/sync', payload, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<WalkingRecord>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] syncTodaySteps network/error:', err?.message);
    return null;
  }
}

/**
 * 2️⃣ مزامنة مجمعة لعدة أيام سابقة عند عودة الاتصال (POST /api/v1/walking/batch-sync)
 */
export async function batchSyncSteps(records: BatchSyncPayload['records']): Promise<ApiResponse<BatchSyncResponseData> | null> {
  try {
    const res = await api.post<ApiResponse<BatchSyncResponseData>>('/v1/walking/batch-sync', { records }, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<BatchSyncResponseData>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] batchSyncSteps error:', err?.message);
    return null;
  }
}

/**
 * 3️⃣ جلب لوحة المتصدرين مع دعم الترقيم (Pagination) والخصوصية (GET /api/v1/walking/leaderboard)
 */
export async function fetchLeaderboard(
  period: LeaderboardPeriod = 'today',
  page: number = 1,
  perPage: number = 10,
  genderFilter?: 'male' | 'female',
  minSteps?: number,
  maxSteps?: number
): Promise<LeaderboardData | null> {
  try {
    const { date: clientDate, timezone } = getClientDateContext();
    let url = `/v1/walking/leaderboard?period=${period}&page=${page}&per_page=${perPage}&date=${clientDate}&timezone=${encodeURIComponent(timezone)}`;
    if (genderFilter) {
      url += `&gender_filter=${genderFilter}`;
    }
    if (typeof minSteps === 'number' && minSteps > 0) {
      url += `&min_steps=${minSteps}`;
    }
    if (typeof maxSteps === 'number' && maxSteps > 0) {
      url += `&max_steps=${maxSteps}`;
    }
    const res = await api.get<ApiResponse<LeaderboardData>>(
      url,
      { skipGlobalErrorHandler: true }
    );
    const parsed = (res as unknown) as ApiResponse<LeaderboardData>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchLeaderboard error:', err?.message);
    return null;
  }
}


/**
 * 3️⃣.2 جلب لوحة شرف سلسلة الأيام النشطة (+10,000 خطوة يومياً) (GET /api/v1/walking/leaderboard/streak)
 */
/**
 * 0️⃣ جلب قائمة نوادي المشي النشطة (GET /api/v1/walking/clubs)
 */
export async function fetchWalkingClubs(): Promise<WalkingClub[] | null> {
  try {
    const res = await api.get<ApiResponse<WalkingClub[]>>('/v1/walking/clubs', {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<WalkingClub[]>;
    return parsed?.success && Array.isArray(parsed.data) ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchWalkingClubs error:', err?.message);
    return null;
  }
}

export async function fetchStreakLeaderboard(
  page: number = 1,
  perPage: number = 10,
  genderFilter?: 'male' | 'female',
  minSteps?: number,
  maxSteps?: number
): Promise<LeaderboardData | null> {
  try {
    const { date: clientDate, timezone } = getClientDateContext();
    let url = `/v1/walking/leaderboard/streak?page=${page}&per_page=${perPage}&date=${clientDate}&timezone=${encodeURIComponent(timezone)}`;
    if (genderFilter) {
      url += `&gender_filter=${genderFilter}`;
    }
    if (typeof minSteps === 'number' && minSteps > 0) {
      url += `&min_steps=${minSteps}`;
    }
    if (typeof maxSteps === 'number' && maxSteps > 0) {
      url += `&max_steps=${maxSteps}`;
    }
    const res = await api.get<ApiResponse<LeaderboardData>>(
      url,
      { skipGlobalErrorHandler: true }
    );
    const parsed = (res as unknown) as ApiResponse<LeaderboardData>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchStreakLeaderboard error:', err?.message);
    return null;
  }
}

/**
 * 4️⃣ جلب الإحصائيات الشخصية والسلسلة المتتالية (GET /api/v1/walking/my-stats)
 */

/**
 * 3️⃣.1 جلب إعدادات خصوصية الصدارة (GET /api/v1/walking/privacy-settings)
 */
export async function getWalkingPrivacySettings(): Promise<ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }> | null> {
  try {
    const res = await api.get<ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }>>('/v1/walking/privacy-settings', {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] getWalkingPrivacySettings error:', err?.message);
    return null;
  }
}

/**
 * 3️⃣.2 تحديث إعدادات خصوصية الصدارة (POST /api/v1/walking/privacy-settings)
 */
export async function updateWalkingPrivacySettings(
  mode: WalkingPrivacyMode
): Promise<ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }> | null> {
  try {
    const res = await api.post<ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }>>(
      '/v1/walking/privacy-settings',
      { leaderboard_mode: mode },
      { skipGlobalErrorHandler: true }
    );
    return (res as unknown) as ApiResponse<{ leaderboard_mode: WalkingPrivacyMode }>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] updateWalkingPrivacySettings error:', err?.message);
    return null;
  }
}

export async function fetchMyWalkingStats(): Promise<PersonalStats | null> {
  try {
    const { date: clientDate, timezone } = getClientDateContext();
    const res = await api.get<ApiResponse<PersonalStats>>(`/v1/walking/my-stats?date=${clientDate}&timezone=${encodeURIComponent(timezone)}`, {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<PersonalStats>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    if (err?.response?.status !== 401) {
      console.warn('[walkingApi] fetchMyWalkingStats error:', err?.message);
    }
    return null;
  }
}

/**
 * 5️⃣ جلب سجل المشي التاريخي (GET /api/v1/walking/history)
 */
export async function fetchWalkingHistory(page: number = 1): Promise<PaginationResponse<WalkingRecord> | null> {
  try {
    const res = await api.get<ApiResponse<PaginationResponse<WalkingRecord>>>(`/v1/walking/history?page=${page}`, {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<PaginationResponse<WalkingRecord>>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    if (err?.response?.status !== 401) {
      console.warn('[walkingApi] fetchWalkingHistory error:', err?.message);
    }
    return null;
  }
}

/**
 * 6️⃣ استعراض المجموعات المتاحة والتحديات (GET /api/v1/walking/groups)
 */
export async function fetchWalkingGroups(
  page: number = 1,
  status: 'active' | 'completed' | 'archived' | 'all' = 'all'
): Promise<PaginationResponse<WalkingGroupItem> | null> {
  try {
    const res = await api.get<ApiResponse<PaginationResponse<WalkingGroupItem>>>(
      `/v1/walking/groups?page=${page}&status=${status}`,
      {
        skipGlobalErrorHandler: true,
      }
    );
    const parsed = (res as unknown) as ApiResponse<PaginationResponse<WalkingGroupItem>>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchWalkingGroups error:', err?.message);
    return null;
  }
}

/**
 * 7️⃣ إنشاء مجموعة أو تحدي جديد (POST /api/v1/walking/groups)
 */
export async function createWalkingGroup(payload: CreateGroupPayload): Promise<ApiResponse<WalkingGroupItem> | null> {
  try {
    const res = await api.post<ApiResponse<WalkingGroupItem>>('/v1/walking/groups', payload, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<WalkingGroupItem>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] createWalkingGroup error:', err?.message);
    return null;
  }
}

/**
 * 7️⃣ب تحديث بيانات مجموعة أو تحدي (PUT /api/v1/walking/groups/{group})
 */
export async function updateWalkingGroup(
  groupId: number,
  payload: UpdateGroupPayload
): Promise<ApiResponse<WalkingGroupItem> | null> {
  try {
    const res = await api.put<ApiResponse<WalkingGroupItem>>(`/v1/walking/groups/${groupId}`, payload, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<WalkingGroupItem>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] updateWalkingGroup error:', err?.message);
    return null;
  }
}

/**
 * 7️⃣ج حذف تحدي أو مجموعة ناعماً (Soft Delete) (DELETE /api/v1/walking/groups/{group})
 */
export async function deleteWalkingGroup(groupId: number): Promise<ApiResponse<null> | null> {
  try {
    const res = await api.delete<ApiResponse<null>>(`/v1/walking/groups/${groupId}`, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<null>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] deleteWalkingGroup error:', err?.message);
    return null;
  }
}

/**
 * 7️⃣د استرجاع تحدي محذوف من سلة المحذوفات (POST /api/v1/walking/groups/{group}/restore)
 */
export async function restoreWalkingGroup(groupId: number): Promise<ApiResponse<WalkingGroupItem> | null> {
  try {
    const res = await api.post<ApiResponse<WalkingGroupItem>>(`/v1/walking/groups/${groupId}/restore`, {}, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<WalkingGroupItem>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] restoreWalkingGroup error:', err?.message);
    return null;
  }
}

/**
 * 8️⃣ جلب تفاصيل المجموعة وأعضائها (GET /api/v1/walking/groups/{group})
 */
export async function fetchWalkingGroupDetails(groupId: number): Promise<WalkingGroupDetail | null> {
  try {
    const res = await api.get<ApiResponse<WalkingGroupDetail>>(`/v1/walking/groups/${groupId}`, {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<WalkingGroupDetail>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchWalkingGroupDetails error:', err?.message);
    return null;
  }
}

/**
 * 9️⃣ الانضمام لمجموعة عامة (POST /api/v1/walking/groups/{group}/join)
 */
export async function joinWalkingGroup(groupId: number): Promise<ApiResponse<null> | null> {
  try {
    const res = await api.post<ApiResponse<null>>(`/v1/walking/groups/${groupId}/join`, {}, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<null>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] joinWalkingGroup error:', err?.message);
    return null;
  }
}

/**
 * 9️⃣ب الانضمام لمجموعة عبر كود/رمز الدعوة (POST /api/v1/walking/groups/join-code)
 */
export async function joinWalkingGroupByCode(inviteCode: string): Promise<ApiResponse<null> | null> {
  try {
    const res = await api.post<ApiResponse<null>>(
      '/v1/walking/groups/join-code',
      { invite_code: inviteCode.trim().toUpperCase() },
      {
        skipGlobalErrorHandler: true,
      }
    );
    return (res as unknown) as ApiResponse<null>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] joinWalkingGroupByCode error:', err?.message);
    return null;
  }
}

/**
 * 🔟 دعوة أو إضافة عضو/أعضاء للمجموعة (POST /api/v1/walking/groups/{group}/members)
 */
export async function inviteGroupMember(
  groupId: number,
  personIds: number | number[]
): Promise<ApiResponse<null> | null> {
  try {
    const payload = Array.isArray(personIds)
      ? { person_ids: personIds }
      : { person_id: personIds };
    const res = await api.post<ApiResponse<null>>(`/v1/walking/groups/${groupId}/members`, payload, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<null>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] inviteGroupMember error:', err?.message);
    return null;
  }
}

export const addGroupMembers = inviteGroupMember;

/**
 * 1️⃣1️⃣ مغادرة أو إزالة عضو من المجموعة (DELETE /api/v1/walking/groups/{group}/members/{personId})
 */
export async function leaveOrRemoveGroupMember(groupId: number, personId: number): Promise<ApiResponse<null> | null> {
  try {
    const res = await api.delete<ApiResponse<null>>(`/v1/walking/groups/${groupId}/members/${personId}`, {
      skipGlobalErrorHandler: true,
    });
    return (res as unknown) as ApiResponse<null>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] leaveOrRemoveGroupMember error:', err?.message);
    return null;
  }
}

/**
 * 1️⃣2️⃣ لوحة صدارة التحدي داخل المجموعة (GET /api/v1/walking/groups/{group}/leaderboard)
 */
export async function fetchGroupLeaderboard(
  groupId: number,
  period: LeaderboardPeriod | 'points' = 'weekly',
  genderFilter?: 'male' | 'female'
): Promise<(LeaderboardData & { points_map?: Record<number, number>; standings?: any[]; total_points_sum?: number }) | null> {
  try {
    let url = `/v1/walking/groups/${groupId}/leaderboard?period=${period}`;
    if (genderFilter) {
      url += `&gender_filter=${genderFilter}`;
    }
    const res = await api.get<ApiResponse<LeaderboardData>>(url, {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<LeaderboardData>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchGroupLeaderboard error:', err?.message);
    return null;
  }
}

/**
 * 1️⃣3️⃣ جلب إعدادات خصوصية العضو في التحدي (GET /api/v1/walking/groups/{group}/member-settings)
 */
export async function fetchMemberChallengeSettings(groupId: number): Promise<MemberChallengeSettings | null> {
  try {
    const res = await api.get<ApiResponse<MemberChallengeSettings>>(`/v1/walking/groups/${groupId}/member-settings`, {
      skipGlobalErrorHandler: true,
    });
    const parsed = (res as unknown) as ApiResponse<MemberChallengeSettings>;
    return parsed?.success ? parsed.data : null;
  } catch (err: any) {
    console.warn('[walkingApi] fetchMemberChallengeSettings error:', err?.message);
    return null;
  }
}

/**
 * 1️⃣4️⃣ تحديث إعدادات خصوصية العضو في التحدي (PATCH /api/v1/walking/groups/{group}/member-settings)
 */
export async function updateMemberChallengeSettings(
  groupId: number,
  settings: Partial<MemberChallengeSettings>
): Promise<ApiResponse<MemberChallengeSettings> | null> {
  try {
    const res = await api.patch<ApiResponse<MemberChallengeSettings>>(
      `/v1/walking/groups/${groupId}/member-settings`,
      settings,
      {
        skipGlobalErrorHandler: true,
      }
    );
    return (res as unknown) as ApiResponse<MemberChallengeSettings>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] updateMemberChallengeSettings error:', err?.message);
    return null;
  }
}

/**
 * 1️⃣5️⃣ إعادة تنشيط مجموعة أو تحدي مؤرشف (POST /api/v1/walking/groups/{group}/reactivate)
 */
export async function reactivateWalkingGroup(
  groupId: number,
  payload?: { end_date?: string }
): Promise<ApiResponse<WalkingGroupDetail> | null> {
  try {
    const res = await api.post<ApiResponse<WalkingGroupDetail>>(
      `/v1/walking/groups/${groupId}/reactivate`,
      payload || {},
      {
        skipGlobalErrorHandler: true,
      }
    );
    return (res as unknown) as ApiResponse<WalkingGroupDetail>;
  } catch (err: any) {
    if (err?.response?.data) return err.response.data;
    console.warn('[walkingApi] reactivateWalkingGroup error:', err?.message);
    return null;
  }
}

