import type { WalkingRecord } from '@/lib/api/walkingApi';
import { cleanDateYMD, getStepColorTier, type StepColorTier } from '@/lib/walking/formatters';
import type { HealthDaySteps } from '@/lib/walking/health/types';
import {
  dateKey,
  ksaDateKey,
  ksaHour,
  MAX_STEPS_PER_HOUR,
  type WalkingLedger,
} from '@/lib/walking/ledger';

export type ActivityPeriod = 'day' | 'week' | 'month' | 'year';

export const PERIOD_TABS: { key: ActivityPeriod; label: string; shortLabel: string; icon: string }[] = [
  { key: 'day', label: 'اليوم', shortLabel: 'يوم', icon: 'clock' },
  { key: 'week', label: 'الأسبوع', shortLabel: 'أسبوع', icon: 'calendar' },
  { key: 'month', label: 'الشهر', shortLabel: 'شهر', icon: 'calendar.badge.clock' },
  { key: 'year', label: 'السنة', shortLabel: 'سنة', icon: 'chart.bar' },
];

export const ARABIC_DAYS_FULL = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
export const ARABIC_DAYS_SHORT = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

export const ARABIC_MONTHS = [
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

// ==========================================
// 1. نماذج بيانات النقاط في الرسوم البيانية
// ==========================================

export interface HourlyBarPoint {
  hour: number; // 0..23
  label: string; // "12 ص", "1 ص", ...
  displayLabel: string; // Only shown at 0, 6, 12, 18, 23 to avoid overlap
  timeRange: string; // "09:00 ص - 10:00 ص"
  steps: number;
  distanceKm: number;
  calories: number;
  isCurrentHour: boolean;
  isActive: boolean;
}

export interface DailyBarPoint {
  dateKey: string;
  date: Date;
  dayName: string; // "الأحد"
  shortDay: string; // "أحد"
  dayNumber: number;
  steps: number;
  distanceKm: number;
  calories: number;
  isToday: boolean;
  isFuture: boolean;
  isGoalReached: boolean;
  tier: StepColorTier;
}

export interface MonthlyBarPoint {
  dayNumber: number; // 1..31
  dateKey: string;
  date: Date;
  steps: number;
  distanceKm: number;
  calories: number;
  isToday: boolean;
  isFuture: boolean;
  isGoalReached: boolean;
  tier: StepColorTier;
  showLabel: boolean;
}

export interface YearlyBarPoint {
  monthIndex: number; // 0..11
  monthName: string; // "يناير"
  shortMonth: string;
  steps: number;
  dailyAverage: number;
  distanceKm: number;
  calories: number;
  isCurrentMonth: boolean;
  isFuture: boolean;
}

// ==========================================
// 2. نماذج ملخصات الفترات
// ==========================================

export interface DaySummary {
  period: 'day';
  title: string;
  selectedDate: Date;
  totalSteps: number;
  activeHoursCount: number; // عدد الساعات التي مشيت فيها
  peakHour: { hour: number; timeRange: string; steps: number } | null;
  distanceKm: number;
  calories: number;
  points: HourlyBarPoint[];
}

export interface WeekSummary {
  period: 'week';
  title: string;
  selectedDate: Date;
  totalSteps: number;
  dailyAverage: number;
  activeDaysCount: number;
  goalReachedDaysCount: number;
  bestDay: { dayName: string; dateKey: string; steps: number } | null;
  distanceKm: number;
  calories: number;
  points: DailyBarPoint[];
}

export interface MonthSummary {
  period: 'month';
  title: string;
  selectedDate: Date;
  totalSteps: number;
  dailyAverage: number;
  activeDaysCount: number;
  goalReachedDaysCount: number;
  bestDay: { dayNumber: number; dateKey: string; steps: number } | null;
  distanceKm: number;
  calories: number;
  points: MonthlyBarPoint[];
}

export interface YearSummary {
  period: 'year';
  title: string;
  selectedDate: Date;
  totalSteps: number;
  monthlyAverage: number;
  dailyAverage: number;
  activeMonthsCount: number;
  bestMonth: { monthName: string; steps: number; monthIndex: number } | null;
  distanceKm: number;
  calories: number;
  points: YearlyBarPoint[];
}

export type ActivityPeriodSummary = DaySummary | WeekSummary | MonthSummary | YearSummary;

// ==========================================
// 3. دوال التاريخ والتنسيق
// ==========================================

export function formatHourLabel(hour: number): { short: string; range: string; displayLabel: string } {
  const period = hour >= 12 ? 'م' : 'ص';
  const displayH = hour % 12 === 0 ? 12 : hour % 12;
  const nextHour = (hour + 1) % 24;
  const nextPeriod = nextHour >= 12 ? 'م' : 'ص';
  const nextDisplayH = nextHour % 12 === 0 ? 12 : nextHour % 12;

  const short = `${displayH} ${period}`;
  const range = `${displayH}:00 ${period} - ${nextDisplayH}:00 ${nextPeriod}`;
  const displayLabel = hour === 0 || hour === 6 || hour === 12 || hour === 18 || hour === 23 ? `${displayH}${period}` : '';

  return { short, range, displayLabel };
}

export function formatPeriodTitle(date: Date, period: ActivityPeriod): string {
  const now = new Date();
  const isThisYear = date.getFullYear() === now.getFullYear();

  if (period === 'day') {
    const isToday = dateKey(date) === dateKey(now);
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    const isYesterday = dateKey(date) === dateKey(yesterday);

    const dayName = ARABIC_DAYS_FULL[date.getDay()];
    const dayNum = date.getDate();
    const monthName = ARABIC_MONTHS[date.getMonth()];

    if (isToday) return `اليوم، ${dayNum} ${monthName}`;
    if (isYesterday) return `أمس، ${dayNum} ${monthName}`;
    return isThisYear ? `${dayName}، ${dayNum} ${monthName}` : `${dayName}، ${dayNum} ${monthName} ${date.getFullYear()}`;
  }

  if (period === 'week') {
    const end = new Date(date);
    const start = new Date(date);
    start.setDate(start.getDate() - 6);

    const sDay = start.getDate();
    const sMonth = ARABIC_MONTHS[start.getMonth()];
    const eDay = end.getDate();
    const eMonth = ARABIC_MONTHS[end.getMonth()];

    if (start.getMonth() === end.getMonth()) {
      return `${sDay} - ${eDay} ${sMonth} ${start.getFullYear()}`;
    }
    return `${sDay} ${sMonth} - ${eDay} ${eMonth} ${end.getFullYear()}`;
  }

  if (period === 'month') {
    return `${ARABIC_MONTHS[date.getMonth()]} ${date.getFullYear()}`;
  }

  return `عام ${date.getFullYear()}`;
}

export function getStartOfWeek(ref: Date): Date {
  const d = new Date(ref);
  d.setDate(d.getDate() - 6);
  d.setHours(0, 0, 0, 0);
  return d;
}

export function canNavigateForward(currentDate: Date, period: ActivityPeriod): boolean {
  const now = new Date();
  if (period === 'day' || period === 'week') {
    return dateKey(currentDate) < dateKey(now);
  }
  if (period === 'month') {
    return (
      currentDate.getFullYear() < now.getFullYear() ||
      (currentDate.getFullYear() === now.getFullYear() && currentDate.getMonth() < now.getMonth())
    );
  }
  return currentDate.getFullYear() < now.getFullYear();
}

export function isCurrentPeriod(date: Date, period: ActivityPeriod): boolean {
  return !canNavigateForward(date, period);
}

export function navigatePeriod(currentDate: Date, period: ActivityPeriod, direction: -1 | 1): Date {
  const next = new Date(currentDate);
  if (period === 'day') {
    next.setDate(next.getDate() + direction);
  } else if (period === 'week') {
    next.setDate(next.getDate() + direction * 7);
  } else if (period === 'month') {
    next.setMonth(next.getMonth() + direction);
  } else if (period === 'year') {
    next.setFullYear(next.getFullYear() + direction);
  }

  const now = new Date();
  if (direction > 0 && next.getTime() > now.getTime()) {
    return now;
  }
  return next;
}

// ==========================================
// 4. استخراج الخطوات وتوزيع الساعات
// ==========================================

export function getStepsForDate(
  dateStr: string,
  ledgerDaily: Record<string, number>,
  historyRecords?: WalkingRecord[],
  todayStepsOverride?: number,
  fallbackDateStr?: string
): number {
  const now = new Date();
  const isToday = fallbackDateStr != null ? dateStr === ksaDateKey(now) : dateStr === dateKey(now);
  if (isToday && todayStepsOverride != null) {
    return Number.isFinite(todayStepsOverride) ? Math.max(0, Math.round(todayStepsOverride)) : 0;
  }
  let steps = ledgerDaily[dateStr] ?? (fallbackDateStr ? ledgerDaily[fallbackDateStr] ?? 0 : 0);
  if (historyRecords && historyRecords.length > 0) {
    const rec = historyRecords.find(
      (r) => cleanDateYMD(r.date) === dateStr || (fallbackDateStr && cleanDateYMD(r.date) === fallbackDateStr)
    );
    if (rec && rec.steps > steps) {
      steps = rec.steps;
    }
  }
  return Number.isFinite(steps) ? Math.max(0, Math.round(steps)) : 0;
}

/**
 * حساب خطوات ساعات اليوم (0 إلى 23)
 * - يعتمد حصراً على قراءات الساعات الفعلية المسجلة من الحساس (CMPedometer / Pedometer)
 *   لضمان التطابق التام 100% مع تطبيق "صحتي" (Apple Health).
 * - لا يقوم بتفريغ الفروق في الساعة الحالية ولا يختلق توزيعاً وهمياً للساعات غير المسجلة.
 */
export function getHourlyStepsForDate(
  dateStr: string,
  _totalSteps: number,
  ledgerHourly?: Record<string, Record<number, number>>,
  fallbackDateStr?: string
): Record<number, number> {
  const stored = ledgerHourly?.[dateStr] ?? (fallbackDateStr ? ledgerHourly?.[fallbackDateStr] : undefined);
  if (!stored || typeof stored !== 'object') {
    return {};
  }

  const sanitized: Record<number, number> = {};
  for (const [hStr, rawVal] of Object.entries(stored)) {
    const h = Number(hStr);
    const val = Number(rawVal);
    if (!Number.isNaN(h) && h >= 0 && h < 24 && !Number.isNaN(val) && val > 0) {
      sanitized[h] = Math.min(MAX_STEPS_PER_HOUR, Math.round(val));
    }
  }

  return sanitized;
}

// ==========================================
// 5. محرك ملخصات الفترات (Apple Health Aggregators)
// ==========================================

/**
 * 1️⃣ ملخص اليوم (Day Summary & 24 Hours)
 */
export function computeDaySummary(
  selectedDate: Date,
  ledger: WalkingLedger,
  historyRecords?: WalkingRecord[],
  todayStepsOverride?: number,
  deviceDay?: HealthDaySteps | null
): DaySummary {
  const now = new Date();
  const isToday = dateKey(selectedDate) === dateKey(now) || (!deviceDay && ksaDateKey(selectedDate) === ksaDateKey(now));
  const kKey = ksaDateKey(selectedDate);
  const lKey = dateKey(selectedDate);
  const dKey = isToday ? ksaDateKey(now) : (ledger.hourly?.[kKey] || ledger.daily[kKey] != null ? kKey : lKey);
  const fallbackKey = dKey === kKey ? lKey : kKey;
  const totalSteps = deviceDay?.total ?? getStepsForDate(dKey, ledger.daily, historyRecords, todayStepsOverride, fallbackKey);
  const hourlyMap = deviceDay?.hourly ?? getHourlyStepsForDate(dKey, totalSteps, ledger.hourly, fallbackKey);

  const curHour = deviceDay ? now.getHours() : ksaHour(now);
  const points: HourlyBarPoint[] = [];

  let activeHoursCount = 0;
  let peakHour: DaySummary['peakHour'] = null;
  let maxHourSteps = 0;

  for (let h = 0; h < 24; h++) {
    const steps = hourlyMap[h] ?? 0;
    const { short, range, displayLabel } = formatHourLabel(h);
    const isCurrentHour = isToday && h === curHour;
    const isActive = steps > 0;

    if (isActive) {
      activeHoursCount++;
      if (steps > maxHourSteps) {
        maxHourSteps = steps;
        peakHour = { hour: h, timeRange: range, steps };
      }
    }

    points.push({
      hour: h,
      label: short,
      displayLabel,
      timeRange: range,
      steps,
      distanceKm: Number((steps * 0.00076).toFixed(2)),
      calories: Math.round(steps * 0.04),
      isCurrentHour,
      isActive,
    });
  }

  const distanceKm = Number((totalSteps * 0.00076).toFixed(2));
  const calories = Math.round(totalSteps * 0.04);

  return {
    period: 'day',
    title: formatPeriodTitle(selectedDate, 'day'),
    selectedDate,
    totalSteps,
    activeHoursCount,
    peakHour,
    distanceKm,
    calories,
    points,
  };
}

/**
 * 2️⃣ ملخص الأسبوع (Week Summary & 7 Days)
 * يُمثل الأيام الـ 7 بالتسلسل الزمني الصاعد (منذ 6 أيام وصولاً إلى اليوم المحدد)
 */
export function computeWeekSummary(
  selectedDate: Date,
  ledger: WalkingLedger,
  historyRecords?: WalkingRecord[],
  todayStepsOverride?: number,
  dailyGoal = 10000,
  deviceDaily?: Record<string, number>
): WeekSummary {
  const now = new Date();
  const nowKey = dateKey(now);

  const points: DailyBarPoint[] = [];
  let totalSteps = 0;
  let activeDaysCount = 0;
  let goalReachedDaysCount = 0;
  let bestDay: WeekSummary['bestDay'] = null;
  let maxSteps = 0;
  let daysElapsed = 0;

  for (let i = 6; i >= 0; i--) {
    const d = new Date(selectedDate);
    d.setDate(d.getDate() - i);
    const k = dateKey(d);
    const isFuture = k > nowKey;
    const isToday = k === nowKey;

    const steps = isFuture ? 0 : deviceDaily?.[k] ?? getStepsForDate(k, ledger.daily, historyRecords, todayStepsOverride);
    if (!isFuture) {
      daysElapsed++;
      totalSteps += steps;
      if (steps > 0) activeDaysCount++;
      if (steps >= dailyGoal) goalReachedDaysCount++;
      if (steps > maxSteps) {
        maxSteps = steps;
        bestDay = {
          dayName: ARABIC_DAYS_FULL[d.getDay()],
          dateKey: k,
          steps,
        };
      }
    }

    points.push({
      dateKey: k,
      date: d,
      dayName: ARABIC_DAYS_FULL[d.getDay()],
      shortDay: ARABIC_DAYS_SHORT[d.getDay()],
      dayNumber: d.getDate(),
      steps,
      distanceKm: Number((steps * 0.00076).toFixed(2)),
      calories: Math.round(steps * 0.04),
      isToday,
      isFuture,
      isGoalReached: steps >= dailyGoal,
      tier: getStepColorTier(steps),
    });
  }

  const dailyAverage = daysElapsed > 0 ? Math.round(totalSteps / daysElapsed) : 0;
  const distanceKm = Number((totalSteps * 0.00076).toFixed(2));
  const calories = Math.round(totalSteps * 0.04);

  return {
    period: 'week',
    title: formatPeriodTitle(selectedDate, 'week'),
    selectedDate,
    totalSteps,
    dailyAverage,
    activeDaysCount,
    goalReachedDaysCount,
    bestDay,
    distanceKm,
    calories,
    points,
  };
}

/**
 * 3️⃣ ملخص الشهر (Month Summary & Calendar Month Days)
 * يُمثل أيام الشهر التقويمي من اليوم الأخير (30/31) إلى اليوم 1
 * بحيث تظهر اليوم الـ 30 في أقصى اليمين في الواجهات العربية (RTL)
 */
export function computeMonthSummary(
  selectedDate: Date,
  ledger: WalkingLedger,
  historyRecords?: WalkingRecord[],
  todayStepsOverride?: number,
  dailyGoal = 10000,
  deviceDaily?: Record<string, number>
): MonthSummary {
  const now = new Date();
  const nowKey = dateKey(now);

  const year = selectedDate.getFullYear();
  const month = selectedDate.getMonth();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const points: MonthlyBarPoint[] = [];
  let totalSteps = 0;
  let activeDaysCount = 0;
  let goalReachedDaysCount = 0;
  let bestDay: MonthSummary['bestDay'] = null;
  let maxSteps = 0;
  let daysElapsed = 0;

  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(year, month, day);
    const k = dateKey(d);
    const isFuture = k > nowKey;
    const isToday = k === nowKey;

    const steps = isFuture ? 0 : deviceDaily?.[k] ?? getStepsForDate(k, ledger.daily, historyRecords, todayStepsOverride);

    if (!isFuture) {
      daysElapsed++;
      totalSteps += steps;
      if (steps > 0) activeDaysCount++;
      if (steps >= dailyGoal) goalReachedDaysCount++;
      if (steps > maxSteps) {
        maxSteps = steps;
        bestDay = {
          dayNumber: day,
          dateKey: k,
          steps,
        };
      }
    }

    // إظهار الترقيم المنظم لأيام الشهر (اليوم 1، وكل 5 أيام، واليوم الأخير 30/31)
    const showLabel = day === 1 || day % 5 === 0 || day === daysInMonth;

    points.push({
      dayNumber: day,
      dateKey: k,
      date: d,
      steps,
      distanceKm: Number((steps * 0.00076).toFixed(2)),
      calories: Math.round(steps * 0.04),
      isToday,
      isFuture,
      isGoalReached: steps >= dailyGoal,
      tier: getStepColorTier(steps),
      showLabel,
    });
  }

  const dailyAverage = daysElapsed > 0 ? Math.round(totalSteps / daysElapsed) : 0;
  const distanceKm = Number((totalSteps * 0.00076).toFixed(2));
  const calories = Math.round(totalSteps * 0.04);

  return {
    period: 'month',
    title: formatPeriodTitle(selectedDate, 'month'),
    selectedDate,
    totalSteps,
    dailyAverage,
    activeDaysCount,
    goalReachedDaysCount,
    bestDay,
    distanceKm,
    calories,
    points,
  };
}

/**
 * 4️⃣ ملخص السنة (Year Summary & 12 Months)
 * يُمثل شهور السنة التقويمة الـ 12 (من يناير إلى ديسمبر) بالتسلسل الصاعد
 */
export function computeYearSummary(
  selectedDate: Date,
  ledger: WalkingLedger,
  historyRecords?: WalkingRecord[],
  todayStepsOverride?: number,
  deviceDaily?: Record<string, number>
): YearSummary {
  const now = new Date();
  const year = selectedDate.getFullYear();

  const points: YearlyBarPoint[] = [];
  let totalSteps = 0;
  let activeMonthsCount = 0;
  let bestMonth: YearSummary['bestMonth'] = null;
  let maxMonthSteps = 0;
  let totalDaysElapsed = 0;

  for (let m = 0; m < 12; m++) {
    const isCurrentMonth = year === now.getFullYear() && m === now.getMonth();
    const isFuture = year > now.getFullYear() || (year === now.getFullYear() && m > now.getMonth());
    const daysInM = new Date(year, m + 1, 0).getDate();

    let monthSteps = 0;
    let daysCount = 0;

    if (!isFuture) {
      const daysToCheck = isCurrentMonth ? now.getDate() : daysInM;
      daysCount = daysToCheck;
      totalDaysElapsed += daysToCheck;

      for (let day = 1; day <= daysToCheck; day++) {
        const dayDate = new Date(year, m, day);
        const k = dateKey(dayDate);
        const steps = deviceDaily?.[k] ?? getStepsForDate(k, ledger.daily, historyRecords, todayStepsOverride);
        monthSteps += steps;
      }

      totalSteps += monthSteps;
      if (monthSteps > 0) activeMonthsCount++;
      if (monthSteps > maxMonthSteps) {
        maxMonthSteps = monthSteps;
        bestMonth = {
          monthName: ARABIC_MONTHS[m],
          monthIndex: m,
          steps: monthSteps,
        };
      }
    }

    const dailyAverage = daysCount > 0 ? Math.round(monthSteps / daysCount) : 0;

    points.push({
      monthIndex: m,
      monthName: ARABIC_MONTHS[m],
      shortMonth: ARABIC_MONTHS[m].slice(0, 4),
      steps: monthSteps,
      dailyAverage,
      distanceKm: Number((monthSteps * 0.00076).toFixed(2)),
      calories: Math.round(monthSteps * 0.04),
      isCurrentMonth,
      isFuture,
    });
  }

  const monthlyAverage = activeMonthsCount > 0 ? Math.round(totalSteps / activeMonthsCount) : 0;
  const dailyAverage = totalDaysElapsed > 0 ? Math.round(totalSteps / totalDaysElapsed) : 0;
  const distanceKm = Number((totalSteps * 0.00076).toFixed(2));
  const calories = Math.round(totalSteps * 0.04);

  return {
    period: 'year',
    title: formatPeriodTitle(selectedDate, 'year'),
    selectedDate,
    totalSteps,
    monthlyAverage,
    dailyAverage,
    activeMonthsCount,
    bestMonth,
    distanceKm,
    calories,
    points,
  };
}

