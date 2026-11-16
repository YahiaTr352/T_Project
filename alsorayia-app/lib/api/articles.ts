import { api } from '@/lib/api/alsorayiaClient';

export interface ArticleAuthor {
  id: number | null;
  name: string;
  avatar: string | null;
}

export interface ArticleCategory {
  id: number | null;
  name: string;
  articles_count?: number;
}

export interface ArticleImage {
  id: number;
  path: string;
  thumbnail_path: string | null;
}

export interface ArticleVideo {
  id: number;
  provider: string;
  video_id: string;
  youtube_url: string | null;
}

export interface ArticleItem {
  id: number;
  title: string;
  excerpt: string;
  created_at: string;
  category: ArticleCategory;
  author: ArticleAuthor;
  images: ArticleImage[];
}

export interface ArticleDetail {
  id: number;
  title: string;
  content: string;
  created_at: string;
  category: ArticleCategory;
  author: ArticleAuthor;
  images: ArticleImage[];
  videos: ArticleVideo[];
}

export interface RelatedArticle {
  id: number;
  title: string;
  created_at: string;
  author: {
    name: string;
    avatar: string | null;
  };
  image: string | null;
}

export interface ArticlesPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ArticlesFilterData {
  available_years: number[];
  categories: ArticleCategory[];
}

export interface ArticlesResultSuccess {
  ok: true;
  articles: ArticleItem[];
  filters: ArticlesFilterData;
  stats: {
    total_articles: number;
    total_authors: number;
    total_images: number;
  };
  meta: ArticlesPaginationMeta;
}

export interface ArticlesResultFailure {
  ok: false;
  message: string;
}

export type ArticlesResult = ArticlesResultSuccess | ArticlesResultFailure;

export interface ArticlesQuery {
  page?: number;
  per_page?: number;
  category?: number | null;
  year?: number | string | null;
  search?: string;
  author?: number | null;
  degree?: string | null;
}

/**
 * `GET /api/gallery/articles` مع باجينيشن وفلاتر.
 */
export async function fetchArticles(query: ArticlesQuery): Promise<ArticlesResult> {
  const params: Record<string, any> = {
    page: query.page ?? 1,
    per_page: query.per_page ?? 12,
  };

  if (query.category != null) params.category = query.category;
  if (query.year != null && query.year !== '') params.year = query.year;
  if (query.search?.trim()) params.search = query.search.trim();
  if (query.author != null) params.author = query.author;
  if (query.degree) params.degree = query.degree;

  try {
    const raw = (await api.get('/gallery/articles', { params })) as any;
    if (!raw || typeof raw !== 'object' || !raw.success) {
      return { ok: false, message: raw?.message || 'تعذر تحميل المقالات.' };
    }
    return {
      ok: true,
      articles: raw.data.articles,
      filters: raw.data.filters,
      stats: raw.data.stats,
      meta: raw.meta,
    };
  } catch (error: any) {
    return { ok: false, message: error?.message || 'فشل الاتصال بالخادم.' };
  }
}

export interface ArticleDetailResultSuccess {
  ok: true;
  article: ArticleDetail;
  related_articles: RelatedArticle[];
}

export type ArticleDetailResult = ArticleDetailResultSuccess | ArticlesResultFailure;

/**
 * `GET /api/gallery/articles/{id}` للحصول على تفاصيل مقال معين.
 */
export async function fetchArticleDetail(id: number): Promise<ArticleDetailResult> {
  try {
    const raw = (await api.get(`/gallery/articles/${id}`)) as any;
    if (!raw || typeof raw !== 'object' || !raw.success) {
      return { ok: false, message: raw?.message || 'تعذر تحميل تفاصيل المقال.' };
    }
    return {
      ok: true,
      article: raw.data.article,
      related_articles: raw.data.related_articles,
    };
  } catch (error: any) {
    return { ok: false, message: error?.message || 'فشل الاتصال بالخادم.' };
  }
}
