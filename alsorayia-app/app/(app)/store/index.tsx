import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { STORE_ACCENT } from '@/components/store/constants';
import { StoreCategoryCard } from '@/components/store/StoreCategoryCard';
import { StoreEmptyPrompt } from '@/components/store/StoreEmptyPrompt';
import { StoreOwnerChip } from '@/components/store/StoreOwnerChip';
import { StoreProductCard } from '@/components/store/StoreProductCard';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useStoreLanding } from '@/hooks/use-store-landing';
import type { ProductListItem, StoreCategory } from '@/lib/api/store';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import { type Href, useRouter } from 'expo-router';
import React, { useCallback, useMemo } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const H_PAD = 20;
const GAP = 12;
const CAT_CELL = (SCREEN_W - H_PAD * 2 - GAP) / 2;
const PROD_CELL = (SCREEN_W - H_PAD * 2 - GAP) / 2;

export default function StoreIndexScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  const cardBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';
  const mutedColor = colorScheme === 'dark' ? '#9CA3AF' : '#64748B';
  const searchBg = colorScheme === 'dark' ? '#1F2937' : '#F1F5F9';

  const {
    categories,
    owners,
    landingLoading,
    landingError,
    personId,
    selectPerson,
    searchInput,
    setSearchInput,
    hasActiveProductFilter,
    products,
    meta,
    loading,
    loadingMore,
    refreshing,
    error,
    refresh,
    loadMore,
    retry,
    hasMore,
  } = useStoreLanding();

  const goCategory = useCallback(
    (id: number) => {
      Haptics.selectionAsync().catch(() => {});
      router.push(`/(app)/store/category/${id}` as Href);
    },
    [router]
  );

  const goProduct = useCallback(
    (id: number) => {
      router.push(`/(app)/store/product/${id}` as Href);
    },
    [router]
  );

  const onOwnerPress = useCallback(
    (id: number) => {
      Haptics.selectionAsync().catch(() => {});
      selectPerson(id);
    },
    [selectPerson]
  );

  const renderCategory = useCallback(
    ({ item }: { item: StoreCategory }) => (
      <StoreCategoryCard
        category={item}
        onPress={() => goCategory(item.id)}
        style={{ width: CAT_CELL }}
        cardBg={cardBg}
        borderColor={colors.border}
      />
    ),
    [goCategory, cardBg, colors.border]
  );

  const renderProduct = useCallback(
    ({ item }: { item: ProductListItem }) => (
      <StoreProductCard
        product={item}
        onPress={() => goProduct(item.id)}
        style={{ width: PROD_CELL }}
        cardBg={cardBg}
        borderColor={colors.border}
        mutedColor={mutedColor}
      />
    ),
    [goProduct, cardBg, colors.border, mutedColor]
  );

  const listHeader = useMemo(
    () => (
      <View style={styles.headerBlock}>
        <LinearGradient
          colors={
            colorScheme === 'dark'
              ? ['#14B8A628', '#0D948820']
              : ['#CCFBF1', '#99F6E4']
          }
          style={styles.heroCard}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}>
          <View style={styles.heroRow}>
            <View style={styles.heroText}>
              <ThemedText type="title" style={styles.heroTitle}>
                متجر الأسر المنتجة
              </ThemedText>
              <ThemedText
                style={[styles.heroSub, { color: mutedColor }]}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                منتجات أسر العائلة — اختر تصنيفاً أو ابحث
              </ThemedText>
            </View>
            <View
              style={[
                styles.heroIcon,
                { backgroundColor: colorScheme === 'dark' ? '#134E4A' : '#FFFFFF' },
              ]}>
              <IconSymbol name="cart.fill" size={28} color={STORE_ACCENT} />
            </View>
          </View>
        </LinearGradient>

        <View style={[styles.searchWrap, { backgroundColor: searchBg, borderColor: colors.border }]}>
          <IconSymbol name="magnifyingglass" size={18} color={mutedColor} />
          <TextInput
            value={searchInput}
            onChangeText={setSearchInput}
            placeholder="ابحث عن منتج…"
            placeholderTextColor={mutedColor}
            style={[styles.searchInput, { color: colors.text }]}
            returnKeyType="search"
            maxLength={120}
          />
          {searchInput.length > 0 ? (
            <Pressable onPress={() => setSearchInput('')} hitSlop={8}>
              <IconSymbol name="xmark.circle.fill" size={18} color={mutedColor} />
            </Pressable>
          ) : null}
        </View>

        {landingError ? (
          <View style={styles.errorBox}>
            <ThemedText style={styles.errorText}>{landingError}</ThemedText>
            <Pressable onPress={retry} style={styles.retryBtn}>
              <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
                إعادة المحاولة
              </ThemedText>
            </Pressable>
          </View>
        ) : null}

        {categories.length > 0 ? (
          <>
            <ThemedText type="defaultSemiBold" style={styles.sectionTitle}>
              التصنيفات
            </ThemedText>
            <FlatList
              data={categories}
              keyExtractor={(c) => String(c.id)}
              renderItem={renderCategory}
              numColumns={2}
              scrollEnabled={false}
              columnWrapperStyle={styles.catRow}
              contentContainerStyle={styles.catGrid}
            />
          </>
        ) : landingLoading ? (
          <ActivityIndicator color={STORE_ACCENT} style={{ marginVertical: 16 }} />
        ) : null}

        {owners.length > 0 ? (
          <>
            <ThemedText type="defaultSemiBold" style={styles.sectionTitle}>
              حسب صاحب المنتج
            </ThemedText>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.ownersScroll}>
              {owners.map((o) => (
                <StoreOwnerChip
                  key={o.id}
                  owner={o}
                  selected={personId === o.id}
                  onPress={() => onOwnerPress(o.id)}
                  cardBg={cardBg}
                  borderColor={colors.border}
                />
              ))}
            </ScrollView>
          </>
        ) : null}

        <ThemedText type="defaultSemiBold" style={styles.sectionTitle}>
          المنتجات
          {hasActiveProductFilter && meta != null ? (
            <ThemedText style={[styles.resultCount, { color: mutedColor }]}>
              {' '}
              ({meta.total})
            </ThemedText>
          ) : null}
        </ThemedText>
      </View>
    ),
    [
      colorScheme,
      mutedColor,
      searchBg,
      colors,
      searchInput,
      setSearchInput,
      landingError,
      retry,
      categories,
      landingLoading,
      owners,
      personId,
      hasActiveProductFilter,
      meta,
      renderCategory,
      cardBg,
      onOwnerPress,
    ]
  );

  const listEmpty = useMemo(() => {
    if (!hasActiveProductFilter) {
      return <StoreEmptyPrompt mutedColor={mutedColor} />;
    }
    if (loading && !refreshing) {
      return <ActivityIndicator color={STORE_ACCENT} style={{ marginTop: 24 }} />;
    }
    if (error) {
      return (
        <View style={styles.errorBox}>
          <ThemedText style={styles.errorText}>{error}</ThemedText>
          <Pressable onPress={retry}>
            <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              إعادة المحاولة
            </ThemedText>
          </Pressable>
        </View>
      );
    }
    return (
      <StoreEmptyPrompt
        mutedColor={mutedColor}
        title="لا توجد نتائج"
        message="جرّب بحثاً آخر أو اختر صاحب منتج مختلف."
      />
    );
  }, [hasActiveProductFilter, loading, refreshing, error, retry, mutedColor]);

  const listFooter = useMemo(() => {
    if (!hasActiveProductFilter) return <View style={{ height: 24 }} />;
    if (loadingMore) {
      return (
        <ActivityIndicator color={STORE_ACCENT} style={{ marginVertical: 16 }} />
      );
    }
    return <View style={{ height: insets.bottom + 24 }} />;
  }, [hasActiveProductFilter, loadingMore, insets.bottom]);

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={90} />
      <HeaderBar
        title="متجر الأسر"
        showBack
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <FlatList
        data={hasActiveProductFilter ? products : []}
        keyExtractor={(p) => String(p.id)}
        renderItem={renderProduct}
        numColumns={hasActiveProductFilter ? 2 : 1}
        key={hasActiveProductFilter ? 'grid' : 'empty'}
        ListHeaderComponent={listHeader}
        ListEmptyComponent={listEmpty}
        ListFooterComponent={listFooter}
        columnWrapperStyle={hasActiveProductFilter ? styles.prodRow : undefined}
        contentContainerStyle={[
          styles.listContent,
          { paddingBottom: insets.bottom + 16 },
        ]}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={refresh}
            tintColor={STORE_ACCENT}
            colors={[STORE_ACCENT]}
          />
        }
        onEndReached={() => {
          if (hasMore) loadMore();
        }}
        onEndReachedThreshold={0.35}
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  listContent: {
    paddingHorizontal: H_PAD,
    paddingTop: 8,
  },
  headerBlock: {
    gap: 4,
    marginBottom: 8,
  },
  heroCard: {
    borderRadius: 20,
    padding: 20,
    marginBottom: 16,
    ...Platform.select({
      ios: {
        shadowColor: STORE_ACCENT,
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.12,
        shadowRadius: 10,
      },
      android: { elevation: 3 },
    }),
  },
  heroRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  heroText: { flex: 1 },
  heroTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    marginBottom: 6,
  },
  heroSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 20,
  },
  heroIcon: {
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: Platform.OS === 'ios' ? 12 : 8,
    marginBottom: 16,
  },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Regular',
    fontSize: 15,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    marginTop: 8,
    marginBottom: 12,
  },
  resultCount: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
  },
  catGrid: { gap: GAP, paddingBottom: 8 },
  catRow: { gap: GAP, flexDirection: 'row' },
  ownersScroll: {
    flexDirection: 'row',
    gap: 10,
    paddingBottom: 12,
  },
  prodRow: { gap: GAP, flexDirection: 'row' },
  errorBox: {
    alignItems: 'center',
    padding: 16,
    gap: 10,
  },
  errorText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    textAlign: 'center',
  },
  retryBtn: { padding: 8 },
});
