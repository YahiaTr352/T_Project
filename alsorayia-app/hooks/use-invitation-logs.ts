import {
  extractInvitationError,
  fetchInvitationLogDetail,
  fetchInvitationLogs,
  isIdentityRequired,
  isProfileIncomplete,
  isUnderageBlocked,
  type InvitationLog,
} from '@/lib/api/invitations';
import { useCallback, useEffect, useRef, useState } from 'react';

export function useInvitationLogs(enabled: boolean) {
  const [items, setItems] = useState<InvitationLog[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [identityRequired, setIdentityRequired] = useState(false);
  const [profileIncomplete, setProfileIncomplete] = useState(false);
  const [underageBlocked, setUnderageBlocked] = useState(false);
  const loadingMoreLock = useRef(false);

  const applyPage = useCallback((payload: unknown, append: boolean) => {
    const wrapped = payload as { notifications?: unknown } | null;
    const paginator = wrapped?.notifications as
      | { data?: InvitationLog[]; current_page?: number; last_page?: number }
      | InvitationLog[]
      | undefined;

    let nextItems: InvitationLog[] = [];
    let current = 1;
    let last = 1;

    if (Array.isArray(paginator)) {
      nextItems = paginator;
    } else if (paginator && Array.isArray(paginator.data)) {
      nextItems = paginator.data;
      current = paginator.current_page ?? 1;
      last = paginator.last_page ?? 1;
    }

    setItems((prev) => (append ? [...prev, ...nextItems] : nextItems));
    setPage(current);
    setLastPage(last);
  }, []);

  const load = useCallback(
    async (pageNumber: number, mode: 'initial' | 'refresh' | 'more') => {
      if (!enabled) return;
      if (mode === 'more') {
        if (loadingMoreLock.current) return;
        loadingMoreLock.current = true;
        setLoadingMore(true);
      } else if (mode === 'refresh') {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
      setError(null);

      const res = await fetchInvitationLogs(pageNumber);
      if (isIdentityRequired(res) || isProfileIncomplete(res) || isUnderageBlocked(res)) {
        setIdentityRequired(isIdentityRequired(res));
        setProfileIncomplete(isProfileIncomplete(res));
        setUnderageBlocked(isUnderageBlocked(res));
        setItems([]);
        setError(res.message ?? null);
      } else if (!res.success || !res.data) {
        setError(extractInvitationError(res));
        if (mode !== 'more') setItems([]);
      } else {
        setIdentityRequired(false);
        setProfileIncomplete(false);
        setUnderageBlocked(false);
        applyPage(res.data, mode === 'more');
      }

      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
      loadingMoreLock.current = false;
    },
    [enabled, applyPage]
  );

  useEffect(() => {
    if (!enabled) return;
    void load(1, 'initial');
  }, [enabled, load]);

  const refresh = useCallback(() => load(1, 'refresh'), [load]);

  const loadMore = useCallback(() => {
    if (loading || loadingMore || refreshing) return;
    if (page >= lastPage) return;
    void load(page + 1, 'more');
  }, [load, loading, loadingMore, refreshing, page, lastPage]);

  const fetchDetail = useCallback(async (id: number) => {
    const res = await fetchInvitationLogDetail(id);
    if (!res.success || !res.data?.notification) {
      return { ok: false as const, message: extractInvitationError(res) };
    }
    return { ok: true as const, notification: res.data.notification };
  }, []);

  const updateItem = useCallback((notification: InvitationLog) => {
    setItems((prev) => prev.map((it) => (it.id === notification.id ? notification : it)));
  }, []);

  return {
    items,
    loading,
    refreshing,
    loadingMore,
    error,
    identityRequired,
    profileIncomplete,
    underageBlocked,
    blocked: identityRequired || profileIncomplete || underageBlocked,
    hasMore: page < lastPage,
    refresh,
    loadMore,
    reload: () => load(1, 'initial'),
    fetchDetail,
    updateItem,
  };
}
