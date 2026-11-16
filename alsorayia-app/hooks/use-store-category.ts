import {
  fetchStoreCategoryPage,
  fetchStoreProducts,
  type ProductListItem,
  type StoreCategory,
  type StorePaginationMeta,
  type StoreSubcategory,
} from '@/lib/api/store';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const PER_PAGE = 12;

function productsQueryKey(
  categoryId: number,
  subcategoryId: number | null
): string {
  return `c:${categoryId}|s:${subcategoryId ?? 'all'}`;
}

export function useStoreCategory(categoryId: number) {
  const [category, setCategory] = useState<StoreCategory | null>(null);
  const [subcategories, setSubcategories] = useState<StoreSubcategory[]>([]);
  const [selectedSubcategoryId, setSelectedSubcategoryId] = useState<number | null>(
    null
  );

  const [products, setProducts] = useState<ProductListItem[]>([]);
  const [meta, setMeta] = useState<StorePaginationMeta | null>(null);
  const [pageLoading, setPageLoading] = useState(true);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const productsRequestId = useRef(0);
  const activeQueryKey = useRef<string>('');

  const fetchProductsPage = useCallback(
    async (
      pageNum: number,
      append: boolean,
      queryKeySnapshot: string,
      subcategoryId: number | null
    ) => {
      const rid = ++productsRequestId.current;

      const res = await fetchStoreProducts({
        page: pageNum,
        per_page: PER_PAGE,
        category: categoryId,
        subcategory: subcategoryId ?? undefined,
      });

      if (rid !== productsRequestId.current) return;
      if (queryKeySnapshot !== activeQueryKey.current) return;

      if (!res.ok) {
        if (append) {
          setLoadingMore(false);
        } else {
          setLoading(false);
          setRefreshing(false);
        }
        setError(res.message);
        return;
      }

      setError(null);

      if (append) {
        setProducts((prev) => {
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
        setProducts(res.items);
      }
      setMeta(res.meta);

      if (append) {
        setLoadingMore(false);
      } else {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [categoryId]
  );

  const loadCategoryInitial = useCallback(async () => {
    if (!Number.isFinite(categoryId) || categoryId <= 0) {
      setNotFound(true);
      setPageLoading(false);
      return;
    }

    setPageLoading(true);
    setNotFound(false);
    setError(null);
    setSelectedSubcategoryId(null);

    const res = await fetchStoreCategoryPage(categoryId, 1);
    setPageLoading(false);

    if (!res.ok) {
      setError(res.message);
      setNotFound(res.status === 404);
      setCategory(null);
      setSubcategories([]);
      setProducts([]);
      setMeta(null);
      return;
    }

    setCategory(res.category);
    setSubcategories(res.subcategories);
    setProducts(res.items);
    setMeta(res.meta);
    activeQueryKey.current = productsQueryKey(categoryId, null);
  }, [categoryId]);

  useEffect(() => {
    void loadCategoryInitial();
  }, [loadCategoryInitial]);

  const qKey = useMemo(
    () => productsQueryKey(categoryId, selectedSubcategoryId),
    [categoryId, selectedSubcategoryId]
  );

  useEffect(() => {
    if (pageLoading || notFound) return;
    if (selectedSubcategoryId == null) return;

    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    void fetchProductsPage(1, false, qKey, selectedSubcategoryId);
  }, [selectedSubcategoryId, qKey, pageLoading, notFound, fetchProductsPage]);

  const selectSubcategory = useCallback(
    (id: number | null) => {
      setSelectedSubcategoryId(id);
      if (id == null) {
        setLoading(true);
        setError(null);
        void (async () => {
          const res = await fetchStoreCategoryPage(categoryId, 1);
          setLoading(false);
          if (!res.ok) {
            setError(res.message);
            setNotFound(res.status === 404);
            return;
          }
          setProducts(res.items);
          setMeta(res.meta);
          activeQueryKey.current = productsQueryKey(categoryId, null);
        })();
      }
    },
    [categoryId]
  );

  const refresh = useCallback(async () => {
    setRefreshing(true);
    setError(null);
    setNotFound(false);

    if (selectedSubcategoryId == null) {
      const res = await fetchStoreCategoryPage(categoryId, 1);
      setRefreshing(false);
      if (!res.ok) {
        setError(res.message);
        setNotFound(res.status === 404);
        return;
      }
      setCategory(res.category);
      setSubcategories(res.subcategories);
      setProducts(res.items);
      setMeta(res.meta);
      activeQueryKey.current = productsQueryKey(categoryId, null);
      return;
    }

    activeQueryKey.current = qKey;
    await fetchProductsPage(1, false, qKey, selectedSubcategoryId);
  }, [categoryId, selectedSubcategoryId, qKey, fetchProductsPage]);

  const loadMore = useCallback(() => {
    if (!meta || loading || loadingMore || refreshing || pageLoading) return;
    if (meta.current_page >= meta.last_page) return;
    if (error || notFound) return;

    const nextPage = meta.current_page + 1;
    setLoadingMore(true);
    void fetchProductsPage(nextPage, true, qKey, selectedSubcategoryId);
  }, [
    meta,
    loading,
    loadingMore,
    refreshing,
    pageLoading,
    error,
    notFound,
    fetchProductsPage,
    qKey,
    selectedSubcategoryId,
  ]);

  const retry = useCallback(() => {
    void loadCategoryInitial();
  }, [loadCategoryInitial]);

  const hasMore = meta != null && meta.current_page < meta.last_page;

  return {
    category,
    subcategories,
    selectedSubcategoryId,
    selectSubcategory,
    products,
    meta,
    pageLoading,
    loading,
    loadingMore,
    refreshing,
    error,
    notFound,
    refresh,
    loadMore,
    retry,
    hasMore,
  };
}
