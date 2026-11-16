import { AnimatedBubbles } from '@/components/animated-bubbles';
import { GalleryFaceSearchModal } from '@/components/gallery/GalleryFaceSearchModal';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import { downloadImage, useZoomPan } from '@/components/ui/zoomable-image';
import { ListSkeleton } from '@/components/ui/skeleton';
import { useGalleryFeed } from '@/hooks/use-gallery-feed';
import type { GalleryFaceSearchResult, GalleryImageItem } from '@/lib/api/gallery';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  FlatList,
  Modal,
  PanResponder,
  Platform,
  Pressable,
  Image as RNImage,
  RefreshControl,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W, height: SCREEN_H } = Dimensions.get('window');
const H_PAD = 16;
const GAP = 10;
const CELL_W = (SCREEN_W - H_PAD * 2 - GAP) / 2;
const CELL_H = Math.round(CELL_W * 0.92);

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function thumbUri(item: GalleryImageItem): string | null {
  const t = item.thumbnail_url?.trim();
  if (t) return t;
  const y = item.youtube_thumbnail_url?.trim();
  if (y) return y;
  const i = item.image_url?.trim();
  return i || null;
}

function fullUri(item: GalleryImageItem): string | null {
  const i = item.image_url?.trim();
  if (i) return i;
  return thumbUri(item);
}

/** عناصر تُعرض في المودال (يوتيوب وPDF وفيديو يُفتحان خارجاً). */
function isPagerItem(item: GalleryImageItem): boolean {
  if (item.media_type === 'youtube' && item.youtube_url?.trim()) return false;
  if (item.media_type === 'pdf' || item.media_type === 'video') return false;
  return !!fullUri(item);
}

/** blurhash رمادي محايد — يظهر فوراً ثم تُستبدل بالصورة */
const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

function LazyGridCover({ uri }: { uri: string }) {
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    setLoading(true);
    const t = setTimeout(() => setLoading(false), 4000);
    return () => clearTimeout(t);
  }, [uri]);

  return (
    <View style={StyleSheet.absoluteFill} pointerEvents="none">
      <Image
        source={{ uri }}
        style={StyleSheet.absoluteFill}
        contentFit="cover"
        transition={220}
        cachePolicy="memory-disk"
        priority="low"
        recyclingKey={uri}
        placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
        placeholderContentFit="cover"
        pointerEvents="none"
        onLoadEnd={() => setLoading(false)}
        onDisplay={() => setLoading(false)}
        onError={() => setLoading(false)}
      />
      {loading ? (
        <View style={styles.lazySpinnerOverlay} pointerEvents="none">
          <ActivityIndicator size="small" color={silaTheme.primary} />
        </View>
      ) : null}
    </View>
  );
}

/**
 * عارض المودال يستخدم Image المدمج بأبعاد رقمية.
 * لا نستخدم expo-image هنا ولا أي transform على الأب — على أندرويد
 * scaleX السالب + inverted FlatList يرسمان Image فارغة رغم أن الرابط سليم.
 * الزوم: نقرة مزدوجة + أزرار تكبير/تصغير (يوقف سحب التنقل أثناء التكبير عبر onZoomChange).
 */
function ViewerPhoto({
  uri,
  width,
  height,
  onZoomChange,
}: {
  uri: string;
  width: number;
  height: number;
  onZoomChange?: (scale: number) => void;
}) {
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const { scale, tx, ty, applyScale, resetZoom, handleTap, panHandlers } = useZoomPan(
    width,
    height,
    onZoomChange
  );

  useEffect(() => {
    resetZoom();
    setLoading(true);
    setFailed(false);
    const t = setTimeout(() => setLoading(false), 8000);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uri]);

  if (width < 8 || height < 8) {
    return <View style={{ flex: 1, backgroundColor: '#000' }} />;
  }

  return (
    <View style={{ width, height, backgroundColor: '#000' }} {...panHandlers}>
      <Pressable onPress={handleTap} style={{ width, height, alignItems: 'center', justifyContent: 'center' }}>
        <RNImage
          source={{ uri }}
          style={{ width, height, transform: [{ translateX: tx }, { translateY: ty }, { scale }] }}
          resizeMode="contain"
          onLoad={() => setLoading(false)}
          onError={() => {
            setLoading(false);
            setFailed(true);
          }}
        />
      </Pressable>
      {loading && !failed ? (
        <View style={styles.viewerSpinner} pointerEvents="none">
          <ActivityIndicator size="large" color={silaTheme.primary} />
          <Text style={styles.viewerLoadingText}>جاري تحميل الصورة...</Text>
        </View>
      ) : null}
      {failed ? (
        <View style={styles.viewerErrorOverlay} pointerEvents="none">
          <Ionicons name="image-outline" size={40} color="rgba(255,255,255,0.7)" />
          <Text style={styles.viewerErrorTitle}>تعذر عرض هذه الصورة</Text>
        </View>
      ) : null}
      {!failed && !loading ? (
        <View style={styles.viewerZoomBar} pointerEvents="box-none">
          <View style={styles.viewerZoomHint} pointerEvents="none">
            <Text style={styles.viewerZoomHintText}>
              {scale > 1 ? `${Math.round(scale * 100)}% — اسحب للتحريك` : 'انقر مرتين للتكبير'}
            </Text>
          </View>
          <View style={styles.viewerZoomBtns}>
            <TouchableOpacity
              style={[styles.viewerZoomBtn, scale <= 1 && styles.viewerNavBtnDisabled]}
              onPress={() => applyScale(scale - 0.5)}
              disabled={scale <= 1}
              hitSlop={8}>
              <Ionicons name="remove" size={18} color="#fff" />
            </TouchableOpacity>
            {scale > 1 ? (
              <TouchableOpacity style={styles.viewerZoomBtn} onPress={resetZoom} hitSlop={8}>
                <Ionicons name="refresh" size={16} color="#fbbf24" />
              </TouchableOpacity>
            ) : null}
            <TouchableOpacity
              style={[styles.viewerZoomBtn, scale >= 4 && styles.viewerNavBtnDisabled]}
              onPress={() => applyScale(scale + 0.5)}
              disabled={scale >= 4}
              hitSlop={8}>
              <Ionicons name="add" size={18} color="#fff" />
            </TouchableOpacity>
          </View>
        </View>
      ) : null}
    </View>
  );
}

export default function GalleryScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const insets = useSafeAreaInsets();
  const { width: pageW } = useWindowDimensions();

  const statusBarH = Platform.OS === 'android' ? (StatusBar.currentHeight ?? 24) : 0;
  const safeTop = Math.max(insets.top, statusBarH, Platform.OS === 'ios' ? 44 : 24);
  const safeBottom = Math.max(insets.bottom, Platform.OS === 'android' ? 24 : 16);

  const {
    categories,
    categoriesError,
    categoryId,
    selectCategory,
    searchInput,
    setSearchInput,
    debouncedSearch,
    items,
    meta,
    loading,
    loadingMore,
    refreshing,
    error,
    categoryError,
    refresh,
    loadMore,
    retry,
    hasMore,
  } = useGalleryFeed();

  /** فهرس في `viewerItems` أو null = المودال مغلق */
  const [viewerIndex, setViewerIndex] = useState<number | null>(null);
  /** مستوى الزوم الحالي في العارض — يوقف سحب التنقل أثناء التكبير */
  const viewerZoomRef = useRef(1);
  const [viewerDownloading, setViewerDownloading] = useState(false);
  const [standaloneViewerItem, setStandaloneViewerItem] = useState<GalleryImageItem | null>(null);
  const [photoBox, setPhotoBox] = useState({ w: 0, h: 0 });
  const [faceSearchModalVisible, setFaceSearchModalVisible] = useState(false);
  const [faceSearchResult, setFaceSearchResult] = useState<GalleryFaceSearchResult | null>(null);
  const [faceSelectedCategory, setFaceSelectedCategory] = useState<number | null>(null);
  const gridListRef = useRef<FlatList<GalleryImageItem>>(null);

  const isFaceFiltered = faceSearchResult !== null && (faceSearchResult.items?.length ?? 0) > 0;

  const displayItems = useMemo(() => {
    if (!isFaceFiltered || !faceSearchResult?.items) return items;
    if (faceSelectedCategory === null) return faceSearchResult.items;
    return faceSearchResult.items.filter((item) => {
      const catId = item.category?.id ?? item.category_id;
      return Number(catId) === Number(faceSelectedCategory);
    });
  }, [isFaceFiltered, faceSearchResult, faceSelectedCategory, items]);

  const viewerItems = useMemo(
    () => displayItems.filter(isPagerItem),
    [displayItems]
  );

  const displayChips = useMemo(() => {
    if (isFaceFiltered && faceSearchResult?.categories && faceSearchResult.categories.length > 0) {
      return faceSearchResult.categories.map((c) => ({
        id: c.id,
        name: c.name,
        total_images_count: c.count,
      }));
    }
    return categories ?? [];
  }, [isFaceFiltered, faceSearchResult, categories]);

  const currentViewerItem =
    standaloneViewerItem ?? (viewerIndex != null ? viewerItems[viewerIndex] ?? null : null);
  const currentViewerUri = currentViewerItem ? fullUri(currentViewerItem) : null;

  const downloadCurrentViewerPhoto = useCallback(async () => {
    if (!currentViewerUri || viewerDownloading) return;
    setViewerDownloading(true);
    try {
      const r = await downloadImage(currentViewerUri);
      Alert.alert('تحميل الصورة', r.message);
    } finally {
      setViewerDownloading(false);
    }
  }, [currentViewerUri, viewerDownloading]);

  useEffect(() => {
    setViewerIndex(null);
  }, [categoryId, debouncedSearch]);

  /** إرجاع تمرير قائمة المعرض لأعلى عند تغيير التصنيف أو البحث */
  useEffect(() => {
    const id = requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        gridListRef.current?.scrollToOffset({ offset: 0, animated: false });
      });
    });
    return () => cancelAnimationFrame(id);
  }, [categoryId, debouncedSearch]);

  useEffect(() => {
    if (viewerIndex != null && viewerIndex >= viewerItems.length) {
      setViewerIndex(null);
    }
  }, [viewerIndex, viewerItems.length]);

  useEffect(() => {
    if (
      viewerIndex != null &&
      hasMore &&
      viewerItems.length > 0 &&
      viewerIndex >= viewerItems.length - 2
    ) {
      loadMore();
    }
  }, [viewerIndex, viewerItems.length, hasMore, loadMore]);

  const chips = useMemo(() => {
    const list = categories ?? [];
    return list;
  }, [categories]);

  const inactiveChipBg =
    colorScheme === 'dark' ? 'rgba(0,0,0,0.45)' : '#FFFFFF';
  const inactiveChipBorder =
    colorScheme === 'dark'
      ? 'rgba(255,255,255,0.14)'
      : 'rgba(55,160,92,0.35)';

  const onChipPress = useCallback(
    (id: number | null) => {
      Haptics.selectionAsync().catch(() => { });
      if (isFaceFiltered) {
        setFaceSelectedCategory(id);
      } else {
        if (id !== categoryId) {
          selectCategory(id);
        }
      }
    },
    [isFaceFiltered, categoryId, selectCategory]
  );

  const openItem = useCallback(
    (item: GalleryImageItem) => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });

      const isYoutube = item.media_type === 'youtube' && item.youtube_url?.trim();
      if (isYoutube) {
        WebBrowser.openBrowserAsync(item.youtube_url!.trim()).catch(() => { });
        return;
      }
      if (item.media_type === 'pdf' || item.media_type === 'video') {
        const uri = fullUri(item);
        if (uri) WebBrowser.openBrowserAsync(uri).catch(() => { });
        return;
      }
      const idx = viewerItems.findIndex((x) => x.id === item.id);
      if (idx >= 0) {
        setStandaloneViewerItem(null);
        setViewerIndex(idx);
      } else {
        setViewerIndex(null);
        setStandaloneViewerItem(item);
      }
    },
    [viewerItems]
  );

  const closeViewer = useCallback(() => {
    viewerZoomRef.current = 1;
    setViewerIndex(null);
    setStandaloneViewerItem(null);
  }, []);

  const stepViewer = useCallback(
    (delta: number) => {
      setViewerIndex((prev) => {
        if (prev == null) return prev;
        const next = prev + delta;
        if (next < 0 || next >= viewerItems.length) return prev;
        Haptics.selectionAsync().catch(() => { });
        return next;
      });
    },
    [viewerItems.length]
  );

  const panResponder = useMemo(
    () =>
      // eslint-disable-next-line react-hooks/refs -- callbacks تقرأ الـ refs فقط أثناء الإيماءات وليس أثناء الـ render
      PanResponder.create({
        // أثناء التكبير أو القرص بإصبعين تُعطَّل حركة التنقل بالسحب حتى لا تتعارض مع الزوم
        onMoveShouldSetPanResponder: (e, g) => {
          const touches = (e.nativeEvent as any).touches as unknown[] | undefined;
          if (touches && touches.length >= 2) return false;
          return (
            viewerZoomRef.current <= 1 &&
            Math.abs(g.dx) > 18 &&
            Math.abs(g.dx) > Math.abs(g.dy) * 1.15
          );
        },
        onPanResponderRelease: (_, g) => {
          if (viewerZoomRef.current > 1) return;
          if (g.dx > 70) stepViewer(1);
          else if (g.dx < -70) stepViewer(-1);
        },
      }),
    [stepViewer]
  );

  const listHeader = (
    <View style={styles.listHeader}>
      {isFaceFiltered && faceSearchResult ? (
        <View
          style={[
            styles.activeFaceFilterCard,
            {
              backgroundColor:
                colorScheme === 'dark' ? 'rgba(55,160,92,0.18)' : '#DCF2DD',
              borderColor:
                colorScheme === 'dark' ? 'rgba(55,160,92,0.45)' : silaTheme.primary,
            },
          ]}
        >
          <View style={styles.activeFaceFilterTopRow}>
            {faceSearchResult.identified_person?.avatar ? (
              <Image
                source={{ uri: faceSearchResult.identified_person.avatar }}
                style={styles.activeFaceFilterAvatar}
                contentFit="cover"
              />
            ) : (
              <View style={[styles.activeFaceFilterIconCircle, { backgroundColor: silaTheme.primary }]}>
                <Ionicons name="scan" size={20} color="#ffffff" />
              </View>
            )}

            <View style={styles.activeFaceFilterTextWrap}>
              <View style={styles.activeFaceFilterTitleRow}>
                <Ionicons name="sparkles" size={14} color={silaTheme.primary} />
                <Text
                  style={[
                    styles.activeFaceFilterTitle,
                    { color: colorScheme === 'dark' ? '#ffffff' : '#111827' },
                  ]}
                  numberOfLines={1}
                >
                  {faceSearchResult.identified_person
                    ? faceSearchResult.identified_person.full_name
                    : 'نتائج البحث بالوجه'}
                </Text>
                {faceSearchResult.identified_person?.similarity ? (
                  <View style={styles.activeFaceFilterSimBadge}>
                    <Text style={styles.activeFaceFilterSimText}>
                      مطابقة {faceSearchResult.identified_person.similarity}
                    </Text>
                  </View>
                ) : null}
              </View>

              <Text
                style={[
                  styles.activeFaceFilterSubtitle,
                  { color: colorScheme === 'dark' ? '#9ca3af' : '#4b5563' },
                ]}
              >
                تم العثور على {faceSearchResult.total || faceSearchResult.items?.length || 0} صورة بالمعرض
              </Text>
            </View>
          </View>

          {/* Action Buttons Row */}
          <View style={styles.activeFaceFilterActionsRow}>
            <TouchableOpacity
              style={[styles.activeFaceFilterActionBtn, { backgroundColor: silaTheme.primary }]}
              onPress={() => setFaceSearchModalVisible(true)}
              activeOpacity={0.85}
            >
              <Ionicons name="camera-reverse-outline" size={15} color="#ffffff" />
              <Text style={styles.activeFaceFilterActionBtnText}>بحث بوجه آخر</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.activeFaceFilterActionBtn,
                styles.activeFaceFilterClearBtn,
                {
                  backgroundColor:
                    colorScheme === 'dark' ? 'rgba(239,68,68,0.18)' : '#fee2e2',
                  borderColor:
                    colorScheme === 'dark' ? 'rgba(239,68,68,0.35)' : '#fca5a5',
                },
              ]}
              onPress={() => {
                setFaceSearchResult(null);
                setFaceSelectedCategory(null);
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              }}
              activeOpacity={0.85}
            >
              <Ionicons name="close-circle-outline" size={15} color="#ef4444" />
              <Text style={[styles.activeFaceFilterActionBtnText, { color: '#ef4444' }]}>
                إلغاء الفلتر
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : (
        <LinearGradient
          colors={
            colorScheme === 'dark'
              ? ['#145147', '#0f3d35']
              : ['#ebf7f0', '#dcf2dd']
          }
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={[
            styles.heroCardUnified,
            {
              borderColor:
                colorScheme === 'dark'
                  ? 'rgba(55,160,92,0.35)'
                  : 'rgba(55,160,92,0.25)',
            },
          ]}
        >
          <View style={styles.heroTextContent}>
            <View style={styles.heroTitleRow}>
              <Ionicons name="images" size={22} color={silaTheme.primary} />
              <ThemedText type="subtitle" style={styles.heroTitle}>
                استكشف ذكريات العائلة
              </ThemedText>
            </View>
            <Text style={[styles.heroSub, { color: colorScheme === 'dark' ? '#9ca3af' : '#4b5563' }]}>
              صور وفيديوهات منسّقة ومبوبة حسب الفعاليات العائلية
            </Text>
          </View>

          {/* Action Button inside Hero Card */}
          <TouchableOpacity
            style={styles.heroSelfieBtn}
            onPress={() => setFaceSearchModalVisible(true)}
            activeOpacity={0.88}
          >
            <LinearGradient
              colors={
                colorScheme === 'dark'
                  ? [silaTheme.primary, silaTheme.primaryDark]
                  : ['#1c6b44', silaTheme.primary]
              }
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.heroSelfieBtnGrad}
            >
              <View style={styles.heroSelfieBtnLeft}>
                <Ionicons name="scan" size={20} color="#ffffff" />
                <Text style={styles.heroSelfieBtnTitle}>ابحث عن صورك بالسيلفي</Text>
                <View style={styles.heroSelfieBadge}>
                  <Text style={styles.heroSelfieBadgeText}>ذكاء اصطناعي ✨</Text>
                </View>
              </View>
              <Ionicons name="chevron-back" size={18} color="rgba(255,255,255,0.85)" />
            </LinearGradient>
          </TouchableOpacity>
        </LinearGradient>
      )}

      {/* <View
        style={[
          styles.searchRow,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}>
        <Ionicons name="search" size={20} color={colors.icon} style={styles.searchIcon} />
        <TextInput
          value={searchInput}
          onChangeText={setSearchInput}
          placeholder="بحث في الاسم أو الوصف…"
          placeholderTextColor={colors.icon}
          style={[styles.searchInput, { color: colors.text }]}
          returnKeyType="search"
        />
        {searchInput.length > 0 ? (
          <TouchableOpacity onPress={() => setSearchInput('')} hitSlop={10}>
            <Ionicons name="close-circle" size={22} color={colors.icon} />
          </TouchableOpacity>
        ) : null}
      </View> */}

      <View
        style={[
          styles.chipsPanel,
          {
            backgroundColor:
              colorScheme === 'dark' ? 'rgba(255,255,255,0.06)' : silaTheme.lightGreen,
            borderColor:
              colorScheme === 'dark' ? 'rgba(55,160,92,0.45)' : silaTheme.border,
          },
        ]}>
        <View style={styles.chipsPanelLabelRow}>
          <View style={styles.chipsPanelAccentBar} />
          <Ionicons name="albums-outline" size={20} color={silaTheme.primary} />
          <Text style={[styles.chipsPanelTitle, { color: colors.text }]}>التصنيفات</Text>
        </View>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipsScrollContent}>
          <TouchableOpacity
            onPress={() => onChipPress(null)}
            style={[
              styles.chipPill,
              (isFaceFiltered ? faceSelectedCategory === null : categoryId === null)
                ? styles.chipPillActive
                : styles.chipPillIdle,
              (isFaceFiltered ? faceSelectedCategory === null : categoryId === null)
                ? {
                  backgroundColor: silaTheme.primary,
                  borderColor: silaTheme.primaryDark,
                }
                : {
                  backgroundColor: inactiveChipBg,
                  borderColor: inactiveChipBorder,
                },
            ]}
            activeOpacity={0.88}>
            <Text
              style={[
                styles.chipPillTitle,
                {
                  color: (isFaceFiltered ? faceSelectedCategory === null : categoryId === null)
                    ? '#FFFFFF'
                    : colors.text,
                },
              ]}
              numberOfLines={1}>
              الكل
            </Text>
            <Text
              style={[
                styles.chipPillSub,
                {
                  color:
                    (isFaceFiltered ? faceSelectedCategory === null : categoryId === null)
                      ? 'rgba(255,255,255,0.88)'
                      : colors.icon,
                },
              ]}>
              {isFaceFiltered
                ? `${faceSearchResult?.total || faceSearchResult?.items?.length || 0} صورة`
                : 'كل الصور'}
            </Text>
          </TouchableOpacity>
          {displayChips.map((c) => {
            const active = isFaceFiltered ? faceSelectedCategory === c.id : categoryId === c.id;
            return (
              <TouchableOpacity
                key={c.id}
                onPress={() => onChipPress(c.id)}
                style={[
                  styles.chipPill,
                  active ? styles.chipPillActive : styles.chipPillIdle,
                  active
                    ? {
                      backgroundColor: silaTheme.primary,
                      borderColor: silaTheme.primaryDark,
                    }
                    : {
                      backgroundColor: inactiveChipBg,
                      borderColor: inactiveChipBorder,
                    },
                ]}
                activeOpacity={0.88}>
                <Text
                  style={[styles.chipPillTitle, { color: active ? '#FFFFFF' : colors.text }]}
                  numberOfLines={1}>
                  {c.name}
                </Text>
                <View style={styles.chipPillMetaRow}>
                  <View
                    style={[
                      styles.chipCountBadge,
                      {
                        backgroundColor: active
                          ? 'rgba(255,255,255,0.22)'
                          : colorScheme === 'dark'
                            ? 'rgba(255,255,255,0.1)'
                            : 'rgba(55,160,92,0.12)',
                      },
                    ]}>
                    <Text
                      style={[
                        styles.chipCountBadgeText,
                        {
                          color: active ? '#FFFFFF' : silaTheme.primaryDark,
                        },
                      ]}>
                      {c.total_images_count}
                    </Text>
                  </View>
                </View>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      </View>

      {categoriesError ? (
        <ThemedText style={[styles.inlineWarn, { color: '#F59E0B' }]}>
          {categoriesError}
        </ThemedText>
      ) : null}

      {isFaceFiltered ? (
        <ThemedText style={[styles.metaLine, { color: colors.icon }]}>
          {displayItems.length === 0
            ? 'لا توجد صور في هذا التصنيف لهذا الشخص'
            : `عرض ${displayItems.length} صورة من أصل ${faceSearchResult?.total || faceSearchResult?.items?.length || 0}`}
        </ThemedText>
      ) : meta && !categoryError && !error ? (
        <ThemedText style={[styles.metaLine, { color: colors.icon }]}>
          {meta.total === 0
            ? 'لا توجد نتائج مطابقة'
            : `${meta.total} عنصر — الصفحة ${meta.current_page} من ${meta.last_page}`}
        </ThemedText>
      ) : null}
    </View>
  );

  const hasError = !!(error || categoryError);
  const blockingMessage = categoryError ?? error;
  const showGridRefetchOverlay = loading && items.length > 0;

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={6} minSize={28} maxSize={90} />
      <HeaderBar title="معرض الصور" />

      <View style={styles.listWrap}>
        <FlatList
          ref={gridListRef}
          data={displayItems}
          keyExtractor={(it) => String(it.id)}
          numColumns={2}
          removeClippedSubviews={false}
          maxToRenderPerBatch={8}
          windowSize={7}
          columnWrapperStyle={styles.columnWrap}
          contentContainerStyle={[
            styles.listContent,
            { paddingBottom: insets.bottom + 24 },
          ]}
          ListHeaderComponent={listHeader}
          ListFooterComponent={
            loadingMore ? (
              <View style={styles.footer}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : null
          }
          ListEmptyComponent={
            loading ? (
              <ListSkeleton variant="grid" count={6} style={{ paddingHorizontal: 0 }} />
            ) : hasError ? (
              <View style={styles.errorWrap}>
                <IconSymbol name="exclamationmark.triangle.fill" size={36} color="#F59E0B" />
                <ThemedText style={styles.errorTitle}>تعذر عرض الصور</ThemedText>
                <ThemedText style={[styles.errorBody, { color: colors.icon }]}>
                  {blockingMessage}
                </ThemedText>
                <TouchableOpacity
                  style={[styles.retryBtn, { backgroundColor: silaTheme.primary }]}
                  onPress={retry}>
                  <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
                </TouchableOpacity>
              </View>
            ) : (
              <View style={styles.empty}>
                <IconSymbol name="photo.fill" size={48} color={colors.icon} />
                <ThemedText style={[styles.emptyText, { color: colors.icon }]}>
                  لا توجد صور في هذا القسم بعد
                </ThemedText>
              </View>
            )
          }
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                if (!isFaceFiltered) refresh();
              }}
              tintColor={colors.primary}
            />
          }
          onEndReached={() => {
            if (!isFaceFiltered && hasMore) loadMore();
          }}
          onEndReachedThreshold={0.35}
          renderItem={({ item }) => {
            const uri = thumbUri(item);
            const isYt = item.media_type === 'youtube';
            return (
              <TouchableOpacity
                style={[
                  styles.cell,
                  {
                    width: CELL_W,
                    height: CELL_H,
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                  },
                ]}
                onPress={() => openItem(item)}
                activeOpacity={0.92}
                delayPressIn={0}>
                {uri ? (
                  <LazyGridCover uri={uri} />
                ) : (
                  <View style={[styles.cellPlaceholder, { backgroundColor: colors.border }]}>
                    <IconSymbol name="photo.fill" size={32} color={colors.icon} />
                  </View>
                )}
                {isYt ? (
                  <View style={styles.playBadge}>
                    <Ionicons name="logo-youtube" size={22} color="#fff" />
                  </View>
                ) : null}
              </TouchableOpacity>
            );
          }}
        />
        {showGridRefetchOverlay ? (
          <View
            style={[
              styles.gridLoadingOverlay,
              {
                backgroundColor:
                  colorScheme === 'dark' ? 'rgba(0,0,0,0.5)' : 'rgba(255,255,255,0.6)',
              },
            ]}
            pointerEvents="none">
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : null}
      </View>

      <Modal
        visible={(viewerIndex !== null || standaloneViewerItem !== null) && currentViewerItem != null}
        transparent
        animationType="fade"
        presentationStyle="overFullScreen"
        statusBarTranslucent
        onRequestClose={closeViewer}>
        {currentViewerItem != null ? (
          <View
            style={[
              styles.viewerRoot,
              { paddingTop: safeTop + 54, paddingBottom: safeBottom + 8 },
            ]}>
            <TouchableOpacity
              style={[styles.modalClose, { top: safeTop + 8 }]}
              onPress={closeViewer}
              hitSlop={12}>
              <Ionicons name="close" size={26} color="#fff" />
            </TouchableOpacity>
            {currentViewerUri ? (
              <TouchableOpacity
                style={[styles.modalDownload, { top: safeTop + 8 }]}
                onPress={downloadCurrentViewerPhoto}
                disabled={viewerDownloading}
                hitSlop={12}>
                {viewerDownloading ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Ionicons name="download" size={22} color="#fff" />
                )}
              </TouchableOpacity>
            ) : null}

            <View
              style={styles.viewerPhotoBox}
              onLayout={(e) => {
                const { width, height } = e.nativeEvent.layout;
                if (width > 0 && height > 0) {
                  setPhotoBox((prev) =>
                    prev.w === width && prev.h === height ? prev : { w: width, h: height }
                  );
                }
              }}
              {...panResponder.panHandlers}>
              {currentViewerUri ? (
                <ViewerPhoto
                  key={currentViewerItem.id}
                  uri={currentViewerUri}
                  width={photoBox.w || pageW}
                  height={photoBox.h}
                  onZoomChange={(s) => {
                    viewerZoomRef.current = s;
                  }}
                />
              ) : null}
            </View>

            {viewerIndex != null ? (
              <View style={styles.viewerNavRow}>
                <TouchableOpacity
                  style={[
                    styles.viewerNavBtn,
                    viewerIndex >= viewerItems.length - 1 && styles.viewerNavBtnDisabled,
                  ]}
                  onPress={() => stepViewer(1)}
                  disabled={viewerIndex >= viewerItems.length - 1}
                  hitSlop={8}>
                  <Ionicons name="chevron-forward" size={22} color="#fff" />
                  <Text style={styles.viewerNavBtnText}>التالي</Text>
                </TouchableOpacity>
                <Text style={styles.viewerCounter}>
                  {viewerIndex + 1} / {viewerItems.length}
                  {meta && meta.total > viewerItems.length
                    ? ` — ${meta.total} في القائمة`
                    : ''}
                </Text>
                <TouchableOpacity
                  style={[styles.viewerNavBtn, viewerIndex <= 0 && styles.viewerNavBtnDisabled]}
                  onPress={() => stepViewer(-1)}
                  disabled={viewerIndex <= 0}
                  hitSlop={8}>
                  <Text style={styles.viewerNavBtnText}>السابق</Text>
                  <Ionicons name="chevron-back" size={22} color="#fff" />
                </TouchableOpacity>
              </View>
            ) : null}

            <ScrollView
              nestedScrollEnabled
              style={styles.viewerMetaScroll}
              contentContainerStyle={styles.viewerMetaContent}
              showsVerticalScrollIndicator={false}>
              {currentViewerItem.category?.name ? (
                <View style={styles.badge}>
                  <ThemedText style={styles.badgeText}>{currentViewerItem.category.name}</ThemedText>
                </View>
              ) : null}
              {currentViewerItem.article?.title?.trim() ? (
                <ThemedText style={[styles.articleLine, { color: colors.icon }]}>
                  مقال: {currentViewerItem.article.title.trim()}
                </ThemedText>
              ) : null}
              {currentViewerItem.description?.trim() ? (
                <ThemedText style={[styles.modalDesc, { color: colors.icon }]}>
                  {stripHtml(currentViewerItem.description)}
                </ThemedText>
              ) : null}
              {currentViewerItem.mentioned_persons?.length ? (
                <ThemedText style={[styles.personsLine, { color: colors.text }]}>
                  المذكورون:{' '}
                  {currentViewerItem.mentioned_persons.map((p) => p.name).join('، ')}
                </ThemedText>
              ) : null}
            </ScrollView>
          </View>
        ) : null}
      </Modal>
      <GalleryFaceSearchModal
        visible={faceSearchModalVisible}
        onClose={() => setFaceSearchModalVisible(false)}
        onSearchResults={(result) => {
          setFaceSearchResult(result);
          setFaceSelectedCategory(null);
          requestAnimationFrame(() => {
            gridListRef.current?.scrollToOffset({ offset: 0, animated: true });
          });
        }}
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  listWrap: { flex: 1, position: 'relative' },
  gridLoadingOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  listContent: { paddingHorizontal: H_PAD, flexGrow: 1 },
  listHeader: { paddingBottom: 8 },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    marginBottom: 12,
  },
  searchIcon: { marginEnd: 8 },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Regular',
    fontSize: 16,
    paddingVertical: 12,

  },
  lazySpinnerOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.08)',
  },
  chipsPanel: {
    borderRadius: 16,
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 10,
    marginBottom: 10,
  },
  chipsPanelLabelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
    paddingHorizontal: 4,
  },
  chipsPanelAccentBar: {
    width: 4,
    height: 22,
    borderRadius: 3,
    backgroundColor: silaTheme.primary,
    marginEnd: 8,
  },
  chipsPanelTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    marginStart: 6,
  },
  chipsScrollContent: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingBottom: 2,
    paddingTop: 2,
  },
  chipPill: {
    marginEnd: 10,
    paddingVertical: 11,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    minWidth: 100,
    maxWidth: 220,
    alignItems: 'center',
  },
  chipPillIdle: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.06,
        shadowRadius: 3,
      },
      android: { elevation: 1 },
    }),
  },
  chipPillActive: {
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.darkGreen,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.35,
        shadowRadius: 8,
      },
      android: { elevation: 5 },
    }),
  },
  chipPillTitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    width: '100%',
  },
  chipPillSub: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    marginTop: 3,
    width: '100%',
  },
  chipPillMetaRow: {
    marginTop: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipCountBadge: {
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 10,
    minWidth: 36,
    alignItems: 'center',
  },
  chipCountBadgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  inlineWarn: { fontFamily: 'Tajawal-Regular', marginBottom: 6 },
  metaLine: { fontSize: 13, fontFamily: 'Tajawal-Regular', marginTop: 4 },
  columnWrap: { justifyContent: 'space-between', marginBottom: GAP },
  cell: {
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1,
  },
  cellPlaceholder: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  playBadge: {
    position: 'absolute',
    top: 8,
    left: 8,
    backgroundColor: 'rgba(220,38,38,0.92)',
    borderRadius: 8,
    padding: 4,
  },
  footer: { paddingVertical: 16 },
  empty: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { marginTop: 12, fontFamily: 'Tajawal-Regular', textAlign: 'center' },
  loadingWrap: { minHeight: 260, justifyContent: 'center', alignItems: 'center', paddingVertical: 40 },
  loadingText: { marginTop: 12, fontFamily: 'Tajawal-Regular' },
  errorWrap: {
    minHeight: 260,
    padding: 24,
    paddingVertical: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  errorTitle: { fontFamily: 'Tajawal-Bold', fontSize: 18, marginTop: 12 },
  errorBody: { textAlign: 'center', marginTop: 8, fontFamily: 'Tajawal-Regular' },
  retryBtn: { marginTop: 20, paddingVertical: 12, paddingHorizontal: 24, borderRadius: 12 },
  retryBtnText: { color: '#fff', fontFamily: 'Tajawal-Bold' },
  viewerRoot: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.94)',
  },
  viewerPhotoBox: {
    flex: 1,
    backgroundColor: '#000',
    overflow: 'hidden',
  },
  viewerSpinner: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalClose: {
    position: 'absolute',
    left: 12,
    zIndex: 10,
    padding: 8,
    borderRadius: 999,
    backgroundColor: 'rgba(0,0,0,0.45)',
  },
  modalDownload: {
    position: 'absolute',
    right: 12,
    zIndex: 10,
    padding: 8,
    borderRadius: 999,
    backgroundColor: 'rgba(0,0,0,0.45)',
  },
  viewerNavRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#111',
  },
  viewerNavBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  viewerNavBtnDisabled: {
    opacity: 0.35,
  },
  viewerNavBtnText: {
    color: '#fff',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  viewerErrorOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  viewerErrorTitle: {
    color: '#fff',
    fontFamily: 'Tajawal-Medium',
    fontSize: 15,
    marginTop: 12,
  },
  viewerLoadingText: {
    color: 'rgba(255,255,255,0.8)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    marginTop: 10,
  },
  viewerZoomBar: {
    position: 'absolute',
    bottom: 10,
    left: 0,
    right: 0,
    alignItems: 'center',
    gap: 8,
  },
  viewerZoomHint: {
    backgroundColor: 'rgba(255,255,255,0.15)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
  },
  viewerZoomHintText: {
    color: '#fff',
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  viewerZoomBtns: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  viewerZoomBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewerMetaScroll: {
    maxHeight: SCREEN_H * 0.22,
    backgroundColor: '#111',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.12)',
  },
  viewerMetaContent: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 16,
  },
  viewerCounter: {
    color: 'rgba(255,255,255,0.92)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    flex: 1,
  },
  badge: {
    alignSelf: 'flex-end',
    marginTop: 8,
    backgroundColor: 'rgba(55,160,92,0.35)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  badgeText: { color: '#a7f3d0', fontSize: 12, fontFamily: 'Tajawal-Medium' },
  articleLine: {
    marginTop: 8,
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',

  },
  modalDesc: {
    marginTop: 10,
    fontFamily: 'Tajawal-Regular',

    lineHeight: 22,
  },
  personsLine: {
    marginTop: 10,
    fontFamily: 'Tajawal-Regular',

    fontSize: 14,
  },
  activeFaceFilterCard: {
    borderRadius: 20,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1.5,
    gap: 12,
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.darkGreen,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
      },
      android: { elevation: 3 },
    }),
  },
  activeFaceFilterTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  activeFaceFilterAvatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    borderWidth: 1.5,
    borderColor: silaTheme.primary,
  },
  activeFaceFilterIconCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  activeFaceFilterTextWrap: {
    flex: 1,
    alignItems: 'flex-start',
  },
  activeFaceFilterTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
  },
  activeFaceFilterTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  activeFaceFilterSimBadge: {
    backgroundColor: 'rgba(55,160,92,0.22)',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  activeFaceFilterSimText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  activeFaceFilterSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  activeFaceFilterActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingTop: 4,
  },
  activeFaceFilterActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 9,
    paddingHorizontal: 14,
    borderRadius: 12,
    flex: 1,
  },
  activeFaceFilterClearBtn: {
    borderWidth: 1,
  },
  activeFaceFilterActionBtnText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    color: '#ffffff',
  },
  heroCardUnified: {
    borderRadius: 20,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    gap: 14,
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.darkGreen,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
      },
      android: { elevation: 2 },
    }),
  },
  heroTextContent: {
    alignItems: 'flex-start',
  },
  heroTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  heroTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
  },
  heroSub: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 18,

  },
  heroSelfieBtn: {
    borderRadius: 14,
    overflow: 'hidden',
    marginTop: 2,
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.darkGreen,
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.2,
        shadowRadius: 6,
      },
      android: { elevation: 3 },
    }),
  },
  heroSelfieBtnGrad: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  heroSelfieBtnLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  heroSelfieBtnTitle: {
    color: '#ffffff',
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  heroSelfieBadge: {
    backgroundColor: 'rgba(255,255,255,0.22)',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.3)',
  },
  heroSelfieBadgeText: {
    color: '#ffffff',
    fontSize: 10,
    fontFamily: 'Tajawal-Medium',
  },
});