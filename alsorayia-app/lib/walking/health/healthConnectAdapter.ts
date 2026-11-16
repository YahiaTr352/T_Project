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
  resolveRecentRange,
  sanitizeSteps,
  subtractManualFromBuckets,
} from './stepMath';
import { ksaDateKey, ksaHour } from '@/lib/walking/ledger';

const HEALTH_CONNECT_CONSENT_KEY = 'walking_health_connect_consent_v1';
const SDK_AVAILABLE = 3;
/** Matches `RecordingMethod.RECORDING_METHOD_MANUAL_ENTRY` in `react-native-health-connect`. */
const RECORDING_METHOD_MANUAL_ENTRY = 3;

interface HealthConnectPermission {
  accessType: 'read' | 'write';
  recordType: string;
}

export interface HealthConnectStepRecord {
  startTime: string;
  endTime: string;
  count: number;
  metadata?: {
    id?: string;
    dataOrigin?: string;
    recordingMethod?: number;
  };
}

interface AggregateGroup {
  startTime: string;
  endTime: string;
  zoneOffset?: string;
  result?: {
    COUNT_TOTAL?: number;
    dataOrigins?: string[];
  };
}

export interface AggregateRecordResult {
  COUNT_TOTAL?: number;
  dataOrigins?: string[];
}

function toBucketStart(group: AggregateGroup): string {
  if (!group.zoneOffset) return group.startTime;
  if (/[zZ]$|[+-]\d{2}:\d{2}$/.test(group.startTime)) return group.startTime;
  return `${group.startTime}${group.zoneOffset}`;
}

export interface HealthConnectNativeModule {
  getSdkStatus(): Promise<number>;
  initialize(): Promise<boolean>;
  requestPermission(permissions: HealthConnectPermission[]): Promise<HealthConnectPermission[]>;
  getGrantedPermissions(): Promise<HealthConnectPermission[]>;
  aggregateRecord?(request: {
    recordType: 'Steps';
    timeRangeFilter: {
      operator: 'between';
      startTime: string;
      endTime: string;
    };
  }): Promise<AggregateRecordResult>;
  aggregateGroupByDuration(request: {
    recordType: 'Steps';
    timeRangeFilter: {
      operator: 'between';
      startTime: string;
      endTime: string;
    };
    timeRangeSlicer: {
      duration: 'HOURS' | 'DAYS';
      length: number;
    };
  }): Promise<AggregateGroup[]>;
  readRecords?(
    recordType: 'Steps',
    options: {
      timeRangeFilter: {
        operator: 'between';
        startTime: string;
        endTime: string;
      };
      pageSize?: number;
      pageToken?: string;
    }
  ): Promise<{ records?: HealthConnectStepRecord[]; pageToken?: string }>;
  openHealthConnectSettings(): void;
  SdkAvailabilityStatus?: {
    SDK_AVAILABLE?: number;
    SDK_UNAVAILABLE?: number;
    SDK_UNAVAILABLE_PROVIDER_UPDATE_REQUIRED?: number;
  };
}

export interface HealthConnectAdapterDeps {
  loadModule: () => HealthConnectNativeModule | null;
  consentStore: HealthConsentStore;
}

function defaultLoadModule(): HealthConnectNativeModule | null {
  try {
    return require('react-native-health-connect') as HealthConnectNativeModule;
  } catch {
    return null;
  }
}

const unavailableState: HealthPermissionState = {
  status: 'unavailable',
  canAskAgain: false,
  requiresSettings: false,
};

function hasStepsReadPermission(permissions: HealthConnectPermission[]): boolean {
  return permissions.some(
    (permission) => permission.accessType === 'read' && permission.recordType === 'Steps'
  );
}

export function createHealthConnectAdapter(
  deps: HealthConnectAdapterDeps
): WalkingHealthProvider {
  const { loadModule, consentStore } = deps;

  async function getReadyModule(): Promise<HealthConnectNativeModule | null> {
    const module = loadModule();
    if (!module) return null;

    try {
      const status = await module.getSdkStatus();
      const available = module.SdkAvailabilityStatus?.SDK_AVAILABLE ?? SDK_AVAILABLE;
      if (status !== available) return null;
      const initialized = await module.initialize();
      return initialized ? module : null;
    } catch {
      return null;
    }
  }

  async function readGroups(
    module: HealthConnectNativeModule,
    duration: 'HOURS' | 'DAYS',
    start: Date,
    end: Date
  ): Promise<AggregateGroup[] | null> {
    try {
      return await module.aggregateGroupByDuration({
        recordType: 'Steps',
        timeRangeFilter: {
          operator: 'between',
          startTime: start.toISOString(),
          endTime: end.toISOString(),
        },
        timeRangeSlicer: {
          duration,
          length: 1,
        },
      });
    } catch {
      return null;
    }
  }

  async function readManualStepsForDay(
    module: HealthConnectNativeModule,
    start: Date,
    end: Date,
    dayKey: string
  ): Promise<{ totalManual: number; hourlyManual: Record<number, number> }> {
    if (typeof module.readRecords !== 'function') {
      return { totalManual: 0, hourlyManual: {} };
    }

    let totalManual = 0;
    const hourlyManual: Record<number, number> = {};
    let pageToken: string | undefined = undefined;
    const MAX_PAGES = 5;

    try {
      for (let page = 0; page < MAX_PAGES; page += 1) {
        const res = await module.readRecords('Steps', {
          timeRangeFilter: {
            operator: 'between',
            startTime: start.toISOString(),
            endTime: end.toISOString(),
          },
          pageSize: 1000,
          ...(pageToken ? { pageToken } : {}),
        });
        const records = res?.records;
        if (!Array.isArray(records) || records.length === 0) break;

        for (const rec of records) {
          if (rec?.metadata?.recordingMethod !== RECORDING_METHOD_MANUAL_ENTRY) continue;
          const count = sanitizeSteps(rec.count);
          if (count <= 0) continue;

          const recDate = parseBucketDate(rec.startTime);
          if (!Number.isNaN(recDate.getTime())) {
            if (ksaDateKey(recDate) === dayKey) {
              totalManual += count;
              const hour = ksaHour(recDate);
              hourlyManual[hour] = (hourlyManual[hour] ?? 0) + count;
            }
          } else {
            totalManual += count;
          }
        }

        if (!res?.pageToken) break;
        pageToken = res.pageToken;
      }
    } catch {
      return { totalManual: 0, hourlyManual: {} };
    }

    return { totalManual, hourlyManual };
  }

  return {
    source: 'health_connect',

    async isAvailable() {
      return Boolean(await getReadyModule());
    },

    async getPermissionState() {
      const module = await getReadyModule();
      if (!module) return unavailableState;

      let granted: HealthConnectPermission[] = [];
      try {
        granted = await module.getGrantedPermissions();
      } catch {
        return { status: 'denied', canAskAgain: false, requiresSettings: true };
      }

      if (hasStepsReadPermission(granted)) {
        await consentStore.set('granted');
        return { status: 'granted', canAskAgain: false, requiresSettings: false };
      }

      const consent = await consentStore.get();
      if (consent === 'granted' || consent === 'denied') {
        await consentStore.set('denied');
        return { status: 'denied', canAskAgain: false, requiresSettings: true };
      }

      return { status: 'undetermined', canAskAgain: true, requiresSettings: false };
    },

    async requestPermissions() {
      const module = await getReadyModule();
      if (!module) return unavailableState;

      try {
        const result = await module.requestPermission([
          { accessType: 'read', recordType: 'Steps' },
        ]);
        if (hasStepsReadPermission(result)) {
          await consentStore.set('granted');
          return { status: 'granted', canAskAgain: false, requiresSettings: false };
        }
      } catch {
        // Fall through to the denied state below.
      }

      await consentStore.set('denied');
      return { status: 'denied', canAskAgain: false, requiresSettings: true };
    },

    async readDay(day: Date): Promise<HealthDaySteps | null> {
      const module = await getReadyModule();
      if (!module) return null;

      // استخدام نافذة اليوم الكاملة حتى 23:59:59 لضمان عدم إسقاط الساعة الحالية الجارية من duration slicer
      const { start, end, dayKey } = resolveDayRange(day, undefined, { fullDay: true });

      // 1. محاولة استعلام الإجمالي الموثوق مباشرة من Health Connect (يتضمن التصفية الموحدة للساعات ومصادر البيانات)
      let authoritativeTotal: number | null = null;
      if (typeof module.aggregateRecord === 'function') {
        try {
          const agg = await module.aggregateRecord({
            recordType: 'Steps',
            timeRangeFilter: {
              operator: 'between',
              startTime: start.toISOString(),
              endTime: end.toISOString(),
            },
          });
          if (typeof agg?.COUNT_TOTAL === 'number' && Number.isFinite(agg.COUNT_TOTAL)) {
            authoritativeTotal = Math.round(agg.COUNT_TOTAL);
          }
        } catch {
          // Fallback to hourly sum if aggregateRecord is not supported
        }
      }

      // 1.1 فحص واستبعاد أي خطوات مضافة يدوياً (RECORDING_METHOD_MANUAL_ENTRY)
      const manual = await readManualStepsForDay(module, start, end, dayKey);
      if (authoritativeTotal != null && manual.totalManual > 0) {
        authoritativeTotal = Math.max(0, authoritativeTotal - manual.totalManual);
      }

      // 2. استعلام تفاصيل الساعات على مدار اليوم
      const groups = await readGroups(module, 'HOURS', start, end);
      if (!groups) {
        if (authoritativeTotal != null) {
          return { total: authoritativeTotal, hourly: {} };
        }
        return null;
      }

      const rawBuckets = subtractManualFromBuckets(
        groups.map((group) => ({
          start: toBucketStart(group),
          steps: group.result?.COUNT_TOTAL ?? 0,
        })),
        manual.hourlyManual,
        manual.totalManual,
        dayKey
      );
      return aggregateHourlyBuckets(rawBuckets, dayKey, authoritativeTotal);
    },

    async readRecentDays(days: number) {
      const module = await getReadyModule();
      if (!module) return null;

      const { start, end } = resolveRecentRange(days);
      const groups = await readGroups(module, 'DAYS', start, end);
      if (!groups) return null;

      return aggregateDailyBuckets(
        groups.map((group) => ({
          start: toBucketStart(group),
          steps: group.result?.COUNT_TOTAL ?? 0,
        }))
      );
    },

    async openSettings() {
      const module = loadModule();
      if (module) {
        try {
          module.openHealthConnectSettings();
          return;
        } catch {
          // Fall through to Store/Settings
        }
      }

      try {
        await Linking.openURL('market://details?id=com.google.android.apps.healthdata');
      } catch {
        try {
          await Linking.openURL(
            'https://play.google.com/store/apps/details?id=com.google.android.apps.healthdata'
          );
        } catch {
          await Linking.openSettings().catch(() => {});
        }
      }
    },
  };
}

export const healthConnectAdapter = createHealthConnectAdapter({
  loadModule: defaultLoadModule,
  consentStore: createSecureConsentStore(HEALTH_CONNECT_CONSENT_KEY),
});
