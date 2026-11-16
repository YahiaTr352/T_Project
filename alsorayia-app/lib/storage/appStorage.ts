import * as FileSystemLegacy from 'expo-file-system/legacy';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { logger } from '@/lib/utils/logger';

const STORAGE_DIR_NAME = 'app_storage';

/**
 * In-memory cache to avoid redundant disk I/O for repeated reads within a session.
 */
const memoryCache = new Map<string, string>();
let storageDirReadyPromise: Promise<string | null> | null = null;

function sanitizeStorageKey(key: string): string {
  return key.replace(/[^a-zA-Z0-9._-]/g, '_');
}

function getStorageDirectory(): string | null {
  const baseDir = FileSystemLegacy.documentDirectory;
  if (!baseDir) return null;
  const normalizedBase = baseDir.endsWith('/') ? baseDir : `${baseDir}/`;
  return `${normalizedBase}${STORAGE_DIR_NAME}/`;
}

function getFileUri(dir: string, key: string): string {
  return `${dir}store_${sanitizeStorageKey(key)}.dat`;
}

async function ensureStorageDirectory(): Promise<string | null> {
  const dir = getStorageDirectory();
  if (!dir) return null;

  if (!storageDirReadyPromise) {
    storageDirReadyPromise = (async () => {
      try {
        const info = await FileSystemLegacy.getInfoAsync(dir);
        if (!info.exists) {
          await FileSystemLegacy.makeDirectoryAsync(dir, { intermediates: true });
        }
        return dir;
      } catch (error) {
        logger.warn('[appStorage] Failed to initialize storage directory:', error);
        storageDirReadyPromise = null;
        return null;
      }
    })();
  }

  return storageDirReadyPromise;
}

/**
 * Unified, unbounded key-value storage engine for non-credential application data.
 *
 * Why this exists:
 * `expo-secure-store` enforces a hard ~2048-byte limit per entry on iOS Keychain / Android Keystore.
 * Storing growing datasets (walking ledger, AI chat history, notification IDs, offline quiz queue)
 * in SecureStore causes silent write failures.
 *
 * Behavior:
 * - Web: uses `localStorage`.
 * - iOS / Android: uses `expo-file-system/legacy` backed by an in-memory cache.
 * - Auto-Migration: transparently migrates legacy entries found in `SecureStore` on first read
 *   and removes them from `SecureStore` to reclaim secure keychain quota.
 */
export const appStorage = {
  async getItem(key: string): Promise<string | null> {
    if (memoryCache.has(key)) {
      return memoryCache.get(key) ?? null;
    }

    if (Platform.OS === 'web') {
      try {
        const value = typeof localStorage !== 'undefined' ? localStorage.getItem(key) : null;
        if (value !== null) {
          memoryCache.set(key, value);
        }
        return value;
      } catch (error) {
        logger.warn(`[appStorage] Web getItem failed for "${key}":`, error);
        return null;
      }
    }

    try {
      const dir = await ensureStorageDirectory();
      if (dir) {
        const fileUri = getFileUri(dir, key);
        const info = await FileSystemLegacy.getInfoAsync(fileUri);
        if (info.exists) {
          const content = await FileSystemLegacy.readAsStringAsync(fileUri);
          memoryCache.set(key, content);
          return content;
        }
      }

      // Transparent auto-migration from legacy SecureStore entries
      const legacyValue = await SecureStore.getItemAsync(key).catch(() => null);
      if (legacyValue !== null) {
        if (dir) {
          const fileUri = getFileUri(dir, key);
          await FileSystemLegacy.writeAsStringAsync(fileUri, legacyValue).catch((err) => {
            logger.warn(`[appStorage] Failed to write migrated key "${key}" to disk:`, err);
          });
        }
        await SecureStore.deleteItemAsync(key).catch(() => {});
        memoryCache.set(key, legacyValue);
        return legacyValue;
      }

      return null;
    } catch (error) {
      logger.warn(`[appStorage] Native getItem failed for "${key}":`, error);
      return null;
    }
  },

  async setItem(key: string, value: string): Promise<boolean> {
    memoryCache.set(key, value);

    if (Platform.OS === 'web') {
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem(key, value);
        }
        return true;
      } catch (error) {
        logger.warn(`[appStorage] Web setItem failed for "${key}":`, error);
        return false;
      }
    }

    try {
      const dir = await ensureStorageDirectory();
      if (!dir) return false;
      const fileUri = getFileUri(dir, key);
      await FileSystemLegacy.writeAsStringAsync(fileUri, value);
      return true;
    } catch (error) {
      logger.warn(`[appStorage] Native setItem failed for "${key}":`, error);
      return false;
    }
  },

  async removeItem(key: string): Promise<boolean> {
    memoryCache.delete(key);

    if (Platform.OS === 'web') {
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem(key);
        }
        return true;
      } catch (error) {
        logger.warn(`[appStorage] Web removeItem failed for "${key}":`, error);
        return false;
      }
    }

    try {
      const dir = await ensureStorageDirectory();
      if (dir) {
        const fileUri = getFileUri(dir, key);
        await FileSystemLegacy.deleteAsync(fileUri, { idempotent: true }).catch(() => {});
      }
      // Ensure any un-migrated SecureStore key is also cleared so it cannot resurrect later
      await SecureStore.deleteItemAsync(key).catch(() => {});
      return true;
    } catch (error) {
      logger.warn(`[appStorage] Native removeItem failed for "${key}":`, error);
      return false;
    }
  },

  async getJson<T>(key: string, fallback: T): Promise<T> {
    const raw = await this.getItem(key);
    if (raw === null || raw === '') {
      return fallback;
    }
    try {
      return JSON.parse(raw) as T;
    } catch (error) {
      logger.warn(`[appStorage] Failed to parse JSON for "${key}":`, error);
      return fallback;
    }
  },

  async setJson<T>(key: string, value: T): Promise<boolean> {
    try {
      const serialized = JSON.stringify(value);
      return await this.setItem(key, serialized);
    } catch (error) {
      logger.warn(`[appStorage] Failed to serialize JSON for "${key}":`, error);
      return false;
    }
  },

  /**
   * Clears in-memory cache state (primarily for unit tests).
   */
  _resetCacheForTests(): void {
    memoryCache.clear();
    storageDirReadyPromise = null;
  },
};
