import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Platform } from 'react-native';
import type MapView from 'react-native-maps';
import * as Haptics from 'expo-haptics';
import type { LatLng } from '@/lib/utils/polylineDecoder';
import {
  getTrekBearing,
  getTrekHeadingSign,
  locateOnTrekPath,
  sliceTrekPathCoords,
  type TrekPath,
} from '@/lib/utils/trekPath';
import type { TrekCurrentPosition, TrekViewMode } from '@/lib/api/walkingTrekApi';

/** اتجاه وجه أيقونة الماشي على الشاشة */
export type WalkerFacing = 'left' | 'right';

const DRAW_DURATION_MS = 3200; // 3.2 ثوانٍ لمشاهدة الماشي يتحرك بوضوح وسلاسة
/** مهلة 450ms حتى تستقر بلاطات الخريطة في الأندرويد قبل بدء سير الماشي */
const FIRST_PLAY_SETTLE_MS = 450;
const REPLAY_SETTLE_MS = 350;
const FRAME_UPDATE_INTERVAL_MS = 25; // وتيرة 25ms (40fps) فائقة السلاسة تضمن انعدام أي تقطيع أو تجميد في الأندرويد
const MIN_ANIMATABLE_KM = 0.05;
/** أقل امتداد للإطار (درجات ≈ 6.5 كم) حتى لا تنزل الكاميرا لمستوى الشوارع في المسافات القصيرة */
const MIN_FRAME_SPAN_DEG = 0.06;
const FRAME_EDGE_PADDING =
  Platform.OS === 'ios'
    ? { top: 70, right: 50, bottom: 200, left: 50 }
    : { top: 100, right: 70, bottom: 280, left: 70 };

const easeInOutCubic = (t: number): number =>
  t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

/** زاويتا إطار يحتوي كل النقاط مع فرض حد أدنى للامتداد */
function computeFrameCorners(points: readonly LatLng[]): LatLng[] {
  let minLat = Infinity;
  let maxLat = -Infinity;
  let minLng = Infinity;
  let maxLng = -Infinity;
  for (const p of points) {
    minLat = Math.min(minLat, p.latitude);
    maxLat = Math.max(maxLat, p.latitude);
    minLng = Math.min(minLng, p.longitude);
    maxLng = Math.max(maxLng, p.longitude);
  }
  const latPad = Math.max(0, (MIN_FRAME_SPAN_DEG - (maxLat - minLat)) / 2);
  const lngPad = Math.max(0, (MIN_FRAME_SPAN_DEG - (maxLng - minLng)) / 2);
  return [
    { latitude: minLat - latPad, longitude: minLng - lngPad },
    { latitude: maxLat + latPad, longitude: maxLng + lngPad },
  ];
}

interface UseTrekJourneyAnimationParams {
  mapRef: React.RefObject<MapView | null>;
  isMapReady: boolean;
  path: TrekPath;
  totalWalkedKm: number;
  currentPosition: TrekCurrentPosition | null;
  viewMode: TrekViewMode;
}

export interface TrekJourneyAnimation {
  /** المسافة المعروضة حالياً على الخريطة (متحركة أثناء الأنيميشن) */
  displayWalkedKm: number;
  isAnimating: boolean;
  walkerPosition: LatLng | null;
  walkerFacing: WalkerFacing;
  /** زاوية ميل الطريق (0 إلى 360 درجة) لتوجيه الماشي بدقة */
  walkerBearing: number;
  /** `animateCamera=false` يضبط الإطار فوراً بلا حركة كاميرا (أول فتح) */
  startAnimation: (animateCamera?: boolean) => void;
  cancelAnimation: () => void;
}

/**
 * أنيميشن رسم المسار: الكاميرا تُضبط مرة واحدة على إطار ثابت يحتوي المسار المقطوع كاملاً،
 * ثم ينمو الخط على الطريق من نقطة الانطلاق حتى آخر نقطة وصلنا لها، والماشي يتقدم على رأسه.
 */
export function useTrekJourneyAnimation({
  mapRef,
  isMapReady,
  path,
  totalWalkedKm,
  currentPosition,
  viewMode,
}: UseTrekJourneyAnimationParams): TrekJourneyAnimation {
  const [animatedKm, setAnimatedKm] = useState(0);
  const [isAnimating, setIsAnimating] = useState(false);
  const [hasPlayed, setHasPlayed] = useState(false);

  const rafIdRef = useRef<number | null>(null);
  const timeoutIdRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // The rAF loop reads the latest props without re-subscribing on every data refresh.
  const latestRef = useRef({ path, totalWalkedKm });
  useEffect(() => {
    latestRef.current = { path, totalWalkedKm };
  }, [path, totalWalkedKm]);

  const clearTimers = useCallback(() => {
    if (rafIdRef.current !== null) {
      cancelAnimationFrame(rafIdRef.current);
      rafIdRef.current = null;
    }
    if (timeoutIdRef.current !== null) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
    }
  }, []);

  const cancelAnimation = useCallback(() => {
    clearTimers();
    setIsAnimating(false);
  }, [clearTimers]);

  const startAnimation = useCallback(
    (animateCamera = false) => {
      clearTimers();

      const { path: trekPath, totalWalkedKm: walkedKm } = latestRef.current;
      const map = mapRef.current;
      if (!map || trekPath.coords.length === 0) {
        setIsAnimating(false);
        return;
      }

      const targetKm = Math.min(Math.max(0, Number(walkedKm) || 0), trekPath.totalKm);
      // ضبط زاوية الرؤية بزوم أوت شامل ليشمل كامل المسار وكافة المحطات والمناطق في المملكة
      const fullRouteCoords = trekPath.coords.length > 0 ? trekPath.coords : sliceTrekPathCoords(trekPath, targetKm);

      try {
        map.fitToCoordinates(computeFrameCorners(fullRouteCoords), {
          edgePadding: FRAME_EDGE_PADDING,
          animated: animateCamera,
        });
      } catch {
        // Map may be unmounting; nothing to frame.
      }

      if (trekPath.coords.length < 2 || targetKm <= trekPath.startKm + MIN_ANIMATABLE_KM) {
        setHasPlayed(true);
        setAnimatedKm(trekPath.startKm);
        setIsAnimating(false);
        return;
      }

      // يُسجَّل التشغيل فقط عندما تكون المسافة صالحة ويبدأ الأنيميشن فعلياً
      setHasPlayed(true);
      setAnimatedKm(trekPath.startKm);
      setIsAnimating(true);

      timeoutIdRef.current = setTimeout(
        () => {
          timeoutIdRef.current = null;
          let startTs: number | null = null;
          let lastDispatchTs = 0;

          const frame = (now: number) => {
            if (startTs === null) startTs = now;
            const elapsed = now - startTs;
            const progress = Math.min(1, elapsed / DRAW_DURATION_MS);

            if (progress >= 1) {
              rafIdRef.current = null;
              setAnimatedKm(targetKm);
              setIsAnimating(false);
              void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
              return;
            }

            // وتيرة تحديث منتظمة وسريعة كل 25ms تضمن 40fps بدون Batching أو قفزات في الأندرويد
            if (now - lastDispatchTs >= FRAME_UPDATE_INTERVAL_MS) {
              lastDispatchTs = now;
              const eased = easeInOutCubic(progress);
              setAnimatedKm(trekPath.startKm + (targetKm - trekPath.startKm) * eased);
            }

            rafIdRef.current = requestAnimationFrame(frame);
          };

          rafIdRef.current = requestAnimationFrame(frame);
        },
        animateCamera ? REPLAY_SETTLE_MS : FIRST_PLAY_SETTLE_MS
      );
    },
    [clearTimers, mapRef]
  );

  // تشغيل تلقائي بمجرد جاهزية الخريطة وتوفر بيانات المسار لضبط الكاميرا على كامل المسار والمحطات
  useEffect(() => {
    if (
      isMapReady &&
      !hasPlayed &&
      path.coords.length > 1
    ) {
      startAnimation(false);
    }
  }, [isMapReady, hasPlayed, path.coords.length, startAnimation]);

  // Switching scope stops any running intro; the map then shows the new scope's real progress.
  const prevViewModeRef = useRef(viewMode);
  useEffect(() => {
    if (prevViewModeRef.current === viewMode) return;
    prevViewModeRef.current = viewMode;
    clearTimers();
    setIsAnimating(false);
  }, [viewMode, clearTimers]);

  useEffect(() => clearTimers, [clearTimers]);

  // Until the intro has run, keep the line collapsed at the start so it never flashes fully drawn.
  const isIntroPhase = isAnimating || !hasPlayed;
  const displayWalkedKm = isIntroPhase ? animatedKm : totalWalkedKm;

  const walkerPosition = useMemo<LatLng | null>(() => {
    if (!isIntroPhase && currentPosition) {
      return { latitude: currentPosition.latitude, longitude: currentPosition.longitude };
    }
    return locateOnTrekPath(path, displayWalkedKm)?.point ?? null;
  }, [isIntroPhase, currentPosition, path, displayWalkedKm]);

  const walkerFacing = useMemo<WalkerFacing>(() => {
    const baseWindowKm = Math.max(0.3, path.totalKm * 0.005);
    // A purely north/south stretch is ambiguous; widen the window until a horizontal trend appears.
    for (const factor of [1, 4, 16, 64]) {
      const sign = getTrekHeadingSign(path, displayWalkedKm, baseWindowKm * factor);
      if (sign !== 0) return sign > 0 ? 'right' : 'left';
    }
    return 'right';
  }, [path, displayWalkedKm]);

  const walkerBearing = useMemo<number>(() => {
    return getTrekBearing(path, displayWalkedKm);
  }, [path, displayWalkedKm]);

  return {
    displayWalkedKm,
    isAnimating,
    walkerPosition,
    walkerFacing,
    walkerBearing,
    startAnimation,
    cancelAnimation,
  };
}
