import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedView } from '@/components/themed-view';
import { Skeleton } from '@/components/ui/skeleton';
import { Colors, silaTheme } from '@/constants/theme';
import { useArticlesFeed } from '@/hooks/use-articles-feed';
import type { ArticleItem } from '@/lib/api/articles';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  FlatList,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const H_PAD = 16;
const GAP = 12;
const CELL_W = (SCREEN_W - H_PAD * 2 - GAP) / 2;
const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

export default function ArticlesScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const insets = useSafeAreaInsets();
  const isDark = colorScheme === 'dark';

  const {
    articles,
    categories,
    availableYears,
    stats,
    categoryId,
    selectedYear,
    selectCategory,
    selectYear,
    searchInput,
    setSearchInput,
    loading,
    loadingMore,
    refreshing,
    error,
    refresh,
    loadMore,
    retry,
  } = useArticlesFeed();

  const [layoutMode, setLayoutMode] = useState<'grid' | 'list'>('grid');

  const onCategoryPress = useCallback((id: number | null) => {
    Haptics.selectionAsync().catch(() => { });
    selectCategory(id);
  }, [selectCategory]);

  const onYearPress = useCallback((yr: number | string | null) => {
    Haptics.selectionAsync().catch(() => { });
    selectYear(yr);
  }, [selectYear]);

  const toggleLayout = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });
    setLayoutMode((prev) => (prev === 'grid' ? 'list' : 'grid'));
  }, []);

  const handleCardPress = useCallback((id: number) => {
    Haptics.selectionAsync().catch(() => { });
    // التوجيه إلى صفحة تفاصيل المقال
    router.push({
      pathname: '/(app)/article/[id]',
      params: { id },
    });
  }, []);

  // المكون الرأسي للرأس
  const renderHeader = useMemo(() => {
    return (
      <View style={styles.headerSection}>
        {/* شريط البحث وتغيير طريقة العرض */}
        <View style={styles.searchRow}>
          <View style={[styles.searchBar, { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#fff', borderColor: isDark ? 'rgba(255,255,255,0.1)' : '#e2ebe7' }]}>
            <Ionicons name="search" size={20} color={isDark ? '#aaa' : '#7a9490'} style={styles.searchIcon} />
            <TextInput
              value={searchInput}
              onChangeText={setSearchInput}
              placeholder="ابحث عن المقالات..."
              placeholderTextColor={isDark ? '#777' : '#9ca3af'}
              style={[styles.searchInput, { color: colors.text }]}
              textAlign="right"
            />
            {searchInput.length > 0 && (
              <TouchableOpacity onPress={() => setSearchInput('')} style={styles.clearSearchBtn}>
                <Ionicons name="close-circle" size={18} color={isDark ? '#888' : '#9ca3af'} />
              </TouchableOpacity>
            )}
          </View>

          <TouchableOpacity
            onPress={toggleLayout}
            style={[styles.layoutToggleBtn, { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#fff', borderColor: isDark ? 'rgba(255,255,255,0.1)' : '#e2ebe7' }]}
            activeOpacity={0.7}
          >
            <Ionicons
              name={layoutMode === 'grid' ? 'list-outline' : 'grid-outline'}
              size={22}
              color={silaTheme.primary}
            />
          </TouchableOpacity>
        </View>

        {/* شريط فلترة السنوات */}
        {availableYears.length > 0 && (
          <View style={styles.yearsWrapper}>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.yearsScroll}
            >
              <View style={styles.innerRow}>
                <TouchableOpacity
                  onPress={() => onYearPress('')}
                  style={[
                    styles.yearChip,
                    selectedYear === '' && styles.yearChipActive,
                    { borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(55,160,92,0.2)' }
                  ]}
                >
                  <Text style={[styles.yearChipText, selectedYear === '' && styles.yearChipTextActive, { color: isDark ? '#fff' : '#1a2e2a' }]}>الكل</Text>
                </TouchableOpacity>

                {availableYears.map((year) => (
                  <TouchableOpacity
                    key={year}
                    onPress={() => onYearPress(year)}
                    style={[
                      styles.yearChip,
                      selectedYear === year && styles.yearChipActive,
                      { borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(55,160,92,0.2)' }
                    ]}
                  >
                    <Text style={[styles.yearChipText, selectedYear === year && styles.yearChipTextActive, { color: isDark ? '#fff' : '#1a2e2a' }]}>
                      {year}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </ScrollView>
          </View>
        )}

        {/* شريط فلترة التصنيفات */}
        <View style={styles.categoriesWrapper}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.categoriesScroll}
          >
            <View style={styles.innerRow}>
              <TouchableOpacity
                onPress={() => onCategoryPress(null)}
                style={[
                  styles.categoryChip,
                  categoryId === null && styles.categoryChipActive,
                  { backgroundColor: categoryId === null ? silaTheme.primary : isDark ? 'rgba(0,0,0,0.45)' : '#fff' }
                ]}
              >
                <Text style={[styles.categoryChipText, categoryId === null && styles.categoryChipTextActive, { color: categoryId === null ? '#fff' : isDark ? '#ddd' : silaTheme.textDark }]}>
                  الكل
                </Text>
                <View style={[styles.countBadge, { backgroundColor: categoryId === null ? 'rgba(255,255,255,0.25)' : 'rgba(55,160,92,0.1)' }]}>
                  <Text style={[styles.countBadgeText, { color: categoryId === null ? '#fff' : silaTheme.primary }]}>
                    {stats.total_articles}
                  </Text>
                </View>
              </TouchableOpacity>

              {categories.map((cat) => {
                const isActive = categoryId === cat.id;
                return (
                  <TouchableOpacity
                    key={cat.id}
                    onPress={() => onCategoryPress(cat.id)}
                    style={[
                      styles.categoryChip,
                      isActive && styles.categoryChipActive,
                      { backgroundColor: isActive ? silaTheme.primary : isDark ? 'rgba(0,0,0,0.45)' : '#fff' }
                    ]}
                  >
                    <Text style={[styles.categoryChipText, isActive && styles.categoryChipTextActive, { color: isActive ? '#fff' : isDark ? '#ddd' : silaTheme.textDark }]}>
                      {cat.name}
                    </Text>
                    <View style={[styles.countBadge, { backgroundColor: isActive ? 'rgba(255,255,255,0.25)' : 'rgba(55,160,92,0.1)' }]}>
                      <Text style={[styles.countBadgeText, { color: isActive ? '#fff' : silaTheme.primary }]}>
                        {cat.articles_count ?? 0}
                      </Text>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>
          </ScrollView>
        </View>
      </View>
    );
  }, [availableYears, selectedYear, categories, categoryId, stats.total_articles, searchInput, layoutMode, colors.text, isDark, onCategoryPress, onYearPress, toggleLayout]);

  // مكون المقال المفرد
  const renderItem = useCallback(({ item }: { item: ArticleItem }) => {
    const mainImg = item.images?.[0]?.path;
    const authorName = item.author?.name || 'غير معروف';
    const authorAvatar = item.author?.avatar;
    const formattedDate = new Date(item.created_at).toLocaleDateString('ar-SA', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });

    if (layoutMode === 'grid') {
      return (
        <TouchableOpacity
          onPress={() => handleCardPress(item.id)}
          style={[styles.gridCard, { backgroundColor: isDark ? '#1a2e2a' : '#ffffff', borderColor: isDark ? 'rgba(255,255,255,0.06)' : '#e2ebe7' }]}
          activeOpacity={0.8}
        >
          <View style={styles.gridCardImageWrapper}>
            {mainImg ? (
              <Image
                source={{ uri: mainImg }}
                style={styles.gridCardImage}
                contentFit="cover"
                transition={200}
                placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
              />
            ) : (
              <View style={[styles.cardImagePlaceholder, { backgroundColor: isDark ? '#142521' : '#f0fdf4' }]}>
                <Ionicons name="document-text" size={36} color={silaTheme.primary} />
              </View>
            )}
            <View style={styles.categoryBadge}>
              <Text style={styles.categoryBadgeText} numberOfLines={1}>
                {item.category?.name || 'مقال'}
              </Text>
            </View>
          </View>

          <View style={styles.gridCardInfo}>
            <Text style={[styles.gridCardTitle, { color: colors.text }]} numberOfLines={2}>
              {item.title}
            </Text>

            <Text style={styles.gridCardExcerpt} numberOfLines={2}>
              {item.excerpt}
            </Text>

            <View style={styles.cardDivider} />

            <View style={styles.authorRow}>
              {authorAvatar ? (
                <Image source={{ uri: authorAvatar }} style={styles.authorAvatar} />
              ) : (
                <View style={styles.authorAvatarPlaceholder}>
                  <Ionicons name="person" size={12} color="#fff" />
                </View>
              )}
              <View style={styles.authorTextCol}>
                <Text style={[styles.authorName, { color: colors.text }]} numberOfLines={1}>
                  {authorName}
                </Text>
                <Text style={styles.cardDate}>{formattedDate}</Text>
              </View>
            </View>
          </View>
        </TouchableOpacity>
      );
    }

    // List view layout
    return (
      <TouchableOpacity
        onPress={() => handleCardPress(item.id)}
        style={[styles.listCard, { backgroundColor: isDark ? '#1a2e2a' : '#ffffff', borderColor: isDark ? 'rgba(255,255,255,0.06)' : '#e2ebe7' }]}
        activeOpacity={0.8}
      >
        <View style={styles.listCardImageWrapper}>
          {mainImg ? (
            <Image
              source={{ uri: mainImg }}
              style={styles.listCardImage}
              contentFit="cover"
              transition={200}
              placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
            />
          ) : (
            <View style={[styles.cardImagePlaceholder, { backgroundColor: isDark ? '#142521' : '#f0fdf4' }]}>
              <Ionicons name="document-text" size={32} color={silaTheme.primary} />
            </View>
          )}
        </View>

        <View style={styles.listCardInfo}>
          <View style={styles.listCardMetaRow}>
            <View style={styles.listCardCategory}>
              <Text style={styles.listCardCategoryText}>{item.category?.name || 'مقال'}</Text>
            </View>
            <Text style={styles.cardDate}>{formattedDate}</Text>
          </View>

          <Text style={[styles.listCardTitle, { color: colors.text }]} numberOfLines={2}>
            {item.title}
          </Text>

          <Text style={styles.listCardExcerpt} numberOfLines={2}>
            {item.excerpt}
          </Text>

          <View style={styles.listCardFooter}>
            <View style={styles.listAuthorRow}>
              {authorAvatar ? (
                <Image source={{ uri: authorAvatar }} style={styles.authorAvatar} />
              ) : (
                <View style={styles.authorAvatarPlaceholder}>
                  <Ionicons name="person" size={12} color="#fff" />
                </View>
              )}
              <Text style={[styles.listAuthorName, { color: colors.text }]} numberOfLines={1}>
                {authorName}
              </Text>
            </View>
            <View style={styles.readMoreBtn}>
              <Text style={styles.readMoreText}>اقرأ المزيد</Text>
              <Ionicons name="arrow-back" size={14} color={silaTheme.primary} />
            </View>
          </View>
        </View>
      </TouchableOpacity>
    );
  }, [layoutMode, colors.text, isDark, handleCardPress]);

  // الهيكل العظمي للتحميل (Skeleton Loaders)
  const renderSkeletons = useMemo(() => {
    const dummyArr = Array.from({ length: 6 });
    return (
      <Skeleton.Group animation="shimmer" duration={1400}>
        <View style={[styles.listContainer, { paddingHorizontal: H_PAD }]}>
          <View style={styles.gridRowWrap}>
            {dummyArr.map((_, index) => (
              <View
                key={index}
                style={[
                  layoutMode === 'grid' ? styles.skeletonGridCard : styles.skeletonListCard,
                  { backgroundColor: isDark ? 'rgba(255,255,255,0.04)' : '#fff', borderColor: isDark ? 'rgba(255,255,255,0.05)' : '#e2ebe7' }
                ]}
              >
                <Skeleton
                  width="100%"
                  height={layoutMode === 'grid' ? 120 : 80}
                  borderRadius={12}
                />
                <View style={styles.skeletonTextWrapper}>
                  <Skeleton width="85%" height={14} borderRadius={4} />
                  <Skeleton width="55%" height={12} borderRadius={4} style={{ marginTop: 8 }} />
                </View>
              </View>
            ))}
          </View>
        </View>
      </Skeleton.Group>
    );
  }, [layoutMode, isDark]);

  return (
    <ThemedView style={styles.container}>
      {/* الخلفية الحية ذات الفقاعات المتحركة */}
      <AnimatedBubbles bubbleCount={12} />

      {/* الهيدر العلوي */}
      <HeaderBar
        title="مقالات العائلة"
        subtitle="تاريخ، ثقافة، وذكريات عائلية ملهمة"
        showBack={true}
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      {loading && !refreshing ? (
        <View style={styles.flexOne}>
          {renderHeader}
          {renderSkeletons}
        </View>
      ) : error ? (
        <View style={styles.errorContainer}>
          <Ionicons name="cloud-offline-outline" size={64} color={silaTheme.textMuted} />
          <Text style={[styles.errorTitle, { color: colors.text }]}>فشل تحميل المقالات</Text>
          <Text style={styles.errorBody}>{error}</Text>
          <TouchableOpacity onPress={retry} style={[styles.retryBtn, { backgroundColor: silaTheme.primary }]}>
            <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          key={layoutMode}
          data={articles}
          renderItem={renderItem}
          keyExtractor={(item) => item.id.toString()}
          numColumns={layoutMode === 'grid' ? 2 : 1}
          columnWrapperStyle={layoutMode === 'grid' ? styles.gridColumnWrapper : undefined}
          contentContainerStyle={[styles.listContainer, { paddingBottom: insets.bottom + 24 }]}
          ListHeaderComponent={renderHeader}
          onEndReached={loadMore}
          onEndReachedThreshold={0.4}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={refresh}
              colors={[silaTheme.primary]}
              tintColor={silaTheme.primary}
            />
          }
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="document-text-outline" size={60} color={silaTheme.textMuted} />
              <Text style={[styles.emptyTitle, { color: colors.text }]}>لا توجد مقالات لعرضها</Text>
              <Text style={styles.emptySubtitle}>جرّب تغيير فلاتر البحث أو السنة</Text>
            </View>
          }
          ListFooterComponent={
            loadingMore ? (
              <View style={styles.loaderFooter}>
                <ActivityIndicator size="small" color={silaTheme.primary} />
              </View>
            ) : null
          }
        />
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  flexOne: {
    flex: 1,
  },
  headerSection: {
    paddingHorizontal: H_PAD,
    paddingTop: 12,
    paddingBottom: 4,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  searchBar: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    gap: 8,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
      },
      android: { elevation: 1 },
    }),
  },
  searchIcon: {
  },
  searchInput: {
    flex: 1,
    height: '100%',
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,

  },
  clearSearchBtn: {
    padding: 4,
  },
  layoutToggleBtn: {
    width: 48,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
      },
      android: { elevation: 1 },
    }),
  },
  yearsWrapper: {
    marginBottom: 12,
  },
  yearsScroll: {
    paddingLeft: 2,
    paddingRight: H_PAD,
  },
  innerRow: {
    flexDirection: 'row',
    gap: 8,
  },
  yearChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
  },
  yearChipActive: {
    backgroundColor: 'rgba(55,160,92,0.15)',
    borderColor: silaTheme.primary,
  },
  yearChipText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',

  },
  yearChipTextActive: {
    color: silaTheme.primary,
    fontFamily: 'Tajawal-Bold',
  },
  categoriesWrapper: {
    marginBottom: 8,
  },
  categoriesScroll: {
    paddingLeft: 2,
    paddingRight: H_PAD,
  },
  categoryChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 20,
    gap: 8,
    minWidth: 80,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.03,
        shadowRadius: 2,
      },
      android: { elevation: 1 },
    }),
  },
  categoryChipActive: {
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.primary,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.25,
        shadowRadius: 6,
      },
      android: { elevation: 4 },
    }),
  },
  categoryChipText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',

  },
  categoryChipTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  countBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 8,
    minWidth: 20,
    alignItems: 'center',
  },
  countBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  listContainer: {
    paddingHorizontal: H_PAD,
  },
  gridColumnWrapper: {
    justifyContent: 'space-between',
    marginBottom: GAP,
  },
  gridCard: {
    width: CELL_W,
    borderRadius: 18,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#145147',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.06,
        shadowRadius: 8,
      },
      android: { elevation: 2 },
    }),
  },
  gridCardImageWrapper: {
    width: '100%',
    height: CELL_W * 0.72,
    position: 'relative',
  },
  gridCardImage: {
    width: '100%',
    height: '100%',
  },
  cardImagePlaceholder: {
    width: '100%',
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  categoryBadge: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: 'rgba(20,81,71,0.85)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    maxWidth: CELL_W - 24,
  },
  categoryBadgeText: {
    color: '#fff',
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  gridCardInfo: {
    padding: 12,
  },
  gridCardTitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 18,
    height: 36,

  },
  gridCardExcerpt: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    lineHeight: 15,
    marginTop: 6,
    height: 30,

  },
  cardDivider: {
    height: 1,
    backgroundColor: 'rgba(55,160,92,0.1)',
    marginVertical: 10,
  },
  authorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  authorAvatar: {
    width: 24,
    height: 24,
    borderRadius: 12,
  },
  authorAvatarPlaceholder: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: silaTheme.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  authorTextCol: {
    flex: 1,
    alignItems: 'flex-start',
  },
  authorName: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',

  },
  cardDate: {
    fontSize: 9,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    marginTop: 1,
  },

  // List View Cards
  listCard: {
    width: '100%',
    flexDirection: 'row',
    borderRadius: 18,
    borderWidth: 1,
    overflow: 'hidden',
    padding: 12,
    marginBottom: GAP,
    gap: 12,
    ...Platform.select({
      ios: {
        shadowColor: '#145147',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
      },
      android: { elevation: 2 },
    }),
  },
  listCardInfo: {
    flex: 1,
    justifyContent: 'space-between',
  },
  listCardMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  listCardCategory: {
    backgroundColor: 'rgba(55,160,92,0.1)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  listCardCategoryText: {
    color: silaTheme.primary,
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  listCardTitle: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 20,

  },
  listCardExcerpt: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    lineHeight: 16,
    marginTop: 6,

  },
  listCardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 12,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: 'rgba(55,160,92,0.08)',
  },
  listAuthorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  listAuthorName: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',

  },
  readMoreBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  readMoreText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  listCardImageWrapper: {
    width: 90,
    height: 90,
    borderRadius: 12,
    overflow: 'hidden',
  },
  listCardImage: {
    width: '100%',
    height: '100%',
  },

  // Loader Footer
  loaderFooter: {
    paddingVertical: 12,
    alignItems: 'center',
  },

  // Skeleton loaders
  gridRowWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: GAP,
  },
  skeletonGridCard: {
    width: CELL_W,
    height: CELL_W * 1.3,
    borderRadius: 18,
    borderWidth: 1,
    padding: 10,
    justifyContent: 'space-between',
  },
  skeletonListCard: {
    width: '100%',
    height: 114,
    borderRadius: 18,
    borderWidth: 1,
    padding: 10,
    flexDirection: 'row',
    gap: 12,
    marginBottom: GAP,
  },
  skeletonImage: {
    width: '100%',
    flex: 1,
    borderRadius: 12,
  },
  skeletonTextWrapper: {
    marginTop: 8,
    height: 48,
    justifyContent: 'center',
  },
  skeletonTextLine: {
    height: 12,
    borderRadius: 6,
  },

  // Empty / Error container
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    marginTop: 12,
  },
  emptySubtitle: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    marginTop: 6,
  },
  errorContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  errorTitle: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
    marginTop: 16,
  },
  errorBody: {
    fontSize: 14,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    textAlign: 'center',
    marginTop: 8,
    paddingHorizontal: 20,
  },
  retryBtn: {
    marginTop: 20,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
  },
  retryBtnText: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
});
