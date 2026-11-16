import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { silaTheme } from '@/constants/theme';
import { useThemeColor } from '@/hooks/use-theme-color';
import type { HomeImportantLink, HomeImportantLinkMedia } from '@/lib/api/homePage';
import { fetchHomePage } from '@/lib/api/homePage';
import { resolveImportantLinkOpenUrl } from '@/lib/importantLinks';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function openUrl(url: string | null | undefined) {
  const u = url?.trim();
  if (!u) return;
  WebBrowser.openBrowserAsync(u).catch(() => { });
}

function sortedMedia(list: HomeImportantLinkMedia[]): HomeImportantLinkMedia[] {
  return [...list].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
}

export default function ImportantLinkDetailScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const borderColor = useThemeColor({}, 'border');
  const cardBackground = useThemeColor({}, 'card');
  const mutedColor = useThemeColor({}, 'icon');

  const id = useMemo(() => {
    const n = Number(idParam);
    return Number.isFinite(n) ? n : NaN;
  }, [idParam]);

  const [link, setLink] = useState<HomeImportantLink | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    if (!Number.isFinite(id)) {
      setLink(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    const home = await fetchHomePage();
    const list = home?.importantLinks ?? [];
    const found = list.find((l) => l.id === id) ?? null;
    setLink(found);
    setLoading(false);
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  const primaryUrl = link ? resolveImportantLinkOpenUrl(link) : null;
  const media = link ? sortedMedia(link.media ?? []) : [];

  const accentFill = silaTheme.primary;
  const onAccent = '#FFFFFF';

  const headerTitle = loading || !link ? 'تفاصيل' : link.title;

  return (
    <ThemedView style={styles.container}>

      <HeaderBar title={headerTitle} showBack onBackPress={() => router.back()} />


      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={accentFill} />
          <ThemedText type="default" style={styles.loadingHint} lightColor={mutedColor} darkColor={mutedColor}>
            جاري التحميل…
          </ThemedText>
        </View>
      ) : !link ? (
        <View style={styles.centered}>
          <ThemedText type="defaultSemiBold">لم يُعثر على العنصر.</ThemedText>
          <TouchableOpacity style={styles.backLink} onPress={() => router.back()} activeOpacity={0.7}>
            <ThemedText type="link" style={styles.backLinkText}>
              رجوع
            </ThemedText>
          </TouchableOpacity>
        </View>
      ) : (
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          {link.image_url ? (
            <View style={styles.heroOuter}>
              <View style={[styles.hero, { backgroundColor: borderColor, borderColor }]}>
                <Image source={{ uri: link.image_url }} style={styles.heroImage} contentFit="contain" />
              </View>
            </View>
          ) : null}

          {link.description ? (
            <ThemedText type="default" style={styles.body} lightColor={mutedColor} darkColor={mutedColor}>
              {stripHtml(link.description)}
            </ThemedText>
          ) : null}

          {media.length ? (
            <View style={styles.mediaSection}>
              <ThemedText type="subtitle" style={styles.mediaHeading}>
                وسائط إضافية
              </ThemedText>
              {media.map((m) => {
                const url = m.file_url?.trim();
                const isVideo = m.kind === 'video';
                return (
                  <View
                    key={m.id}
                    style={[styles.mediaCard, { borderColor, backgroundColor: cardBackground }]}
                  >
                    {m.title ? (
                      <ThemedText type="defaultSemiBold" style={styles.mediaTitle}>
                        {m.title}
                      </ThemedText>
                    ) : null}
                    {m.description ? (
                      <ThemedText type="default" style={styles.mediaDesc} lightColor={mutedColor} darkColor={mutedColor}>
                        {stripHtml(m.description)}
                      </ThemedText>
                    ) : null}
                    {!url ? null : isVideo ? (
                      <TouchableOpacity
                        style={[styles.videoBtn, { backgroundColor: accentFill }]}
                        onPress={() => openUrl(url)}
                        activeOpacity={0.85}
                      >
                        <Ionicons name="play-circle" size={26} color={onAccent} />
                        <Text style={styles.onAccentText}>تشغيل الفيديو</Text>
                      </TouchableOpacity>
                    ) : (
                      <TouchableOpacity activeOpacity={0.9} onPress={() => openUrl(url)}>
                        <View style={[styles.mediaImageWrap, { backgroundColor: borderColor }]}>
                          <Image source={{ uri: url }} style={styles.mediaImage} contentFit="contain" />
                        </View>
                      </TouchableOpacity>
                    )}
                  </View>
                );
              })}
            </View>
          ) : null}

          {primaryUrl ? (
            <TouchableOpacity
              style={[styles.cta, { backgroundColor: accentFill, shadowColor: silaTheme.primaryDark }]}
              onPress={() => openUrl(primaryUrl)}
              activeOpacity={0.88}
            >
              <Text style={[styles.onAccentText, styles.ctaLabel]}>
                {link.type === 'app' ? 'فتح التطبيق' : 'فتح الرابط'}
              </Text>
            </TouchableOpacity>
          ) : null}

          <View style={styles.footerSpacer} />
        </ScrollView>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 12,
  },
  loadingHint: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    marginTop: 8,
  },
  backLink: {
    marginTop: 8,
  },
  backLinkText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  scroll: { flex: 1 },
  scrollContent: { paddingBottom: 24, paddingTop: 8 },
  heroOuter: {
    marginTop: 8,
    marginHorizontal: 16,
  },
  hero: {
    width: '100%',
    height: 220,
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  heroImage: { width: '100%', height: '100%' },
  body: {
    marginTop: 16,
    paddingHorizontal: 16,
    lineHeight: 24,
    fontFamily: 'Tajawal-Regular',
  },
  mediaSection: {
    marginTop: 24,
    paddingHorizontal: 16,
    gap: 14,
  },
  mediaHeading: {
    marginBottom: 4,
    fontFamily: 'Tajawal-Bold',
  },
  mediaCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 12,
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  mediaTitle: {
    fontSize: 15,
  },
  mediaDesc: {
    fontSize: 13,
    lineHeight: 20,
    fontFamily: 'Tajawal-Regular',
  },
  mediaImageWrap: {
    width: '100%',
    height: 200,
    borderRadius: 12,
    overflow: 'hidden',
  },
  mediaImage: {
    width: '100%',
    height: '100%',
  },
  videoBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 14,
    borderRadius: 12,
    marginTop: 4,
  },
  onAccentText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  ctaLabel: {
    fontSize: 16,
  },
  cta: {
    marginHorizontal: 16,
    marginTop: 28,
    paddingVertical: 15,
    borderRadius: 14,
    alignItems: 'center',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 8,
    elevation: 4,
  },
  footerSpacer: { height: 40 },
});
