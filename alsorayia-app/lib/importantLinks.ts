import { Platform } from 'react-native';

import type { HomeImportantLink } from '@/lib/api/homePage';

/** يطابق سلوك الموقع: تطبيقات من المتجر حسب النظام، وإلا `url`. */
export function resolveImportantLinkOpenUrl(link: HomeImportantLink): string | null {
  if (link.type === 'app') {
    if (Platform.OS === 'ios') {
      return link.url_ios?.trim() || link.url?.trim() || link.url_android?.trim() || null;
    }
    if (Platform.OS === 'android') {
      return link.url_android?.trim() || link.url?.trim() || link.url_ios?.trim() || null;
    }
    return link.url?.trim() || link.url_ios?.trim() || link.url_android?.trim() || null;
  }
  return link.url?.trim() || link.url_ios?.trim() || link.url_android?.trim() || null;
}
