import {
  fetchQuranNavigation,
  type QuranNavItem,
} from '@/lib/api/quranCompetitions';
import { useCallback, useEffect, useState } from 'react';

export function useQuranNavigation() {
  const [items, setItems] = useState<QuranNavItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setError(null);
    setLoading(true);
    const res = await fetchQuranNavigation();
    setLoading(false);
    if (!res.ok) {
      setError(res.message);
      setItems([]);
      return;
    }
    setItems(res.items);
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { items, loading, error, reload };
}
