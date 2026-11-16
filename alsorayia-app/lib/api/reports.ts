import { api, WEB_BASE_URL } from '@/lib/api/alsorayiaClient';

export interface ReportsLocationObject {
  id?: number;
  name?: string;
  normalized_name?: string;
  description?: string | null;
  country?: string | null;
  city?: string | null;
  persons_count?: number;
  [key: string]: unknown;
}

/** شخص في قوائم التقارير — يطابق الحقول الشائعة في JSON العام. */
export interface ReportsPersonRow {
  id: number;
  full_name: string;
  gender?: string | null;
  age?: number | null;
  birth_date?: string | null;
  profile_url: string | null;
  location?: string | ReportsLocationObject | null;
}

export interface ReportsGenerationTier {
  total: number;
  males: number;
  females: number;
  members: ReportsPersonRow[];
  relatives: ReportsPersonRow[];
  relatives_count?: number;
}

/** مفاتيح الجيل أرقام في الخادم؛ بعد JSON قد تصبح نصية. */
export type ReportsGenerationsBreakdown = Record<
  string | number,
  ReportsGenerationTier
>;

export interface ReportsGrandfatherBlock {
  grandfather_id: number;
  grandfather_name: string;
  grandfather_profile_url: string | null;
  total_descendants: number;
  male_descendants: number;
  female_descendants: number;
  total_relatives: number;
  generations_breakdown: ReportsGenerationsBreakdown;
}

export interface ReportsLocationStatRow {
  id?: number;
  name?: string;
  /** عدد الأحياء أو المعيار المعروض في الويب */
  count?: number;
  persons_count?: number;
  persons_list_url?: string | null;
  [key: string]: unknown;
}

export interface ReportsCommonNameRow {
  name: string;
  count?: number;
  persons_list_url?: string | null;
  [key: string]: unknown;
}

export interface ReportsPageData {
  totalFamilyMembers: number;
  maleCount: number;
  femaleCount: number;
  masterDegreeCount: number;
  phdCount: number;
  bachelorDegreeCount: number;
  allFamilyMembersByAge: ReportsPersonRow[];
  allFamilyFemalesByAge: ReportsPersonRow[];
  generationsData: ReportsGrandfatherBlock[];
  locationsStatistics: ReportsLocationStatRow[];
  mostCommonNames: ReportsCommonNameRow[];
}

export interface ReportsLocationPersonsResponse {
  success?: boolean;
  location?: { id: number; name: string };
  persons: ReportsPersonRow[];
  total: number;
  males: number;
  females: number;
}

export interface ReportsNamePersonsResponse {
  success?: boolean;
  name?: string;
  persons: ReportsPersonRow[];
  total: number;
  males: number;
  females: number;
}

export interface ReportsPersonStatisticsResponse {
  person?: ReportsPersonRow & { profile_url?: string | null };
  statistics?: {
    generations_breakdown?: ReportsGenerationsBreakdown;
    [key: string]: unknown;
  };
}

/**
 * يحوّل مسار الملف الشخصي أو أي مسار نسبي إلى URL كامل للمتصفح.
 */
export function resolveReportsWebUrl(url: string | null | undefined): string | null {
  const u = url?.trim();
  if (!u) return null;
  if (/^https?:\/\//i.test(u)) return u;
  const base = WEB_BASE_URL.replace(/\/$/, '');
  const path = u.startsWith('/') ? u : `/${u}`;
  return `${base}${path}`;
}

/**
 * يستخرج مسار axios تحت `/api` (مثل `/reports/location/1/persons`) من رابط كامل أو نسبي.
 */
export function apiPathFromPersonsListUrl(url: string | null | undefined): string | null {
  const u = url?.trim();
  if (!u) return null;
  try {
    if (/^https?:\/\//i.test(u)) {
      const pathname = new URL(u).pathname;
      return stripApiPrefix(pathname);
    }
    if (u.startsWith('/api/')) {
      return stripApiPrefix(u);
    }
    return u.startsWith('/') ? u : `/${u}`;
  } catch {
    return null;
  }
}

function stripApiPrefix(pathname: string): string {
  const p = pathname.replace(/^\/api(\/|$)/, '/');
  return p.startsWith('/') ? p : `/${p}`;
}

/**
 * `GET /api/reports`
 */
export async function fetchReportsPage(): Promise<ReportsPageData | null> {
  const raw = (await api.get('/reports')) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  return raw as ReportsPageData;
}

/**
 * `GET /api/reports/location/{locationId}/persons`
 */
export async function fetchReportsLocationPersons(
  locationId: number
): Promise<ReportsLocationPersonsResponse | null> {
  const raw = (await api.get(
    `/reports/location/${locationId}/persons`
  )) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  return raw as ReportsLocationPersonsResponse;
}

/**
 * `GET /api/reports/name/{name}/persons` — مرّر الاسم بعد ترميز المسار إن لزم.
 */
export async function fetchReportsNamePersons(
  nameSegment: string
): Promise<ReportsNamePersonsResponse | null> {
  const segment = encodeURIComponent(nameSegment.trim());
  if (!segment) return null;
  const raw = (await api.get(`/reports/name/${segment}/persons`)) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  return raw as ReportsNamePersonsResponse;
}

/**
 * `GET /api/reports/person/{personId}/statistics`
 */
export async function fetchReportsPersonStatistics(
  personId: number
): Promise<ReportsPersonStatisticsResponse | null> {
  const raw = (await api.get(
    `/reports/person/${personId}/statistics`
  )) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  return raw as ReportsPersonStatisticsResponse;
}

/**
 * يستدعي المسار المشتق من `persons_list_url` (نفس الجلسة والرؤوس).
 */
export async function fetchReportsByPersonsListUrl(
  personsListUrl: string | null | undefined
): Promise<ReportsLocationPersonsResponse | ReportsNamePersonsResponse | null> {
  const path = apiPathFromPersonsListUrl(personsListUrl);
  if (!path || path.length < 2) return null;
  const raw = (await api.get(path)) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  return raw as ReportsLocationPersonsResponse | ReportsNamePersonsResponse;
}
