import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import { MAX_FONT_SIZE_MULTIPLIER } from '@/lib/utils/responsive';
import React, { useMemo, useState, useRef, useCallback } from 'react';
import {
  useColorScheme,
  ScrollView,
  View,
  Text,
  Modal,
  StyleSheet,
  TouchableOpacity,
  Pressable,
  Dimensions,
  ActivityIndicator,
  Platform,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as Sharing from 'expo-sharing';
import * as WebBrowser from 'expo-web-browser';
import RenderHTML, {
  defaultSystemFonts,
  type MixedStyleDeclaration,
  HTMLContentModel,
  HTMLElementModel,
} from 'react-native-render-html';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

const TA_JAWAL_FONTS = ['Tajawal-Regular', 'Tajawal-Medium', 'Tajawal-Bold', 'Tajawal-ExtraBold'];

const customHTMLElementModels = {
  font: HTMLElementModel.fromCustomModel({
    tagName: 'font',
    contentModel: HTMLContentModel.mixed,
  }),
  iframe: HTMLElementModel.fromCustomModel({
    tagName: 'iframe',
    contentModel: HTMLContentModel.block,
  }),
};

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function normalizeHtml(html: string): string {
  const trimmed = html.trim();
  if (!trimmed) return '';
  if (/<[a-z][\s\S]*>/i.test(trimmed)) return trimmed;
  return `<p dir="rtl">${trimmed.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>`;
}

export function hasHtmlContent(html: string | null | undefined): boolean {
  if (!html) return false;
  if (/<(iframe|img|video|embed|object|svg)/i.test(html)) return true;
  return stripHtml(html).length > 0;
}

// ─────────────────────────────────────────────
// FULLSCREEN INTERACTIVE IMAGE ZOOM MODAL
// ─────────────────────────────────────────────
interface ImageZoomViewerModalProps {
  visible: boolean;
  imageUri: string | null;
  onClose: () => void;
}

export function ImageZoomViewerModal({ visible, imageUri, onClose }: ImageZoomViewerModalProps) {
  const [zoomScale, setZoomScale] = useState(1);
  const [imgLoading, setImgLoading] = useState(true);
  const [sharing, setSharing] = useState(false);
  const lastTapRef = useRef<number>(0);
  const scrollViewRef = useRef<ScrollView>(null);

  const handleResetZoom = useCallback(() => {
    setZoomScale(1);
    if (scrollViewRef.current && Platform.OS === 'ios') {
      scrollViewRef.current.scrollResponderZoomTo({
        x: 0,
        y: 0,
        width: SCREEN_WIDTH,
        height: SCREEN_HEIGHT,
        animated: true,
      });
    }
  }, []);

  const handleClose = useCallback(() => {
    handleResetZoom();
    onClose();
  }, [handleResetZoom, onClose]);

  const handleDoubleTap = useCallback(() => {
    const now = Date.now();
    if (now - lastTapRef.current < 300) {
      setZoomScale((prev) => (prev > 1.2 ? 1 : 2.5));
    }
    lastTapRef.current = now;
  }, []);

  const handleZoomIn = () => setZoomScale((prev) => Math.min(prev + 0.5, 4));
  const handleZoomOut = () => setZoomScale((prev) => Math.max(prev - 0.5, 1));

  const handleShare = async () => {
    if (!imageUri || sharing) return;
    setSharing(true);
    try {
      const isAvailable = await Sharing.isAvailableAsync();
      if (isAvailable) {
        await Sharing.shareAsync(imageUri, {
          dialogTitle: 'مشاركة الصورة',
          mimeType: 'image/jpeg',
        });
      }
    } catch (e) {
      console.warn('Sharing image failed:', e);
    } finally {
      setSharing(false);
    }
  };

  if (!visible || !imageUri) return null;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={handleClose}>
      <View style={zoomStyles.overlay}>
        {/* Top Control Bar */}
        <View style={zoomStyles.topBar}>
          <TouchableOpacity
            style={zoomStyles.iconBtn}
            onPress={handleClose}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
          >
            <Ionicons name="close" size={24} color="#fff" />
          </TouchableOpacity>

          <View style={zoomStyles.hintBadge}>
            <Ionicons name="scan-outline" size={14} color="#fbbf24" />
            <Text style={zoomStyles.hintText}>
              {zoomScale > 1 ? `${Math.round(zoomScale * 100)}%` : 'انقر مرتين للتكبير'}
            </Text>
          </View>

          <TouchableOpacity
            style={zoomStyles.iconBtn}
            onPress={handleShare}
            disabled={sharing}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
          >
            {sharing ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Ionicons name="share-outline" size={22} color="#fff" />
            )}
          </TouchableOpacity>
        </View>

        {/* Zoomable Image Content */}
        <ScrollView
          ref={scrollViewRef}
          style={zoomStyles.scrollArea}
          contentContainerStyle={zoomStyles.scrollContent}
          maximumZoomScale={5}
          minimumZoomScale={1}
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          centerContent={true}
          bouncesZoom={true}
        >
          <Pressable onPress={handleDoubleTap} style={zoomStyles.imageWrapper}>
            {imgLoading && (
              <View style={zoomStyles.loadingBox}>
                <ActivityIndicator size="large" color="#fbbf24" />
                <Text style={zoomStyles.loadingText}>جاري تحميل الصورة...</Text>
              </View>
            )}
            <Image
              source={{ uri: imageUri }}
              style={[
                zoomStyles.fullscreenImage,
                { transform: [{ scale: zoomScale }] },
              ]}
              contentFit="contain"
              onLoadStart={() => setImgLoading(true)}
              onLoad={() => setImgLoading(false)}
              onError={() => setImgLoading(false)}
            />
          </Pressable>
        </ScrollView>

        {/* Bottom Floating Zoom Controls */}
        <View style={zoomStyles.bottomControls}>
          <TouchableOpacity
            style={[zoomStyles.controlBtn, zoomScale <= 1 && { opacity: 0.5 }]}
            onPress={handleZoomOut}
            disabled={zoomScale <= 1}
          >
            <Ionicons name="remove-circle-outline" size={22} color="#fff" />
            <Text style={zoomStyles.controlBtnText}>تصغير</Text>
          </TouchableOpacity>

          {zoomScale > 1 && (
            <TouchableOpacity style={zoomStyles.resetBtn} onPress={handleResetZoom}>
              <Ionicons name="refresh-outline" size={18} color="#fbbf24" />
              <Text style={zoomStyles.resetBtnText}>إعادة ضبط</Text>
            </TouchableOpacity>
          )}

          <TouchableOpacity
            style={[zoomStyles.controlBtn, zoomScale >= 4 && { opacity: 0.5 }]}
            onPress={handleZoomIn}
            disabled={zoomScale >= 4}
          >
            <Ionicons name="add-circle-outline" size={22} color="#fff" />
            <Text style={zoomStyles.controlBtnText}>تكبير</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

// ─────────────────────────────────────────────
// EMBEDDED HTML IMAGE NODE COMPONENT
// ─────────────────────────────────────────────
interface HtmlImageNodeProps {
  src: string;
  alt?: string;
  contentWidth: number;
  onZoom: (uri: string) => void;
  borderColor?: string;
}

function HtmlImageNode({ src, contentWidth, onZoom, borderColor }: HtmlImageNodeProps) {
  const [aspectRatio, setAspectRatio] = useState<number>(16 / 9);
  const [loading, setLoading] = useState(true);

  const maxW = Math.max(contentWidth - 16, 200);

  return (
    <View style={[imgNodeStyles.container, { maxWidth: maxW }]}>
      <TouchableOpacity
        activeOpacity={0.88}
        onPress={() => onZoom(src)}
        style={[
          imgNodeStyles.touchWrap,
          {
            aspectRatio,
            borderColor: borderColor ?? 'rgba(245, 158, 11, 0.25)',
          },
        ]}
      >
        <Image
          source={{ uri: src }}
          style={imgNodeStyles.image}
          contentFit="cover"
          onLoad={(e) => {
            setLoading(false);
            if (e.source?.width && e.source?.height) {
              setAspectRatio(e.source.width / e.source.height);
            }
          }}
          onError={() => setLoading(false)}
        />

        {loading && (
          <View style={imgNodeStyles.loadingOverlay}>
            <ActivityIndicator size="small" color="#fbbf24" />
          </View>
        )}

        {/* Corner Magnifier Hint Badge */}
        <View style={imgNodeStyles.zoomBadge}>
          <Ionicons name="search" size={12} color="#ffffff" />
          <Text style={imgNodeStyles.zoomBadgeText}>تكبير 🔍</Text>
        </View>
      </TouchableOpacity>
    </View>
  );
}

// ─────────────────────────────────────────────
// EMBEDDED IFRAME / VIDEO NODE COMPONENT
// ─────────────────────────────────────────────
interface HtmlIframeNodeProps {
  src: string;
  title?: string;
  contentWidth: number;
  borderColor?: string;
}

function extractYoutubeVideoId(url: string): string | null {
  if (!url) return null;
  const match = url.match(
    /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|live\/))([a-zA-Z0-9_-]{11})/i
  );
  return match ? match[1] : null;
}

function HtmlIframeNode({ src, title, contentWidth, borderColor }: HtmlIframeNodeProps) {
  const cleanSrc = (src || '').trim();
  const ytId = extractYoutubeVideoId(cleanSrc);
  const isYoutube = !!ytId;

  if (Platform.OS === 'web') {
    return (
      <View
        style={[
          iframeStyles.webContainer,
          {
            width: contentWidth,
            borderColor: borderColor ?? 'rgba(0,0,0,0.1)',
          },
        ]}
      >
        <iframe
          src={cleanSrc}
          title={title || 'فيديو'}
          style={{
            width: '100%',
            height: '100%',
            border: 'none',
          }}
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowFullScreen
        />
      </View>
    );
  }

  const thumbUrl = isYoutube ? `https://img.youtube.com/vi/${ytId}/hqdefault.jpg` : null;

  const handlePlay = async () => {
    try {
      await WebBrowser.openBrowserAsync(cleanSrc, {
        presentationStyle: WebBrowser.WebBrowserPresentationStyle.CURRENT_CONTEXT,
      });
    } catch (e) {
      console.warn('Failed to open video:', e);
    }
  };

  return (
    <View style={[iframeStyles.container, { width: contentWidth }]}>
      <TouchableOpacity
        activeOpacity={0.88}
        onPress={handlePlay}
        style={[
          iframeStyles.touchCard,
          {
            borderColor: borderColor ?? 'rgba(0,0,0,0.12)',
          },
        ]}
      >
        {thumbUrl ? (
          <Image
            source={{ uri: thumbUrl }}
            style={iframeStyles.thumbnail}
            contentFit="cover"
          />
        ) : (
          <View style={iframeStyles.placeholderBg} />
        )}

        <View style={iframeStyles.overlay} />

        <View style={iframeStyles.playButton}>
          <Ionicons name="play" size={26} color="#ffffff" style={{ marginLeft: 3 }} />
        </View>

        {title ? (
          <View style={iframeStyles.titleBar}>
            <ThemedText style={iframeStyles.titleText} numberOfLines={1}>
              {title}
            </ThemedText>
          </View>
        ) : null}
      </TouchableOpacity>
    </View>
  );
}

// ─────────────────────────────────────────────
// MAIN HTML CONTENT COMPONENT
// ─────────────────────────────────────────────
type HtmlContentProps = {
  html: string | null | undefined;
  contentWidth: number;
  emptyMessage?: string;
  textColor?: string;
};

export function HtmlContent({
  html,
  contentWidth,
  emptyMessage = 'لا يوجد وصف',
  textColor,
}: HtmlContentProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const color = textColor ?? colors.text;

  const [selectedImage, setSelectedImage] = useState<string | null>(null);

  const source = useMemo(() => {
    const normalized = normalizeHtml(html || '');
    return normalized ? { html: normalized } : null;
  }, [html]);

  const baseStyle = useMemo<MixedStyleDeclaration>(
    () => ({
      color,
      fontFamily: 'Tajawal-Regular',
      fontSize: 14,
      lineHeight: 24,
      writingDirection: 'rtl',
    }),
    [color]
  );

  const tagsStyles = useMemo(
    () =>
      ({
        body: { margin: 0, padding: 0 },
        p: { marginTop: 0, marginBottom: 8 },
        a: { color: colors.primary },
        table: {
          borderWidth: 1,
          borderColor: colors.border,
          borderRadius: 8,
          marginVertical: 12,
          overflow: 'hidden',
        },
        thead: {
          backgroundColor: colorScheme === 'dark' ? '#1F2937' : '#F1F5F9',
        },
        tbody: {},
        tr: {
          flexDirection: 'row',
          borderBottomWidth: 1,
          borderBottomColor: colors.border,
        },
        th: {
          minWidth: 100,
          padding: 8,
          fontWeight: 'bold',
          color: colors.text,
          borderLeftWidth: 1,
          borderLeftColor: colors.border,
        },
        td: {
          minWidth: 100,
          padding: 8,
          color: colors.text,
          borderLeftWidth: 1,
          borderLeftColor: colors.border,
        },
      }) satisfies Record<string, MixedStyleDeclaration>,
    [colors.primary, colors.border, colors.text, colorScheme]
  );

  const renderers = useMemo(
    () => ({
      table: (props: any) => {
        const TDefaultRenderer = props.TDefaultRenderer;
        return (
          <ScrollView horizontal={true} showsHorizontalScrollIndicator={true} style={props.style}>
            <View style={{ flexDirection: 'column' }}>
              <TDefaultRenderer {...props} />
            </View>
          </ScrollView>
        );
      },
      img: (props: any) => {
        const src = props.tnode?.attributes?.src;
        if (!src) return null;
        return (
          <HtmlImageNode
            src={src}
            alt={props.tnode?.attributes?.alt}
            contentWidth={contentWidth}
            borderColor={colors.border}
            onZoom={(uri) => setSelectedImage(uri)}
          />
        );
      },
      iframe: (props: any) => {
        const src = props.tnode?.attributes?.src;
        if (!src) return null;
        const title = props.tnode?.attributes?.title;
        return (
          <HtmlIframeNode
            src={src}
            title={title}
            contentWidth={contentWidth}
            borderColor={colors.border}
          />
        );
      },
    }),
    [contentWidth, colors.border]
  );

  if (!source) {
    return (
      <ThemedText style={{ fontFamily: 'Tajawal-Regular', fontSize: 14, lineHeight: 24 }}>
        {emptyMessage}
      </ThemedText>
    );
  }

  return (
    <>
      <RenderHTML
        contentWidth={contentWidth}
        source={source}
        baseStyle={baseStyle}
        tagsStyles={tagsStyles}
        renderers={renderers}
        ignoredStyles={['color', 'backgroundColor']}
        systemFonts={[...defaultSystemFonts, ...TA_JAWAL_FONTS]}
        defaultTextProps={{ selectable: true, maxFontSizeMultiplier: MAX_FONT_SIZE_MULTIPLIER }}
        customHTMLElementModels={customHTMLElementModels}
      />

      <ImageZoomViewerModal
        visible={!!selectedImage}
        imageUri={selectedImage}
        onClose={() => setSelectedImage(null)}
      />
    </>
  );
}

// ─────────────────────────────────────────────
// STYLES
// ─────────────────────────────────────────────
const zoomStyles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.94)',
    justifyContent: 'space-between',
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: Platform.OS === 'ios' ? 50 : 20,
    paddingBottom: 12,
    zIndex: 10,
  },
  iconBtn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(255, 255, 255, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  hintBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.4)',
  },
  hintText: {
    color: '#fef08a',
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  scrollArea: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  imageWrapper: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT * 0.72,
    justifyContent: 'center',
    alignItems: 'center',
  },
  fullscreenImage: {
    width: '100%',
    height: '100%',
  },
  loadingBox: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    zIndex: 2,
  },
  loadingText: {
    color: '#fbbf24',
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  bottomControls: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
    paddingBottom: Platform.OS === 'ios' ? 36 : 20,
    paddingTop: 10,
    zIndex: 10,
  },
  controlBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.18)',
  },
  controlBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  resetBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: 'rgba(245, 158, 11, 0.25)',
    borderWidth: 1,
    borderColor: '#fbbf24',
  },
  resetBtnText: {
    color: '#fbbf24',
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
});

const imgNodeStyles = StyleSheet.create({
  container: {
    width: '100%',
    marginVertical: 10,
    alignSelf: 'center',
  },
  touchWrap: {
    width: '100%',
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1.2,
    backgroundColor: 'rgba(0, 0, 0, 0.05)',
    position: 'relative',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.08)',
  },
  zoomBadge: {
    position: 'absolute',
    bottom: 8,
    right: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(255, 255, 255, 0.3)',
  },
  zoomBadgeText: {
    color: '#ffffff',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
});

const iframeStyles = StyleSheet.create({
  container: {
    marginVertical: 10,
    alignSelf: 'center',
  },
  webContainer: {
    aspectRatio: 16 / 9,
    borderRadius: 14,
    overflow: 'hidden',
    marginVertical: 10,
    borderWidth: 1,
    alignSelf: 'center',
  },
  touchCard: {
    width: '100%',
    aspectRatio: 16 / 9,
    borderRadius: 14,
    overflow: 'hidden',
    backgroundColor: '#0f172a',
    position: 'relative',
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  thumbnail: {
    ...StyleSheet.absoluteFill,
    width: '100%',
    height: '100%',
  },
  placeholderBg: {
    ...StyleSheet.absoluteFill,
    backgroundColor: '#1e293b',
  },
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0, 0, 0, 0.35)',
  },
  playButton: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: 'rgba(239, 68, 68, 0.95)',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 6,
    elevation: 8,
  },
  titleBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
  },
  titleText: {
    color: '#ffffff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    textAlign: 'right',
  },
});
