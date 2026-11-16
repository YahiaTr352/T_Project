import Constants from 'expo-constants';
import * as Crypto from 'expo-crypto';
import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { api } from '@/lib/api/alsorayiaClient';

const DEVICE_UUID_STORAGE_KEY = 'app_device_persistent_uuid';
let cachedDeviceUuid: string | null = null;

/**
 * جلب أو توليد معرّف مشفّر وفريد ومستدام للجهاز
 * يتم حفظه في SecureStore (Keychain على iOS و KeyStore على Android)
 */
export async function getDeviceIdentifier(): Promise<string> {
  if (cachedDeviceUuid) return cachedDeviceUuid;

  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage !== 'undefined') {
        let webId = localStorage.getItem(DEVICE_UUID_STORAGE_KEY);
        if (!webId) {
          webId = Crypto.randomUUID();
          localStorage.setItem(DEVICE_UUID_STORAGE_KEY, webId);
        }
        cachedDeviceUuid = webId;
        return webId;
      }
      return Crypto.randomUUID();
    }

    let storedId = await SecureStore.getItemAsync(DEVICE_UUID_STORAGE_KEY);
    if (!storedId) {
      storedId = Crypto.randomUUID();
      await SecureStore.setItemAsync(DEVICE_UUID_STORAGE_KEY, storedId);
    }
    cachedDeviceUuid = storedId;
    return storedId;
  } catch (err) {
    console.warn('[devices] Error accessing SecureStore for device UUID:', err);
    const fallbackId = Crypto.randomUUID();
    cachedDeviceUuid = fallbackId;
    return fallbackId;
  }
}

export interface DeviceMetadata {
  device_uuid: string;
  expo_push_token?: string;
  device_name?: string;
  device_model?: string;
  os_platform: string;
  os_version?: string;
  app_version?: string;
}

/**
 * استرجاع توكن الـ Push المحفوظ محلياً لإرفاقه بطلبات المصادقة
 */
export async function getCachedPushToken(): Promise<string | null> {
  try {
    if (Platform.OS === 'web') {
      return typeof localStorage !== 'undefined' ? localStorage.getItem('expo_push_token') : null;
    }
    return await SecureStore.getItemAsync('expo_push_token');
  } catch {
    return null;
  }
}

/**
 * جمع البصمة البرمجية والفيزيائية للجهاز لربطها وتوثيقها في المصادقة البيومترية
 */
export async function getDeviceMetadata(): Promise<DeviceMetadata> {
  const deviceUuid = await getDeviceIdentifier();
  const pushToken = await getCachedPushToken();

  return {
    device_uuid: deviceUuid,
    expo_push_token: pushToken || undefined,
    device_name:
      Device.brand ||
      (Platform.OS === 'ios' ? 'Apple' : Platform.OS === 'android' ? 'Android' : 'Web Device'),
    device_model: Device.modelName || 'Device',
    os_platform: Platform.OS,
    os_version: Device.osVersion ? String(Device.osVersion) : undefined,
    app_version: Constants?.expoConfig?.version || '1.0.0',
  };
}

export type DeviceLinkedVia = 'identity' | 'phone' | 'none';

export type DeviceRegisterData = {
  device_id: number;
  linked_person_id: number | null;
  is_linked: boolean;
  linked_via: DeviceLinkedVia;
};

export type DeviceRegisterResult = {
  success: boolean;
  message?: string;
  data?: DeviceRegisterData | null;
  errors?: unknown;
};

/**
 * `POST /api/devices/register` — بدون هاتف.
 * الربط بالعضو يتم عبر Bearer هوية (وجه / واتساب) إن وُجد.
 */
export async function registerDevice(
  expoPushToken: string
): Promise<DeviceRegisterResult | null> {
  const deviceUuid = await getDeviceIdentifier();
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage !== 'undefined') localStorage.setItem('expo_push_token', expoPushToken);
    } else {
      await SecureStore.setItemAsync('expo_push_token', expoPushToken);
    }
  } catch {}

  const raw = (await api.post('/devices/register', {
    expo_push_token: expoPushToken,
    device_uuid: deviceUuid,
    device_name: Device.brand || undefined,
    device_model: Device.modelName || undefined,
    os_platform: Platform.OS,
    os_version: Device.osVersion || undefined,
    app_version: Constants?.expoConfig?.version || undefined,
  })) as unknown;

  if (!raw || typeof raw !== 'object') return null;
  return raw as DeviceRegisterResult;
}
