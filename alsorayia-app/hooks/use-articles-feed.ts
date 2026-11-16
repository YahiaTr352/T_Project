import {
  fetchArticles,
  type ArticleCategory,
  type ArticleItem,
  type ArticlesPaginationMeta,
} from '@/lib/api/articles';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const PER_PAGE = 12;
const SEARCH_DEBOUNCE_MS = 350;

function queryKey(
  categoryId: number | null,
  year: number | string | null,
  search: string
): string {
  return `${categoryId ?? 'all'}|${year ?? 'all'}|${search.trim()}`;
}

export function useArticlesFeed() {
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [selectedYear, setSelectedYear] = useState<number | string | null>('');
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const [articles, setArticles] = useState<ArticleItem[]>([]);
  const [categories, setCategories] = useState<ArticleCategory[]>([]);
  const [availableYears, setAvailableYears] = useState<number[]>([]);
  const [stats, setStats] = useState({
    total_articles: 0,
    total_authors: 0,
    total_images: 0,
  });

  const [meta, setMeta] = useState<ArticlesPaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const articlesRequestId = useRef(0);
  const activeQueryKey = useRef<string>('');

  useEffect(() => {
    const t = setTimeout(() => {
      setDebouncedSearch(searchInput);
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [searchInput]);

  const fetchArticlesPage = useCallback(
    async (
      pageNum: number,
      append: boolean,
      queryKeySnapshot: string
    ): Promise<void> => {
      const rid = ++articlesRequestId.current;

      const res = await fetchArticles({
        page: pageNum,
        per_page: PER_PAGE,
        category: categoryId,
        year: selectedYear,
        search: debouncedSearch.trim() || undefined,
      });

      if (rid !== articlesRequestId.current) return;
      if (queryKeySnapshot !== activeQueryKey.current) return;

      if (!res.ok) {
        if (append) {
          setLoadingMore(false);
        } else {
          setLoading(false);
          setRefreshing(false);
          setArticles([]);
          setMeta(null);
          setPage(1);
        }
        setError(res.message);
        return;
      }

      setError(null);

      if (append) {
        setArticles((prev) => {
          const seen = new Set(prev.map((x) => x.id));
          const next = [...prev];
          for (const it of res.articles) {
            if (!seen.has(it.id)) {
              seen.add(it.id);
              next.push(it);
            }
          }
          return next;
        });
      } else {
        setArticles(res.articles);
        setCategories(res.filters.categories);
        setAvailableYears(res.filters.available_years);
        setStats(res.stats);
      }
      
      setMeta(res.meta);
      setPage(res.meta.current_page);

      if (append) {
        setLoadingMore(false);
      } else {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [categoryId, selectedYear, debouncedSearch]
  );

  const qKey = useMemo(
    () => queryKey(categoryId, selectedYear, debouncedSearch),
    [categoryId, selectedYear, debouncedSearch]
  );

  useEffect(() => {
    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    setPage(1);
    void fetchArticlesPage(1, false, qKey);
  }, [qKey, fetchArticlesPage]);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    activeQueryKey.current = qKey;
    setPage(1);
    setError(null);
    await fetchArticlesPage(1, false, qKey);
  }, [fetchArticlesPage, qKey]);

  const loadMore = useCallback(() => {
    if (!meta || loading || loadingMore || refreshing) return;
    if (meta.current_page >= meta.last_page) return;
    if (error) return;

    const nextPage = meta.current_page + 1;
    setLoadingMore(true);
    void fetchArticlesPage(nextPage, true, qKey);
  }, [meta, loading, loadingMore, refreshing, error, fetchArticlesPage, qKey]);

  const selectCategory = useCallback((id: number | null) => {
    setCategoryId(id);
  }, []);

  const selectYear = useCallback((yr: number | string | null) => {
    setSelectedYear(yr);
  }, []);

  const retry = useCallback(() => {
    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    void fetchArticlesPage(1, false, qKey);
  }, [fetchArticlesPage, qKey]);

  const hasMore = meta != null && meta.current_page < meta.last_page;

  return {
    articles,
    categories,
    availableYears,
    stats,
    categoryId,
    selectedYear,
    selectCategory,
    selectYear,
    searchInput,
    setSearchInput,
    debouncedSearch,
    meta,
    page,
    loading,
    loadingMore,
    refreshing,
    error,
    refresh,
    loadMore,
    retry,
    hasMore,
  };
}
