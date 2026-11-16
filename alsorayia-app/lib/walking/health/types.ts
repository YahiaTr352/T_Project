import type { WalkingSource } from '@/lib/api/walkingApi';

export type HealthProviderSource = Extract<WalkingSource, 'apple_health' | 'health_connect'>;

export type HealthPermissionStatus = 'undetermined' | 'granted' | 'denied' | 'unavailable';

export interface HealthPermissionState {
  status: HealthPermissionStatus;
  canAskAgain: boolean;
  requiresSettings: boolean;
}

export interface HealthDaySteps {
  total: number;
  hourly: Record<number, number>;
}

export interface WalkingHealthProvider {
  readonly source: HealthProviderSource;
  isAvailable(): Promise<boolean>;
  getPermissionState(): Promise<HealthPermissionState>;
  requestPermissions(): Promise<HealthPermissionState>;
  readDay(day: Date): Promise<HealthDaySteps | null>;
  readRecentDays(days: number): Promise<Record<string, number> | null>;
  openSettings(): void | Promise<void>;
}

export interface HealthConsentStore {
  get(): Promise<'granted' | 'denied' | null>;
  set(value: 'granted' | 'denied'): Promise<void>;
}
