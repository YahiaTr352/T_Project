import { isAxiosError } from 'axios';
import { api } from '@/lib/api/alsorayiaClient';

export interface StoreFilters {
  category: number | null;
  subcategory: number | null;
  person: number | null;
  search: string | null;
}

export interface StorePaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface StorePaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  path: string;
  links: StorePaginationLinks;
}

export interface StoreCategoryRef {
  id: number;
  name: string;
}

export interface ProductListItem {
  id: number;
  name: string;
  description: string | null;
  price: string | null;
  main_image_url: string | null;
  category: StoreCategoryRef | null;
  subcategory: StoreCategoryRef | null;
  owner: { id: number; name: string } | null;
}

export interface StoreCategory {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  products_count: number;
  sort_order: number;
}

export interface StoreOwner {
  id: number;
  name: string;
  products_count: number;
}

export interface StoreSubcategory {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  product_category_id: number;
  category: StoreCategoryRef | null;
  sort_order: number;
}

export interface StoreContact {
  phone: string | null;
  whatsapp: string | null;
  email: string | null;
  instagram: string | null;
  facebook: string | null;
  website_url: string | null;
  location_url: string | null;
}

export interface StoreMediaItem {
  id: number;
  media_type: 'image' | 'video' | 'youtube' | string;
  url: string;
  thumbnail_url: string | null;
}

export interface StoreProductDetail {
  id: number;
  name: string;
  description: string | null;
  features: string[];
  price: string | null;
  price_formatted: string | null;
  main_image_url: string | null;
  contact: StoreContact;
  category: StoreCategoryRef | null;
  subcategory: StoreCategoryRef | null;
  owner: { id: number; name: string } | null;
  location: { id: number; name: string } | null;
  media: StoreMediaItem[];
}

export type StoreProductsSuccess = {
  ok: true;
  items: ProductListItem[];
  meta: StorePaginationMeta;
  filters?: StoreFilters;
};

export type StoreProductsFailure = {
  ok: false;
  message: string;
  status?: number;
};

export type StoreProductsResult = StoreProductsSuccess | StoreProductsFailure;

export interface StoreProductsQuery {
  page?: number;
  per_page?: number;
  category?: number | null;
  subcategory?: number | null;
  person?: number | null;
  search?: string;
}

export type StoreLandingSuccess = {
  ok: true;
  categories: StoreCategory[];
  owners: StoreOwner[];
  filters: StoreFilters;
};

export type StoreLandingFailure = {
  ok: false;
  message: string;
};

export type StoreLandingResult = StoreLandingSuccess | StoreLandingFailure;

export type StoreCategoryPageSuccess = {
  ok: true;
  category: StoreCategory;
  subcategories: StoreSubcategory[];
  items: ProductListItem[];
  meta: StorePaginationMeta;
};

export type StoreCategoryPageFailure = {
  ok: false;
  message: string;
  status?: number;
};

export type StoreCategoryPageResult = StoreCategoryPageSuccess | StoreCategoryPageFailure;

export type StoreProductShowSuccess = {
  ok: true;
  product: StoreProductDetail;
  related: ProductListItem[];
};

export type StoreProductShowFailure = {
  ok: false;
  message: string;
  status?: number;
};

export type StoreProductShowResult = StoreProductShowSuccess | StoreProductShowFailure;

const DEFAULT_PER_PAGE = 12;

export function emptyStoreMeta(path = '/store/products'): StorePaginationMeta {
  return {
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    total: 0,
    from: null,
    to: null,
    path,
    links: { first: null, last: null, prev: null, next: null },
  };
}

function hasProductFilter(query: StoreProductsQuery): boolean {
  if (query.category != null && query.category > 0) return true;
  if (query.subcategory != null && query.subcategory > 0) return true;
  if (query.person != null && query.person > 0) return true;
  const s = query.search?.trim();
  return !!s;
}

function parseMeta(raw: unknown): StorePaginationMeta | null {
  if (!raw || typeof raw !== 'object') return null;
  const m = raw as Partial<StorePaginationMeta>;
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
        ? (m.links as StorePaginationLinks)
        : { first: null, last: null, prev: null, next: null },
  };
}

function parseFilters(raw: unknown): StoreFilters {
  const f = (raw && typeof raw === 'object' ? raw : {}) as Partial<StoreFilters>;
  return {
    category: typeof f.category === 'number' ? f.category : null,
    subcategory: typeof f.subcategory === 'number' ? f.subcategory : null,
    person: typeof f.person === 'number' ? f.person : null,
    search: typeof f.search === 'string' ? f.search : null,
  };
}

function sortCategories(list: StoreCategory[]): StoreCategory[] {
  return [...list].sort((a, b) => {
    const o = a.sort_order - b.sort_order;
    if (o !== 0) return o;
    return a.name.localeCompare(b.name, 'ar');
  });
}

function sortSubcategories(list: StoreSubcategory[]): StoreSubcategory[] {
  return [...list].sort((a, b) => {
    const o = a.sort_order - b.sort_order;
    if (o !== 0) return o;
    return a.name.localeCompare(b.name, 'ar');
  });
}

function extractErrorMessage(
  data: unknown,
  fallback: string
): string {
  if (data && typeof data === 'object' && 'message' in data) {
    const m = (data as { message?: unknown }).message;
    if (typeof m === 'string' && m.trim()) return m;
  }
  return fallback;
}

/** `GET /api/store` */
export async function fetchStoreLanding(): Promise<StoreLandingResult> {
  try {
    const raw = (await api.get('/store', { skipGlobalErrorHandler: true })) as unknown;
    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المتجر.' };
    }
    const body = raw as {
      success?: boolean;
      categories?: unknown;
      owners?: unknown;
      filters?: unknown;
      message?: string;
    };
    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'تعذر تحميل المتجر.'),
      };
    }
    const categories = Array.isArray(body.categories)
      ? sortCategories(body.categories as StoreCategory[])
      : [];
    const owners = Array.isArray(body.owners) ? (body.owners as StoreOwner[]) : [];
    return {
      ok: true,
      categories,
      owners,
      filters: parseFilters(body.filters),
    };
  } catch (e) {
    if (isAxiosError(e) && e.response) {
      return {
        ok: false,
        message: extractErrorMessage(e.response.data, 'تعذر تحميل المتجر.'),
      };
    }
    return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
  }
}

/** `GET /api/store/products` */
export async function fetchStoreProducts(
  query: StoreProductsQuery
): Promise<StoreProductsResult> {
  if (!hasProductFilter(query)) {
    return { ok: true, items: [], meta: emptyStoreMeta() };
  }

  const params: Record<string, string | number> = {
    page: query.page ?? 1,
    per_page: Math.min(50, Math.max(1, query.per_page ?? DEFAULT_PER_PAGE)),
  };
  if (query.category != null && query.category > 0) params.category = query.category;
  if (query.subcategory != null && query.subcategory > 0) {
    params.subcategory = query.subcategory;
  }
  if (query.person != null && query.person > 0) params.person = query.person;
  const s = query.search?.trim().slice(0, 120);
  if (s) params.search = s;

  try {
    const raw = (await api.get('/store/products', {
      params,
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المنتجات.' };
    }

    const body = raw as {
      success?: boolean;
      data?: unknown;
      meta?: unknown;
      filters?: unknown;
      message?: string;
    };

    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'تعذر تحميل المنتجات.'),
      };
    }

    const items = Array.isArray(body.data) ? (body.data as ProductListItem[]) : [];
    const meta = parseMeta(body.meta);
    if (!meta) {
      return { ok: false, message: 'استجابة غير صالحة من الخادم.' };
    }

    return {
      ok: true,
      items,
      meta,
      filters: parseFilters(body.filters),
    };
  } catch (e) {
    if (isAxiosError(e) && e.response) {
      return {
        ok: false,
        message: extractErrorMessage(e.response.data, 'تعذر تحميل المنتجات.'),
        status: e.response.status,
      };
    }
    return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
  }
}

/** `GET /api/store/categories/{id}` */
export async function fetchStoreCategoryPage(
  categoryId: number,
  page = 1
): Promise<StoreCategoryPageResult> {
  try {
    const raw = (await api.get(`/store/categories/${categoryId}`, {
      params: { page },
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل التصنيف.' };
    }

    const body = raw as {
      success?: boolean;
      category?: unknown;
      subcategories?: unknown;
      products?: { data?: unknown; meta?: unknown };
      message?: string;
    };

    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'التصنيف غير موجود أو غير متاح.'),
        status: 404,
      };
    }

    if (!body.category || typeof body.category !== 'object') {
      return { ok: false, message: 'استجابة غير صالحة من الخادم.' };
    }

    const subcategories = Array.isArray(body.subcategories)
      ? sortSubcategories(body.subcategories as StoreSubcategory[])
      : [];
    const productsBlock = body.products;
    const items =
      productsBlock &&
      typeof productsBlock === 'object' &&
      Array.isArray((productsBlock as { data?: unknown }).data)
        ? ((productsBlock as { data: ProductListItem[] }).data)
        : [];
    const meta = parseMeta(
      productsBlock && typeof productsBlock === 'object'
        ? (productsBlock as { meta?: unknown }).meta
        : null
    );
    if (!meta) {
      return { ok: false, message: 'استجابة غير صالحة من الخادم.' };
    }

    return {
      ok: true,
      category: body.category as StoreCategory,
      subcategories,
      items,
      meta,
    };
  } catch (e) {
    if (isAxiosError(e) && e.response) {
      return {
        ok: false,
        message: extractErrorMessage(
          e.response.data,
          'التصنيف غير موجود أو غير متاح.'
        ),
        status: e.response.status,
      };
    }
    return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
  }
}

/** `GET /api/store/products/{id}` */
export async function fetchStoreProduct(
  productId: number
): Promise<StoreProductShowResult> {
  try {
    const raw = (await api.get(`/store/products/${productId}`, {
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المنتج.' };
    }

    const body = raw as {
      success?: boolean;
      data?: unknown;
      related_products?: unknown;
      message?: string;
    };

    if (body.success === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'المنتج غير موجود.'),
        status: 404,
      };
    }

    if (!body.data || typeof body.data !== 'object') {
      return { ok: false, message: 'استجابة غير صالحة من الخادم.' };
    }

    const d = body.data as StoreProductDetail & { features?: unknown };
    const product: StoreProductDetail = {
      ...d,
      features: Array.isArray(d.features)
        ? d.features.filter((x): x is string => typeof x === 'string')
        : [],
      contact: d.contact ?? {
        phone: null,
        whatsapp: null,
        email: null,
        instagram: null,
        facebook: null,
        website_url: null,
        location_url: null,
      },
      media: Array.isArray(d.media) ? d.media : [],
    };

    const related = Array.isArray(body.related_products)
      ? (body.related_products as ProductListItem[])
      : [];

    return { ok: true, product, related };
  } catch (e) {
    if (isAxiosError(e) && e.response) {
      return {
        ok: false,
        message: extractErrorMessage(e.response.data, 'المنتج غير موجود.'),
        status: e.response.status,
      };
    }
    return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
  }
}

export function buildWhatsappUrl(whatsapp: string): string {
  const digits = whatsapp.replace(/\D/g, '');
  return `https://wa.me/${digits}`;
}

export function formatListPrice(price: string | null | undefined): string {
  const p = price?.trim();
  if (!p) return 'السعر عند الطلب';
  return `${p} ر.س`;
}

export function formatDetailPrice(
  priceFormatted: string | null | undefined,
  price: string | null | undefined
): string {
  const f = priceFormatted?.trim();
  if (f) return f;
  return formatListPrice(price);
}

export function normalizeExternalUrl(raw: string | null | undefined): string | null {
  const u = raw?.trim();
  if (!u) return null;
  if (/^https?:\/\//i.test(u)) return u;
  if (u.startsWith('@')) return `https://instagram.com/${u.slice(1)}`;
  return u;
}
