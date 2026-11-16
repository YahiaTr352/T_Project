import { Platform } from 'react-native';
import type { WalkingSource } from '@/lib/api/walkingApi';
import { appleHealthAdapter } from './appleHealthAdapter';
import { healthConnectAdapter } from './healthConnectAdapter';
import type { WalkingHealthProvider } from './types';

export * from './types';
export { createAppleHealthAdapter } from './appleHealthAdapter';
export { createHealthConnectAdapter } from './healthConnectAdapter';
export {
  aggregateDailyBuckets,
  aggregateHourlyBuckets,
  resolveDayRange,
  resolveRecentRange,
  sanitizeSteps,
} from './stepMath';

export function getHealthProvider(): WalkingHealthProvider | null {
  if (Platform.OS === 'ios') return appleHealthAdapter;
  if (Platform.OS === 'android') return healthConnectAdapter;
  return null;
}

export function getDeviceWalkingSource(): WalkingSource {
  if (Platform.OS === 'ios' || Platform.OS === 'android') return 'device';
  return 'manual';
}

export function getWalkingSourceLabel(source: WalkingSource | null | undefined): string | null {
  switch (source) {
    case 'apple_health':
      return 'صحتي';
    case 'health_connect':
      return 'Health Connect';
    case 'device':
      return 'حساس الجهاز';
    case 'google_fit':
      return 'Google Fit';
    case 'manual':
      return 'يدوي';
    default:
      return null;
  }
}
