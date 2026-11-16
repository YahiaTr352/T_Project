/* eslint-disable @typescript-eslint/no-require-imports */
import type { HealthConsentStore } from './types';

export function createSecureConsentStore(key: string): HealthConsentStore {
  return {
    async get() {
      try {
        const SecureStore = require('expo-secure-store');
        const raw = await SecureStore.getItemAsync(key);
        return raw === 'granted' || raw === 'denied' ? raw : null;
      } catch {
        return null;
      }
    },
    async set(value) {
      try {
        const SecureStore = require('expo-secure-store');
        await SecureStore.setItemAsync(key, value);
      } catch {
        return;
      }
    },
  };
}
