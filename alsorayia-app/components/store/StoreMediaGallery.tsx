import { ThemedText } from '@/components/themed-text';
import type { StoreMediaItem } from '@/lib/api/store';
import { Image } from 'expo-image';
import * as Haptics from 'expo-haptics';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  FlatList,
  Modal,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Pressable,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
  useWindowDimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { IMAGE_PLACEHOLDER_BLURHASH, STORE_ACCENT } from './constants';

const { width: SCREEN_W } = Dimensions.get('window');
const HERO_H = 260;
const THUMB_W = 72;
const THUMB_H = 72;

type ImageSlide = {
  kind: 'image';
  key: string;
  uri: string;
};

type PlayableSlide = {
  kind: 'youtube' | 'video';
  key: string;
  url: string;
  thumb: string | null;
};

type GallerySlide = ImageSlide | PlayableSlide;

function buildSlides(
  mainImageUrl: string | null | undefined,
  media: StoreMediaItem[]
): GallerySlide[] {
  const slides: GallerySlide[] = [];
  const seenImages = new Set<string>();

  const main = mainImageUrl?.trim();
  if (main) {
    slides.push({ kind: 'image', key: 'main', uri: main });
    seenImages.add(main);
  }

  for (const item of media) {
    const url = item.url?.trim();
    if (!url) continue;

    if (item.media_type === 'image') {
      if (!seenImages.has(url)) {
        slides.push({ kind: 'image', key: `img-${item.id}`, uri: url });
        seenImages.add(url);
      }
      continue;
    }

    if (item.media_type === 'youtube' || item.media_type === 'video') {
      slides.push({
        kind: item.media_type,
        key: `${item.media_type}-${item.id}`,
        url,
        thumb: item.thumbnail_url?.trim() || null,
      });
    }
  }

  return slides;
}

function openExternalMedia(url: string) {
  WebBrowser.openBrowserAsync(url).catch(() => {});
}

type Props = {
  media: StoreMediaItem[];
  mainImageUrl?: string | null;
};

function HeroImage({ uri }: { uri: string }) {
  const [loading, setLoading] = useState(true);
  return (
    <View style={styles.heroSlideInner}>
      <Image
        source={{ uri }}
        style={StyleSheet.absoluteFill}
        contentFit="cover"
        transition={200}
        cachePolicy="memory-disk"
        placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
        onLoadStart={() => setLoading(true)}
        onLoadEnd={() => setLoading(false)}
        onError={() => setLoading(false)}
      />
      {loading ? (
        <View style={styles.heroSpinner} pointerEvents="none">
          <ActivityIndicator color="#FFFFFF" />
        </View>
      ) : null}
      <View style={styles.zoomHint} pointerEvents="none">
        <Ionicons name="expand" size={18} color="#FFFFFF" />
      </View>
    </View>
  );
}

function ViewerImage({ uri }: { uri: string }) {
  const [loading, setLoading] = useState(true);
  return (
    <View style={styles.viewerImageWrap}>
      <Image
        source={{ uri }}
        style={StyleSheet.absoluteFill}
        contentFit="contain"
        transition={200}
        cachePolicy="memory-disk"
        onLoadStart={() => setLoading(true)}
        onLoadEnd={() => setLoading(false)}
        onError={() => setLoading(false)}
      />
      {loading ? (
        <ActivityIndicator color="#FFFFFF" size="large" />
      ) : null}
    </View>
  );
}

export function StoreMediaGallery({ media, mainImageUrl }: Props) {
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const heroW = width - 40;

  const slides = useMemo(
    () => buildSlides(mainImageUrl, media),
    [mainImageUrl, media]
  );

  const imageSlides = useMemo(
    () => slides.filter((s): s is ImageSlide => s.kind === 'image'),
    [slides]
  );

  const [heroIndex, setHeroIndex] = useState(0);
  const [viewerIndex, setViewerIndex] = useState<number | null>(null);
  const heroRef = useRef<FlatList<GallerySlide>>(null);
  const viewerRef = useRef<FlatList<ImageSlide>>(null);

  const openPlayable = useCallback((slide: PlayableSlide) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    openExternalMedia(slide.url);
  }, []);

  const openImageViewer = useCallback(
    (uri: string) => {
      const idx = imageSlides.findIndex((s) => s.uri === uri);
      if (idx < 0) return;
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
      setViewerIndex(idx);
      requestAnimationFrame(() => {
        viewerRef.current?.scrollToOffset({ offset: idx * SCREEN_W, animated: false });
      });
    },
    [imageSlides]
  );

  const onHeroPress = useCallback(
    (slide: GallerySlide) => {
      if (slide.kind === 'image') {
        openImageViewer(slide.uri);
        return;
      }
      openPlayable(slide);
    },
    [openImageViewer, openPlayable]
  );

  const onHeroScrollEnd = useCallback(
    (e: NativeSyntheticEvent<NativeScrollEvent>) => {
      const pageW = e.nativeEvent.layoutMeasurement.width || heroW;
      const idx = Math.round(e.nativeEvent.contentOffset.x / pageW);
      if (idx >= 0 && idx < slides.length) setHeroIndex(idx);
    },
    [heroW, slides.length]
  );

  const onViewerScrollEnd = useCallback(
    (e: NativeSyntheticEvent<NativeScrollEvent>) => {
      const idx = Math.round(e.nativeEvent.contentOffset.x / SCREEN_W);
      if (idx >= 0 && idx < imageSlides.length) setViewerIndex(idx);
    },
    [imageSlides.length]
  );

  const scrollHeroTo = useCallback(
    (index: number) => {
      if (index < 0 || index >= slides.length) return;
      setHeroIndex(index);
      heroRef.current?.scrollToOffset({ offset: index * heroW, animated: true });
    },
    [heroW, slides.length]
  );

  const onThumbPress = useCallback(
    (item: GallerySlide, index: number) => {
      scrollHeroTo(index);
      if (item.kind === 'image') {
        openImageViewer(item.uri);
      } else {
        openPlayable(item);
      }
    },
    [scrollHeroTo, openImageViewer, openPlayable]
  );

  if (slides.length === 0) return null;

  return (
    <View style={styles.wrap}>
      <FlatList
        ref={heroRef}
        data={slides}
        keyExtractor={(item) => item.key}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        decelerationRate="fast"
        onMomentumScrollEnd={onHeroScrollEnd}
        getItemLayout={(_, index) => ({
          length: heroW,
          offset: heroW * index,
          index,
        })}
        renderItem={({ item }) => (
          <Pressable
            onPress={() => onHeroPress(item)}
            style={[styles.heroSlide, { width: heroW }]}
            accessibilityRole="button"
            accessibilityLabel={
              item.kind === 'image' ? 'عرض الصورة بالحجم الكامل' : 'تشغيل الفيديو'
            }>
            {item.kind === 'image' ? (
              <HeroImage uri={item.uri} />
            ) : (
              <View style={styles.heroSlideInner}>
                {item.thumb ? (
                  <Image
                    source={{ uri: item.thumb }}
                    style={StyleSheet.absoluteFill}
                    contentFit="cover"
                    cachePolicy="memory-disk"
                    placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
                  />
                ) : (
                  <View style={[styles.heroSlideInner, styles.videoPlaceholder]}>
                    <Ionicons
                      name={item.kind === 'youtube' ? 'logo-youtube' : 'videocam'}
                      size={40}
                      color={STORE_ACCENT}
                    />
                  </View>
                )}
                <View style={styles.playOverlay}>
                  <View style={styles.playCircle}>
                    <Ionicons
                      name={item.kind === 'youtube' ? 'logo-youtube' : 'play'}
                      size={28}
                      color="#FFFFFF"
                    />
                  </View>
                  <ThemedText style={styles.playLabel}>
                    {item.kind === 'youtube' ? 'مشاهدة على يوتيوب' : 'تشغيل الفيديو'}
                  </ThemedText>
                </View>
              </View>
            )}
          </Pressable>
        )}
      />

      {slides.length > 1 ? (
        <View style={styles.dotsRow}>
          {slides.map((s, i) => (
            <View
              key={s.key}
              style={[styles.dot, i === heroIndex && styles.dotActive]}
            />
          ))}
        </View>
      ) : null}

      {slides.length > 1 ? (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.thumbsScroll}>
          {slides.map((item, index) => {
            const thumb = item.kind === 'image' ? item.uri : item.thumb;
            const isActive = index === heroIndex;
            const isPlayable = item.kind !== 'image';

            return (
              <Pressable
                key={item.key}
                onPress={() => onThumbPress(item, index)}
                style={[styles.thumbWrap, isActive && styles.thumbWrapActive]}>
                {thumb ? (
                  <Image
                    source={{ uri: thumb }}
                    style={styles.thumb}
                    contentFit="cover"
                    cachePolicy="memory-disk"
                  />
                ) : (
                  <View style={[styles.thumb, styles.thumbPlaceholder]}>
                    <Ionicons name="image" size={20} color={STORE_ACCENT} />
                  </View>
                )}
                {isPlayable ? (
                  <View style={styles.thumbPlay}>
                    <Ionicons name="play" size={14} color="#FFFFFF" />
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </ScrollView>
      ) : null}

      <Modal
        visible={viewerIndex !== null && imageSlides.length > 0}
        transparent
        animationType="fade"
        onRequestClose={() => setViewerIndex(null)}>
        {viewerIndex !== null && imageSlides.length > 0 ? (
          <View style={[styles.viewerRoot, { paddingTop: insets.top }]}>
            <TouchableOpacity
              style={[styles.viewerClose, { top: insets.top + 8 }]}
              onPress={() => setViewerIndex(null)}
              hitSlop={12}
              accessibilityLabel="إغلاق">
              <Ionicons name="close" size={28} color="#FFFFFF" />
            </TouchableOpacity>

            {imageSlides.length > 1 ? (
              <View style={[styles.viewerCounter, { top: insets.top + 12 }]}>
                <ThemedText style={styles.viewerCounterText}>
                  {viewerIndex + 1} / {imageSlides.length}
                </ThemedText>
              </View>
            ) : null}

            <FlatList
              ref={viewerRef}
              data={imageSlides}
              keyExtractor={(item) => item.key}
              horizontal
              pagingEnabled
              showsHorizontalScrollIndicator={false}
              decelerationRate="fast"
              initialScrollIndex={Math.min(viewerIndex, imageSlides.length - 1)}
              getItemLayout={(_, index) => ({
                length: SCREEN_W,
                offset: SCREEN_W * index,
                index,
              })}
              onMomentumScrollEnd={onViewerScrollEnd}
              onScrollToIndexFailed={(info) => {
                viewerRef.current?.scrollToOffset({
                  offset: info.index * SCREEN_W,
                  animated: false,
                });
              }}
              renderItem={({ item }) => (
                <View style={[styles.viewerPage, { width: SCREEN_W }]}>
                  <ViewerImage uri={item.uri} />
                </View>
              )}
            />
          </View>
        ) : null}
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 10,
  },
  heroSlide: {
    height: HERO_H,
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: '#E2E8F0',
  },
  heroSlideInner: {
    flex: 1,
    backgroundColor: '#1E293B',
  },
  heroSpinner: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.2)',
  },
  zoomHint: {
    position: 'absolute',
    bottom: 10,
    left: 10,
    backgroundColor: 'rgba(0,0,0,0.45)',
    borderRadius: 8,
    padding: 6,
  },
  videoPlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#0F172A',
  },
  playOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  playCircle: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: 'rgba(0,0,0,0.5)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  playLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#FFFFFF',
  },
  dotsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 6,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: 'rgba(0,0,0,0.2)',
  },
  dotActive: {
    backgroundColor: STORE_ACCENT,
    width: 18,
  },
  thumbsScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 2,
  },
  thumbWrap: {
    borderRadius: 10,
    overflow: 'hidden',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  thumbWrapActive: {
    borderColor: STORE_ACCENT,
  },
  thumb: {
    width: THUMB_W,
    height: THUMB_H,
    backgroundColor: '#E2E8F0',
  },
  thumbPlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  thumbPlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewerRoot: {
    flex: 1,
    backgroundColor: '#000',
  },
  viewerClose: {
    position: 'absolute',
    right: 16,
    zIndex: 10,
    padding: 8,
  },
  viewerCounter: {
    position: 'absolute',
    alignSelf: 'center',
    zIndex: 10,
    backgroundColor: 'rgba(0,0,0,0.5)',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  viewerCounterText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#FFFFFF',
  },
  viewerPage: {
    flex: 1,
    justifyContent: 'center',
  },
  viewerImageWrap: {
    width: '100%',
    height: '85%',
    alignItems: 'center',
    justifyContent: 'center',
  },
});
