import { api } from './alsorayiaClient';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

export interface AppVersionCheckResult {
  action: 'FORCE_UPDATE' | 'OPTIONAL_UPDATE' | 'UP_TO_DATE' | 'MAINTENANCE';
  is_mandatory: boolean;
  client_version: string;
  latest_version: string;
  min_supported_version?: string;
  store_url?: string;
  title?: string;
  release_notes?: string;
  message?: string;
}

export interface AppBootstrapSettings {
  app_name: string;
  site_name: string;
  tagline?: string;
  support_email?: string;
  support_phone?: string;
  support_whatsapp?: string;
  privacy_policy_url: string;
  terms_of_service_url: string;
  about_us_url: string;
  pages_status: {
    about: boolean;
    privacy: boolean;
    terms: boolean;
  };
  maintenance: {
    is_active: boolean;
    message?: string;
  };
}

/**
 * الحصول على رقم إصدار التطبيق الحالي من Expo Config
 */
export function getCurrentAppVersion(): string {
  return Constants.expoConfig?.version || '1.0.0';
}

/**
 * فحص حالة التحديث للتطبيق من خادم العائلة
 */
export async function checkAppVersionApi(): Promise<AppVersionCheckResult | null> {
  try {
    const currentVersion = getCurrentAppVersion();
    const platform = Platform.OS === 'ios' ? 'ios' : Platform.OS === 'android' ? 'android' : 'web';

    const response = (await api.post('/v1/app/check-version', {
      platform,
      current_version: currentVersion,
    })) as any;

    if (response && response.success && response.data) {
      return response.data as AppVersionCheckResult;
    }

    return null;
  } catch (error) {
    console.warn('[AppVersionApi] Failed to check app version:', error);
    return null;
  }
}

/**
 * جلب الإعدادات العامة والروابط القانونية من السيرفر
 */
export async function fetchAppBootstrapSettingsApi(): Promise<AppBootstrapSettings | null> {
  try {
    const response = (await api.get('/v1/app/settings')) as any;
    if (response && response.success && response.data) {
      return response.data as AppBootstrapSettings;
    }
    return null;
  } catch (error) {
    console.warn('[AppVersionApi] Failed to fetch bootstrap settings:', error);
    return null;
  }
}
