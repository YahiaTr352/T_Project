import * as SecureStore from 'expo-secure-store';
import { Appearance, Platform } from 'react-native';

const THEME_PREF_KEY = 'app_theme_preference';

export type StoredThemePreference = 'dark' | 'light';

export async function getThemePreference(): Promise<StoredThemePreference | null> {
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage === 'undefined') return null;
      const v = localStorage.getItem(THEME_PREF_KEY);
      return v === 'dark' || v === 'light' ? v : null;
    }
    const v = await SecureStore.getItemAsync(THEME_PREF_KEY);
    return v === 'dark' || v === 'light' ? v : null;
  } catch {
    return null;
  }
}

/** يحفظ الوضع ويطبّقه فوراً على المظهر الأصلي */
export async function setThemePreference(mode: StoredThemePreference): Promise<void> {
  try {
    if (Platform.OS === 'web') {
      localStorage.setItem(THEME_PREF_KEY, mode);
    } else {
      await SecureStore.setItemAsync(THEME_PREF_KEY, mode);
    }
  } catch {
    /* يُطبَّق المظهر حتى لو فشل الحفظ */
  }
  if (mode === 'light' || mode === 'dark') {
    Appearance.setColorScheme(mode);
  }
}

/** يقرأ التخزين ويطبّقه: إذا كان `null` يتبع ثيم النظام تلقائياً دون تمرير null لـ Android */
export async function hydrateThemeFromStorage(): Promise<void> {
  const pref = await getThemePreference();
  if (pref === 'light' || pref === 'dark') {
    Appearance.setColorScheme(pref);
  }
}
