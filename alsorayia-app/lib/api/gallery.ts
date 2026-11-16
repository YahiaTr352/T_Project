import { isAxiosError } from 'axios';
import { api, apiUpload } from '@/lib/api/alsorayiaClient';

/** يطابق عنصر `data` في `GET /api/gallery/categories`. */
export interface GalleryCategory {
  id: number;
  name: string;
  description: string | null;
  parent_id: number | null;
  sort_order: number;
  is_active: boolean;
  total_images_count: number;
}

export interface GalleryCategoryPerson {
  id: number;
  name: string;
}

export interface GalleryImageCategory {
  id: number;
  name: string;
  parent_id: number | null;
}

export interface GalleryImageArticle {
  id: number;
  title: string;
  person?: GalleryCategoryPerson | null;
}

/** عنصر في `GET /api/gallery/images` — `data[]`. */
export interface GalleryImageItem {
  id: number;
  name: string;
  description: string | null;
  media_type: string;
  youtube_url: string | null;
  youtube_embed_url: string | null;
  youtube_thumbnail_url: string | null;
  image_url: string | null;
  thumbnail_url: string | null;
  category_id: number | null;
  article_id: number | null;
  category: GalleryImageCategory | null;
  article: GalleryImageArticle | null;
  mentioned_persons: GalleryCategoryPerson[];
  similarity?: string;
  created_at: string;
}

export interface GalleryPaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface GalleryImagesMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  path: string;
  links: GalleryPaginationLinks;
}

export type GalleryImagesSuccess = {
  ok: true;
  items: GalleryImageItem[];
  meta: GalleryImagesMeta;
};

export type GalleryImagesFailure = {
  ok: false;
  message: string;
  status?: number;
};

export type GalleryImagesResult = GalleryImagesSuccess | GalleryImagesFailure;

export interface GalleryImagesQuery {
  page?: number;
  per_page?: number;
  category?: number | null;
  deep?: boolean | 0 | 1;
  search?: string;
}

function sortCategories(list: GalleryCategory[]): GalleryCategory[] {
  return [...list].sort((a, b) => {
    const o = a.sort_order - b.sort_order;
    if (o !== 0) return o;
    return a.name.localeCompare(b.name, 'ar');
  });
}

/**
 * `GET /api/gallery/categories`
 * @see docs Laravel: `frontend-gallery-api.md`
 */
export async function fetchGalleryCategories(): Promise<GalleryCategory[] | null> {
  const raw = (await api.get('/gallery/categories')) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  const d = raw as { success?: boolean; data?: unknown };
  if (d.success === false) return null;
  const data = d.data;
  if (!Array.isArray(data)) return null;
  return sortCategories(data as GalleryCategory[]);
}

function parseMeta(raw: unknown): GalleryImagesMeta | null {
  if (!raw || typeof raw !== 'object') return null;
  const m = raw as Partial<GalleryImagesMeta>;
  if (
    typeof m.current_page !== 'number' ||
    typeof m.last_page !== 'number' ||
    typeof m.per_page !== 'number' ||
    typeof m.total !== 'number'
  ) {
    return null;
  }
  return {
    current_page: m.current_page,
    last_page: m.last_page,
    per_page: m.per_page,
    total: m.total,
    from: typeof m.from === 'number' ? m.from : null,
    to: typeof m.to === 'number' ? m.to : null,
    path: typeof m.path === 'string' ? m.path : '',
    links:
      m.links && typeof m.links === 'object'
        ? (m.links as GalleryPaginationLinks)
        : { first: null, last: null, prev: null, next: null },
  };
}

/**
 * `GET /api/gallery/images` مع باجينيشن وفلاتر.
 * يستخدم `skipGlobalErrorHandler` لعرض رسالة 404 (تصنيف غير متاح) في الواجهة.
 */
export async function fetchGalleryImages(
  query: GalleryImagesQuery
): Promise<GalleryImagesResult> {
  const params: Record<string, string | number> = {
    page: query.page ?? 1,
    per_page: Math.min(50, Math.max(1, query.per_page ?? 24)),
  };
  if (query.category != null && query.category > 0) {
    params.category = query.category;
    params.deep = query.deep === false ? 0 : 1;
  }
  const s = query.search?.trim();
  if (s) params.search = s;

  try {
    const raw = (await api.get('/gallery/images', {
      params,
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المعرض.' };
    }

    const body = raw as {
      success?: boolean;
      data?: unknown;
      meta?: unknown;
      message?: string;
    };

    if (body.success === false) {
      return {
        ok: false,
        message:
          typeof body.message === 'string' && body.message.trim()
            ? body.message
            : 'تعذر تحميل المعرض.',
      };
    }

    const items = Array.isArray(body.data) ? (body.data as GalleryImageItem[]) : [];
    const meta = parseMeta(body.meta);
    if (!meta) {
      return { ok: false, message: 'استجابة غير صالحة من الخادم.' };
    }

    return { ok: true, items, meta };
  } catch (e) {
    if (isAxiosError(e) && e.response) {
      const status = e.response.status;
      const data = e.response.data;
      let message = 'تعذر تحميل المعرض.';
      if (data && typeof data === 'object' && 'message' in data) {
        const m = (data as { message?: unknown }).message;
        if (typeof m === 'string' && m.trim()) {
          message =
            m === 'Server Error' || status >= 500
              ? 'حدث خطأ في الخادم أثناء معالجة الصور.'
              : m;
        }
      }
      return { ok: false, message, status };
    }
    return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
  }
}


export interface GalleryFaceSearchResult {
  ok: boolean;
  message?: string;
  total?: number;
  identified_person?: {
    id: number;
    full_name: string;
    avatar?: string;
    similarity: string;
  } | null;
  items?: GalleryImageItem[];
  categories?: { id: number; name: string; count: number }[];
  articles?: { id: number; title: string; count: number }[];
  years?: { year: string; count: number }[];
}

/**
 * البحث بالوجه في المعرض عبر سيلفي أو رفع صورة
 */
export async function searchGalleryByFace(
  payload: { image_base64: string; threshold?: number } | FormData
): Promise<GalleryFaceSearchResult> {
  try {
    let res: any;
    if (payload && 'image_base64' in payload) {
      res = await api.post('/gallery/search-by-face', payload, {
        timeout: 60000,
        skipRetry: true,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      });
    } else {
      res = await apiUpload('/gallery/search-by-face', payload as FormData, 60000);
    }

    console.log('[FaceSearch] Raw server response:', JSON.stringify(res, null, 2));
    console.log('[FaceSearch] res.data type:', typeof res?.data, 'isArray:', Array.isArray(res?.data), 'length:', Array.isArray(res?.data) ? res.data.length : 'N/A');

    if (res && res.success) {
      return {
        ok: true,
        total: res.total || 0,
        identified_person: res.identified_person || null,
        items: Array.isArray(res.data) ? (res.data as GalleryImageItem[]) : (res.data && typeof res.data === 'object' ? Object.values(res.data) as GalleryImageItem[] : []),
        categories: Array.isArray(res.categories) ? res.categories : [],
        articles: Array.isArray(res.articles) ? res.articles : [],
        years: Array.isArray(res.years) ? res.years : [],
      };
    }

    // Even when success=false, preserve identified_person if the backend returned one.
    // This prevents losing the recognised person info on partial failures.
    return {
      ok: false,
      message: typeof res?.message === 'string' && res.message.trim() ? res.message : 'لم يتم العثور على أي تطابق.',
      identified_person: res?.identified_person || null,
      items: [],
      categories: [],
      articles: [],
      years: [],
    };
  } catch (err: any) {
    let message = 'تعذر الاتصال بخدمة التعرف على الوجوه.';
    if (err?.response?.data?.message) {
      message = err.response.data.message;
    }
    return { ok: false, message, items: [], categories: [], articles: [], years: [] };
  }
}
