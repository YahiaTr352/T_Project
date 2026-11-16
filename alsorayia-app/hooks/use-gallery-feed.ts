import {
  fetchGalleryCategories,
  fetchGalleryImages,
  type GalleryCategory,
  type GalleryImageItem,
  type GalleryImagesMeta,
} from '@/lib/api/gallery';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const PER_PAGE = 24;
const SEARCH_DEBOUNCE_MS = 350;

function queryKey(categoryId: number | null, search: string): string {
  return `${categoryId ?? 'all'}|${search.trim()}`;
}

export function useGalleryFeed() {
  const [categories, setCategories] = useState<GalleryCategory[] | null>(null);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [categoriesError, setCategoriesError] = useState<string | null>(null);

  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const [items, setItems] = useState<GalleryImageItem[]>([]);
  const [meta, setMeta] = useState<GalleryImagesMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [categoryError, setCategoryError] = useState<string | null>(null);

  const imagesRequestId = useRef(0);
  const activeQueryKey = useRef<string>('');

  useEffect(() => {
    const t = setTimeout(() => {
      setDebouncedSearch(searchInput);
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [searchInput]);

  const loadCategories = useCallback(async () => {
    setCategoriesError(null);
    setCategoriesLoading(true);
    const list = await fetchGalleryCategories();
    setCategoriesLoading(false);
    if (list === null) {
      setCategoriesError('تعذر تحميل التصنيفات.');
      setCategories([]);
      return;
    }
    setCategories(list);
  }, []);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  const fetchImagesPage = useCallback(
    async (
      pageNum: number,
      append: boolean,
      queryKeySnapshot: string
    ): Promise<void> => {
      const rid = ++imagesRequestId.current;

      const res = await fetchGalleryImages({
        page: pageNum,
        per_page: PER_PAGE,
        category: categoryId ?? undefined,
        deep: categoryId != null ? true : undefined,
        search: debouncedSearch.trim() || undefined,
      });

      if (rid !== imagesRequestId.current) return;
      if (queryKeySnapshot !== activeQueryKey.current) return;

      if (!res.ok) {
        if (append) {
          setLoadingMore(false);
        } else {
          setLoading(false);
          setRefreshing(false);
          setItems([]);
          setMeta(null);
          setPage(1);
        }
        if (res.status === 404) {
          setCategoryError(res.message);
          setError(null);
        } else {
          setError(res.message);
          setCategoryError(null);
        }
        return;
      }

      setCategoryError(null);
      setError(null);

      if (append) {
        setItems((prev) => {
          const seen = new Set(prev.map((x) => x.id));
          const next = [...prev];
          for (const it of res.items) {
            if (!seen.has(it.id)) {
              seen.add(it.id);
              next.push(it);
            }
          }
          return next;
        });
      } else {
        setItems(res.items);
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
    [categoryId, debouncedSearch]
  );

  const qKey = useMemo(
    () => queryKey(categoryId, debouncedSearch),
    [categoryId, debouncedSearch]
  );

  useEffect(() => {
    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    setCategoryError(null);
    setPage(1);
    void fetchImagesPage(1, false, qKey);
  }, [qKey, fetchImagesPage]);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    await loadCategories();
    activeQueryKey.current = qKey;
    setPage(1);
    setError(null);
    setCategoryError(null);
    await fetchImagesPage(1, false, qKey);
  }, [loadCategories, fetchImagesPage, qKey]);

  const loadMore = useCallback(() => {
    if (!meta || loading || loadingMore || refreshing) return;
    if (meta.current_page >= meta.last_page) return;
    if (error || categoryError) return;

    const nextPage = meta.current_page + 1;
    setLoadingMore(true);
    void fetchImagesPage(nextPage, true, qKey);
  }, [meta, loading, loadingMore, refreshing, error, categoryError, fetchImagesPage, qKey]);

  const selectCategory = useCallback((id: number | null) => {
    setCategoryId(id);
  }, []);

  const retry = useCallback(() => {
    void loadCategories();
    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    setCategoryError(null);
    void fetchImagesPage(1, false, qKey);
  }, [loadCategories, fetchImagesPage, qKey]);

  const hasMore = meta != null && meta.current_page < meta.last_page;

  return {
    categories,
    categoriesLoading,
    categoriesError,
    categoryId,
    selectCategory,
    searchInput,
    setSearchInput,
    debouncedSearch,
    items,
    meta,
    page,
    loading,
    loadingMore,
    refreshing,
    error,
    categoryError,
    refresh,
    loadMore,
    retry,
    hasMore,
    perPage: PER_PAGE,
  };
}
