import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  I18nManager,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  type LayoutChangeEvent,
} from 'react-native';
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withTiming,
} from 'react-native-reanimated';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import {
  fetchWalkingRaceTimeline,
  type WalkingRaceConfig,
  type WalkingRaceDay,
  type WalkingRaceEntry,
  type WalkingRaceTimelineData,
} from '@/lib/api/quiz';

const RTL_ROW = I18nManager.isRTL ? 'row' : 'row-reverse';
const ROW_HEIGHT = 50;
const DAILY_ROW_HEIGHT = 60;
const VALUE_LABEL_RESERVE = 88;
const MIN_BAR_WIDTH = 28;
const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

function toEasternArabicDigits(input: number | string): string {
  return String(input).replace(/\d/g, (digit) => EASTERN_DIGITS[Number(digit)] ?? digit);
}

function formatRaceValue(value: number, showPoints: boolean): string {
  const safe = Math.max(0, Math.round(value));
  const grouped = safe.toLocaleString('en-US');
  const remainder = safe % 100;
  const unit = showPoints ? (remainder >= 3 && remainder <= 10 ? 'نقاط' : 'نقطة') : 'خطوة';
  return `${toEasternArabicDigits(grouped)} ${unit}`;
}

function resolveAvatarUri(raw?: string | null): string | null {
  if (!raw || typeof raw !== 'string') return null;
  const trimmed = raw.trim();
  if (!trimmed) return null;

  if (/^https?:\/\//i.test(trimmed)) {
    if (trimmed.includes('localhost') || trimmed.includes('127.0.0.1')) {
      return trimmed.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, WEB_BASE_URL);
    }
    return trimmed;
  }

  const base = WEB_BASE_URL.replace(/\/$/, '');
  return `${base}/${trimmed.replace(/^\//, '')}`;
}

function getInitials(name: string): string {
  const cleaned = name.trim();
  if (!cleaned) return '؟';
  const parts = cleaned.split(/\s+/);
  return parts[0].slice(0, 1);
}

interface RaceTheme {
  backdrop: string;
  cardBg: string;
  cardBorder: string;
  titleColor: string;
  subtitleColor: string;
  nameColor: string;
  valueColor: string;
  dateBoxBg: string;
  dateBoxBorder: string;
  dateBoxText: string;
  surfaceBg: string;
  surfaceBorder: string;
  controlIcon: string;
  dotActive: string;
  dotInactive: string;
  accentBg: string;
  divider: string;
  avatarBgFallback: string;
  avatarBorder: string;
  viewerRing: string;
  stateText: string;
  errorColor: string;
  triggerBg: string;
}

function getRaceTheme(isDark: boolean): RaceTheme {
  if (isDark) {
    return {
      backdrop: 'rgba(2, 6, 23, 0.82)',
      cardBg: '#0f172a',
      cardBorder: '#1e293b',
      titleColor: '#f8fafc',
      subtitleColor: '#94a3b8',
      nameColor: '#f1f5f9',
      valueColor: '#cbd5e1',
      dateBoxBg: '#1e293b',
      dateBoxBorder: '#475569',
      dateBoxText: '#f8fafc',
      surfaceBg: '#1e293b',
      surfaceBorder: '#334155',
      controlIcon: '#e2e8f0',
      dotActive: '#3b82f6',
      dotInactive: '#334155',
      accentBg: '#2563eb',
      divider: '#1e293b',
      avatarBgFallback: '#334155',
      avatarBorder: '#334155',
      viewerRing: '#38bdf8',
      stateText: '#cbd5e1',
      errorColor: '#f87171',
      triggerBg: '#2563eb',
    };
  }

  return {
    backdrop: 'rgba(15, 23, 42, 0.72)',
    cardBg: '#f9f6f0',
    cardBorder: '#e5dfd3',
    titleColor: '#0f2942',
    subtitleColor: '#475569',
    nameColor: '#0f172a',
    valueColor: '#334155',
    dateBoxBg: '#f9f6f0',
    dateBoxBorder: '#94a3b8',
    dateBoxText: '#0f2942',
    surfaceBg: '#ede8dd',
    surfaceBorder: '#cbd5e1',
    controlIcon: '#0f2942',
    dotActive: '#1e3a8a',
    dotInactive: '#cbd5e1',
    accentBg: '#1e3a8a',
    divider: '#e5dfd3',
    avatarBgFallback: '#e2e8f0',
    avatarBorder: '#e2ddd3',
    viewerRing: '#1e3a8a',
    stateText: '#475569',
    errorColor: '#dc2626',
    triggerBg: '#1e3a8a',
  };
}

interface RaceRowSnapshot {
  entry: WalkingRaceEntry;
  targetTop: number;
  targetBarWidth: number;
  visible: boolean;
  startValue: number;
  endValue: number;
}

interface AnimatedRaceRowProps {
  snapshot: RaceRowSnapshot;
  showPoints: boolean;
  animDurationMs: number;
  transitionKey: string;
  theme: RaceTheme;
}

const AnimatedRaceRow = React.memo(function AnimatedRaceRow({
  snapshot,
  showPoints,
  animDurationMs,
  transitionKey,
  theme,
}: AnimatedRaceRowProps) {
  const { entry, targetTop, targetBarWidth, visible, startValue, endValue } = snapshot;

  const translateY = useSharedValue(targetTop);
  const opacity = useSharedValue(visible ? 1 : 0);
  const barWidth = useSharedValue(targetBarWidth);

  const [displayValue, setDisplayValue] = useState<number>(endValue);
  const lastDisplayedRef = useRef<number>(endValue);
  const [imgError, setImgError] = useState(false);

  const avatarUri = useMemo(() => resolveAvatarUri(entry.avatar), [entry.avatar]);

  useEffect(() => {
    const timingConfig = {
      duration: animDurationMs,
      easing: Easing.out(Easing.cubic),
    };

    translateY.value = withTiming(targetTop, timingConfig);
    opacity.value = withTiming(visible ? 1 : 0, {
      duration: Math.min(320, animDurationMs),
      easing: Easing.out(Easing.ease),
    });
    barWidth.value = withTiming(targetBarWidth, timingConfig);
  }, [targetTop, targetBarWidth, visible, animDurationMs, translateY, opacity, barWidth]);

  useEffect(() => {
    const fromValue = lastDisplayedRef.current !== endValue ? startValue : lastDisplayedRef.current;
    if (fromValue === endValue) {
      setDisplayValue(endValue);
      lastDisplayedRef.current = endValue;
      return;
    }

    let frameId = 0;
    const startTime = Date.now();
    const duration = Math.max(200, Math.min(animDurationMs, 2200));

    const tick = () => {
      const elapsed = Date.now() - startTime;
      const t = Math.min(1, elapsed / duration);
      // Cubic ease-out matches Reanimated bar width timing
      const eased = 1 - Math.pow(1 - t, 3);
      const current = Math.round(fromValue + (endValue - fromValue) * eased);
      lastDisplayedRef.current = current;
      setDisplayValue(current);

      if (t < 1) {
        frameId = requestAnimationFrame(tick);
      } else {
        lastDisplayedRef.current = endValue;
        setDisplayValue(endValue);
      }
    };

    frameId = requestAnimationFrame(tick);
    return () => {
      if (frameId) cancelAnimationFrame(frameId);
    };
  }, [endValue, startValue, animDurationMs, transitionKey]);

  const rowAnimatedStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: translateY.value }],
    opacity: opacity.value,
  }));

  const barAnimatedStyle = useAnimatedStyle(() => ({
    width: barWidth.value,
  }));

  const showImage = Boolean(avatarUri) && !imgError;

  return (
    <Animated.View style={[styles.raceRow, showPoints && styles.dailyRaceRow, rowAnimatedStyle]} pointerEvents={visible ? 'auto' : 'none'}>
      {/* Avatar on the RIGHT in RTL */}
      <View
        style={[
          styles.avatarCircle,
          {
            backgroundColor: showImage ? theme.avatarBgFallback : entry.color || theme.accentBg,
            borderColor: entry.is_viewer ? theme.viewerRing : theme.avatarBorder,
          },
          entry.is_viewer ? styles.viewerAvatarRing : null,
        ]}
      >
        {showImage ? (
          <Image
            source={{ uri: avatarUri! }}
            style={styles.avatarImage}
            contentFit="cover"
            transition={150}
            onError={() => setImgError(true)}
          />
        ) : (
          <Text style={styles.avatarInitialText}>{getInitials(entry.name)}</Text>
        )}
      </View>

      <View style={styles.rowBody}>
        <Text style={[styles.contestantName, { color: theme.nameColor }]} numberOfLines={1} ellipsizeMode="tail">
          {entry.name}
          {entry.is_viewer ? ' (أنت)' : ''}
        </Text>

        <View style={styles.barTrackRow}>
          <Animated.View
            style={[
              styles.coloredBar,
              { backgroundColor: entry.color || theme.accentBg },
              barAnimatedStyle,
            ]}
          >
            <Text style={styles.rankInsideBar}>{toEasternArabicDigits(entry.rank)}</Text>
          </Animated.View>

          <View style={styles.raceValues}>
            <Text style={[styles.valueLabel, { color: theme.valueColor }]} numberOfLines={1}>
              {formatRaceValue(displayValue, showPoints)}
            </Text>
            {showPoints && (
              <Text style={[styles.stepsDetail, { color: theme.subtitleColor }]} numberOfLines={1}>
                {formatRaceValue(entry.daily_steps, false)}
              </Text>
            )}
          </View>
        </View>
      </View>
    </Animated.View>
  );
});

export interface WalkingRaceModalProps {
  visible: boolean;
  onClose: () => void;
  questionId: number;
  raceConfig?: WalkingRaceConfig | null;
}

export default function WalkingRaceModal({
  visible,
  onClose,
  questionId,
  raceConfig,
}: WalkingRaceModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const theme = useMemo(() => getRaceTheme(isDark), [isDark]);

  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [timeline, setTimeline] = useState<WalkingRaceTimelineData | null>(null);
  const [activeSectionKey, setActiveSectionKey] = useState<string | null>(null);
  const [dayIndex, setDayIndex] = useState(0);
  const [isPlaying, setIsPlaying] = useState(false);
  const [trackWidth, setTrackWidth] = useState(240);

  const loadTimeline = useCallback(async () => {
    if (!questionId) return;
    setLoading(true);
    setErrorMsg(null);
    try {
      const res = await fetchWalkingRaceTimeline(questionId);
      if (!res.success || !res.data) {
        setErrorMsg(res.message || 'تعذر تحميل سباق الترتيب اليومي حالياً.');
        setTimeline(null);
        return;
      }

      if (res.data.access_denied) {
        setErrorMsg('هذا السباق غير متاح للعرض حسب إعدادات الخصوصية.');
        setTimeline(res.data);
        return;
      }

      setTimeline(res.data);
      const keys = Object.keys(res.data.sections || {});
      const initialSection =
        res.data.default_section && res.data.sections[res.data.default_section]
          ? res.data.default_section
          : keys[0] ?? null;

      setActiveSectionKey(initialSection);
      setDayIndex(0);
      const initialDays = initialSection ? res.data.sections[initialSection]?.days ?? [] : [];
      setIsPlaying(initialDays.length > 1);
    } catch {
      setErrorMsg('حدث خطأ أثناء الاتصال بالخادم. حاول مرة أخرى.');
    } finally {
      setLoading(false);
    }
  }, [questionId]);

  useEffect(() => {
    if (visible) {
      void loadTimeline();
    } else {
      setIsPlaying(false);
    }
  }, [visible, loadTimeline]);

  const sectionKeys = useMemo(
    () => Object.keys(timeline?.sections ?? {}),
    [timeline]
  );

  const activeDays: WalkingRaceDay[] = useMemo(() => {
    if (!timeline || !activeSectionKey) return [];
    return timeline.sections[activeSectionKey]?.days ?? [];
  }, [timeline, activeSectionKey]);

  const safeDayIndex = Math.min(dayIndex, Math.max(0, activeDays.length - 1));
  const currentDay: WalkingRaceDay | null = activeDays[safeDayIndex] ?? null;
  const showPoints = (timeline?.config ?? raceConfig)?.metric === 'points';
  const rowHeight = showPoints ? DAILY_ROW_HEIGHT : ROW_HEIGHT;
  const previousDay: WalkingRaceDay | null =
    safeDayIndex > 0 ? activeDays[safeDayIndex - 1] ?? null : null;

  const speedMs = timeline?.config?.speed_ms ?? raceConfig?.speed_ms ?? 1400;
  const maxRanks = timeline?.config?.max_ranks ?? raceConfig?.max_ranks ?? 10;
  const animDurationMs = Math.min(Math.max(Math.round(speedMs * 0.78), 350), 2200);

  // Auto-play progression day by day
  useEffect(() => {
    if (!visible || !isPlaying || activeDays.length <= 1) return;

    if (safeDayIndex >= activeDays.length - 1) {
      setIsPlaying(false);
      return;
    }

    const timer = setTimeout(() => {
      setDayIndex((prev) => {
        if (prev >= activeDays.length - 1) {
          setIsPlaying(false);
          return prev;
        }
        return prev + 1;
      });
    }, speedMs);

    return () => clearTimeout(timer);
  }, [visible, isPlaying, safeDayIndex, activeDays.length, speedMs]);

  const handleTrackLayout = useCallback((event: LayoutChangeEvent) => {
    const fullRowWidth = event.nativeEvent.layout.width;
    // Subtract avatar (36) + gap (10)
    const computedTrack = Math.max(120, fullRowWidth - 46);
    setTrackWidth(computedTrack);
  }, []);

  const displayedSlotsCount = useMemo(() => {
    let highest = 0;
    for (const day of activeDays) {
      if (day.top_entries.length > highest) {
        highest = day.top_entries.length;
      }
    }
    const effectiveLimit = maxRanks > 0 ? maxRanks : Math.max(highest, 1);
    return Math.max(1, Math.min(effectiveLimit, highest || effectiveLimit));
  }, [activeDays, maxRanks]);

  // Build stable pool of all entries that appear in any day so rows animate vertically between positions
  const rowSnapshots: RaceRowSnapshot[] = useMemo(() => {
    if (!currentDay) return [];

    const maxValue = Math.max(1, (showPoints ? currentDay.max_points : currentDay.max_steps) ?? 0);
    const valueKey = showPoints ? 'points' : 'steps';
    const previousValueKey = showPoints ? 'prev_points' : 'prev_steps';
    const maxBarWidth = Math.max(MIN_BAR_WIDTH + 16, trackWidth - VALUE_LABEL_RESERVE);

    const prevMap = new Map<string, WalkingRaceEntry>();
    if (previousDay) {
      for (const item of previousDay.top_entries) {
        prevMap.set(item.entry_id, item);
      }
    }

    const currentMap = new Map<string, WalkingRaceEntry>();
    for (const item of currentDay.top_entries) {
      currentMap.set(item.entry_id, item);
    }

    const allEntriesMap = new Map<string, WalkingRaceEntry>();
    for (const day of activeDays) {
      for (const entry of day.top_entries) {
        if (!allEntriesMap.has(entry.entry_id)) {
          allEntriesMap.set(entry.entry_id, entry);
        }
      }
    }

    const snapshots: RaceRowSnapshot[] = [];
    allEntriesMap.forEach((baseEntry, entryId) => {
      const cur = currentMap.get(entryId);
      const prev = prevMap.get(entryId);
      const entry = cur ?? prev ?? baseEntry;

      const visibleNow = Boolean(cur);
      const targetTop = visibleNow ? (cur!.rank - 1) * rowHeight : displayedSlotsCount * rowHeight;
      const targetValue = (cur ?? prev)?.[valueKey] ?? 0;
      const startValue = prev?.[valueKey] ?? cur?.[previousValueKey] ?? 0;
      const fraction = Math.min(1, Math.max(0, targetValue / maxValue));
      const targetBarWidth = visibleNow
        ? MIN_BAR_WIDTH + (maxBarWidth - MIN_BAR_WIDTH) * fraction
        : MIN_BAR_WIDTH;

      snapshots.push({
        entry,
        targetTop,
        targetBarWidth,
        visible: visibleNow,
        startValue,
        endValue: targetValue,
      });
    });

    return snapshots;
  }, [activeDays, currentDay, previousDay, trackWidth, displayedSlotsCount, showPoints, rowHeight]);

  const totalDots = Math.max(
    timeline?.total_competition_days ?? 0,
    activeDays.length
  );

  const handleSelectSection = (key: string) => {
    setActiveSectionKey(key);
    setDayIndex(0);
    const days = timeline?.sections[key]?.days ?? [];
    setIsPlaying(days.length > 1);
  };

  const handlePrevDay = () => {
    setIsPlaying(false);
    setDayIndex((prev) => Math.max(0, prev - 1));
  };

  const handleNextDay = () => {
    setIsPlaying(false);
    setDayIndex((prev) => Math.min(activeDays.length - 1, prev + 1));
  };

  const handleTogglePlay = () => {
    if (activeDays.length <= 1) return;
    if (safeDayIndex >= activeDays.length - 1 && !isPlaying) {
      setDayIndex(0);
      setIsPlaying(true);
      return;
    }
    setIsPlaying((prev) => !prev);
  };

  const handleReplay = () => {
    if (activeDays.length === 0) return;
    setDayIndex(0);
    setIsPlaying(activeDays.length > 1);
  };

  const modalTitle =
    timeline?.config?.title || raceConfig?.title || 'ترتيب المتسابقين اليومي';
  const modalSubtitle =
    timeline?.config?.subtitle ||
    raceConfig?.subtitle ||
    (showPoints ? 'ترتيب المشاركين بنقاط كل يوم' : 'منافسة يومية في عدد الخطوات التراكمية');

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={[styles.backdrop, { backgroundColor: theme.backdrop }]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} />

        <View style={[styles.card, { backgroundColor: theme.cardBg, borderColor: theme.cardBorder }]}>
          {/* ── Top Header (Matching Reference Video) ──────────────── */}
          <View style={styles.headerRow}>
            <View style={styles.headerTextGroup}>
              <Text style={[styles.titleText, { color: theme.titleColor }]} numberOfLines={2}>
                {modalTitle}
              </Text>
              <Text style={[styles.subtitleText, { color: theme.subtitleColor }]} numberOfLines={2}>
                {modalSubtitle}
              </Text>
            </View>

            <View style={styles.headerLeftGroup}>
              {Boolean(currentDay?.date_label) && (
                <View style={[styles.dateBox, { backgroundColor: theme.dateBoxBg, borderColor: theme.dateBoxBorder }]}>
                  <Text style={[styles.dateBoxText, { color: theme.dateBoxText }]}>{currentDay?.date_label}</Text>
                </View>
              )}

              <TouchableOpacity
                onPress={onClose}
                style={[styles.closeButton, { backgroundColor: theme.surfaceBg }]}
                hitSlop={10}
                accessibilityLabel="إغلاق"
              >
                <Ionicons name="close" size={20} color={theme.controlIcon} />
              </TouchableOpacity>
            </View>
          </View>

          {/* ── Section Switcher (Only when multiple segregated sections) ── */}
          {sectionKeys.length > 1 && timeline && (
            <View style={styles.sectionTabsRow}>
              {sectionKeys.map((key) => {
                const active = key === activeSectionKey;
                return (
                  <TouchableOpacity
                    key={key}
                    onPress={() => handleSelectSection(key)}
                    style={[
                      styles.sectionTab,
                      active
                        ? { backgroundColor: theme.accentBg, borderColor: theme.accentBg }
                        : { backgroundColor: theme.surfaceBg, borderColor: theme.surfaceBorder },
                    ]}
                    activeOpacity={0.8}
                  >
                    <Text
                      style={[
                        styles.sectionTabText,
                        active ? styles.sectionTabTextActive : { color: theme.subtitleColor },
                      ]}
                    >
                      {timeline.sections[key]?.label || key}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}

          {/* ── Timeline Dots Row ──────────────────────────────────── */}
          {totalDots > 0 && !loading && !errorMsg && (
            <View style={styles.dotsContainer}>
              {Array.from({ length: totalDots }).map((_, index) => {
                const isPlayable = index < activeDays.length;
                const isElapsedOrActive = index <= safeDayIndex && isPlayable;
                const isCurrent = index === safeDayIndex;

                return (
                  <TouchableOpacity
                    key={index}
                    disabled={!isPlayable}
                    onPress={() => {
                      if (!isPlayable) return;
                      setIsPlaying(false);
                      setDayIndex(index);
                    }}
                    hitSlop={6}
                    style={[
                      styles.dot,
                      { backgroundColor: isElapsedOrActive ? theme.dotActive : theme.dotInactive },
                      isCurrent ? styles.dotCurrent : null,
                    ]}
                  />
                );
              })}
            </View>
          )}

          {/* ── Main Race Stage / States ───────────────────────────── */}
          {loading ? (
            <View style={styles.stateBox}>
              <ActivityIndicator size="large" color={theme.dotActive} />
              <Text style={[styles.stateText, { color: theme.stateText }]}>جاري تجهيز سباق الترتيب اليومي...</Text>
            </View>
          ) : errorMsg ? (
            <View style={styles.stateBox}>
              <Ionicons name="alert-circle-outline" size={34} color={theme.errorColor} />
              <Text style={[styles.stateErrorText, { color: theme.errorColor }]}>{errorMsg}</Text>
              <TouchableOpacity
                onPress={() => void loadTimeline()}
                style={[styles.retryBtn, { backgroundColor: theme.accentBg }]}
                activeOpacity={0.8}
              >
                <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
              </TouchableOpacity>
            </View>
          ) : activeDays.length === 0 ? (
            <View style={styles.stateBox}>
              <Ionicons name={showPoints ? 'trophy-outline' : 'footsteps-outline'} size={34} color={theme.subtitleColor} />
              <Text style={[styles.stateText, { color: theme.stateText }]}>
                {showPoints ? 'لا توجد نتائج نقاط بعد في هذه المسابقة.' : 'لا توجد بيانات خطوات مسجلة لأيام المسابقة بعد.'}
              </Text>
            </View>
          ) : (
            <ScrollView
              style={styles.stageScroll}
              contentContainerStyle={styles.stageScrollContent}
              showsVerticalScrollIndicator={false}
            >
              <View
                onLayout={handleTrackLayout}
                style={[styles.stage, { height: displayedSlotsCount * rowHeight }]}
              >
                {rowSnapshots.map((snap) => (
                  <AnimatedRaceRow
                    key={snap.entry.entry_id}
                    snapshot={snap}
                    showPoints={showPoints}
                    animDurationMs={animDurationMs}
                    transitionKey={`${activeSectionKey ?? 's'}-${safeDayIndex}`}
                    theme={theme}
                  />
                ))}
              </View>
            </ScrollView>
          )}

          {/* ── Playback Footer Controls ───────────────────────────── */}
          {!loading && !errorMsg && activeDays.length > 0 && (
            <View style={[styles.controlsBar, { borderTopColor: theme.divider }]}>
              <View style={styles.controlsGroup}>
                <TouchableOpacity
                  onPress={handlePrevDay}
                  disabled={safeDayIndex <= 0}
                  style={[
                    styles.iconControlBtn,
                    { backgroundColor: theme.surfaceBg },
                    safeDayIndex <= 0 && styles.controlDisabled,
                  ]}
                  accessibilityLabel="اليوم السابق"
                >
                  <Ionicons name="play-skip-forward" size={16} color={theme.controlIcon} />
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={handleTogglePlay}
                  disabled={activeDays.length <= 1}
                  style={[
                    styles.playPauseBtn,
                    { backgroundColor: theme.accentBg },
                    activeDays.length <= 1 && styles.controlDisabled,
                  ]}
                  accessibilityLabel={isPlaying ? 'إيقاف مؤقت' : 'تشغيل'}
                >
                  <Ionicons name={isPlaying ? 'pause' : 'play'} size={16} color="#ffffff" />
                  <Text style={styles.playPauseText}>{isPlaying ? 'إيقاف' : 'تشغيل'}</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={handleNextDay}
                  disabled={safeDayIndex >= activeDays.length - 1}
                  style={[
                    styles.iconControlBtn,
                    { backgroundColor: theme.surfaceBg },
                    safeDayIndex >= activeDays.length - 1 && styles.controlDisabled,
                  ]}
                  accessibilityLabel="اليوم التالي"
                >
                  <Ionicons name="play-skip-back" size={16} color={theme.controlIcon} />
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={handleReplay}
                  style={[styles.iconControlBtn, { backgroundColor: theme.surfaceBg }]}
                  accessibilityLabel="إعادة التشغيل"
                >
                  <Ionicons name="refresh" size={16} color={theme.controlIcon} />
                </TouchableOpacity>
              </View>

              <Text style={[styles.dayCounterText, { color: theme.subtitleColor }]}>
                اليوم {toEasternArabicDigits(safeDayIndex + 1)} من{' '}
                {toEasternArabicDigits(activeDays.length)}
              </Text>
            </View>
          )}
        </View>
      </View>
    </Modal>
  );
}

export interface WalkingRaceLauncherProps {
  questionId: number;
  raceConfig?: WalkingRaceConfig | null;
  compact?: boolean;
}

export function WalkingRaceLauncher({
  questionId,
  raceConfig,
  compact = false,
}: WalkingRaceLauncherProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const [visible, setVisible] = useState(false);

  if (!raceConfig?.enabled) {
    return null;
  }

  const label = raceConfig.button_text?.trim() || 'سباق الترتيب اليومي';

  return (
    <>
      <TouchableOpacity
        onPress={() => setVisible(true)}
        activeOpacity={0.82}
        style={[
          styles.triggerButton,
          { backgroundColor: isDark ? '#2563eb' : '#1e3a8a' },
          compact && styles.triggerButtonCompact,
        ]}
      >
        <Ionicons name="bar-chart" size={compact ? 13 : 15} color="#ffffff" />
        <Text style={[styles.triggerButtonText, compact && styles.triggerButtonTextCompact]}>
          {label}
        </Text>
      </TouchableOpacity>

      <WalkingRaceModal
        visible={visible}
        onClose={() => setVisible(false)}
        questionId={questionId}
        raceConfig={raceConfig}
      />
    </>
  );
}

const styles = StyleSheet.create({
  triggerButton: {
    flexDirection: RTL_ROW,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    backgroundColor: '#1e3a8a',
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 10,
    shadowColor: '#0f2942',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.14,
    shadowRadius: 4,
    elevation: 2,
  },
  triggerButtonCompact: {
    paddingVertical: 6,
    paddingHorizontal: 11,
    borderRadius: 8,
  },
  triggerButtonText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#ffffff',
    paddingTop: 1,
  },
  triggerButtonTextCompact: {
    fontSize: 12,
  },

  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.72)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 24,
  },
  card: {
    width: '100%',
    maxWidth: 460,
    maxHeight: '92%',
    backgroundColor: '#f9f6f0',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#e5dfd3',
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 10,
  },

  headerRow: {
    flexDirection: RTL_ROW,
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 10,
  },
  headerTextGroup: {
    flex: 1,
    alignItems: I18nManager.isRTL ? 'flex-start' : 'flex-end',
  },
  titleText: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 17,
    color: '#0f2942',
    textAlign: I18nManager.isRTL ? undefined : 'right',
    writingDirection: 'rtl',
    paddingTop: 2,
  },
  subtitleText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
    color: '#475569',
    textAlign: I18nManager.isRTL ? undefined : 'right',
    writingDirection: 'rtl',
    marginTop: 2,
  },
  headerLeftGroup: {
    flexDirection: RTL_ROW,
    alignItems: 'center',
    gap: 8,
  },
  dateBox: {
    backgroundColor: '#f9f6f0',
    borderWidth: 1,
    borderColor: '#94a3b8',
    borderRadius: 8,
    paddingHorizontal: 11,
    paddingVertical: 5,
  },
  dateBoxText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    color: '#0f2942',
    paddingTop: 1,
  },
  closeButton: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: '#ede8dd',
    alignItems: 'center',
    justifyContent: 'center',
  },

  sectionTabsRow: {
    flexDirection: RTL_ROW,
    gap: 8,
    marginTop: 10,
  },
  sectionTab: {
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: 1,
  },
  sectionTabActive: {
    backgroundColor: '#1e3a8a',
    borderColor: '#1e3a8a',
  },
  sectionTabInactive: {
    backgroundColor: '#f1ede4',
    borderColor: '#cbd5e1',
  },
  sectionTabText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    paddingTop: 1,
  },
  sectionTabTextActive: {
    color: '#ffffff',
  },
  sectionTabTextInactive: {
    color: '#475569',
  },

  dotsContainer: {
    flexDirection: RTL_ROW,
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 6,
    marginTop: 12,
    marginBottom: 10,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  dotActive: {
    backgroundColor: '#1e3a8a',
  },
  dotInactive: {
    backgroundColor: '#cbd5e1',
  },
  dotCurrent: {
    transform: [{ scale: 1.3 }],
  },

  stageScroll: {
    flexGrow: 0,
    flexShrink: 1,
    marginTop: 2,
  },
  stageScrollContent: {
    paddingBottom: 4,
  },
  stage: {
    position: 'relative',
    width: '100%',
    overflow: 'hidden',
  },

  raceRow: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: ROW_HEIGHT,
    flexDirection: RTL_ROW,
    alignItems: 'center',
    gap: 10,
    paddingVertical: 3,
  },
  dailyRaceRow: {
    height: DAILY_ROW_HEIGHT,
  },
  avatarCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderColor: '#e2ddd3',
  },
  viewerAvatarRing: {
    borderColor: '#1e3a8a',
    borderWidth: 2,
  },
  avatarImage: {
    width: '100%',
    height: '100%',
  },
  avatarInitialText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#ffffff',
    paddingTop: 2,
  },

  rowBody: {
    flex: 1,
    alignItems: I18nManager.isRTL ? 'flex-start' : 'flex-end',
    justifyContent: 'center',
  },
  contestantName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    color: '#0f172a',
    textAlign: I18nManager.isRTL ? undefined : 'right',
    writingDirection: 'rtl',
    marginBottom: 2,
  },
  barTrackRow: {
    width: '100%',
    flexDirection: RTL_ROW,
    alignItems: 'center',
    gap: 6,
  },
  coloredBar: {
    height: 20,
    borderRadius: 4,
    flexDirection: RTL_ROW,
    alignItems: 'center',
    justifyContent: 'flex-start',
    paddingHorizontal: 7,
  },
  rankInsideBar: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 11.5,
    color: '#ffffff',
    paddingTop: 1,
  },
  valueLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
    color: '#334155',
    flexShrink: 0,
  },
  raceValues: {
    flexShrink: 0,
  },
  stepsDetail: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 10.5,
  },

  stateBox: {
    minHeight: 200,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 24,
    gap: 10,
  },
  stateText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    color: '#475569',
    textAlign: 'center',
  },
  stateErrorText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    color: '#b91c1c',
    textAlign: 'center',
  },
  retryBtn: {
    marginTop: 6,
    backgroundColor: '#1e3a8a',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  retryBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#ffffff',
  },

  controlsBar: {
    flexDirection: RTL_ROW,
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: '#e5dfd3',
    marginTop: 10,
    paddingTop: 10,
  },
  controlsGroup: {
    flexDirection: RTL_ROW,
    alignItems: 'center',
    gap: 6,
  },
  iconControlBtn: {
    width: 34,
    height: 34,
    borderRadius: 8,
    backgroundColor: '#ede8dd',
    alignItems: 'center',
    justifyContent: 'center',
  },
  playPauseBtn: {
    flexDirection: RTL_ROW,
    alignItems: 'center',
    gap: 5,
    backgroundColor: '#1e3a8a',
    height: 34,
    paddingHorizontal: 12,
    borderRadius: 8,
  },
  playPauseText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    color: '#ffffff',
    paddingTop: 1,
  },
  controlDisabled: {
    opacity: 0.4,
  },
  dayCounterText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    color: '#475569',
  },
});
