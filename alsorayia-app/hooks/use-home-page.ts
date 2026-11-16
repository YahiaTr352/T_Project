import { fetchHomePage, type HomePageData } from '@/lib/api/homePage';
import { useCallback, useEffect, useState } from 'react';

export function useHomePage() {
  const [data, setData] = useState<HomePageData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (isRefresh: boolean) => {
    if (isRefresh) setRefreshing(true);
    else {
      setLoading(true);
    }
    if (!isRefresh) setError(null);
    const res = await fetchHomePage();
    if (!res) {
      setError('تعذر تحميل البيانات. تحقق من الاتصال وحاول مرة أخرى.');
      if (!isRefresh) setData(null);
    } else {
      setData(res);
      setError(null);
    }
    setLoading(false);
    setRefreshing(false);
  }, []);

  useEffect(() => {
    load(false);
  }, [load]);

  const refresh = useCallback(() => {
    return load(true);
  }, [load]);

  return { data, loading, error, refreshing, refresh };
}
