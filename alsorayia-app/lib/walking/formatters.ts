/**
 * دوال تنسيق أرقام الخطوات وتصنيف مستويات المشي وتمييز المتصدرين
 */

/**
 * تنسيق عدد الخطوات بشكل متماسك ودقيق يمنع انفصال أو تباعد الأرقام.
 * ينتج رقماً متماسكاً مثل 4,058 أو 12,500 بدون فجوات الترقيم العربي القديمة.
 */
export { formatSteps, toEnglishDigits, formatEnglishNumber } from '@/lib/utils/numberFormat';

/**
 * تصنيفات ألوان الخطوات حسب المتطلبات:
 * - 15,000 خطوة وأكثر: لون ذهبي #EDC967
 * - 10,000 خطوة وأكثر: لون فضي #BBBCC0
 * - 5,000 خطوة وأكثر: لون برونزي #F2BA7A
 * - أقل من 5,000 خطوة: لون أخضر #009000
 */
export type StepColorTier = 'gold' | 'silver' | 'bronze' | 'green' | 'gray';

export interface StepTierConfig {
  tier: StepColorTier;
  label: string;
  textColor: string;
  badgeBgLight: string;
  badgeBgDark: string;
  barColor: string;
  borderColor: string;
  cardBgLight?: string;
  cardBgDark?: string;
}

export function getStepColorTier(steps: number): StepColorTier {
  const s = Math.round(steps || 0);
  if (s >= 15000) return 'gold';
  if (s >= 10000) return 'silver';
  if (s >= 5000) return 'bronze';
  return 'green';
}

const ARABIC_DAYS_FULL = [
  'الأحد',
  'الإثنين',
  'الثلاثاء',
  'الأربعاء',
  'الخميس',
  'الجمعة',
  'السبت',
];

const ARABIC_MONTHS_SHORT = [
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

/**
 * تنظيف التاريخ من أي جزء وقت زائد مثل HH:mm:ss أو صيغ ISO
 */
export function cleanDateYMD(d?: string | null): string {
  if (!d) return '';
  return d.trim().split(/[ T]/)[0] || '';
}

export function formatGroupDates(start?: string | null, end?: string | null): string {
  const cleanStart = cleanDateYMD(start);
  const cleanEnd = cleanDateYMD(end);

  if (cleanStart && cleanEnd) {
    const sParts = cleanStart.split('-');
    const eParts = cleanEnd.split('-');
    if (sParts.length === 3 && eParts.length === 3) {
      const sDay = parseInt(sParts[2], 10);
      const sMonth = ARABIC_MONTHS_SHORT[parseInt(sParts[1], 10) - 1] || sParts[1];
      const eDay = parseInt(eParts[2], 10);
      const eMonth = ARABIC_MONTHS_SHORT[parseInt(eParts[1], 10) - 1] || eParts[1];

      if (sMonth === eMonth) {
        return `${sDay} - ${eDay} ${sMonth}`;
      }
      return `${sDay} ${sMonth} - ${eDay} ${eMonth}`;
    }
    return `${cleanStart.slice(5)} - ${cleanEnd.slice(5)}`;
  }
  if (cleanStart && !cleanEnd) {
    const sParts = cleanStart.split('-');
    if (sParts.length === 3) {
      const sDay = parseInt(sParts[2], 10);
      const sMonth = ARABIC_MONTHS_SHORT[parseInt(sParts[1], 10) - 1] || sParts[1];
      return `من ${sDay} ${sMonth} (مستمر ♾️)`;
    }
    return `من ${cleanStart.slice(5)} (مستمر ♾️)`;
  }
  if (!cleanStart && cleanEnd) {
    const eParts = cleanEnd.split('-');
    if (eParts.length === 3) {
      const eDay = parseInt(eParts[2], 10);
      const eMonth = ARABIC_MONTHS_SHORT[parseInt(eParts[1], 10) - 1] || eParts[1];
      return `حتى ${eDay} ${eMonth}`;
    }
    return `حتى ${cleanEnd.slice(5)}`;
  }
  return 'تحدي مستمر ♾️';
}

export function getStepTierConfig(steps: number, isDark: boolean): StepTierConfig {
  const tier = getStepColorTier(steps);

  switch (tier as string) {
    case 'gold':
      return {
        tier: 'gold',
        label: '+15,000 خطوة',
        textColor: '#EDC967',
        badgeBgLight: 'rgba(237, 201, 103, 0.18)',
        badgeBgDark: 'rgba(237, 201, 103, 0.22)',
        barColor: '#EDC967',
        borderColor: '#EDC967',
        cardBgLight: '#FFFDF5',
        cardBgDark: '#261C08',
      };
    case 'silver':
      return {
        tier: 'silver',
        label: '+10,000 خطوة',
        textColor: isDark ? '#E2E8F0' : '#475569',
        badgeBgLight: 'rgba(187, 188, 192, 0.22)',
        badgeBgDark: 'rgba(187, 188, 192, 0.25)',
        barColor: '#BBBCC0',
        borderColor: '#BBBCC0',
        cardBgLight: '#F8FAFC',
        cardBgDark: '#1E293B',
      };
    case 'bronze':
    case 'beige':
      return {
        tier: 'bronze',
        label: '+5,000 خطوة',
        textColor: '#C87A38',
        badgeBgLight: 'rgba(200, 122, 56, 0.18)',
        badgeBgDark: 'rgba(200, 122, 56, 0.22)',
        barColor: '#C87A38',
        borderColor: '#C87A38',
        cardBgLight: '#FFF7ED',
        cardBgDark: '#2A160E',
      };
    case 'green':
    case 'gray':
    default:
      return {
        tier: 'green',
        label: 'أقل من 5,000',
        textColor: '#009000',
        badgeBgLight: 'rgba(0, 144, 0, 0.12)',
        badgeBgDark: 'rgba(0, 144, 0, 0.20)',
        barColor: '#009000',
        borderColor: '#009000',
        cardBgLight: '#F0FDF4',
        cardBgDark: '#052E16',
      };
  }
}

export interface StepMilestoneLegendItem {
  key: StepColorTier;
  label: string;
  dotColor: string;
  textColor: string;
}

export function getStepMilestoneLegend(isDark: boolean): StepMilestoneLegendItem[] {
  return [
    {
      key: 'gold',
      label: '+15 ألف',
      dotColor: '#EDC967',
      textColor: '#EDC967',
    },
    {
      key: 'silver',
      label: '+10 آلاف',
      dotColor: '#BBBCC0',
      textColor: isDark ? '#E2E8F0' : '#475569',
    },
    {
      key: 'bronze',
      label: '+5 آلاف',
      dotColor: '#C87A38',
      textColor: '#C87A38',
    },
    {
      key: 'green',
      label: '< 5 آلاف',
      dotColor: '#009000',
      textColor: '#009000',
    },
  ];
}

/**
 * تمييز الثلاثة المتقدمين بالمشي (المراكز 1، 2، 3)
 */
export interface TopRankConfig {
  medal: string;
  borderColor: string;
  borderWidth: number;
  bgGradientLight: readonly [string, string];
  bgGradientDark: readonly [string, string];
  tagText: string;
  badgeColor: string;
}

export function getTopLeaderHighlight(rank: number, hasSteps: boolean): TopRankConfig | null {
  if (!hasSteps || rank <= 0 || rank > 3) return null;

  if (rank === 1) {
    return {
      medal: '🥇',
      borderColor: '#F59E0B',
      borderWidth: 2,
      bgGradientLight: ['#FFFBEB', '#FEF3C7'],
      bgGradientDark: ['#2E2207', '#1F1705'],
      tagText: 'المتصدر الأول 👑',
      badgeColor: '#D97706',
    };
  }

  if (rank === 2) {
    return {
      medal: '🥈',
      borderColor: '#94A3B8',
      borderWidth: 1.8,
      bgGradientLight: ['#F8FAFC', '#F1F5F9'],
      bgGradientDark: ['#1E293B', '#0F172A'],
      tagText: 'المركز الثاني 🥈',
      badgeColor: '#475569',
    };
  }

  if (rank === 3) {
    return {
      medal: '🥉',
      borderColor: '#9A3412',
      borderWidth: 1.5,
      bgGradientLight: ['#FDF7F2', '#FAECE1'],
      bgGradientDark: ['#28180E', '#190E08'],
      tagText: 'المركز الثالث 🥉',
      badgeColor: '#7C2D12',
    };
  }

  return null;
}

/**
 * تنسيق التاريخ باللغة العربية (مثال: 14 مايو 2026)
 */
export interface FormatDateArOptions {
  /**
   * تضمين اسم اليوم في بداية التاريخ (مثال: "الخميس، 15 مارس 2026")
   */
  includeWeekday?: boolean;
}

export function formatDateAr(dateStr?: string | null, options?: FormatDateArOptions): string {
  if (!dateStr) return '';
  const clean = cleanDateYMD(dateStr);
  if (!clean) return '';
  const parts = clean.split('-');
  if (parts.length === 3) {
    const year = parseInt(parts[0], 10);
    const monthIndex = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);
    const month = ARABIC_MONTHS_SHORT[monthIndex] || parts[1];


    if (
      options?.includeWeekday &&
      !Number.isNaN(year) &&
      !Number.isNaN(monthIndex) &&
      !Number.isNaN(day)
    ) {
      const d = new Date(Date.UTC(year, monthIndex, day));
      const dayName = ARABIC_DAYS_FULL[d.getUTCDay()];
      if (dayName) {
        return `${dayName}، ${day} ${month} ${year}`;
      }
    }

    return `${day} ${month} ${year}`;
  }
  return clean;
}

/**
 * تنسيق عدد أيام سلسلة الاستمرارية بالعربية مع مراعاة قواعد الجمع والمثنى
 * 0 -> 0 يوم
 * 1 -> يوم واحد
 * 2 -> يومان
 * 3..10 -> X أيام
 * 11+ -> X يوماً
 */
export function formatStreakDays(days: number | null | undefined): string {
  const d = Math.max(0, Math.round(days || 0));
  if (d === 0) return '0 يوم';
  if (d === 1) return 'يوم واحد';
  if (d === 2) return 'يومان';
  if (d >= 3 && d <= 10) return `${d} أيام`;
  return `${d} يوماً`;
}

/**
 * تنسيق نطاق تاريخ لوحة المتصدرين للفترات المتدحرجة (أسبوع متدحرج / شهر متدحرج)
 */
export function formatLeaderboardPeriodRange(
  period: string,
  dateRange?: { from: string; to: string } | null
): string {
  if (period === 'today') {
    return 'اليوم';
  }
  if (period === 'all_time') {
    return 'النشاط التراكمي الشامل';
  }
  if (!dateRange || !dateRange.from || !dateRange.to) {
    return period === 'weekly' ? 'آخر 7 أيام متصلة' : 'آخر 30 يوماً متصلة';
  }

  const cleanFrom = cleanDateYMD(dateRange.from);
  const cleanTo = cleanDateYMD(dateRange.to);
  const fParts = cleanFrom.split('-');
  const tParts = cleanTo.split('-');

  if (fParts.length === 3 && tParts.length === 3) {
    const fDay = parseInt(fParts[2], 10);
    const fMonth = ARABIC_MONTHS_SHORT[parseInt(fParts[1], 10) - 1] || fParts[1];
    const tDay = parseInt(tParts[2], 10);
    const tMonth = ARABIC_MONTHS_SHORT[parseInt(tParts[1], 10) - 1] || tParts[1];

    const prefix = period === 'weekly' ? 'آخر 7 أيام' : 'آخر 30 يوماً';
    if (fMonth === tMonth) {
      return `${prefix}: ${fDay} - ${tDay} ${tMonth}`;
    }
    return `${prefix}: ${fDay} ${fMonth} - ${tDay} ${tMonth}`;
  }

  return period === 'weekly' ? 'آخر 7 أيام متصلة' : 'آخر 30 يوماً متصلة';
}



