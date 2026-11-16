import { isAxiosError } from 'axios';
import { api } from '@/lib/api/alsorayiaClient';

export type QuranRouteType = 'competition' | 'category';

export interface QuranNavItem {
  category_id: number;
  name: string;
  route_type: QuranRouteType;
  competition_id: number | null;
  competitions_count: number;
}

export interface QuranCategoryRef {
  id: number;
  name: string;
  description?: string | null;
  sort_order?: number;
}

export interface QuranCompetitionListItem {
  id: number;
  title: string;
  description: string | null;
  hijri_year: string;
  start_date: string | null;
  end_date: string | null;
  cover_image_url: string | null;
  category: { id: number; name: string } | null;
  winners_count: number;
}

export interface QuranWinnerPerson {
  id: number;
  name: string;
}

export interface QuranWinner {
  id: number;
  position: number;
  position_name: string;
  category: string | null;
  notes: string | null;
  person: QuranWinnerPerson | null;
}

export interface QuranMediaItem {
  id: number;
  media_type: 'image' | 'video' | 'youtube' | string;
  url: string;
  thumbnail_url: string | null;
  caption: string | null;
  sort_order: number;
}

export interface QuranSectionPerson {
  id: number;
  name: string;
  sort_order: number;
}

export interface QuranSection {
  id: number;
  name: string;
  sort_order: number;
  people: QuranSectionPerson[];
}

export interface QuranManager {
  id: number;
  sort_order: number;
  person: QuranWinnerPerson | null;
}

export interface QuranCompetitionDetail {
  id: number;
  title: string;
  description: string | null;
  hijri_year: string;
  start_date: string | null;
  end_date: string | null;
  cover_image_url: string | null;
  category: QuranCategoryRef | null;
  winners: QuranWinner[];
  media: QuranMediaItem[];
  sections: QuranSection[];
  managers: QuranManager[];
}

export type QuranNavigationSuccess = { ok: true; items: QuranNavItem[] };
export type QuranNavigationFailure = { ok: false; message: string };
export type QuranNavigationResult = QuranNavigationSuccess | QuranNavigationFailure;

export type QuranCompetitionsListSuccess = {
  ok: true;
  filters: { category: number | null; search: string | null };
  categories: QuranCategoryRef[];
  current_competitions: QuranCompetitionListItem[];
  previous_competitions: QuranCompetitionListItem[];
};
export type QuranCompetitionsListFailure = { ok: false; message: string; status?: number };
export type QuranCompetitionsListResult =
  | QuranCompetitionsListSuccess
  | QuranCompetitionsListFailure;

export type QuranCategoryPageSuccess = {
  ok: true;
  redirect_to_competition_id: number | null;
  category: QuranCategoryRef;
  current_competitions: QuranCompetitionListItem[];
  previous_competitions: QuranCompetitionListItem[];
};
export type QuranCategoryPageFailure = { ok: false; message: string; status?: number };
export type QuranCategoryPageResult = QuranCategoryPageSuccess | QuranCategoryPageFailure;

export type QuranCompetitionShowSuccess = {
  ok: true;
  is_single_in_category: boolean;
  competition: QuranCompetitionDetail;
};
export type QuranCompetitionShowFailure = { ok: false; message: string; status?: number };
export type QuranCompetitionShowResult = QuranCompetitionShowSuccess | QuranCompetitionShowFailure;

export interface FetchQuranCompetitionsParams {
  category?: number;
  search?: string;
}

function extractErrorMessage(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && 'message' in data) {
    const m = (data as { message?: unknown }).message;
    if (typeof m === 'string' && m.trim()) return m;
  }
  return fallback;
}

function axiosFailure(
  e: unknown,
  fallback: string
): { ok: false; message: string; status?: number } {
  if (isAxiosError(e) && e.response) {
    return {
      ok: false,
      message: extractErrorMessage(e.response.data, fallback),
      status: e.response.status,
    };
  }
  return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
}

function parseListItem(raw: unknown): QuranCompetitionListItem | null {
  if (!raw || typeof raw !== 'object') return null;
  const o = raw as Record<string, unknown>;
  if (typeof o.id !== 'number' || typeof o.title !== 'string') return null;
  return raw as QuranCompetitionListItem;
}

function parseList(raw: unknown): QuranCompetitionListItem[] {
  if (!Array.isArray(raw)) return [];
  return raw.map(parseListItem).filter((x): x is QuranCompetitionListItem => x != null);
}

/** `GET /api/quran-competitions/navigation` */
export async function fetchQuranNavigation(): Promise<QuranNavigationResult> {
  try {
    const raw = (await api.get('/quran-competitions/navigation', {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل قائمة القرآن والسنة.' };
    }
    const body = raw as { success?: boolean; data?: unknown; message?: string };
    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'تعذر تحميل قائمة القرآن والسنة.'),
      };
    }
    const items = Array.isArray(body.data) ? (body.data as QuranNavItem[]) : [];
    return { ok: true, items };
  } catch (e) {
    return axiosFailure(e, 'تعذر تحميل قائمة القرآن والسنة.');
  }
}

/** `GET /api/quran-competitions` */
export async function fetchQuranCompetitions(
  params?: FetchQuranCompetitionsParams
): Promise<QuranCompetitionsListResult> {
  const query: Record<string, string | number> = {};
  if (params?.category != null && params.category > 0) query.category = params.category;
  const s = params?.search?.trim().slice(0, 120);
  if (s) query.search = s;

  try {
    const raw = (await api.get('/quran-competitions', {
      params: Object.keys(query).length ? query : undefined,
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المسابقات.' };
    }
    const body = raw as {
      success?: boolean;
      message?: string;
      filters?: { category?: number | null; search?: string | null };
      categories?: unknown;
      current_competitions?: unknown;
      previous_competitions?: unknown;
    };
    if (body.success === false) {
      return { ok: false, message: extractErrorMessage(body, 'تعذر تحميل المسابقات.') };
    }
    const filters = body.filters ?? { category: null, search: null };
    const categories = Array.isArray(body.categories)
      ? (body.categories as QuranCategoryRef[])
      : [];
    return {
      ok: true,
      filters: {
        category: filters.category ?? null,
        search: filters.search ?? null,
      },
      categories,
      current_competitions: parseList(body.current_competitions),
      previous_competitions: parseList(body.previous_competitions),
    };
  } catch (e) {
    return axiosFailure(e, 'تعذر تحميل المسابقات.');
  }
}

/** `GET /api/quran-competitions/categories/{id}` */
export async function fetchQuranCategory(
  categoryId: number
): Promise<QuranCategoryPageResult> {
  try {
    const raw = (await api.get(`/quran-competitions/categories/${categoryId}`, {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل التصنيف.' };
    }
    const body = raw as {
      success?: boolean;
      message?: string;
      redirect_to_competition_id?: number | null;
      category?: QuranCategoryRef;
      current_competitions?: unknown;
      previous_competitions?: unknown;
    };
    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'التصنيف غير موجود أو غير متاح.'),
        status: 404,
      };
    }
    if (!body.category || typeof body.category.id !== 'number') {
      return { ok: false, message: 'تعذر تحميل التصنيف.' };
    }
    return {
      ok: true,
      redirect_to_competition_id: body.redirect_to_competition_id ?? null,
      category: body.category,
      current_competitions: parseList(body.current_competitions),
      previous_competitions: parseList(body.previous_competitions),
    };
  } catch (e) {
    const fail = axiosFailure(e, 'تعذر تحميل التصنيف.');
    if (isAxiosError(e) && e.response?.status === 404) {
      fail.status = 404;
    }
    return fail;
  }
}

/** `GET /api/quran-competitions/{id}` */
export async function fetchQuranCompetition(
  competitionId: number
): Promise<QuranCompetitionShowResult> {
  try {
    const raw = (await api.get(`/quran-competitions/${competitionId}`, {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المسابقة.' };
    }
    const body = raw as {
      success?: boolean;
      message?: string;
      is_single_in_category?: boolean;
      data?: QuranCompetitionDetail;
    };
    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'المسابقة غير موجودة أو غير متاحة.'),
        status: 404,
      };
    }
    if (!body.data || typeof body.data.id !== 'number') {
      return { ok: false, message: 'تعذر تحميل المسابقة.' };
    }
    const d = body.data;
    return {
      ok: true,
      is_single_in_category: Boolean(body.is_single_in_category),
      competition: {
        ...d,
        winners: Array.isArray(d.winners) ? d.winners : [],
        media: Array.isArray(d.media) ? d.media : [],
        sections: Array.isArray(d.sections) ? d.sections : [],
        managers: Array.isArray(d.managers) ? d.managers : [],
      },
    };
  } catch (e) {
    const fail = axiosFailure(e, 'تعذر تحميل المسابقة.');
    if (isAxiosError(e) && e.response?.status === 404) {
      fail.status = 404;
    }
    return fail;
  }
}

/** تحويل وسائط القرآن لشكل متوافق مع StoreMediaGallery */
export function quranMediaToStoreShape(
  media: QuranMediaItem[]
): { id: number; media_type: string; url: string; thumbnail_url: string | null }[] {
  return media.map((m) => ({
    id: m.id,
    media_type: m.media_type,
    url: m.url,
    thumbnail_url: m.thumbnail_url,
  }));
}
