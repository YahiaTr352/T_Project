import { api } from '@/lib/api/alsorayiaClient';

/** نطاق العرض على الخريطة: الكل (العائلة) | جروب | نفسي */
export type TrekViewMode = 'community' | 'group' | 'personal';

export interface TrekWaypoint {
  id: number;
  sort_order: number;
  city_name: string;
  location_title: string;
  location_description: string | null;
  latitude: number;
  longitude: number;
  distance_from_prev_km: number;
  cumulative_km: number;
  steps_from_prev?: number;
  cumulative_steps?: number;
  status: 'start_point' | 'completed' | 'current_target' | 'upcoming';
  icon_type: 'home' | 'landmark' | 'mosque' | 'city' | 'trophy';
  route_polyline?: string | null;
  route_metadata?: Record<string, any> | null;
}

export interface TrekCurrentSegment {
  from_city: string;
  from_location: string;
  to_city: string;
  to_location: string;
  segment_index: number;
  total_segments: number;
  segment_distance_km: number;
  segment_walked_km: number;
  segment_remaining_km: number;
  segment_progress_percentage: number;
  route_polyline?: string | null;
}

export interface TrekCurrentPosition {
  latitude: number;
  longitude: number;
  label: string;
}

export interface TrekContributor {
  rank: number;
  person_id: number;
  name: string;
  avatar: string | null;
  gender: string | null;
  total_km: number;
  total_steps: number;
  today_km: number;
}

export interface MyTrekContribution {
  total_km: number;
  total_steps: number;
  today_km: number;
  today_steps: number;
  share_percentage: number;
  my_progress_percentage?: number;
  current_position?: TrekCurrentPosition | null;
  current_segment?: TrekCurrentSegment | null;
  waypoints?: TrekWaypoint[];
}

export interface GroupTrekProgress {
  group_id: number;
  name: string;
  description: string | null;
  status: string;
  is_public: boolean;
  is_member: boolean;
  members_count: number;
  total_km: number;
  total_steps: number;
  today_km: number;
  today_steps: number;
  overall_progress_percentage: number;
  share_percentage: number;
  current_position: TrekCurrentPosition | null;
  current_segment: TrekCurrentSegment | null;
  waypoints: TrekWaypoint[];
  top_contributors: TrekContributor[];
}

export interface ScheduledTrekInfo {
  id: number;
  title: string;
  description: string | null;
  scheduled_at: string | null;
  remaining_seconds: number | null;
  waypoints_count: number;
  total_distance_km: number;
}

export interface WalkingTrekInfo {
  id: number;
  title: string;
  description: string | null;
  cover_image: string | null;
  total_distance_km: number;
  total_required_steps?: number;
  total_walked_km: number;
  total_walked_steps: number;
  overall_progress_percentage: number;
  today_walked_km: number;
  today_walked_steps: number;
  status: 'active' | 'scheduled' | 'inactive' | 'upcoming' | 'completed' | 'archived';
  is_loop: boolean;
  start_date: string | null;
  end_date: string | null;
  scheduled_at?: string | null;
  waypoints_count: number;
}

export interface ActiveTrekResponseData {
  is_active?: boolean;
  has_active_trek?: boolean;
  status?: 'active' | 'scheduled' | 'inactive';
  next_scheduled_trek?: ScheduledTrekInfo | null;
  trek: WalkingTrekInfo | null;
  current_segment: TrekCurrentSegment | null;
  current_position: TrekCurrentPosition | null;
  waypoints: TrekWaypoint[];
  top_contributors: TrekContributor[];
  my_contribution: MyTrekContribution | null;
  groups_progress?: GroupTrekProgress[];
}

/**
 * جلب بيانات مسار الرحلة الافتراضية النشطة عبر المملكة
 * GET /api/v1/walking/treks/active
 */
export async function getActiveTrek(groupId?: number): Promise<ActiveTrekResponseData | null> {
  try {
    const params: Record<string, any> = {};
    if (groupId) params.group_id = groupId;

    const res: any = await api.get('/v1/walking/treks/active', {
      params,
      skipGlobalErrorHandler: true,
    });

    if (!res) return null;

    // حالة 1: الرد مُغلف بـ ApiResponse { success: true, data: { trek: ... } }
    if (res.data && res.data.trek) {
      return res.data as ActiveTrekResponseData;
    }

    // حالة 2: الرد مستخرج بالفعل عبر الـ Interceptor { trek: ... }
    if (res.trek) {
      return res as ActiveTrekResponseData;
    }

    return null;
  } catch (error) {
    console.warn('[walkingTrekApi] Failed to fetch active trek:', error);
    return null;
  }
}

export interface TrekPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
}

export interface PaginatedTrekContributorsData {
  contributors: TrekContributor[];
  pagination: TrekPagination;
  gender: 'male' | 'female';
}

export interface GetTrekContributorsParams {
  trekId?: number;
  groupId?: number;
  page?: number;
  perPage?: number;
  gender?: 'male' | 'female';
}

/**
 * جلب قائمة المتصدرين في مسار الرحلة بالصفحات (Infinite Scroll)
 * GET /api/v1/walking/treks/contributors
 */
export async function getTrekContributors(
  params: GetTrekContributorsParams = {}
): Promise<PaginatedTrekContributorsData | null> {
  try {
    const queryParams: Record<string, any> = {};
    if (params.trekId) queryParams.trek_id = params.trekId;
    if (params.groupId) queryParams.group_id = params.groupId;
    if (params.page) queryParams.page = params.page;
    if (params.perPage) queryParams.per_page = params.perPage;
    if (params.gender) queryParams.gender = params.gender;

    const endpoint = params.trekId
      ? `/v1/walking/treks/${params.trekId}/contributors`
      : '/v1/walking/treks/contributors';

    const res: any = await api.get(endpoint, {
      params: queryParams,
      skipGlobalErrorHandler: true,
    });

    if (!res) return null;

    if (res.data && res.data.contributors) {
      return res.data as PaginatedTrekContributorsData;
    }

    if (res.contributors) {
      return res as PaginatedTrekContributorsData;
    }

    return null;
  } catch (error) {
    console.warn('[walkingTrekApi] Failed to fetch paginated trek contributors:', error);
    return null;
  }
}
