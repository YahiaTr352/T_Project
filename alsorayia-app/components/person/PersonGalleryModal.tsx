import React, { useCallback, useEffect, useState } from 'react';
import {
  Modal,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  ActivityIndicator,
  useWindowDimensions,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { fetchPersonImages, type PersonImageItem } from '@/lib/api/familyTreeApi';

import { ImageViewerModal, type ViewerImage } from '@/components/ui/zoomable-image';

interface PersonGalleryModalProps {
  visible: boolean;
  onClose: () => void;
  personId: number;
  personName?: string;
  colors: any;
  initialCount?: number;
}

const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

export const PersonGalleryModal: React.FC<PersonGalleryModalProps> = ({
  visible,
  onClose,
  personId,
  personName,
  colors,
  initialCount = 0,
}) => {
  const insets = useSafeAreaInsets();
  const { width: windowWidth, height: windowHeight } = useWindowDimensions();

  const [images, setImages] = useState<PersonImageItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [viewerIndex, setViewerIndex] = useState<number | null>(null);

  const numColumns = 2;
  const cardWidth = (windowWidth - 32 - 12) / numColumns;

  const loadImages = useCallback(async () => {
    if (!personId) return;
    setLoading(true);
    setError(false);
    try {
      const res = await fetchPersonImages(personId);
      if (res.success && res.data) {
        setImages(res.data);
      } else {
        setImages([]);
      }
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  }, [personId]);

  useEffect(() => {
    if (visible) {
      loadImages();
    }
  }, [visible, loadImages]);

  const handleClose = () => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setViewerIndex(null);
    onClose();
  };

  const handleSelectImage = (index: number) => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setViewerIndex(index);
  };

  const viewerImages: ViewerImage[] = React.useMemo(() => {
    return images
      .map((item) => ({
        uri: item.image_url || item.thumbnail_url || '',
        title: item.category?.name ? `${item.category.name}` : personName ? `صور ${personName}` : null,
      }))
      .filter((img) => !!img.uri);
  }, [images, personName]);

  return (
    <>
      <Modal
        visible={visible && viewerIndex === null}
        transparent
        animationType="slide"
        onRequestClose={handleClose}
      >
        <View style={styles.overlay}>
          <View
            style={[
              styles.container,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                paddingBottom: Math.max(insets.bottom, 16),
                maxHeight: windowHeight * 0.88,
              },
            ]}
          >
            {/* مقبض السحب للأعلى */}
            <View style={styles.dragHandleWrap}>
              <View style={[styles.dragHandle, { backgroundColor: colors.border }]} />
            </View>

            {/* رأس المودال */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={handleClose}
                style={[styles.closeBtn, { backgroundColor: colors.primary + '15' }]}
                hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
              >
                <Ionicons name="close" size={20} color={colors.text} />
              </TouchableOpacity>

              <View style={styles.headerTitleWrap}>
                <Text style={[styles.headerTitle, { color: colors.text }]}>
                  معرض صور {personName ? `«${personName}»` : ''}
                </Text>
                <Text style={[styles.headerSubtitle, { color: colors.icon }]}>
                  {images.length > 0 ? `${images.length} صورة` : `${initialCount} صورة`}
                </Text>
              </View>

              <View style={{ width: 36 }} />
            </View>

            {/* محتوى المعرض */}
            {loading ? (
              <View style={styles.loaderWrap}>
                <ActivityIndicator size="large" color={colors.primary} />
                <Text style={[styles.loadingText, { color: colors.icon }]}>جاري تحميل الصور...</Text>
              </View>
            ) : error ? (
              <View style={styles.emptyWrap}>
                <Ionicons name="alert-circle-outline" size={54} color="#e74c3c" />
                <Text style={[styles.emptyText, { color: colors.text }]}>تعذر جلب الصور</Text>
                <TouchableOpacity
                  style={[styles.retryBtn, { backgroundColor: colors.primary }]}
                  onPress={loadImages}
                >
                  <Text style={styles.retryText}>إعادة المحاولة</Text>
                </TouchableOpacity>
              </View>
            ) : images.length === 0 ? (
              <View style={styles.emptyWrap}>
                <Ionicons name="images-outline" size={54} color={colors.icon} style={{ opacity: 0.6 }} />
                <Text style={[styles.emptyText, { color: colors.text }]}>لا توجد صور متوفرة لهذا العضو حالياً</Text>
              </View>
            ) : (
              <FlatList
                data={images}
                keyExtractor={(item) => String(item.id)}
                numColumns={numColumns}
                showsVerticalScrollIndicator={false}
                contentContainerStyle={styles.listContent}
                columnWrapperStyle={styles.columnWrapper}
                renderItem={({ item, index }) => {
                  const uri = item.image_url || item.thumbnail_url;
                  return (
                    <TouchableOpacity
                      style={[
                        styles.imageCard,
                        {
                          width: cardWidth,
                          backgroundColor: colors.background,
                          borderColor: colors.border,
                        },
                      ]}
                      activeOpacity={0.8}
                      onPress={() => handleSelectImage(index)}
                    >
                      <Image
                        source={{ uri: uri || '' }}
                        style={styles.imageThumb}
                        contentFit="cover"
                        transition={200}
                        placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
                      />
                      {item.category?.name ? (
                        <View style={[styles.categoryBadge, { backgroundColor: 'rgba(0,0,0,0.65)' }]}>
                          <Text style={styles.categoryText} numberOfLines={1}>
                            {item.category.name}
                          </Text>
                        </View>
                      ) : null}
                    </TouchableOpacity>
                  );
                }}
              />
            )}
          </View>
        </View>
      </Modal>

      {/* عارض الصور الموحد المتكامل (زوم + تكبير + تحميل + تقليب بين الصور) */}
      {viewerIndex !== null && viewerImages.length > 0 && (
        <ImageViewerModal
          visible={viewerIndex !== null}
          images={viewerImages}
          initialIndex={viewerIndex ?? 0}
          onClose={() => setViewerIndex(null)}
        />
      )}
    </>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'flex-end',
  },
  container: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingTop: 10,
    paddingHorizontal: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -6 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  dragHandleWrap: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  dragHandle: {
    width: 44,
    height: 4.5,
    borderRadius: 3,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(150,150,150,0.15)',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    alignItems: 'center',
    flex: 1,
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  headerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  listContent: {
    paddingVertical: 16,
    gap: 12,
  },
  columnWrapper: {
    justifyContent: 'space-between',
  },
  imageCard: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
    height: 150,
    position: 'relative',
  },
  imageThumb: {
    width: '100%',
    height: '100%',
  },
  categoryBadge: {
    position: 'absolute',
    bottom: 6,
    left: 6,
    right: 6,
    borderRadius: 8,
    paddingHorizontal: 6,
    paddingVertical: 3,
  },
  categoryText: {
    color: '#fff',
    fontSize: 10,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  loaderWrap: {
    paddingVertical: 60,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
  },
  emptyWrap: {
    paddingVertical: 60,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  emptyText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 20,
    marginTop: 6,
  },
  retryText: {
    color: '#fff',
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },
});
