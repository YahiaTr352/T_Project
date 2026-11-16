import { api, WEB_BASE_URL } from '@/lib/api/alsorayiaClient';

/** شخص مرتبط بعلاقة رضاعة (طفل داخل بطاقة أم، أو أب رضاعة). */
export interface BreastfeedingPersonRef {
  id: number;
  name: string;
  first_name?: string;
  avatar?: string | null;
  profile_url: string | null;
}

export interface BreastfeedingChildRow extends BreastfeedingPersonRef {
  start_date: string | null;
  end_date: string | null;
  duration_months: number | null;
  is_active: boolean;
  notes: string | null;
  relationship_id: number;
  breastfeeding_father: BreastfeedingPersonRef | null;
}

export interface BreastfeedingMotherRow {
  id: number;
  name: string;
  first_name: string;
  avatar: string | null;
  profile_url: string | null;
  children: BreastfeedingChildRow[];
}

export interface BreastfeedingNursingMotherRef {
  id: number;
  name: string;
  first_name?: string;
  avatar?: string | null;
  profile_url: string | null;
}

export interface BreastfeedingChildPerspectiveRow {
  id: number;
  name: string;
  first_name: string;
  avatar: string | null;
  profile_url: string | null;
  relationship_id: number;
  start_date: string | null;
  end_date: string | null;
  duration_months: number | null;
  is_active: boolean;
  notes: string | null;
  nursing_mother: BreastfeedingNursingMotherRef;
  breastfeeding_father: BreastfeedingPersonRef | null;
}

export interface BreastfeedingStats {
  total_relationships: number;
  total_nursing_mothers: number;
  total_breastfed_children: number;
  active_breastfeeding: number;
}

export type BreastfeedingViewMode = 'mothers' | 'children';

export interface BreastfeedingPublicResponse {
  mothers: BreastfeedingMotherRow[];
  children: BreastfeedingChildPerspectiveRow[];
  stats: BreastfeedingStats;
  view_mode: BreastfeedingViewMode;
  search: string;
}

const SEARCH_MAX = 255;

export function resolveBreastfeedingProfileUrl(
  url: string | null | undefined
): string | null {
  const u = url?.trim();
  if (!u) return null;
  if (/^https?:\/\//i.test(u)) return u;
  const base = WEB_BASE_URL.replace(/\/$/, '');
  const path = u.startsWith('/') ? u : `/${u}`;
  return `${base}${path}`;
}

export interface FetchBreastfeedingPublicParams {
  search?: string;
  viewMode?: BreastfeedingViewMode;
}

/**
 * `GET /api/breastfeeding` — علاقات الرضاعة العامة (نفس مصدر الويب).
 * @see Laravel `BreastfeedingPublicApiController`
 */
export async function fetchBreastfeedingPublic(
  params?: FetchBreastfeedingPublicParams
): Promise<BreastfeedingPublicResponse | null> {
  const trimmed = params?.search?.trim();
  const search =
    trimmed && trimmed.length > 0
      ? trimmed.slice(0, SEARCH_MAX)
      : undefined;
  const view_mode = params?.viewMode;

  const raw = (await api.get('/breastfeeding', {
    params: {
      ...(search !== undefined ? { search } : {}),
      ...(view_mode ? { view_mode } : {}),
    },
  })) as unknown;

  if (!raw || typeof raw !== 'object') return null;

  const d = raw as Partial<BreastfeedingPublicResponse>;
  if (!Array.isArray(d.mothers) || !Array.isArray(d.children)) return null;
  if (!d.stats || typeof d.stats !== 'object') return null;

  return raw as BreastfeedingPublicResponse;
}
