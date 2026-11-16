import * as FileSystemLegacy from 'expo-file-system/legacy';
import * as SecureStore from 'expo-secure-store';

import { appStorage } from '../appStorage';

const mockFileSystem = FileSystemLegacy as typeof FileSystemLegacy & {
  __resetMockFs?: () => void;
};

describe('appStorage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    appStorage._resetCacheForTests();
    mockFileSystem.__resetMockFs?.();
  });

  it('stores and retrieves string values via FileSystem and caches reads in memory', async () => {
    const saved = await appStorage.setItem('test_key', 'hello_world');
    expect(saved).toBe(true);
    expect(FileSystemLegacy.writeAsStringAsync).toHaveBeenCalledTimes(1);

    const firstRead = await appStorage.getItem('test_key');
    expect(firstRead).toBe('hello_world');
    // Served from memoryCache without reading disk
    expect(FileSystemLegacy.readAsStringAsync).not.toHaveBeenCalled();

    // Simulate cold restart by clearing only the memory cache
    appStorage._resetCacheForTests();
    const secondRead = await appStorage.getItem('test_key');
    expect(secondRead).toBe('hello_world');
    expect(FileSystemLegacy.readAsStringAsync).toHaveBeenCalledTimes(1);
  });

  it('handles large payloads well beyond SecureStore 2KB limit', async () => {
    const largePayload = Array.from({ length: 500 }, (_, i) => ({
      id: `notif_${i}`,
      text: 'x'.repeat(20),
    }));

    const ok = await appStorage.setJson('large_payload_key', largePayload);
    expect(ok).toBe(true);

    appStorage._resetCacheForTests();
    const restored = await appStorage.getJson<typeof largePayload>('large_payload_key', []);
    expect(restored).toHaveLength(500);
    expect(restored[499].id).toBe('notif_499');
  });

  it('automatically migrates legacy SecureStore entries to FileSystem and deletes them from SecureStore', async () => {
    const legacyJson = JSON.stringify({ daily: { '2026-11-15': 8400 } });
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValueOnce(legacyJson);

    const value = await appStorage.getItem('walking_step_ledger_v2');

    expect(value).toBe(legacyJson);
    expect(SecureStore.getItemAsync).toHaveBeenCalledWith('walking_step_ledger_v2');
    expect(FileSystemLegacy.writeAsStringAsync).toHaveBeenCalledWith(
      'file:///mock-documents/app_storage/store_walking_step_ledger_v2.dat',
      legacyJson
    );
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith('walking_step_ledger_v2');

    // Next cold read comes directly from disk without touching SecureStore again
    appStorage._resetCacheForTests();
    const afterMigration = await appStorage.getItem('walking_step_ledger_v2');
    expect(afterMigration).toBe(legacyJson);
    expect(SecureStore.getItemAsync).toHaveBeenCalledTimes(1);
  });

  it('returns fallback on missing or malformed JSON in getJson', async () => {
    expect(await appStorage.getJson('missing_key', { ok: false })).toEqual({ ok: false });

    await appStorage.setItem('bad_json', '{not valid json');
    appStorage._resetCacheForTests();

    expect(await appStorage.getJson('bad_json', ['fallback'])).toEqual(['fallback']);
  });

  it('removes item from memory, FileSystem, and SecureStore', async () => {
    await appStorage.setItem('to_delete', 'value');
    const removed = await appStorage.removeItem('to_delete');

    expect(removed).toBe(true);
    expect(FileSystemLegacy.deleteAsync).toHaveBeenCalledTimes(1);
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith('to_delete');
    expect(await appStorage.getItem('to_delete')).toBeNull();
  });
});
