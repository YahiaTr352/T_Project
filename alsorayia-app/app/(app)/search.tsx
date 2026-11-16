import { searchAndRankPersons } from '@/lib/utils/searchEngine';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useRouter } from 'expo-router';
import React, { useMemo, useState, useEffect } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
    ScrollView,
    StyleSheet,
    TextInput,
    TouchableOpacity,
    View,
    Image,
    ActivityIndicator,
    useColorScheme,
    I18nManager,
    Modal,
    Pressable,
    Platform,
} from 'react-native';

import { fetchPersonsSearchList, searchPersonByImage, WEB_BASE_URL } from '@/lib/api/familyTreeApi';
import { imageUriToBase64 } from '@/lib/api/authApi';
import * as ImagePicker from 'expo-image-picker';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import { appStorage } from '@/lib/storage/appStorage';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withRepeat,
  withTiming,
  Easing,
} from 'react-native-reanimated';
import { LinearGradient } from 'expo-linear-gradient';
import * as Haptics from 'expo-haptics';

export function resolveAvatarUrl(p: any): string | null {
  if (!p) return null;
  const raw = p.avatar || p.photo_url || p.photo;
  if (!raw || typeof raw !== 'string' || !raw.trim()) return null;
  const u = raw.trim();
  if (/^https?:\/\//i.test(u)) {
    if (u.includes('localhost') || u.includes('127.0.0.1')) {
      return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, WEB_BASE_URL);
    }
    return u;
  }
  const base = WEB_BASE_URL.replace(/\/$/, '');
  return `${base}/${u.replace(/^\//, '')}`;
}

const formatSimilarity = (val: any) => {
  if (val === undefined || val === null) return '';
  if (typeof val === 'string' && val.includes('%')) return val;
  const num = parseFloat(val);
  if (!isNaN(num)) {
    if (num <= 1.0) {
      return `${Math.round(num * 100)}%`;
    }
    return `${Math.round(num)}%`;
  }
  return String(val);
};

const getSimilarityColor = (val: any): [string, string] => {
  const num = parseFloat(val);
  if (isNaN(num)) return ['#37a05c', '#166534'];
  const percent = num <= 1.0 ? num * 100 : num;
  if (percent >= 85) return ['#10B981', '#059669']; // Emerald Green
  if (percent >= 70) return ['#F59E0B', '#D97706']; // Amber Orange
  return ['#6B7280', '#4B5563']; // Slate Gray
};


const ACCENT_COLORS = {
  family: '#3B82F6',
};

const RECENT_KEY = 'recent_person_searches';

export default function SearchScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const router = useRouter();
  
  const [query, setQuery] = useState('');
  const [allPersons, setAllPersons] = useState<any[]>([]);
  const [recentSearches, setRecentSearches] = useState<any[]>([]);
  const [loadingDb, setLoadingDb] = useState(false);

  // Image search states
  const [imageLoading, setImageLoading] = useState(false);
  const [imageMatches, setImageMatches] = useState<any[]>([]);
  const [isImageSearchActive, setIsImageSearchActive] = useState(false);
  const [selectedImage, setSelectedImage] = useState<string | null>(null);
  const [isPickerVisible, setIsPickerVisible] = useState(false);

  // Reanimated Shared Values for Face Scanner
  const scanProgress = useSharedValue(0);
  const radarScale = useSharedValue(1);
  const radarOpacity = useSharedValue(0.6);

  useEffect(() => {
    if (imageLoading) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      
      // Laser scan loop back and forth
      scanProgress.value = withRepeat(
        withTiming(1, { duration: 1600, easing: Easing.inOut(Easing.ease) }),
        -1,
        true
      );

      // Radar pulse loop
      radarScale.value = withRepeat(
        withTiming(1.6, { duration: 1800, easing: Easing.out(Easing.ease) }),
        -1,
        false
      );
      radarOpacity.value = withRepeat(
        withTiming(0, { duration: 1800, easing: Easing.out(Easing.ease) }),
        -1,
        false
      );
    } else {
      scanProgress.value = 0;
      radarScale.value = 1;
      radarOpacity.value = 0.6;
    }
  }, [imageLoading, radarOpacity, radarScale, scanProgress]);

  const scanLineStyle = useAnimatedStyle(() => {
    return {
      transform: [
        {
          translateY: scanProgress.value * 180, // Height of the scanner container
        },
      ],
    };
  });

  const radarStyle1 = useAnimatedStyle(() => {
    return {
      transform: [{ scale: radarScale.value }],
      opacity: radarOpacity.value,
    };
  });

  const handleImageSearch = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setIsPickerVisible(true);
  };

  const takePhoto = async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('صلاحية مرفوضة', 'نحتاج للوصول للكاميرا لالتقاط صورة للبحث.');
      return;
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      setSelectedImage(result.assets[0].uri);
      uploadImageForSearch(result.assets[0]);
    }
  };

  const pickImage = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('صلاحية مرفوضة', 'نحتاج للوصول لمعرض الصور لاختيار صورة للبحث.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      setSelectedImage(result.assets[0].uri);
      uploadImageForSearch(result.assets[0]);
    }
  };

  const uploadImageForSearch = async (asset: ImagePicker.ImagePickerAsset) => {
    setQuery(''); // clear textual search
    setIsImageSearchActive(true);
    setImageLoading(true);
    setImageMatches([]);

    try {
      let base64 = asset.base64;
      if (!base64) {
        try {
          base64 = await imageUriToBase64(asset.uri);
        } catch {}
      }

      let res: any = null;

      if (base64) {
        const dataUri = base64.startsWith('data:') ? base64 : `data:image/jpeg;base64,${base64}`;
        res = await searchPersonByImage({ image_base64: dataUri });
      }

      // إذا رفض السيرفر القديم Base64 (طلب حقل image)، نجرّب الرفع المباشر كـ FormData كبديل
      if (!res || (res?.errors?.image && !res?.data)) {
        const localUri = asset.uri;
        const cleanUri = Platform.OS === 'android' ? localUri : localUri.replace('file://', '');
        const rawExt = localUri.split('.').pop()?.split('?')[0]?.toLowerCase() || 'jpg';
        const ext = ['jpg', 'jpeg', 'png', 'webp'].includes(rawExt) ? (rawExt === 'jpeg' ? 'jpg' : rawExt) : 'jpg';
        const cleanFilename = `face_search_${Date.now()}.${ext}`;
        const mimeType = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';

        const formData = new FormData();
        formData.append('image', {
          uri: cleanUri,
          name: cleanFilename,
          type: mimeType,
        } as any);

        res = await searchPersonByImage(formData);
      }

      if (res && res.status === 'success' && res.data) {
        setImageMatches(res.data);
        if (res.data.length > 0) {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } else {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
          CustomAlert.alert('نتيجة البحث', res.message || 'لم نتمكن من العثور على أي تطابق للوجه في شجرة العائلة.');
        }
      } else {
        setImageMatches([]);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
        if (res?.message) {
          CustomAlert.alert('تنبيه', res.message);
        }
      }
    } catch (e: any) {
      console.error('Image search failed', e);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('خطأ', e?.message || 'فشل الاتصال بخدمة التعرف على الوجوه.');
      setImageMatches([]);
    } finally {
      setImageLoading(false);
    }
  };



  useEffect(() => {
    // Load recent history
    appStorage.getItem(RECENT_KEY).then(res => {
      if (res) {
        try {
          const parsed = JSON.parse(res);
          if (Array.isArray(parsed)) setRecentSearches(parsed);
        } catch {}
      }
    }).catch(() => {});

    // Fetch catalog
    setLoadingDb(true);
    fetchPersonsSearchList().then(res => {
      setLoadingDb(false);
      // Ensure we have an array
      if (res && Array.isArray(res.persons)) {
        setAllPersons(res.persons);
      } else if (Array.isArray(res)) {
        setAllPersons(res);
      }
    }).catch(() => setLoadingDb(false));
  }, []);

  const hasQuery = query.trim().length > 0;

  const filteredFamily = useMemo(() => {
    return searchAndRankPersons(allPersons, query, 30);
  }, [query, allPersons]);

  const handleSelectPerson = async (person: any) => {
    if (!person?.id) return;
    
    // Save to recents first to avoid delay
    const filtered = recentSearches.filter(p => p.id !== person.id);
    const updated = [person, ...filtered].slice(0, 10);
    setRecentSearches(updated);
    appStorage.setItem(RECENT_KEY, JSON.stringify(updated)).catch(() => {});

    // Navigate to the dedicated person page
    router.push(`/(app)/person/${person.id}`);
  };

  const handleRemoveRecent = (id: number) => {
    const updated = recentSearches.filter(p => p.id !== id);
    setRecentSearches(updated);
    appStorage.setItem(RECENT_KEY, JSON.stringify(updated)).catch(() => {});
  };

  const handleClearHistory = () => {
    setRecentSearches([]);
    appStorage.removeItem(RECENT_KEY).catch(() => {});
  };

  return (
    <ThemedView style={styles.container}>
      <HeaderBar
        title="البحث السريع"
        subtitle="ابحث عن أفراد العائلة مباشرة"
        showBack
        onBackPress={() => router.back()}
      />

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        {/* Search Input Area */}
        <View style={[styles.searchBarWrap, { backgroundColor: isDark ? colors.card : '#F3F4F6', borderColor: colors.border }]}>
          <IconSymbol name="magnifyingglass" size={22} color={colors.icon} />
          <TextInput
            value={query}
            onChangeText={(text) => {
              setQuery(text);
              if (text.trim().length > 0) {
                setIsImageSearchActive(false);
              }
            }}
            placeholder="ابحث عن عضو بالاسم..."
            placeholderTextColor={colors.icon}
            style={[styles.searchInput, { color: colors.text }]}
            returnKeyType="search"
          />
          {query.length > 0 && (
            <TouchableOpacity onPress={() => { setQuery(''); setIsImageSearchActive(false); }} hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}>
              <IconSymbol name="xmark.circle.fill" size={22} color={colors.icon} />
            </TouchableOpacity>
          )}
          <TouchableOpacity
            onPress={handleImageSearch}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
            style={{ paddingLeft: 4 }}
          >
            <MaterialIcons name="photo-camera" size={22} color={colors.primary} />
          </TouchableOpacity>
        </View>

        {loadingDb && allPersons.length === 0 && (
          <View style={{ padding: 20 }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        )}

        {/* Recent Search History */}
        {!hasQuery && recentSearches.length > 0 && (
          <View style={styles.recentSection}>
            <View style={styles.recentHeaderRow}>
              <ThemedText type="subtitle" style={styles.sectionTitle}>
                عمليات البحث الأخيرة
              </ThemedText>
              <TouchableOpacity onPress={handleClearHistory}>
                <ThemedText style={{ color: '#EF4444', fontFamily: 'Tajawal-Medium', fontSize: 13 }}>
                  مسح السجل
                </ThemedText>
              </TouchableOpacity>
            </View>
            <View style={[styles.recentCard, { backgroundColor: isDark ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
              {recentSearches.map((r, index) => (
                <View key={r.id} style={[styles.recentRow, index === recentSearches.length - 1 ? styles.recentRowLast : null]}>
                  <TouchableOpacity
                    style={styles.recentRowClickable}
                    onPress={() => handleSelectPerson(r)}
                  >
                    <IconSymbol name="clock.fill" size={18} color={colors.icon} />
                    <ThemedText style={styles.recentText}>{r.full_name}</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity onPress={() => handleRemoveRecent(r.id)} style={styles.removeHistoryBtn}>
                    <IconSymbol name="xmark" size={14} color={colors.icon} />
                  </TouchableOpacity>
                </View>
              ))}
            </View>
          </View>
        )}

        {/* Search Results */}
        {hasQuery && (
          <View style={styles.resultSection}>
            {filteredFamily.length > 0 ? (
              <>
                <View style={styles.sectionHeaderRow}>
                  <View style={[styles.sectionIconWrap, { backgroundColor: ACCENT_COLORS.family + '28' }]}>
                    <IconSymbol name="person.2.fill" size={20} color={ACCENT_COLORS.family} />
                  </View>
                  <ThemedText type="subtitle" style={styles.sectionTitleInline}>
                    نتائج البحث في العائلة
                  </ThemedText>
                </View>
                {filteredFamily.map((p) => (
                  <TouchableOpacity
                    key={p.id}
                    onPress={() => handleSelectPerson(p)}
                    style={[styles.resultCard, { backgroundColor: isDark ? colors.card : '#FFFFFF', borderColor: colors.border }]}
                  >
                    <View style={[styles.avatarPlaceholder, { backgroundColor: ACCENT_COLORS.family + '30', overflow: 'hidden' }]}>
                      {resolveAvatarUrl(p) ? (
                        <Image source={{ uri: resolveAvatarUrl(p)! }} style={{ width: 48, height: 48 }} />
                      ) : (
                        <ThemedText style={[styles.avatarLetter, { color: ACCENT_COLORS.family }]}>
                          {p.gender === 'female' ? '👩' : '👨'}
                        </ThemedText>
                      )}
                    </View>
                    <View style={styles.resultCardContent}>
                      <ThemedText style={styles.resultCardTitle}>{p.full_name}</ThemedText>
                      <ThemedText style={[styles.resultCardSub, { color: colors.icon }]}>
                        {p.death_date ? 'رحمه الله' : 'عرض التفاصيل والشجرة'}
                      </ThemedText>
                    </View>
                    <IconSymbol name={I18nManager.isRTL ? "chevron.left" : "chevron.right"} size={20} color={colors.icon} />
                  </TouchableOpacity>
                ))}
              </>
            ) : (
              <View style={styles.emptyWrap}>
                <View style={[styles.emptyIconWrap, { backgroundColor: colors.border }]}>
                  <IconSymbol name="magnifyingglass" size={40} color={colors.icon} />
                </View>
                <ThemedText type="subtitle" style={styles.emptyTitle}>
                  {"لا توجد نتائج لـ \"" + query + "\""}
                </ThemedText>
                <ThemedText style={[styles.emptySub, { color: colors.icon }]}>
                  تأكد من صحة كتابة الاسم، أو جرب اسماً آخر.
                </ThemedText>
              </View>
            )}
          </View>
        )}

        {/* Image Search Loading */}
        {imageLoading && (
          <View style={styles.scannerWrapper}>
            <View style={[styles.scannerCard, { backgroundColor: isDark ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
              <View style={[styles.imageFrameOuterCircle, { borderColor: colors.primary + '30' }]}>
                {/* Glowing Radar Pulse Effect */}
                <Animated.View style={[styles.radarCircle, { backgroundColor: colors.primary + '20' }, radarStyle1]} />
                
                <View style={[styles.imageFrameContainer, { borderColor: colors.primary }]}>
                  {selectedImage ? (
                    <Image source={{ uri: selectedImage }} style={styles.scannerImage} />
                  ) : (
                    <MaterialIcons name="face" size={80} color={colors.primary + '50'} />
                  )}
                  {/* The Scanning Laser Line */}
                  <Animated.View style={[styles.scanLine, { backgroundColor: colors.primary }, scanLineStyle]} />
                </View>
              </View>
              
              <View style={styles.scanningStatusContainer}>
                <ActivityIndicator size="small" color={colors.primary} />
                <ThemedText style={styles.scanningText}>
                  جاري فحص ملامح الوجه ومطابقتها...
                </ThemedText>
              </View>
              <ThemedText style={[styles.scanningSubText, { color: colors.icon }]}>
                نقوم بمطابقة الصورة مع قاعدة بيانات شجرة العائلة للوصول إلى أقرب تطابق
              </ThemedText>
            </View>
          </View>
        )}

        {/* Image Search Results */}
        {!imageLoading && isImageSearchActive && (
          <View style={styles.resultSection}>
            {imageMatches.length > 0 ? (
              <>
                <View style={styles.sectionHeaderRow}>
                  <View style={[styles.sectionIconWrap, { backgroundColor: colors.primary + '28' }]}>
                    <MaterialIcons name="face" size={20} color={colors.primary} />
                  </View>
                  <ThemedText type="subtitle" style={styles.sectionTitleInline}>
                    أفراد العائلة المطابقين بالوجه
                  </ThemedText>
                </View>
                {imageMatches.map((match) => {
                  const p = match.person;
                  const similarityText = formatSimilarity(match.similarity);
                  const gradientColors = getSimilarityColor(match.similarity);
                  
                  return (
                    <TouchableOpacity
                      key={p.id}
                      activeOpacity={0.8}
                      onPress={() => handleSelectPerson(p)}
                      style={[
                        styles.matchCard, 
                        { 
                          backgroundColor: isDark ? colors.card : '#FFFFFF', 
                          borderColor: colors.border,
                        }
                      ]}
                    >
                      <View style={[styles.avatarPlaceholder, { backgroundColor: colors.primary + '15', overflow: 'hidden' }]}>
                        {resolveAvatarUrl(p) ? (
                          <Image source={{ uri: resolveAvatarUrl(p)! }} style={{ width: 54, height: 54 }} />
                        ) : (
                          <ThemedText style={styles.avatarEmoji}>
                            {p.gender === 'female' ? '👩' : '👨'}
                          </ThemedText>
                        )}
                      </View>
                      
                      <View style={styles.matchCardContent}>
                        <ThemedText style={styles.matchCardTitle}>{p.full_name}</ThemedText>
                        <ThemedText style={[styles.matchCardSub, { color: colors.icon }]}>
                          {p.death_date ? 'رحمه الله' : 'عرض التفاصيل والشجرة'}
                        </ThemedText>
                      </View>

                      <View style={styles.matchScoreColumn}>
                        <LinearGradient
                          colors={gradientColors}
                          start={{ x: 0, y: 0 }}
                          end={{ x: 1, y: 1 }}
                          style={styles.similarityBadge}
                        >
                          <MaterialIcons name="auto-awesome" size={12} color="#FFFFFF" />
                          <ThemedText style={styles.similarityBadgeText}>
                            {similarityText}
                          </ThemedText>
                        </LinearGradient>
                        <ThemedText style={[styles.similarityLabel, { color: colors.icon }]}>تطابق</ThemedText>
                      </View>

                      <IconSymbol 
                        name={I18nManager.isRTL ? "chevron.left" : "chevron.right"} 
                        size={18} 
                        color={colors.icon} 
                        style={{ marginLeft: 8 }}
                      />
                    </TouchableOpacity>
                  );
                })}
              </>
            ) : (
              <View style={styles.emptyWrap}>
                <View style={[styles.emptyIconWrap, { backgroundColor: colors.border }]}>
                  <MaterialIcons name="face-retouching-off" size={40} color={colors.icon} />
                </View>
                <ThemedText type="subtitle" style={styles.emptyTitle}>
                  لم يتم العثور على أي وجه مطابق
                </ThemedText>
                <ThemedText style={[styles.emptySub, { color: colors.icon }]}>
                  تأكد أن الصورة واضحة وتحتوي على وجه شخص واحد بشكل بارز.
                </ThemedText>
              </View>
            )}
          </View>
        )}
      </ScrollView>

      {/* Custom Animated Bottom Sheet Modal */}
      <Modal
        visible={isPickerVisible}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setIsPickerVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <Pressable 
            style={StyleSheet.absoluteFill} 
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              setIsPickerVisible(false);
            }}
          >
            <View style={styles.modalBackdrop} />
          </Pressable>
          
          <View style={[styles.bottomSheetContainer, { backgroundColor: isDark ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
            <View style={[styles.bottomSheetHandle, { backgroundColor: isDark ? '#3D3D3D' : '#E5E7EB' }]} />
            
            <View style={styles.bottomSheetHeader}>
              <View style={[styles.bottomSheetHeaderIcon, { backgroundColor: colors.primary + '20' }]}>
                <MaterialIcons name="face" size={24} color={colors.primary} />
              </View>
              <View style={{ alignItems: 'flex-start' }}>
                <ThemedText style={styles.bottomSheetTitle}>البحث بمطابقة الوجه</ThemedText>
                <ThemedText style={[styles.bottomSheetSubtitle, { color: colors.icon }]}>اختر طريقة رفع صورة الفرد للمطابقة</ThemedText>
              </View>
            </View>

            <View style={styles.pickerOptionsRow}>
              <TouchableOpacity 
                activeOpacity={0.8}
                style={[styles.pickerOptionCard, { backgroundColor: isDark ? '#262626' : '#F9FAFB', borderColor: colors.border }]} 
                onPress={() => { 
                  Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  setIsPickerVisible(false); 
                  takePhoto(); 
                }}
              >
                <LinearGradient
                  colors={['#10B981', '#059669']}
                  style={styles.pickerIconGradient}
                >
                  <MaterialIcons name="photo-camera" size={28} color="#FFFFFF" />
                </LinearGradient>
                <ThemedText style={styles.pickerOptionTitle}>التقاط بالكاميرا</ThemedText>
                <ThemedText style={[styles.pickerOptionDesc, { color: colors.icon }]}>صوّر الفرد مباشرة بالكاميرا</ThemedText>
              </TouchableOpacity>

              <TouchableOpacity 
                activeOpacity={0.8}
                style={[styles.pickerOptionCard, { backgroundColor: isDark ? '#262626' : '#F9FAFB', borderColor: colors.border }]} 
                onPress={() => { 
                  Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  setIsPickerVisible(false); 
                  pickImage(); 
                }}
              >
                <LinearGradient
                  colors={['#3B82F6', '#2563EB']}
                  style={styles.pickerIconGradient}
                >
                  <MaterialIcons name="photo-library" size={28} color="#FFFFFF" />
                </LinearGradient>
                <ThemedText style={styles.pickerOptionTitle}>معرض الصور</ThemedText>
                <ThemedText style={[styles.pickerOptionDesc, { color: colors.icon }]}>اختر صورة من ألبوم الصور</ThemedText>
              </TouchableOpacity>
            </View>

            <TouchableOpacity 
              activeOpacity={0.8}
              style={[styles.cancelButton, { backgroundColor: isDark ? '#333333' : '#F3F4F6' }]} 
              onPress={() => {
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                setIsPickerVisible(false);
              }}
            >
              <ThemedText style={[styles.cancelButtonText, { color: isDark ? '#FFFFFF' : '#374151' }]}>إلغاء</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  scroll: { flex: 1 },
  scrollContent: { paddingHorizontal: 20, paddingBottom: 40 },
  searchBarWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    height: 52,
    borderRadius: 16,
    borderWidth: 1,
    paddingHorizontal: 16,
    gap: 12,
    marginBottom: 20,
  },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 16,
    paddingVertical: 0,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  recentSection: { marginBottom: 28 },
  recentHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  recentCard: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  recentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(0,0,0,0.06)',
  },
  recentRowClickable: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    gap: 12,
  },
  recentRowLast: { borderBottomWidth: 0 },
  recentText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 15,
  },
  removeHistoryBtn: {
    padding: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  resultSection: { marginBottom: 28 },
  sectionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 14,
  },
  sectionIconWrap: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  sectionTitleInline: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  resultCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    marginBottom: 10,
  },
  avatarPlaceholder: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  avatarLetter: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 20,
  },
  resultCardContent: { flex: 1 },
  resultCardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    marginBottom: 4,
  },
  resultCardSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  emptyWrap: {
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyIconWrap: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    marginBottom: 8,
    textAlign: 'center',
  },
  emptySub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    textAlign: 'center',
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.4)',
  },
  bottomSheetContainer: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 24,
    paddingBottom: Platform.OS === 'ios' ? 40 : 24,
    elevation: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
  },
  bottomSheetHandle: {
    width: 40,
    height: 5,
    borderRadius: 3,
    alignSelf: 'center',
    marginBottom: 20,
  },
  bottomSheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 24,
  },
  bottomSheetHeaderIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
  },
  bottomSheetTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'left',
  },
  bottomSheetSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    textAlign: 'left',
    marginTop: 2,
  },
  pickerOptionsRow: {
    flexDirection: 'row',
    gap: 16,
    marginBottom: 24,
  },
  pickerOptionCard: {
    flex: 1,
    borderRadius: 18,
    borderWidth: 1,
    padding: 16,
    alignItems: 'center',
  },
  pickerIconGradient: {
    width: 56,
    height: 56,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  pickerOptionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    marginBottom: 4,
    textAlign: 'center',
  },
  pickerOptionDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'center',
  },
  cancelButton: {
    height: 50,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cancelButtonText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  // Scanner Styles
  scannerWrapper: {
    marginVertical: 10,
    alignItems: 'center',
  },
  scannerCard: {
    width: '100%',
    borderRadius: 20,
    borderWidth: 1,
    padding: 24,
    alignItems: 'center',
  },
  imageFrameOuterCircle: {
    width: 196,
    height: 196,
    borderRadius: 98,
    borderWidth: 1.5,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
    position: 'relative',
  },
  imageFrameContainer: {
    width: 180,
    height: 180,
    borderRadius: 90,
    borderWidth: 3,
    overflow: 'hidden',
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
    backgroundColor: 'rgba(0,0,0,0.03)',
  },
  scannerImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  scanLine: {
    position: 'absolute',
    left: 0,
    right: 0,
    height: 4,
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.8,
    shadowRadius: 6,
    elevation: 4,
  },
  radarCircle: {
    position: 'absolute',
    width: 196,
    height: 196,
    borderRadius: 98,
  },
  scanningStatusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  scanningText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  scanningSubText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 18,
  },
  // Match results styles
  matchCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 18,
    borderWidth: 1,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 4,
    elevation: 1,
  },
  matchCardContent: {
    flex: 1,
    paddingHorizontal: 12,
  },
  matchCardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    textAlign: 'left',
    marginBottom: 2,
  },
  matchCardSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'left',
  },
  matchScoreColumn: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  similarityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  similarityBadgeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  similarityLabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10,
    marginTop: 2,
  },
  avatarEmoji: {
    fontSize: 22,
  },
});
