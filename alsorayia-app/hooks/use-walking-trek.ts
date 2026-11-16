import { useState, useEffect, useCallback, useRef } from 'react';
import {
  getActiveTrek,
  type ActiveTrekResponseData,
  type TrekWaypoint,
} from '@/lib/api/walkingTrekApi';
import type { TrekRouteSegment } from '@/lib/utils/trekPath';

export function useWalkingTrek(groupId?: number, initialData?: ActiveTrekResponseData | null) {
  const [trekData, setTrekData] = useState<ActiveTrekResponseData | null>(initialData ?? null);
  const [loading, setLoading] = useState(initialData ? false : true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedWaypoint, setSelectedWaypoint] = useState<TrekWaypoint | null>(null);
  const [selectedSegment, setSelectedSegment] = useState<TrekRouteSegment | null>(null);
  const fetchingRef = useRef(false);

  useEffect(() => {
    if (initialData) {
      setTrekData(initialData);
      setLoading(false);
    }
  }, [initialData]);

  const fetchTrek = useCallback(async (isRefresh = false) => {
    if (fetchingRef.current) return;
    fetchingRef.current = true;

    if (isRefresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    setError(null);

    try {
      const res = await getActiveTrek(groupId);
      if (res && res.trek) {
        setTrekData(res);
      } else if (res && (res as any).data?.trek) {
        setTrekData((res as any).data);
      } else {
        setTrekData(null);
      }
    } catch (err: any) {
      setError(err?.message || 'تعذر تحميل مسار الرحلة');
    } finally {
      fetchingRef.current = false;
      setLoading(false);
      setRefreshing(false);
    }
  }, [groupId]);

  useEffect(() => {
    if (!initialData) {
      fetchTrek();
    }
  }, [fetchTrek, initialData]);

  const refresh = useCallback(() => {
    return fetchTrek(true);
  }, [fetchTrek]);

  return {
    trekData,
    hasActiveTrek: Boolean(trekData?.trek),
    loading,
    refreshing,
    error,
    refresh,
    selectedWaypoint,
    setSelectedWaypoint,
    selectedSegment,
    setSelectedSegment,
  };
}
