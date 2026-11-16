import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export interface DatePickerModalProps {
  visible: boolean;
  onClose: () => void;
  onSelect: (dateString: string) => void;
  onClear?: () => void;
  value?: string | null; // Format: YYYY-MM-DD
  title?: string;
  minimumDate?: Date | string | null;
  maximumDate?: Date | string | null;
  allowClear?: boolean;
}

const ARABIC_MONTHS = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
];

const WEEK_DAYS = ['سبت', 'أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة'];

function parseDate(val?: string | Date | number | null): Date | null {
  if (!val) return null;
  if (val instanceof Date || Object.prototype.toString.call(val) === '[object Date]') {
    const d = val as Date;
    return isNaN(d.getTime()) ? null : d;
  }
  if (typeof val === 'number') {
    const d = new Date(val);
    return isNaN(d.getTime()) ? null : d;
  }
  if (typeof val !== 'string') return null;
  const clean = val.trim().split(/[ T]/)[0] || '';
  const match = clean.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (!match) {
    const fallback = new Date(clean);
    return isNaN(fallback.getTime()) ? null : fallback;
  }
  const y = parseInt(match[1], 10);
  const m = parseInt(match[2], 10) - 1;
  const d = parseInt(match[3], 10);
  const date = new Date(y, m, d);
  return isNaN(date.getTime()) ? null : date;
}

function formatDateYmd(year: number, month: number, day: number): string {
  const m = String(month + 1).padStart(2, '0');
  const d = String(day).padStart(2, '0');
  return `${year}-${m}-${d}`;
}

function daysInMonth(year: number, month: number): number {
  return new Date(year, month + 1, 0).getDate();
}

export function formatArabicDateDisplay(ymdString?: string | null): string {
  if (!ymdString) return '';
  const parsed = parseDate(ymdString);
  if (!parsed) return ymdString;
  const day = parsed.getDate();
  const month = ARABIC_MONTHS[parsed.getMonth()];
  const year = parsed.getFullYear();
  return `${day} ${month} ${year}`;
}

function resolveValidDate(
  val?: string | Date | number | null,
  minDate?: Date | null,
  maxDate?: Date | null
): { year: number; month: number; day: number } {
  let target = parseDate(val);
  if (!target) {
    const now = new Date();
    if (maxDate && now > maxDate) {
      target = maxDate;
    } else if (minDate && now < minDate) {
      target = minDate;
    } else {
      target = now;
    }
  }

  if (!target || isNaN(target.getTime())) {
    target = new Date();
  }

  return {
    year: target.getFullYear(),
    month: target.getMonth(),
    day: target.getDate(),
  };
}

type DateParts = { year: number; month: number; day: number };

/**
 * Projects the current day onto a new year/month, clamping overflowing days
 * (e.g. 31 → 30, Feb 29 → 28) and pulling the result back inside [min, max].
 */
function projectSelectionToYearMonth(
  year: number,
  month: number,
  preferredDay: number,
  minDate: Date | null,
  maxDate: Date | null
): DateParts {
  const day = Math.min(preferredDay, daysInMonth(year, month));
  const candidate = new Date(year, month, day);

  if (minDate) {
    const minDateOnly = new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate());
    if (candidate < minDateOnly) {
      return { year: minDateOnly.getFullYear(), month: minDateOnly.getMonth(), day: minDateOnly.getDate() };
    }
  }
  if (maxDate) {
    const maxDateOnly = new Date(maxDate.getFullYear(), maxDate.getMonth(), maxDate.getDate());
    if (candidate > maxDateOnly) {
      return { year: maxDateOnly.getFullYear(), month: maxDateOnly.getMonth(), day: maxDateOnly.getDate() };
    }
  }
  return { year, month, day };
}

export function DatePickerModal({
  visible,
  onClose,
  onSelect,
  onClear,
  value,
  title = 'اختيار التاريخ',
  minimumDate,
  maximumDate,
  allowClear = true,
}: DatePickerModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[colorScheme ?? 'light'];
  const insets = useSafeAreaInsets();

  // Normalize date inputs to string representation for stable memoization
  const minKey = useMemo(() => {
    if (!minimumDate) return null;
    if (minimumDate instanceof Date) return minimumDate.toDateString();
    return String(minimumDate);
  }, [minimumDate]);

  const maxKey = useMemo(() => {
    if (!maximumDate) return null;
    if (maximumDate instanceof Date) return maximumDate.toDateString();
    return String(maximumDate);
  }, [maximumDate]);

  const min = useMemo(() => parseDate(minimumDate), [minKey]);
  const max = useMemo(() => parseDate(maximumDate), [maxKey]);

  // Initial parsed date or fallback to today/max
  const initialResolved = useMemo(
    () => resolveValidDate(value, min, max),
    [value, minKey, maxKey]
  );

  const [selectedDate, setSelectedDate] = useState<{
    year: number;
    month: number;
    day: number;
  }>(initialResolved);

  // Current view in the calendar (year and month currently displayed)
  const [viewYear, setViewYear] = useState(initialResolved.year);
  const [viewMonth, setViewMonth] = useState(initialResolved.month);

  // Picker mode: 'days' = standard calendar grid, 'yearMonth' = fast year/month jump
  const [pickerMode, setPickerMode] = useState<'days' | 'yearMonth'>('days');

  const yearListRef = useRef<FlatList>(null);
  const prevVisibleRef = useRef(false);

  // Sync state ONLY when modal transitions from closed to open, or when value changes
  useEffect(() => {
    if (visible && (!prevVisibleRef.current || value)) {
      const resolved = resolveValidDate(value, min, max);
      setSelectedDate(resolved);
      setViewYear(resolved.year);
      setViewMonth(resolved.month);
      setPickerMode('days');
    }
    prevVisibleRef.current = visible;
  }, [visible, value]);

  // Generate range of available years (e.g. from 1910 to 2040)
  const minYear = min ? min.getFullYear() : 1910;
  const maxYear = max ? max.getFullYear() : new Date().getFullYear() + 15;
  const yearsList = useMemo(() => {
    const list: number[] = [];
    for (let y = maxYear; y >= minYear; y--) {
      list.push(y);
    }
    return list;
  }, [minYear, maxYear]);

  // Scroll to selected year when switching to 'yearMonth' mode
  useEffect(() => {
    if (pickerMode === 'yearMonth') {
      const index = yearsList.indexOf(viewYear);
      if (index >= 0) {
        setTimeout(() => {
          yearListRef.current?.scrollToIndex({
            index,
            animated: true,
            viewPosition: 0.5,
          });
        }, 120);
      }
    }
  }, [pickerMode, viewYear, yearsList]);

  // Days in the current viewing month - always padded to 42 cells (6 full weeks) for rock-solid zero CLS height
  const calendarDays = useMemo(() => {
    const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

    // Map JS getDay() (0 = Sun, 1 = Mon ... 6 = Sat) to Saturday index (0 = Sat, 1 = Sun ... 6 = Fri)
    const jsDay = firstDayOfMonth.getDay();
    const satOffset = (jsDay + 1) % 7;

    const days: {
      day: number | null;
      isCurrentMonth: boolean;
      disabled: boolean;
      isSelected: boolean;
      isToday: boolean;
    }[] = [];

    // Leading empty slots
    for (let i = 0; i < satOffset; i++) {
      days.push({
        day: null,
        isCurrentMonth: false,
        disabled: true,
        isSelected: false,
        isToday: false,
      });
    }

    const today = new Date();
    const isCurrentYearMonthToday =
      today.getFullYear() === viewYear && today.getMonth() === viewMonth;

    const minDateOnly = min ? new Date(min.getFullYear(), min.getMonth(), min.getDate()) : null;
    const maxDateOnly = max ? new Date(max.getFullYear(), max.getMonth(), max.getDate()) : null;

    for (let d = 1; d <= daysInMonth; d++) {
      const thisDate = new Date(viewYear, viewMonth, d);
      let disabled = false;
      if (minDateOnly && thisDate < minDateOnly) {
        disabled = true;
      }
      if (maxDateOnly && thisDate > maxDateOnly) {
        disabled = true;
      }

      const isSelected =
        selectedDate.year === viewYear &&
        selectedDate.month === viewMonth &&
        selectedDate.day === d;

      const isToday = isCurrentYearMonthToday && today.getDate() === d;

      days.push({
        day: d,
        isCurrentMonth: true,
        disabled,
        isSelected,
        isToday,
      });
    }

    // Trailing empty slots to guarantee exactly 42 slots (6 rows of 7 days)
    // This prevents the bottom sheet modal from jumping / shrinking / growing between months!
    while (days.length < 42) {
      days.push({
        day: null,
        isCurrentMonth: false,
        disabled: true,
        isSelected: false,
        isToday: false,
      });
    }

    return days;
  }, [viewYear, viewMonth, selectedDate, min, max]);

  // Group 42 items into 6 consistent week rows
  const weeks = useMemo(() => {
    const rows: (typeof calendarDays)[] = [];
    for (let i = 0; i < calendarDays.length; i += 7) {
      rows.push(calendarDays.slice(i, i + 7));
    }
    return rows;
  }, [calendarDays]);

  const handlePrevMonth = () => {
    try {
      Haptics.selectionAsync();
    } catch {}
    if (viewMonth === 0) {
      if (viewYear > minYear) {
        setViewYear((y) => y - 1);
        setViewMonth(11);
      }
    } else {
      setViewMonth((m) => m - 1);
    }
  };

  const handleNextMonth = () => {
    try {
      Haptics.selectionAsync();
    } catch {}
    if (viewMonth === 11) {
      if (viewYear < maxYear) {
        setViewYear((y) => y + 1);
        setViewMonth(0);
      }
    } else {
      setViewMonth((m) => m + 1);
    }
  };

  const handleSelectDay = (day: number) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelectedDate({
      year: viewYear,
      month: viewMonth,
      day,
    });
  };

  // The fast selector must move the actual selection, otherwise confirming
  // after a year/month jump silently returns the previously selected date.
  const applyYearMonthSelection = (year: number, month: number) => {
    const next = projectSelectionToYearMonth(year, month, selectedDate.day, min, max);
    setSelectedDate(next);
    setViewYear(next.year);
    setViewMonth(next.month);
  };

  const handleConfirm = () => {
    try {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {}
    const ymd = formatDateYmd(selectedDate.year, selectedDate.month, selectedDate.day);
    onSelect(ymd);
    onClose();
  };

  const handleClear = () => {
    try {
      Haptics.selectionAsync();
    } catch {}
    onClear?.();
    onClose();
  };

  const handleJumpToToday = () => {
    try {
      Haptics.selectionAsync();
    } catch {}
    const today = new Date();
    if (max && today > max) return;
    if (min && today < min) return;
    setSelectedDate({
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate(),
    });
    setViewYear(today.getFullYear());
    setViewMonth(today.getMonth());
    setPickerMode('days');
  };

  const currentFormatted = useMemo(() => {
    return formatArabicDateDisplay(
      formatDateYmd(selectedDate.year, selectedDate.month, selectedDate.day)
    );
  }, [selectedDate]);

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent>
      <View style={styles.overlay}>
        <Pressable style={styles.backdrop} onPress={onClose} />

        <View
          style={[
            styles.sheetContainer,
            {
              backgroundColor: isDark ? '#18181B' : '#FFFFFF',
              borderColor: isDark ? '#27272A' : '#E4E4E7',
              paddingBottom: Math.max(insets.bottom, 20),
            },
          ]}>
          {/* Top Grabber */}
          <View style={styles.grabberWrap}>
            <View style={[styles.grabber, { backgroundColor: isDark ? '#3F3F46' : '#D4D4D8' }]} />
          </View>

          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity
              onPress={onClose}
              style={[styles.closeBtn, { backgroundColor: isDark ? '#27272A' : '#F4F4F5' }]}>
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>

            <View style={styles.titleWrap}>
              <ThemedText style={styles.sheetTitle}>{title}</ThemedText>
              <ThemedText style={[styles.selectedDateBadge, { color: silaTheme.primary }]}>
                {currentFormatted}
              </ThemedText>
            </View>

            {allowClear && onClear ? (
              <TouchableOpacity
                onPress={handleClear}
                style={[styles.clearBtn, { backgroundColor: isDark ? '#27272A' : '#F4F4F5' }]}>
                <ThemedText style={styles.clearBtnText}>مسح</ThemedText>
              </TouchableOpacity>
            ) : (
              <View style={{ width: 40 }} />
            )}
          </View>

          {/* Calendar Navigation Bar */}
          <View
            style={[
              styles.navBar,
              {
                backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                borderColor: isDark ? '#3F3F46' : '#E5E7EB',
              },
            ]}>
            {pickerMode === 'days' ? (
              <>
                <TouchableOpacity
                  onPress={handleNextMonth}
                  style={styles.navArrowBtn}
                  hitSlop={8}>
                  <Ionicons name="chevron-forward" size={22} color={colors.text} />
                </TouchableOpacity>

                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={() => {
                    try {
                      Haptics.selectionAsync();
                    } catch {}
                    setPickerMode('yearMonth');
                  }}
                  style={styles.monthYearSelectorBtn}>
                  <ThemedText style={styles.monthYearText}>
                    {ARABIC_MONTHS[viewMonth]} {viewYear}
                  </ThemedText>
                  <View style={[styles.switchModeBadge, { backgroundColor: silaTheme.primaryLight + '20' }]}>
                    <ThemedText style={[styles.switchModeBadgeText, { color: silaTheme.primary }]}>
                      تغيير السنة والشهر
                    </ThemedText>
                    <Ionicons name="swap-vertical" size={13} color={silaTheme.primary} />
                  </View>
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={handlePrevMonth}
                  style={styles.navArrowBtn}
                  hitSlop={8}>
                  <Ionicons name="chevron-back" size={22} color={colors.text} />
                </TouchableOpacity>
              </>
            ) : (
              <View style={styles.yearMonthNavHeader}>
                <ThemedText style={styles.yearMonthHeaderTitle}>
                  اختر السنة والشهر
                </ThemedText>
                <TouchableOpacity
                  onPress={() => setPickerMode('days')}
                  style={[styles.doneYearMonthBtn, { backgroundColor: silaTheme.primary }]}>
                  <ThemedText style={styles.doneYearMonthText}>العودة للتقويم</ThemedText>
                  <Ionicons name="checkmark" size={16} color="#FFFFFF" />
                </TouchableOpacity>
              </View>
            )}
          </View>

          {/* Body Content */}
          {pickerMode === 'days' ? (
            <View style={styles.calendarBody}>
              {/* Weekday Labels (RTL Saturday to Friday) */}
              <View style={styles.weekDaysRow}>
                {WEEK_DAYS.map((w, idx) => (
                  <View key={idx} style={styles.weekDayCell}>
                    <ThemedText
                      style={[
                        styles.weekDayText,
                        { color: idx === 0 || idx === 6 ? silaTheme.gold : colors.icon },
                      ]}>
                      {w}
                    </ThemedText>
                  </View>
                ))}
              </View>

              {/* Day Grid with 6 fixed rows (zero CLS, stable size) */}
              <View style={styles.daysGrid}>
                {weeks.map((week, wIdx) => (
                  <View key={`week-${wIdx}`} style={styles.weekRow}>
                    {week.map((item, dIdx) => {
                      if (item.day === null) {
                        return <View key={`empty-${wIdx}-${dIdx}`} style={styles.dayCell} />;
                      }

                      return (
                        <TouchableOpacity
                          key={`day-${item.day}`}
                          disabled={item.disabled}
                          onPress={() => item.day !== null && handleSelectDay(item.day)}
                          activeOpacity={0.7}
                          style={[
                            styles.dayCell,
                            item.isSelected && {
                              backgroundColor: silaTheme.primary,
                              borderRadius: 22,
                            },
                            item.isToday && !item.isSelected && {
                              borderWidth: 1.5,
                              borderColor: silaTheme.primary,
                              borderRadius: 22,
                            },
                          ]}>
                          <ThemedText
                            style={[
                              styles.dayText,
                              {
                                color: item.disabled
                                  ? isDark
                                    ? '#52525B'
                                    : '#D1D5DB'
                                  : item.isSelected
                                  ? '#FFFFFF'
                                  : colors.text,
                                fontWeight: item.isSelected || item.isToday ? '700' : '500',
                              },
                            ]}>
                            {item.day}
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                ))}
              </View>
            </View>
          ) : (
            /* Year & Month Fast Selector */
            <View style={styles.yearMonthPickerContainer}>
              {/* Years Column */}
              <View style={[styles.yearsColumn, { borderLeftColor: isDark ? '#27272A' : '#E5E7EB' }]}>
                <ThemedText style={[styles.columnLabel, { color: colors.icon }]}>السنة</ThemedText>
                <FlatList
                  ref={yearListRef}
                  data={yearsList}
                  keyExtractor={(item) => String(item)}
                  showsVerticalScrollIndicator={false}
                  getItemLayout={(_, index) => ({
                    length: 44,
                    offset: 44 * index,
                    index,
                  })}
                  renderItem={({ item: y }) => {
                    const isSelected = viewYear === y;
                    return (
                      <TouchableOpacity
                        onPress={() => {
                          try {
                            Haptics.selectionAsync();
                          } catch {}
                          applyYearMonthSelection(y, viewMonth);
                        }}
                        style={[
                          styles.yearItem,
                          isSelected && {
                            backgroundColor: isDark ? '#27272A' : '#EEF2FF',
                            borderColor: silaTheme.primary,
                          },
                        ]}>
                        <ThemedText
                          style={[
                            styles.yearItemText,
                            {
                              color: isSelected ? silaTheme.primary : colors.text,
                              fontWeight: isSelected ? '700' : '400',
                            },
                          ]}>
                          {y}
                        </ThemedText>
                      </TouchableOpacity>
                    );
                  }}
                />
              </View>

              {/* Months Column */}
              <View style={styles.monthsColumn}>
                <ThemedText style={[styles.columnLabel, { color: colors.icon }]}>الشهر</ThemedText>
                <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.monthsGrid}>
                  {ARABIC_MONTHS.map((mName, mIdx) => {
                    const isSelected = viewMonth === mIdx;
                    return (
                      <TouchableOpacity
                        key={mIdx}
                        onPress={() => {
                          try {
                            Haptics.selectionAsync();
                          } catch {}
                          applyYearMonthSelection(viewYear, mIdx);
                          setPickerMode('days');
                        }}
                        style={[
                          styles.monthItem,
                          {
                            backgroundColor: isSelected
                              ? silaTheme.primary
                              : isDark
                              ? '#27272A'
                              : '#F3F4F6',
                          },
                        ]}>
                        <ThemedText
                          style={[
                            styles.monthItemText,
                            {
                              color: isSelected ? '#FFFFFF' : colors.text,
                              fontWeight: isSelected ? '700' : '500',
                            },
                          ]}>
                          {mName}
                        </ThemedText>
                      </TouchableOpacity>
                    );
                  })}
                </ScrollView>
              </View>
            </View>
          )}

          {/* Footer Shortcuts & Confirm Button */}
          <View style={styles.footer}>
            <TouchableOpacity
              onPress={handleJumpToToday}
              style={[
                styles.todayBtn,
                {
                  borderColor: isDark ? '#3F3F46' : '#E5E7EB',
                  backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                },
              ]}>
              <Ionicons name="today-outline" size={16} color={silaTheme.primary} />
              <ThemedText style={[styles.todayBtnText, { color: colors.text }]}>اليوم</ThemedText>
            </TouchableOpacity>

            <TouchableOpacity
              activeOpacity={0.85}
              onPress={handleConfirm}
              style={[styles.confirmBtn, { backgroundColor: silaTheme.primary }]}>
              <ThemedText style={styles.confirmBtnText}>تأكيد الاختيار</ThemedText>
              <Ionicons name="checkmark-circle" size={20} color="#FFFFFF" />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.55)',
    justifyContent: 'flex-end',
  },
  backdrop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
  },
  sheetContainer: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingHorizontal: 20,
    paddingTop: 10,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  grabberWrap: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  grabber: {
    width: 44,
    height: 5,
    borderRadius: 3,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    marginBottom: 10,
  },
  titleWrap: {
    alignItems: 'center',
  },
  sheetTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    fontWeight: '700',
  },
  selectedDateBadge: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
    marginTop: 2,
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  clearBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  clearBtnText: {
    fontSize: 12,
    color: '#EF4444',
    fontFamily: 'Tajawal-Bold',
    fontWeight: '700',
  },
  navBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 14,
    minHeight: 56,
  },
  navArrowBtn: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 12,
  },
  monthYearSelectorBtn: {
    alignItems: 'center',
  },
  monthYearText: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    fontWeight: '700',
  },
  switchModeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    marginTop: 3,
  },
  switchModeBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  yearMonthNavHeader: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  yearMonthHeaderTitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  doneYearMonthBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 10,
  },
  doneYearMonthText: {
    fontSize: 12,
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
  },
  calendarBody: {
    height: 330,
    justifyContent: 'flex-start',
  },
  weekDaysRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginBottom: 6,
    paddingBottom: 6,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#E5E7EB',
  },
  weekDayCell: {
    width: 44,
    alignItems: 'center',
  },
  weekDayText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  daysGrid: {
    flexDirection: 'column',
    justifyContent: 'flex-start',
  },
  weekRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
    height: 44,
    marginVertical: 1,
  },
  dayCell: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dayText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
  yearMonthPickerContainer: {
    flexDirection: 'row',
    height: 330,
    marginBottom: 0,
  },
  yearsColumn: {
    width: 100,
    borderLeftWidth: 1,
    paddingHorizontal: 4,
  },
  monthsColumn: {
    flex: 1,
    paddingHorizontal: 8,
  },
  columnLabel: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    marginBottom: 6,
    textAlign: 'center',
  },
  yearItem: {
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    marginVertical: 2,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  yearItemText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  monthsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: 8,
    paddingBottom: 20,
  },
  monthItem: {
    width: '48%',
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  monthItemText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginTop: 10,
  },
  todayBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 16,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
  },
  todayBtnText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  confirmBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 48,
    borderRadius: 14,
  },
  confirmBtnText: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    color: '#FFFFFF',
  },
});
