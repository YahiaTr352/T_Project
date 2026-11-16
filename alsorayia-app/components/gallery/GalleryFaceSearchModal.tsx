import { Colors, silaTheme } from '@/constants/theme';
import { imageUriToBase64 } from '@/lib/api/authApi';
import {
  GalleryFaceSearchResult,
  GalleryImageItem,
  searchGalleryByFace,
} from '@/lib/api/gallery';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  FlatList,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';


const { width: SCREEN_W } = Dimensions.get('window');
const GRID_PADDING = 16;
const GRID_GAP = 12;
const CARD_WIDTH = Math.floor((SCREEN_W - GRID_PADDING * 2 - GRID_GAP) / 2);
const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

interface GalleryFaceSearchModalProps {
  visible: boolean;
  onClose: () => void;
  onSearchResults?: (result: GalleryFaceSearchResult) => void;
  onSelectImage?: (item: GalleryImageItem) => void;
}

const getPhotoUri = (item: GalleryImageItem): string => {
  return item.thumbnail_url?.trim() || item.image_url?.trim() || item.youtube_thumbnail_url?.trim() || '';
};

export const GalleryFaceSearchModal: React.FC<GalleryFaceSearchModalProps> = ({
  visible,
  onClose,
  onSearchResults,
  onSelectImage,
}) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const T = isDark ? Colors.dark : Colors.light;

  const [isScanning, setIsScanning] = useState(false);
  const [searchResult, setSearchResult] = useState<GalleryFaceSearchResult | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [selectedImageUri, setSelectedImageUri] = useState<string | null>(null);

  // Pulse animation for scanner
  const [pulseAnim] = useState(() => new Animated.Value(1));

  useEffect(() => {
    if (isScanning) {
      Animated.loop(
        Animated.sequence([
          Animated.timing(pulseAnim, {
            toValue: 1.15,
            duration: 800,
            useNativeDriver: true,
          }),
          Animated.timing(pulseAnim, {
            toValue: 1,
            duration: 800,
            useNativeDriver: true,
          }),
        ])
      ).start();
    } else {
      pulseAnim.setValue(1);
    }
  }, [isScanning, pulseAnim]);

  const resetState = useCallback(() => {
    setIsScanning(false);
    setSearchResult(null);
    setSelectedCategory(null);
    setSelectedImageUri(null);
  }, []);

  const handleClose = useCallback(() => {
    resetState();
    onClose();
  }, [resetState, onClose]);

  const performSearch = useCallback(async (asset: ImagePicker.ImagePickerAsset) => {
    setIsScanning(true);
    setSelectedImageUri(asset.uri);
    setSearchResult(null);
    setSelectedCategory(null);

    try {
      let base64 = asset.base64;
      if (!base64) {
        try {
          base64 = await imageUriToBase64(asset.uri);
        } catch { }
      }

      let res: GalleryFaceSearchResult | null = null;

      if (base64) {
        const dataUri = base64.startsWith('data:') ? base64 : `data:image/jpeg;base64,${base64}`;
        res = await searchGalleryByFace({ image_base64: dataUri });
      }

      if (!res || !res.ok) {
        const localUri = asset.uri;
        const cleanUri = Platform.OS === 'android' ? localUri : localUri.replace('file://', '');
        const rawExt = localUri.split('.').pop()?.split('?')[0]?.toLowerCase() || 'jpg';
        const ext = ['jpg', 'jpeg', 'png', 'webp'].includes(rawExt) ? (rawExt === 'jpeg' ? 'jpg' : rawExt) : 'jpg';
        const cleanFilename = `face_gallery_${Date.now()}.${ext}`;
        const mimeType = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';

        const formData = new FormData();
        formData.append('image', {
          uri: cleanUri,
          name: cleanFilename,
          type: mimeType,
        } as any);

        res = await searchGalleryByFace(formData);
      }

      if (res && res.ok && res.items && res.items.length > 0) {
        console.log('[FaceSearchModal] Search success. items count:', res.items.length, 'categories count:', res.categories?.length);
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        if (onSearchResults) {
          onSearchResults(res);
          handleClose();
          return;
        }
        setSearchResult(res);
      } else if (res && res.ok) {
        // ok but 0 items
        setSearchResult({
          ok: false,
          message: 'لم نتمكن من العثور على صور مطابقة لهذا الوجه.',
          identified_person: res.identified_person || null,
          items: [],
          categories: [],
        });
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      } else {
        setSearchResult({
          ok: false,
          message: res?.message || 'لم نتمكن من العثور على أي تطابق.',
          identified_person: res?.identified_person || null,
          items: [],
          categories: [],
        });
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      }
    } catch (err: any) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('خطأ', 'تعذر الاتصال بخدمة التعرف على الوجوه.');
    } finally {
      setIsScanning(false);
    }
  }, []);

  const handleTakePhoto = useCallback(async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول إلى الكاميرا لالتقاط صورة سيلفي.');
      return;
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ['images'],
      cameraType: ImagePicker.CameraType.front,
      allowsEditing: true,
      quality: 0.75,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      await performSearch(result.assets[0]);
    }
  }, [performSearch]);

  const handlePickImage = useCallback(async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول إلى معرض الصور لاختيار صورة.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.75,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      await performSearch(result.assets[0]);
    }
  }, [performSearch]);

  const filteredItems = useMemo(() => {
    if (!searchResult || !searchResult.items) return [];
    if (selectedCategory === null) return searchResult.items;
    return searchResult.items.filter((item) => {
      const catId = item.category?.id ?? item.category_id;
      return Number(catId) === Number(selectedCategory);
    });
  }, [searchResult, selectedCategory]);

  const renderGridItem = useCallback(
    ({ item }: { item: GalleryImageItem }) => {
      const photoUri = getPhotoUri(item);
      return (
        <TouchableOpacity
          key={item.id}
          style={[styles.photoCard, { width: CARD_WIDTH, height: CARD_WIDTH }]}
          onPress={() => {
            handleClose();
            onSelectImage?.(item);
          }}

          activeOpacity={0.88}
        >
          {photoUri ? (
            <Image
              source={{ uri: photoUri }}
              style={StyleSheet.absoluteFill}
              contentFit="cover"
              transition={200}
              cachePolicy="memory-disk"
              recyclingKey={String(item.id)}
              placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
              placeholderContentFit="cover"
            />
          ) : (
            <View style={[styles.photoCardPlaceholder, { backgroundColor: isDark ? '#1e293b' : '#f1f5f9' }]}>
              <Ionicons name="image-outline" size={32} color={isDark ? '#475569' : '#94a3b8'} />
            </View>
          )}

          <LinearGradient
            colors={['transparent', 'rgba(0,0,0,0.8)']}
            style={StyleSheet.absoluteFill}
          />

          {item.similarity ? (
            <View style={styles.simBadge}>
              <Text style={styles.simBadgeText}>{item.similarity}</Text>
            </View>
          ) : null}

          <View style={styles.photoFooter}>
            <Text style={styles.photoCategoryTitle} numberOfLines={1}>
              {item.category?.name || item.name || 'صورة عائلية'}
            </Text>
          </View>
        </TouchableOpacity>
      );
    },
    [handleClose, onSelectImage, isDark]
  );

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={handleClose}>
      <View style={styles.modalOverlay}>
        <View style={[styles.modalCard, { backgroundColor: isDark ? '#121926' : '#ffffff' }]}>
          {/* Top Drag Handle Indicator */}
          <View style={styles.dragHandleWrap}>
            <View
              style={[
                styles.dragHandle,
                { backgroundColor: isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.15)' },
              ]}
            />
          </View>

          {/* Fixed Header */}
          <View
            style={[
              styles.headerRow,
              { borderBottomColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)' },
            ]}
          >
            <View style={styles.headerRightGroup}>
              <View
                style={[
                  styles.headerIconBox,
                  { backgroundColor: isDark ? 'rgba(55,160,92,0.2)' : '#DCF2DD' },
                ]}
              >
                <Ionicons name="scan" size={20} color={silaTheme.primary} />
              </View>
              <View style={styles.headerTitleWrap}>
                <Text style={[styles.headerTitle, { color: isDark ? '#ffffff' : '#111827' }]}>
                  البحث عن الصور بالوجه
                </Text>
                <Text style={[styles.headerSubtitle, { color: isDark ? '#9ca3af' : '#6b7280' }]}>
                  البحث الذكي بالسيلفي في معرض العائلة
                </Text>
              </View>
            </View>

            <TouchableOpacity
              onPress={handleClose}
              style={[
                styles.closeBtn,
                { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)' },
              ]}
              activeOpacity={0.7}
            >
              <Ionicons name="close" size={20} color={isDark ? '#cbd5e1' : '#4b5563'} />
            </TouchableOpacity>
          </View>

          {/* State 1: Input Selection State (No search yet) */}
          {!isScanning && !searchResult && (
            <ScrollView
              contentContainerStyle={styles.bodyScrollContent}
              showsVerticalScrollIndicator={false}
            >
              <View style={styles.selectorWrap}>
                <View style={styles.avatarScanFrame}>
                  <View
                    style={[
                      styles.scanCircle,
                      {
                        backgroundColor: isDark ? 'rgba(55,160,92,0.15)' : '#DCF2DD',
                        borderColor: silaTheme.primary,
                      },
                    ]}
                  >
                    <Ionicons name="person" size={44} color={silaTheme.primary} />
                  </View>
                  <Text style={[styles.scanPromptTitle, { color: isDark ? '#f8fafc' : '#111827' }]}>
                    التقط صورة سيلفي أو اختر صورة لك
                  </Text>
                  <Text style={[styles.scanPromptDesc, { color: isDark ? '#9ca3af' : '#6b7280' }]}>
                    سنقوم بمطابقة ملامح وجهك بالذكاء الاصطناعي مع جميع الصور والفعاليات في المعرض العائلي
                  </Text>
                </View>

                <View style={styles.actionsRow}>
                  <TouchableOpacity
                    style={[styles.actionBtn, styles.primaryActionBtn, { backgroundColor: silaTheme.primary }]}
                    onPress={handleTakePhoto}
                    activeOpacity={0.88}
                  >
                    <Ionicons name="camera" size={20} color="#ffffff" />
                    <Text style={styles.primaryActionBtnText}>التقاط سيلفي 🤳</Text>
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[
                      styles.actionBtn,
                      styles.secondaryActionBtn,
                      {
                        backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#f8fafa',
                        borderColor: isDark ? 'rgba(255,255,255,0.12)' : '#e2ebe7',
                      },
                    ]}
                    onPress={handlePickImage}
                    activeOpacity={0.88}
                  >
                    <Ionicons name="images-outline" size={20} color={isDark ? '#f8fafc' : '#111827'} />
                    <Text style={[styles.secondaryActionBtnText, { color: isDark ? '#f8fafc' : '#111827' }]}>
                      رفع من الألبوم
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>
            </ScrollView>
          )}

          {/* State 2: Scanning In Progress */}
          {isScanning && (
            <View style={styles.scanningWrap}>
              <Animated.View
                style={[
                  styles.scanningAnimCircle,
                  {
                    borderColor: silaTheme.primary,
                    transform: [{ scale: pulseAnim }],
                  },
                ]}
              >
                {selectedImageUri ? (
                  <Image source={{ uri: selectedImageUri }} style={styles.scanningPreviewImg} contentFit="cover" />
                ) : (
                  <ActivityIndicator size="large" color={silaTheme.primary} />
                )}
              </Animated.View>
              <Text style={[styles.scanningTitle, { color: isDark ? '#f8fafc' : '#111827' }]}>
                جاري تحليل الوجه ومطابقته…
              </Text>
              <Text style={[styles.scanningDesc, { color: isDark ? '#9ca3af' : '#6b7280' }]}>
                نبحث في أرشيف المعرض والفعاليات العائلية
              </Text>
            </View>
          )}

          {/* State 3: Search Results Grid (Virtualized FlatList) */}
          {!isScanning && searchResult && (
            <FlatList
              style={styles.resultsFlatList}
              contentContainerStyle={[
                styles.resultsListContent,
                { paddingBottom: Platform.OS === 'ios' ? 44 : 28 },
              ]}
              data={filteredItems}
              keyExtractor={(item) => String(item.id)}
              numColumns={2}
              columnWrapperStyle={filteredItems.length > 1 ? styles.columnWrapper : undefined}
              showsVerticalScrollIndicator={true}
              initialNumToRender={8}
              maxToRenderPerBatch={10}
              windowSize={7}
              removeClippedSubviews={Platform.OS === 'android'}
              ListHeaderComponent={
                <View style={styles.listHeaderWrap}>
                  {/* Person Identified Badge */}
                  {searchResult.identified_person && (
                    <View
                      style={[
                        styles.personBadge,
                        {
                          backgroundColor: isDark ? 'rgba(55,160,92,0.18)' : '#DCF2DD',
                          borderColor: isDark ? 'rgba(55,160,92,0.35)' : '#bbf7d0',
                        },
                      ]}
                    >
                      <Image
                        source={{
                          uri:
                            searchResult.identified_person.avatar ||
                            'https://alsorayia.com/assets/img/default-avatar.png',
                        }}
                        style={styles.personAvatar}
                        contentFit="cover"
                      />
                      <View style={styles.personInfo}>
                        <View style={styles.personHeaderRow}>
                          <Ionicons name="checkmark-circle" size={15} color={silaTheme.primary} />
                          <Text
                            style={[
                              styles.personGreeting,
                              { color: isDark ? silaTheme.primaryLight : silaTheme.primaryDark },
                            ]}
                          >
                            تم التعرف على الشخصية
                          </Text>
                        </View>
                        <Text style={[styles.personName, { color: isDark ? '#ffffff' : '#111827' }]}>
                          {searchResult.identified_person.full_name}
                        </Text>
                      </View>
                      <View style={styles.similarityPill}>
                        <Text style={styles.similarityText}>
                          مطابقة {searchResult.identified_person.similarity}
                        </Text>
                      </View>
                    </View>
                  )}

                  {/* Categories Horizontal Tabs */}
                  {searchResult.categories && searchResult.categories.length > 0 && (
                    <View style={styles.categoryScrollOuter}>
                      <ScrollView
                        horizontal
                        showsHorizontalScrollIndicator={false}
                        contentContainerStyle={styles.categoryPillsScroll}
                      >
                        <TouchableOpacity
                          style={[
                            styles.catPill,
                            selectedCategory === null
                              ? { backgroundColor: silaTheme.primary }
                              : {
                                backgroundColor: isDark
                                  ? 'rgba(255,255,255,0.08)'
                                  : '#f3f4f6',
                              },
                          ]}
                          onPress={() => setSelectedCategory(null)}
                          activeOpacity={0.88}
                        >
                          <Text
                            style={[
                              styles.catPillText,
                              selectedCategory === null
                                ? { color: '#ffffff', fontFamily: 'Tajawal-Bold' }
                                : { color: isDark ? '#9ca3af' : '#4b5563' },
                            ]}
                          >
                            الكل ({searchResult.total || searchResult.items?.length || 0})
                          </Text>
                        </TouchableOpacity>

                        {searchResult.categories.map((cat) => (
                          <TouchableOpacity
                            key={cat.id}
                            style={[
                              styles.catPill,
                              selectedCategory === cat.id
                                ? { backgroundColor: silaTheme.primary }
                                : {
                                  backgroundColor: isDark
                                    ? 'rgba(255,255,255,0.08)'
                                    : '#f3f4f6',
                                },
                            ]}
                            onPress={() => setSelectedCategory(cat.id)}
                            activeOpacity={0.88}
                          >
                            <Text
                              style={[
                                styles.catPillText,
                                selectedCategory === cat.id
                                  ? { color: '#ffffff', fontFamily: 'Tajawal-Bold' }
                                  : { color: isDark ? '#9ca3af' : '#4b5563' },
                              ]}
                            >
                              {cat.name} ({cat.count})
                            </Text>
                          </TouchableOpacity>
                        ))}
                      </ScrollView>
                    </View>
                  )}

                  {/* Results Count & Clear Filter Bar */}
                  <View style={styles.resultsCountRow}>
                    <Text style={[styles.resultsCountText, { color: isDark ? '#cbd5e1' : '#4b5563' }]}>
                      {filteredItems.length === 1
                        ? 'صورة واحدة مطابقة'
                        : filteredItems.length === 2
                          ? 'صورتان مطابقتان'
                          : `${filteredItems.length} صورة مطابقة`}
                    </Text>
                    {selectedCategory !== null && (
                      <TouchableOpacity
                        onPress={() => setSelectedCategory(null)}
                        style={styles.clearFilterBtn}
                        activeOpacity={0.7}
                      >
                        <Ionicons name="close-circle" size={14} color={silaTheme.primary} />
                        <Text style={styles.clearFilterText}>عرض الكل</Text>
                      </TouchableOpacity>
                    )}
                  </View>
                </View>
              }
              renderItem={renderGridItem}
              ListEmptyComponent={
                <View style={styles.emptyContainer}>
                  <Ionicons name="images-outline" size={48} color={isDark ? '#4b5563' : '#9ca3af'} />
                  <Text style={[styles.emptyTitle, { color: isDark ? '#f8fafc' : '#111827' }]}>
                    {searchResult.identified_person
                      ? `لا توجد صور في هذا التصنيف`
                      : 'لم نجد صوراً تطابق هذا الوجه'}
                  </Text>
                  <Text style={[styles.emptySubtitle, { color: isDark ? '#9ca3af' : '#6b7280' }]}>
                    {searchResult.identified_person
                      ? 'اضغط على "عرض الكل" بالأعلى للاطلاع على كافة الصور المطابقة لهذا الشخص.'
                      : 'يرجى المحاولة باستخدام صورة سيلفي بأبعاد ووضوح أفضل.'}
                  </Text>
                </View>
              }
              ListFooterComponent={
                <TouchableOpacity
                  style={[
                    styles.reSearchBtn,
                    {
                      borderColor: isDark ? 'rgba(255,255,255,0.15)' : '#e2ebe7',
                      backgroundColor: isDark ? 'rgba(255,255,255,0.04)' : '#f8fafa',
                    },
                  ]}
                  onPress={resetState}
                  activeOpacity={0.8}
                >
                  <Ionicons name="refresh-outline" size={18} color={isDark ? '#cbd5e1' : '#4b5563'} />
                  <Text style={[styles.reSearchText, { color: isDark ? '#cbd5e1' : '#4b5563' }]}>
                    البحث بصورة أخرى
                  </Text>
                </TouchableOpacity>
              }
            />
          )}
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.65)',
    justifyContent: 'flex-end',
  },
  modalCard: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    height: '92%',
    overflow: 'hidden',
  },
  dragHandleWrap: {
    alignItems: 'center',
    paddingVertical: 10,
  },
  dragHandle: {
    width: 38,
    height: 5,
    borderRadius: 3,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingBottom: 14,
    borderBottomWidth: 1,
  },
  headerRightGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  headerIconBox: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    flex: 1,
    alignItems: 'flex-start',
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',

  },
  headerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,

  },
  closeBtn: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 17,
  },
  bodyScrollContent: {
    padding: 18,
  },
  selectorWrap: {
    alignItems: 'center',
    paddingVertical: 20,
  },
  avatarScanFrame: {
    alignItems: 'center',
    marginBottom: 24,
  },
  scanCircle: {
    width: 90,
    height: 90,
    borderRadius: 45,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  scanPromptTitle: {
    fontSize: 17,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    marginBottom: 8,
  },
  scanPromptDesc: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    paddingHorizontal: 16,
    lineHeight: 20,
  },
  actionsRow: {
    width: '100%',
    gap: 12,
  },
  actionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 14,
    borderRadius: 16,
    width: '100%',
  },
  primaryActionBtn: {
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.darkGreen,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.25,
        shadowRadius: 8,
      },
      android: { elevation: 4 },
    }),
  },
  primaryActionBtnText: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    color: '#ffffff',
  },
  secondaryActionBtn: {
    borderWidth: 1.5,
  },
  secondaryActionBtnText: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
  },
  scanningWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  scanningAnimCircle: {
    width: 120,
    height: 120,
    borderRadius: 60,
    borderWidth: 3,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
    backgroundColor: '#000000',
  },
  scanningPreviewImg: {
    width: '100%',
    height: '100%',
  },
  scanningTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 6,
    textAlign: 'center',
  },
  scanningDesc: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  resultsFlatList: {
    flex: 1,
  },
  resultsListContent: {
    paddingHorizontal: GRID_PADDING,
    paddingTop: 14,
  },
  listHeaderWrap: {
    width: '100%',
    marginBottom: 10,
  },
  personBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 14,
  },
  personAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
  },
  personInfo: {
    flex: 1,
    marginHorizontal: 12,
    alignItems: 'flex-start',
  },
  personHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  personGreeting: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  personName: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    marginTop: 1,
  },
  similarityPill: {
    backgroundColor: 'rgba(55,160,92,0.2)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  similarityText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  categoryScrollOuter: {
    marginBottom: 12,
  },
  categoryPillsScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 2,
  },
  catPill: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 18,
  },
  catPillText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  resultsCountRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 4,
    marginBottom: 6,
  },
  resultsCountText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  clearFilterBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 2,
    paddingHorizontal: 6,
  },
  clearFilterText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  columnWrapper: {
    justifyContent: 'space-between',
    marginBottom: GRID_GAP,
  },
  photoCard: {
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: '#1e293b',
    position: 'relative',
  },
  photoCardPlaceholder: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
  },

  simBadge: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: 'rgba(27,100,55,0.88)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
    zIndex: 2,
  },
  simBadgeText: {
    color: '#ffffff',
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  photoFooter: {
    position: 'absolute',
    bottom: 8,
    left: 10,
    right: 10,
    alignItems: 'flex-start',
    zIndex: 2,
  },
  photoCategoryTitle: {
    color: '#ffffff',
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  emptyContainer: {
    alignItems: 'center',
    paddingVertical: 40,
    paddingHorizontal: 20,
  },
  emptyTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    marginTop: 12,
  },
  emptySubtitle: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    marginTop: 6,
    lineHeight: 18,
  },
  reSearchBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 20,
    marginBottom: 10,
    paddingVertical: 13,
    borderWidth: 1,
    borderRadius: 14,
  },
  reSearchText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
});
