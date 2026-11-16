import {
  fetchStoreLanding,
  fetchStoreProducts,
  type ProductListItem,
  type StoreCategory,
  type StoreOwner,
  type StorePaginationMeta,
} from '@/lib/api/store';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const PER_PAGE = 12;
const SEARCH_DEBOUNCE_MS = 350;

function queryKey(personId: number | null, search: string): string {
  return `p:${personId ?? 'none'}|s:${search.trim()}`;
}

export function useStoreLanding() {
  const [categories, setCategories] = useState<StoreCategory[]>([]);
  const [owners, setOwners] = useState<StoreOwner[]>([]);
  const [landingLoading, setLandingLoading] = useState(true);
  const [landingError, setLandingError] = useState<string | null>(null);

  const [personId, setPersonId] = useState<number | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const [products, setProducts] = useState<ProductListItem[]>([]);
  const [meta, setMeta] = useState<StorePaginationMeta | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const productsRequestId = useRef(0);
  const activeQueryKey = useRef<string>('');

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [searchInput]);

  const hasActiveProductFilter = useMemo(() => {
    return personId != null || debouncedSearch.trim().length > 0;
  }, [personId, debouncedSearch]);

  const loadLanding = useCallback(async () => {
    setLandingError(null);
    setLandingLoading(true);
    const res = await fetchStoreLanding();
    setLandingLoading(false);
    if (!res.ok) {
      setLandingError(res.message);
      setCategories([]);
      setOwners([]);
      return;
    }
    setCategories(res.categories);
    setOwners(res.owners);
  }, []);

  useEffect(() => {
    void loadLanding();
  }, [loadLanding]);

  const fetchProductsPage = useCallback(
    async (pageNum: number, append: boolean, queryKeySnapshot: string) => {
      const rid = ++productsRequestId.current;

      const res = await fetchStoreProducts({
        page: pageNum,
        per_page: PER_PAGE,
        person: personId ?? undefined,
        search: debouncedSearch.trim() || undefined,
      });

      if (rid !== productsRequestId.current) return;
      if (queryKeySnapshot !== activeQueryKey.current) return;

      if (!res.ok) {
        if (append) {
          setLoadingMore(false);
        } else {
          setLoading(false);
          setRefreshing(false);
          setProducts([]);
          setMeta(null);
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
    [personId, debouncedSearch]
  );

  const qKey = useMemo(
    () => queryKey(personId, debouncedSearch),
    [personId, debouncedSearch]
  );

  useEffect(() => {
    if (!hasActiveProductFilter) {
      activeQueryKey.current = qKey;
      setProducts([]);
      setMeta(null);
      setLoading(false);
      setError(null);
      return;
    }

    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    void fetchProductsPage(1, false, qKey);
  }, [qKey, hasActiveProductFilter, fetchProductsPage]);

  const selectPerson = useCallback((id: number | null) => {
    setPersonId((prev) => (prev === id ? null : id));
  }, []);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    await loadLanding();
    if (!hasActiveProductFilter) {
      setRefreshing(false);
      return;
    }
    activeQueryKey.current = qKey;
    setError(null);
    await fetchProductsPage(1, false, qKey);
  }, [loadLanding, hasActiveProductFilter, qKey, fetchProductsPage]);

  const loadMore = useCallback(() => {
    if (!hasActiveProductFilter || !meta || loading || loadingMore || refreshing) return;
    if (meta.current_page >= meta.last_page) return;
    if (error) return;

    const nextPage = meta.current_page + 1;
    setLoadingMore(true);
    void fetchProductsPage(nextPage, true, qKey);
  }, [
    hasActiveProductFilter,
    meta,
    loading,
    loadingMore,
    refreshing,
    error,
    fetchProductsPage,
    qKey,
  ]);

  const retry = useCallback(() => {
    void loadLanding();
    if (!hasActiveProductFilter) return;
    activeQueryKey.current = qKey;
    setLoading(true);
    setError(null);
    void fetchProductsPage(1, false, qKey);
  }, [loadLanding, hasActiveProductFilter, qKey, fetchProductsPage]);

  const hasMore =
    hasActiveProductFilter && meta != null && meta.current_page < meta.last_page;

  return {
    categories,
    owners,
    landingLoading,
    landingError,
    personId,
    selectPerson,
    searchInput,
    setSearchInput,
    debouncedSearch,
    hasActiveProductFilter,
    products,
    meta,
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
