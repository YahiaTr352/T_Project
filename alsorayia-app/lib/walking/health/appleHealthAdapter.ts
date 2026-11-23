/* eslint-disable @typescript-eslint/no-require-imports */
import { Linking } from 'react-native';
import type {
  HealthConsentStore,
  HealthDaySteps,
  HealthPermissionState,
  WalkingHealthProvider,
} from './types';
import { createSecureConsentStore } from './consentStore';
import {
  aggregateDailyBuckets,
  aggregateHourlyBuckets,
  parseBucketDate,
  resolveDayRange,
  resolveDeviceDayRange,
  resolveRecentRange,
  subtractManualFromBuckets,
  type RawStepBucket,
} from './stepMath';
import { dateKey, ksaDateKey, ksaHour, startOfDay } from '@/lib/walking/ledger';

const APPLE_HEALTH_CONSENT_KEY = 'walking_apple_health_consent_v1';
const STEP_COUNT_IDENTIFIER = 'HKQuantityTypeIdentifierStepCount';
/**
 * القيمة النصية الفعلية (raw key) للثابت `HKMetadataKeyWasUserEntered` داخل HealthKit هي `HKWasUserEntered`.
 * استخدام اسم الثابت نفسه كنص لا يطابق أي عينة، فيمر الإدخال اليدوي دون فلترة.
 */
export const USER_ENTERED_METADATA_KEY = 'HKWasUserEntered';
const LEGACY_USER_ENTERED_KEY_NAME = 'HKMetadataKeyWasUserEntered';
const APPLE_HEALTH_APP_BUNDLE_ID = 'com.apple.Health';
/** Raw value of `NSComparisonPredicate.Operator.notEqualTo`. */
const NOT_EQUAL_TO_OPERATOR = 5;
const DAY_MS = 24 * 60 * 60 * 1000;

export interface HealthKitMetadataPredicate {
  withMetadataKey: string;
  operatorType?: number;
  value?: string | number | boolean | Date;
}

export interface HealthKitSampleFilterBase {
  date?: {
    startDate?: Date;
    endDate?: Date;
    strictStartDate?: boolean;
    strictEndDate?: boolean;
  };
  metadata?: HealthKitMetadataPredicate;
}

export interface HealthKitSampleFilter extends HealthKitSampleFilterBase {
  NOT?: HealthKitSampleFilterBase[];
  OR?: HealthKitSampleFilterBase[];
}

export interface HealthKitStatistic {
  startDate?: Date | string;
  endDate?: Date | string;
  sumQuantity?: {
    quantity?: number;
    unit?: string;
  };
  sources?: readonly {
    name?: string;
    bundleIdentifier?: string;
  }[];
}

export interface HealthKitQuantitySample {
  startDate?: Date | string;
  endDate?: Date | string;
  quantity?: number;
  metadata?: Record<string, unknown>;
  device?: unknown;
  sourceRevision?: {
    source?: {
      name?: string;
      bundleIdentifier?: string;
    };
  };
}

export interface HealthKitNativeModule {
  isHealthDataAvailable?(): boolean | Promise<boolean>;
  isHealthDataAvailableAsync?(): Promise<boolean>;
  requestAuthorization(types: {
    toRead?: readonly string[];
    toShare?: readonly string[];
  }): Promise<boolean>;
  queryStatisticsForQuantity?(
    identifier: string,
    statistics: readonly string[],
    options?: {
      filter?: HealthKitSampleFilter;
      unit?: string;
    }
  ): Promise<HealthKitStatistic>;
  queryStatisticsCollectionForQuantity(
    identifier: string,
    statistics: readonly string[],
    anchorDate: Date,
    intervalComponents: { hour?: number; day?: number },
    options?: {
      filter?: HealthKitSampleFilter;
      unit?: string;
    }
  ): Promise<readonly HealthKitStatistic[]>;
  queryQuantitySamples?(
    identifier: string,
    options: {
      filter?: HealthKitSampleFilter;
      limit: number;
      unit?: string;
    }
  ): Promise<readonly HealthKitQuantitySample[]>;
}

export interface AppleHealthAdapterDeps {
  loadModule: () => HealthKitNativeModule | null;
  consentStore: HealthConsentStore;
}

function defaultLoadModule(): HealthKitNativeModule | null {
  try {
    const loaded = require('@kingstinct/react-native-healthkit');
    return (loaded?.requestAuthorization ? loaded : (loaded?.default ?? loaded)) as HealthKitNativeModule;
  } catch {
    return null;
  }
}

const unavailableState: HealthPermissionState = {
  status: 'unavailable',
  canAskAgain: false,
  requiresSettings: false,
};

async function checkAvailability(module: HealthKitNativeModule): Promise<boolean> {
  try {
    if (typeof module.isHealthDataAvailableAsync === 'function') {
      return Boolean(await module.isHealthDataAvailableAsync());
    }
    if (typeof module.isHealthDataAvailable === 'function') {
      return Boolean(await Promise.resolve(module.isHealthDataAvailable()));
    }
    return true;
  } catch {
    return false;
  }
}

function toIsoString(value: Date | string | undefined): string {
  if (value instanceof Date) return value.toISOString();
  return String(value ?? '');
}

function isTruthyMetadataFlag(value: unknown): boolean {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function isUserEnteredSample(sample: HealthKitQuantitySample): boolean {
  return (
    isTruthyMetadataFlag(sample.metadata?.[USER_ENTERED_METADATA_KEY]) ||
    isTruthyMetadataFlag(sample.metadata?.[LEGACY_USER_ENTERED_KEY_NAME])
  );
}

function toRawBuckets(stats: readonly HealthKitStatistic[] | null | undefined): RawStepBucket[] {
  if (!Array.isArray(stats)) return [];
  return stats.map((item) => ({
    start: toIsoString(item.startDate),
    steps: item.sumQuantity?.quantity ?? 0,
  }));
}

function sumRawBucketsForDay(buckets: RawStepBucket[], dayKey: string): number {
  let sum = 0;
  for (const bucket of buckets) {
    const date = parseBucketDate(bucket.start);
    if (Number.isNaN(date.getTime()) || ksaDateKey(date) !== dayKey) continue;
    const numeric = Number(bucket.steps);
    if (Number.isFinite(numeric) && numeric > 0) {
      sum += numeric;
    }
  }
  return sum;
}

async function readOverallTotal(
  module: HealthKitNativeModule,
  filter: HealthKitSampleFilter
): Promise<number | undefined> {
  if (typeof module.queryStatisticsForQuantity !== 'function') return undefined;
  try {
    const overall = await module.queryStatisticsForQuantity(
      STEP_COUNT_IDENTIFIER,
      ['cumulativeSum'],
      { filter, unit: 'count' }
    );
    const quantity = overall?.sumQuantity?.quantity;
    return quantity != null && Number.isFinite(quantity) ? Math.max(0, Math.round(quantity)) : undefined;
  } catch {
    return undefined;
  }
}

/**
 * قراءة العينات اليدوية لليوم مباشرة عبر `queryQuantitySamples` بدون الاعتماد على فلتر metadata في محرك SQLite،
 * وفحص `HKWasUserEntered` ومعرّف مصدر `com.apple.Health` برمجياً في JS لتوزيعها على ساعات يوم السعودية.
 */
async function readManualSteps(
  module: HealthKitNativeModule,
  start: Date,
  end: Date,
  dayKey: string
): Promise<{ total: number; hourly: Record<number, number>; inspectedSamples: boolean }> {
  const empty = { total: 0, hourly: {}, inspectedSamples: false };
  if (typeof module.queryQuantitySamples !== 'function') return empty;

  const fullDayEnd = new Date(Math.max(end.getTime(), start.getTime() + DAY_MS - 1000));
  try {
    const samples = await module.queryQuantitySamples(STEP_COUNT_IDENTIFIER, {
      filter: {
        date: {
          startDate: start,
          endDate: fullDayEnd,
        },
      },
      limit: 0,
      unit: 'count',
    });
    if (!Array.isArray(samples)) return empty;

    let total = 0;
    const hourly: Record<number, number> = {};
    for (const sample of samples) {
      if (!isUserEnteredSample(sample)) continue;
      const numeric = Number(sample.quantity);
      if (!Number.isFinite(numeric) || numeric <= 0) continue;
      const startedAt = parseBucketDate(toIsoString(sample.startDate));
      if (Number.isNaN(startedAt.getTime())) {
        total += numeric;
        continue;
      }
      if (ksaDateKey(startedAt) !== dayKey) continue;
      total += numeric;
      const hour = ksaHour(startedAt);
      hourly[hour] = (hourly[hour] ?? 0) + numeric;
    }
    return { total: Math.round(total), hourly, inspectedSamples: true };
  } catch {
    return empty;
  }
}

async function readPersonalManualSteps(
  module: HealthKitNativeModule,
  start: Date,
  end: Date,
): Promise<Record<string, HealthDaySteps> | null> {
  if (!module.queryQuantitySamples) return null;

  // Query the metadata keys for the whole range without loading every recorded step sample.
  const samples = await module.queryQuantitySamples(STEP_COUNT_IDENTIFIER, {
    filter: {
      date: { startDate: start, endDate: end, strictStartDate: true, strictEndDate: true },
      OR: [USER_ENTERED_METADATA_KEY, LEGACY_USER_ENTERED_KEY_NAME].map((withMetadataKey) => ({
        metadata: { withMetadataKey },
      })),
    },
    limit: 0,
    unit: 'count',
  });
  const days: Record<string, HealthDaySteps> = {};
  for (const sample of samples) {
    const steps = sample.quantity;
    if (!isUserEnteredSample(sample) || steps == null || !Number.isFinite(steps) || steps <= 0) continue;
    const at = parseBucketDate(toIsoString(sample.startDate));
    if (!(at >= start && at < end)) continue;

    const key = dateKey(at);
    const day = days[key] ?? (days[key] = { total: 0, hourly: {} });
    const hour = at.getHours();
    day.total += steps;
    day.hourly[hour] = (day.hourly[hour] ?? 0) + steps;
  }
  return days;
}

interface AppleHealthProvider extends WalkingHealthProvider {
  readPersonalDay(day: Date): Promise<HealthDaySteps | null>;
  readPersonalDays(firstDay: Date, lastDay: Date): Promise<Record<string, number> | null>;
}

export function createAppleHealthAdapter(deps: AppleHealthAdapterDeps): AppleHealthProvider {
  const { loadModule, consentStore } = deps;

  return {
    source: 'apple_health',

    async isAvailable() {
      const module = loadModule();
      if (!module) return false;
      return checkAvailability(module);
    },

    async getPermissionState() {
      const module = loadModule();
      if (!module) return unavailableState;

      const available = await checkAvailability(module);
      if (!available) return unavailableState;

      const consent = await consentStore.get();
      if (consent === 'granted') {
        return { status: 'granted', canAskAgain: false, requiresSettings: false };
      }
      if (consent === 'denied') {
        return { status: 'denied', canAskAgain: false, requiresSettings: true };
      }
      return { status: 'undetermined', canAskAgain: true, requiresSettings: false };
    },

    async requestPermissions() {
      const module = loadModule();
      if (!module) return unavailableState;

      const available = await checkAvailability(module);
      if (!available) return unavailableState;

      try {
        const granted = await module.requestAuthorization({
          toRead: [STEP_COUNT_IDENTIFIER],
          toShare: [],
        });

        if (!granted) {
          await consentStore.set('denied');
          return { status: 'denied', canAskAgain: false, requiresSettings: true };
        }

        await consentStore.set('granted');
        return { status: 'granted', canAskAgain: false, requiresSettings: false };
      } catch {
        await consentStore.set('denied');
        return { status: 'denied', canAskAgain: false, requiresSettings: true };
      }
    },

    async readDay(day: Date): Promise<HealthDaySteps | null> {
      const module = loadModule();
      if (!module) return null;

      // الاعتماد دائماً على نافذة يوم السعودية (00:00 إلى 23:59 بتوقيت الرياض) حتى لا تتصفر الخطوات
      // للمستخدمين في دول تسبق السعودية (مثل الصين UTC+8) قبل منتصف الليل بتوقيت السعودية.
      const { start, end, dayKey } = resolveDayRange(day, new Date());
      const dateFilter = {
        startDate: start,
        endDate: end,
        strictStartDate: true,
        strictEndDate: true,
      };

      try {
        const overallTotal = await readOverallTotal(module, { date: dateFilter });

        const stats = await module.queryStatisticsCollectionForQuantity(
          STEP_COUNT_IDENTIFIER,
          ['cumulativeSum'],
          start,
          { hour: 1 },
          {
            filter: { date: dateFilter },
            unit: 'count',
          }
        );

        let rawBuckets = toRawBuckets(stats);
        let authoritativeTotal = overallTotal;

        const manual = await readManualSteps(module, start, end, dayKey);
        if (manual.inspectedSamples && manual.total > 0) {
          rawBuckets = subtractManualFromBuckets(
            rawBuckets,
            manual.hourly,
            manual.total,
            dayKey
          );
          if (authoritativeTotal != null) {
            authoritativeTotal = Math.max(0, authoritativeTotal - manual.total);
          }
        }

        if (!Array.isArray(stats) && rawBuckets.length === 0) {
          if (authoritativeTotal != null) {
            return { total: authoritativeTotal, hourly: {} };
          }
          return null;
        }

        return aggregateHourlyBuckets(
          rawBuckets,
          dayKey,
          authoritativeTotal
        );
      } catch {
        return null;
      }
    },

    async readPersonalDay(day) {
      const module = loadModule();
      const { start, end } = resolveDeviceDayRange(day);
      if (!module || start >= end) return null;

      const filter = { date: { startDate: start, endDate: end, strictStartDate: true, strictEndDate: true } };
      const [total, stats, manualDays] = await Promise.all([
        readOverallTotal(module, filter),
        module.queryStatisticsCollectionForQuantity(
          STEP_COUNT_IDENTIFIER, ['cumulativeSum'], start, { hour: 1 }, { filter, unit: 'count' }
        ).catch(() => null),
        readPersonalManualSteps(module, start, end).catch(() => null),
      ]);
      if (!manualDays) return null;

      const key = dateKey(day);
      const measured = Array.isArray(stats) ? stats.filter((item) => {
        const time = parseBucketDate(toIsoString(item.startDate));
        return time >= start && time < end && Number.isFinite(item.sumQuantity?.quantity);
      }) : [];
      if (total == null && measured.length === 0) {
        if (!stats || key !== dateKey(new Date())) return null;

        // A readable prior day distinguishes a fresh local day from unavailable history.
        const yesterday = new Date(day.getFullYear(), day.getMonth(), day.getDate() - 1);
        const priorTotal = await readOverallTotal(module, {
          date: { startDate: yesterday, endDate: start, strictStartDate: true, strictEndDate: true },
        });
        return priorTotal == null ? null : { total: 0, hourly: {} };
      }

      const manual = manualDays[key];
      const raw = toRawBuckets(measured);
      const buckets = manual ? subtractManualFromBuckets(raw, manual.hourly, manual.total, key, true) : raw;
      const { total: hourlyTotal, hourly } = aggregateHourlyBuckets(buckets, key, undefined, true);
      return { total: total == null ? hourlyTotal : Math.max(0, total - Math.round(manual?.total ?? 0)), hourly };
    },

    async readPersonalDays(firstDay, lastDay) {
      const module = loadModule();
      const start = startOfDay(firstDay);
      const { end } = resolveDeviceDayRange(lastDay);
      if (!module || start >= end) return null;

      try {
        const [stats, manualDays] = await Promise.all([
          module.queryStatisticsCollectionForQuantity(
            STEP_COUNT_IDENTIFIER, ['cumulativeSum'], start, { day: 1 }, {
              filter: { date: { startDate: start, endDate: end, strictStartDate: true, strictEndDate: true } },
              unit: 'count',
            }
          ),
          readPersonalManualSteps(module, start, end),
        ]);
        if (!manualDays) return null;

        const daily: Record<string, number> = {};
        for (const item of stats) {
          const steps = item.sumQuantity?.quantity;
          const date = parseBucketDate(toIsoString(item.startDate));
          if (steps != null && Number.isFinite(steps) && date >= start && date < end) {
            const key = dateKey(date);
            daily[key] = Math.max(0, Math.round(steps - (manualDays[key]?.total ?? 0)));
          }
        }
        return daily;
      } catch {
        return null;
      }
    },

    async readRecentDays(days: number): Promise<Record<string, number> | null> {
      const module = loadModule();
      if (!module) return null;

      const { start, end } = resolveRecentRange(days);
      try {
        const stats = await module.queryStatisticsCollectionForQuantity(
          STEP_COUNT_IDENTIFIER,
          ['cumulativeSum'],
          start,
          { day: 1 },
          {
            filter: {
              date: {
                startDate: start,
                endDate: end,
              },
            },
            unit: 'count',
          }
        );

        if (!Array.isArray(stats)) return null;

        const rawBuckets: RawStepBucket[] = stats.map((item) => ({
          start: item.startDate instanceof Date ? item.startDate.toISOString() : String(item.startDate ?? ''),
          steps: item.sumQuantity?.quantity ?? 0,
        }));

        return aggregateDailyBuckets(rawBuckets);
      } catch {
        return null;
      }
    },

    async openSettings() {
      try {
        await Linking.openURL('x-apple-health://');
      } catch {
        await Linking.openSettings().catch(() => {});
      }
    },
  };
}

export const appleHealthAdapter = createAppleHealthAdapter({
  loadModule: defaultLoadModule,
  consentStore: createSecureConsentStore(APPLE_HEALTH_CONSENT_KEY),
});
