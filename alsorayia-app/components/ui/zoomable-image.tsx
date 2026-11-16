import { Image, type ImageContentFit } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as FileSystem from 'expo-file-system';
import * as FileSystemLegacy from 'expo-file-system/legacy';
import * as Haptics from 'expo-haptics';
import * as Sharing from 'expo-sharing';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  Image as RNImage,
  Modal,
  PanResponder,
  Platform,
  Pressable,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

/** blurhash رمادي محايد — يظهر فوراً ثم تُستبدل بالصورة (موحد لكل الشاشات) */
export const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

const { width: SCREEN_W } = Dimensions.get('window');

// ─────────────────────────────────────────────
// AppImage: تحميل موحد (placeholder + spinner + خطأ مع إعادة المحاولة)
// ─────────────────────────────────────────────
interface AppImageProps {
  uri: string | null | undefined;
  style?: StyleProp<ViewStyle>;
  contentFit?: ImageContentFit;
  loaderColor?: string;
  errorLabel?: string;
}

export function AppImage({
  uri,
  style,
  contentFit = 'cover',
  loaderColor = '#10B981',
  errorLabel = 'تعذر تحميل الصورة — انقر لإعادة المحاولة',
}: AppImageProps) {
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    setLoading(true);
    setFailed(false);
    const t = setTimeout(() => setLoading(false), 8000);
    return () => clearTimeout(t);
  }, [uri, retryKey]);

  if (!uri?.trim()) {
    return (
      <View style={[styles.fill, styles.errorBox, style]}>
        <Ionicons name="image-outline" size={28} color="rgba(120,120,120,0.8)" />
      </View>
    );
  }

  return (
    <Pressable
      style={[styles.fill, style]}
      onPress={failed ? () => setRetryKey((k) => k + 1) : undefined}
      disabled={!failed}
    >
      <Image
        key={retryKey}
        source={{ uri }}
        style={StyleSheet.absoluteFill}
        contentFit={contentFit}
        transition={220}
        cachePolicy="memory-disk"
        placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
        onLoadStart={() => {
          setLoading(true);
          setFailed(false);
        }}
        onLoad={() => setLoading(false)}
        onDisplay={() => setLoading(false)}
        onError={() => {
          setLoading(false);
          setFailed(true);
        }}
      />
      {loading && !failed ? (
        <View style={styles.loadingOverlay} pointerEvents="none">
          <ActivityIndicator size="small" color={loaderColor} />
        </View>
      ) : null}
      {failed ? (
        <View style={[styles.fill, styles.errorBox]} pointerEvents="none">
          <Ionicons name="refresh-circle-outline" size={28} color="rgba(120,120,120,0.9)" />
          <Text style={styles.errorText}>{errorLabel}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

// ─────────────────────────────────────────────
// منطق الزوم + التحريك الموحد (قرص للأصابع + سحب عند التكبير + دبل-تاب + أزرار)
// ─────────────────────────────────────────────
export interface ViewerImage {
  uri: string;
  title?: string | null;
}

export const ZOOM_MIN = 1;
export const ZOOM_MAX = 4;

function clampOffset(v: number, viewSize: number, scale: number): number {
  const m = Math.max(0, (viewSize * scale - viewSize) / 2);
  return Math.min(m, Math.max(-m, v));
}

function touchDistance(touches: Array<{ pageX: number; pageY: number }>): number {
  const dx = touches[0].pageX - touches[1].pageX;
  const dy = touches[0].pageY - touches[1].pageY;
  return Math.hypot(dx, dy);
}

const IMAGE_MIME_BY_EXT: Record<string, string> = {
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  png: 'image/png',
  webp: 'image/webp',
  gif: 'image/gif',
  heic: 'image/heic',
  heif: 'image/heif',
  bmp: 'image/bmp',
  tif: 'image/tiff',
  tiff: 'image/tiff',
  avif: 'image/avif',
};

function imageFileMeta(uri: string): { ext: string; mimeType: string } {
  const clean = uri.split('#')[0].split('?')[0];
  const match = /\.([a-zA-Z0-9]{2,5})$/.exec(clean);
  const ext = match?.[1]?.toLowerCase();
  if (ext && IMAGE_MIME_BY_EXT[ext]) {
    return { ext, mimeType: IMAGE_MIME_BY_EXT[ext] };
  }
  return { ext: 'jpg', mimeType: 'image/jpeg' };
}

async function downloadToCache(uri: string, fileName: string): Promise<string> {
  try {
    const dest = new FileSystem.File(FileSystem.Paths.cache, fileName);
    const file = await FileSystem.File.downloadFileAsync(uri, dest, { idempotent: true });
    return file.uri;
  } catch (error) {
    // Older native builds may not support the class-based File API.
    console.warn('[downloadImage] modern download failed, falling back to legacy:', error);
    const cacheDir = FileSystemLegacy.cacheDirectory;
    if (!cacheDir) throw new Error('cache-directory-unavailable');
    const result = await FileSystemLegacy.downloadAsync(uri, `${cacheDir}${fileName}`);
    return result.uri;
  }
}

async function saveToGallery(fileUri: string): Promise<boolean> {
  let ML: typeof import('expo-media-library/legacy') | null = null;
  try {
    // The legacy API still works on both old and new native builds.
    ML = await import('expo-media-library/legacy');
  } catch (error) {
    console.warn('[downloadImage] media-library unavailable:', error);
    return false;
  }

  const isExpoGo =
    Constants.executionEnvironment === ExecutionEnvironment.StoreClient ||
    Constants.appOwnership === 'expo';

  if (!isExpoGo) {
    try {
      const current = await ML.getPermissionsAsync(true);
      if (!current.granted && current.canAskAgain) {
        await ML.requestPermissionsAsync(true);
      }
    } catch (error) {
      // Saving may still be allowed (Android 13+ needs no permission), so keep going.
      console.warn('[downloadImage] media permission check failed:', error);
    }
  }

  try {
    await ML.saveToLibraryAsync(fileUri);
    return true;
  } catch (error) {
    console.warn('[downloadImage] save to gallery failed:', error);
    return false;
  }
}

/** Download an image and save it straight to the device gallery (share sheet as fallback). */
export async function downloadImage(uri: string): Promise<{ ok: boolean; message: string }> {
  if (Platform.OS === 'web') {
    const a = document.createElement('a');
    a.href = uri;
    a.download = `alsorayia-${Date.now()}.jpg`;
    a.target = '_blank';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    return { ok: true, message: 'بدأ تحميل الصورة' };
  }

  const { ext, mimeType } = imageFileMeta(uri);
  const fileName = `alsorayia-${Date.now()}.${ext}`;

  let fileUri: string;
  try {
    fileUri = await downloadToCache(uri, fileName);
  } catch (error) {
    console.warn('[downloadImage] download failed:', error);
    return { ok: false, message: 'تعذر تنزيل الصورة، تحقق من الاتصال وحاول مجدداً' };
  }

  if (await saveToGallery(fileUri)) {
    return { ok: true, message: 'تم حفظ الصورة في معرض الصور' };
  }

  try {
    const available = await Sharing.isAvailableAsync();
    if (!available) {
      return { ok: false, message: 'تعذر حفظ الصورة: الحفظ والمشاركة غير متاحين على هذا الجهاز' };
    }
    await Sharing.shareAsync(fileUri, { mimeType, dialogTitle: 'حفظ الصورة' });
    return { ok: true, message: 'اختر «حفظ الصورة» من قائمة المشاركة لحفظها على جهازك' };
  } catch (error) {
    console.warn('[downloadImage] share fallback failed:', error);
    return { ok: false, message: 'تعذر حفظ الصورة على هذا الجهاز، حاول مجدداً' };
  }
}

export function useZoomPan(
  viewW: number,
  viewH: number,
  onZoomChange?: (scale: number) => void
) {
  const [scale, setScaleState] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const lastTapRef = useRef(0);
  const gestureRef = useRef({
    mode: 'none' as 'none' | 'pan' | 'pinch',
    startDist: 0,
    startScale: 1,
    baseX: 0,
    baseY: 0,
  });
  const liveRef = useRef({ scale: 1, x: 0, y: 0 });
  useEffect(() => {
    liveRef.current = { scale, x: offset.x, y: offset.y };
  }, [scale, offset]);

  const commit = useCallback(
    (nextScale: number, x: number, y: number) => {
      const s = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, nextScale));
      const nx = s <= 1 ? 0 : clampOffset(x, viewW, s);
      const ny = s <= 1 ? 0 : clampOffset(y, viewH, s);
      setScaleState(s);
      setOffset({ x: nx, y: ny });
      onZoomChange?.(s);
    },
    [onZoomChange, viewW, viewH]
  );

  const applyScale = useCallback(
    (next: number) => {
      const cur = liveRef.current;
      commit(next, cur.x, cur.y);
    },
    [commit]
  );

  const resetZoom = useCallback(() => commit(1, 0, 0), [commit]);

  const toggleZoom = useCallback(() => {
    const cur = liveRef.current.scale;
    commit(cur > 1.2 ? 1 : 2.5, 0, 0);
  }, [commit]);

  const handleTap = useCallback(() => {
    const now = Date.now();
    if (now - lastTapRef.current < 300) toggleZoom();
    lastTapRef.current = now;
  }, [toggleZoom]);

  const panResponder = useMemo(
    () =>
      // eslint-disable-next-line react-hooks/refs -- callbacks تقرأ الـ refs فقط أثناء الإيماءات وليس أثناء الـ render
      PanResponder.create({
        onStartShouldSetPanResponder: () => false,
        onMoveShouldSetPanResponder: (e, g) => {
          const touches = (e.nativeEvent as any).touches as
            | Array<{ pageX: number; pageY: number }>
            | undefined;
          if (touches && touches.length >= 2) return true;
          return (
            liveRef.current.scale > 1 && (Math.abs(g.dx) > 4 || Math.abs(g.dy) > 4)
          );
        },
        onPanResponderGrant: (e) => {
          const touches = (e.nativeEvent as any).touches as
            | Array<{ pageX: number; pageY: number }>
            | undefined;
          if (touches && touches.length >= 2) {
            gestureRef.current = {
              mode: 'pinch',
              startDist: Math.max(1, touchDistance(touches)),
              startScale: liveRef.current.scale,
              baseX: liveRef.current.x,
              baseY: liveRef.current.y,
            };
          } else {
            gestureRef.current = {
              mode: 'pan',
              startDist: 0,
              startScale: liveRef.current.scale,
              baseX: liveRef.current.x,
              baseY: liveRef.current.y,
            };
          }
        },
        onPanResponderMove: (e, g) => {
          const st = gestureRef.current;
          const touches = (e.nativeEvent as any).touches as
            | Array<{ pageX: number; pageY: number }>
            | undefined;
          if (st.mode === 'pinch' && touches && touches.length >= 2) {
            const d = Math.max(1, touchDistance(touches));
            commit((st.startScale * d) / st.startDist, st.baseX, st.baseY);
          } else if (st.mode === 'pan') {
            commit(liveRef.current.scale, st.baseX + g.dx, st.baseY + g.dy);
          }
        },
        onPanResponderRelease: () => {
          gestureRef.current.mode = 'none';
        },
        onPanResponderTerminate: () => {
          gestureRef.current.mode = 'none';
        },
        onPanResponderTerminationRequest: (e) => {
          const touches = (e.nativeEvent as any).touches as
            | Array<{ pageX: number; pageY: number }>
            | undefined;
          // أثناء القرص بإصبعين لا نتنازل عن الإيماءة لأي أب (التنقل/البيجر)
          if (touches && touches.length >= 2) return false;
          return liveRef.current.scale <= 1;
        },
      }),
    [commit]
  );

  return { scale, tx: offset.x, ty: offset.y, applyScale, resetZoom, toggleZoom, handleTap, panHandlers: panResponder.panHandlers };
}

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
          <ActivityIndicator size="large" color="#10B981" />
          <Text style={styles.viewerLoadingText}>جاري تحميل الصورة...</Text>
        </View>
      ) : null}

      {failed ? (
        <View style={styles.viewerSpinner} pointerEvents="none">
          <Ionicons name="image-outline" size={40} color="rgba(255,255,255,0.7)" />
          <Text style={styles.viewerLoadingText}>تعذر عرض هذه الصورة</Text>
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
              style={[styles.viewerZoomBtn, scale <= 1 && styles.viewerZoomBtnDisabled]}
              onPress={() => applyScale(scale - 0.5)}
              disabled={scale <= 1}
              hitSlop={8}
            >
              <Ionicons name="remove" size={18} color="#fff" />
            </TouchableOpacity>
            {scale > 1 ? (
              <TouchableOpacity style={styles.viewerZoomBtn} onPress={resetZoom} hitSlop={8}>
                <Ionicons name="refresh" size={16} color="#fbbf24" />
              </TouchableOpacity>
            ) : null}
            <TouchableOpacity
              style={[styles.viewerZoomBtn, scale >= 4 && styles.viewerZoomBtnDisabled]}
              onPress={() => applyScale(scale + 0.5)}
              disabled={scale >= 4}
              hitSlop={8}
            >
              <Ionicons name="add" size={18} color="#fff" />
            </TouchableOpacity>
          </View>
        </View>
      ) : null}
    </View>
  );
}

// ─────────────────────────────────────────────
// ImageViewerModal: عارض موحد فائق السلاسة بتصميم متوافق 100% مع شريط الحالة وشريط التنقل
// ─────────────────────────────────────────────
export interface ImageViewerModalProps {
  visible: boolean;
  images: ViewerImage[];
  initialIndex?: number;
  onClose: () => void;
}

export function ImageViewerModal({
  visible,
  images,
  initialIndex = 0,
  onClose,
}: ImageViewerModalProps) {
  const insets = useSafeAreaInsets();
  const [index, setIndex] = useState(initialIndex);
  const [downloading, setDownloading] = useState(false);
  const [photoBox, setPhotoBox] = useState({ w: 0, h: 0 });
  const zoomScaleRef = useRef(1);

  // حساب الهوامش الآمنة لضمان عدم التداخل مع شريط الحالة وشريط أزرار النظام (أندرويد وiOS)
  const statusBarH = Platform.OS === 'android' ? (StatusBar.currentHeight ?? 24) : 0;
  const safeTop = Math.max(insets.top, statusBarH, Platform.OS === 'ios' ? 44 : 24);
  const safeBottom = Math.max(insets.bottom, Platform.OS === 'android' ? 24 : 16);

  useEffect(() => {
    if (visible) {
      setIndex(Math.min(Math.max(0, initialIndex), Math.max(0, images.length - 1)));
      zoomScaleRef.current = 1;
    }
  }, [visible, initialIndex, images.length]);

  const step = useCallback(
    (delta: number) => {
      setIndex((prev) => {
        const next = prev + delta;
        if (next < 0 || next >= images.length) return prev;
        zoomScaleRef.current = 1;
        void Haptics.selectionAsync().catch(() => {});
        return next;
      });
    },
    [images.length]
  );

  // إيماءة التمرير السريع لليمين واليسار للتنقل بين الصور (تتعطل تلقائياً أثناء التكبير)
  const swipePanResponder = useMemo(
    () =>
      // eslint-disable-next-line react-hooks/refs -- callbacks تقرأ الـ refs فقط أثناء الإيماءات وليس أثناء الـ render
      PanResponder.create({
        onMoveShouldSetPanResponder: (e, g) => {
          const touches = (e.nativeEvent as any).touches as unknown[] | undefined;
          if (touches && touches.length >= 2) return false;
          return (
            zoomScaleRef.current <= 1 &&
            Math.abs(g.dx) > 20 &&
            Math.abs(g.dx) > Math.abs(g.dy) * 1.2
          );
        },
        onPanResponderRelease: (_, g) => {
          if (zoomScaleRef.current > 1) return;
          if (g.dx > 60) step(-1);
          else if (g.dx < -60) step(1);
        },
      }),
    [step]
  );

  if (!visible || images.length === 0) return null;
  const current = images[Math.min(index, images.length - 1)];

  const handleDownload = async () => {
    if (!current?.uri || downloading) return;
    setDownloading(true);
    try {
      const r = await downloadImage(current.uri);
      Alert.alert('تحميل الصورة', r.message);
    } finally {
      setDownloading(false);
    }
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      presentationStyle="overFullScreen"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={styles.viewerRoot}>
        {/* شريط الرأس العلوي محمي تماماً من شريط الحالة والنوتش */}
        <View style={[styles.viewerHeader, { paddingTop: safeTop, height: safeTop + 54 }]}>
          <TouchableOpacity
            style={styles.headerBtn}
            onPress={onClose}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
            accessibilityLabel="إغلاق"
          >
            <Ionicons name="close" size={24} color="#fff" />
          </TouchableOpacity>

          <View style={styles.headerCenter}>
            {!!current?.title?.trim() && (
              <Text style={styles.headerTitle} numberOfLines={1}>
                {current.title}
              </Text>
            )}
            {images.length > 1 && (
              <Text style={styles.headerCounter}>
                {index + 1} / {images.length}
              </Text>
            )}
          </View>

          <TouchableOpacity
            style={styles.headerBtn}
            onPress={handleDownload}
            disabled={downloading}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
            accessibilityLabel="تحميل الصورة"
          >
            {downloading ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Ionicons name="download-outline" size={22} color="#fff" />
            )}
          </TouchableOpacity>
        </View>

        {/* مساحة عرض الصورة تحتل كامل الارتفاع المتاح بين الرأس وشريط التنقل */}
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
          {...swipePanResponder.panHandlers}
        >
          {current?.uri ? (
            <ViewerPhoto
              key={`${current.uri}-${index}`}
              uri={current.uri}
              width={photoBox.w || SCREEN_W}
              height={photoBox.h}
              onZoomChange={(s) => {
                zoomScaleRef.current = s;
              }}
            />
          ) : null}
        </View>

        {/* شريط التنقل السفلي محمي تماماً من أزرار النظام السفلية */}
        {images.length > 1 ? (
          <View style={[styles.viewerNavRow, { paddingBottom: safeBottom + 10 }]}>
            <TouchableOpacity
              style={[styles.viewerNavBtn, index <= 0 && styles.viewerNavBtnDisabled]}
              onPress={() => step(-1)}
              disabled={index <= 0}
              hitSlop={8}
            >
              <Ionicons name="chevron-back" size={20} color="#fff" />
              <Text style={styles.viewerNavBtnText}>السابق</Text>
            </TouchableOpacity>

            <Text style={styles.viewerNavCounter}>
              {index + 1} من {images.length}
            </Text>

            <TouchableOpacity
              style={[styles.viewerNavBtn, index >= images.length - 1 && styles.viewerNavBtnDisabled]}
              onPress={() => step(1)}
              disabled={index >= images.length - 1}
              hitSlop={8}
            >
              <Text style={styles.viewerNavBtnText}>التالي</Text>
              <Ionicons name="chevron-forward" size={20} color="#fff" />
            </TouchableOpacity>
          </View>
        ) : (
          <View style={{ height: safeBottom + 10 }} />
        )}
      </View>
    </Modal>
  );
}

// ─────────────────────────────────────────────
// ZoomableImage: صورة + شارة تكبير + عارض مدمج (جاهزة للاستخدام في أي شاشة)
// ─────────────────────────────────────────────
interface ZoomableImageProps {
  uri: string | null | undefined;
  title?: string | null;
  style?: StyleProp<ViewStyle>;
  contentFit?: ImageContentFit;
  showBadge?: boolean;
  /** لتمرير عدة صور للعارض بدل صورة واحدة */
  gallery?: ViewerImage[];
}

export function ZoomableImage({
  uri,
  title,
  style,
  contentFit = 'cover',
  showBadge = true,
  gallery,
}: ZoomableImageProps) {
  const [viewerVisible, setViewerVisible] = useState(false);
  const images = gallery?.length ? gallery : uri ? [{ uri, title }] : [];

  return (
    <>
      <Pressable
        style={[styles.fill, style]}
        onPress={() => {
          if (images.length) setViewerVisible(true);
        }}
        disabled={!images.length}
      >
        <View style={StyleSheet.absoluteFill} pointerEvents="none">
          <AppImage uri={uri} contentFit={contentFit} />
        </View>
        {showBadge && !!images.length ? (
          <View style={styles.zoomBadge} pointerEvents="none">
            <Ionicons name="expand" size={12} color="#fff" />
            <Text style={styles.zoomBadgeText}>تكبير</Text>
          </View>
        ) : null}
      </Pressable>
      <ImageViewerModal
        visible={viewerVisible}
        images={images}
        onClose={() => setViewerVisible(false)}
      />
    </>
  );
}

const styles = StyleSheet.create({
  fill: {
    overflow: 'hidden',
  },
  loadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.04)',
  },
  errorBox: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: '#F1F5F9',
    padding: 12,
  },
  errorText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    color: '#64748B',
    textAlign: 'center',
  },
  zoomBadge: {
    position: 'absolute',
    bottom: 6,
    left: 6,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0,0,0,0.55)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  zoomBadgeText: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
  viewerRoot: {
    flex: 1,
    backgroundColor: '#000',
    justifyContent: 'space-between',
  },
  viewerHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    zIndex: 20,
    backgroundColor: 'rgba(0,0,0,0.65)',
  },
  headerBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerCenter: {
    flex: 1,
    alignItems: 'center',
    paddingHorizontal: 12,
  },
  headerTitle: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    textAlign: 'center',
  },
  headerCounter: {
    color: 'rgba(255,255,255,0.75)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    marginTop: 2,
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
    gap: 10,
    backgroundColor: '#000',
  },
  viewerLoadingText: {
    color: 'rgba(255,255,255,0.8)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  viewerZoomBar: {
    position: 'absolute',
    bottom: 12,
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
  viewerZoomBtnDisabled: {
    opacity: 0.35,
  },
  viewerNavRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 10,
    backgroundColor: '#111',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.12)',
  },
  viewerNavBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(255,255,255,0.15)',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 12,
  },
  viewerNavBtnDisabled: {
    opacity: 0.35,
  },
  viewerNavBtnText: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  viewerNavCounter: {
    color: '#fff',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
});

