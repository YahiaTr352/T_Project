import { AnimatedBubbles } from '@/components/animated-bubbles';
import { HtmlContent } from '@/components/html-content';
import HeaderBar from '@/components/headerBar';
import { IMAGE_PLACEHOLDER_BLURHASH, STORE_ACCENT } from '@/components/store/constants';
import { StoreProductCard } from '@/components/store/StoreProductCard';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useStoreCategory } from '@/hooks/use-store-category';
import type { ProductListItem } from '@/lib/api/store';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { type Href, useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useMemo } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const H_PAD = 20;
const GAP = 12;
const PROD_CELL = (SCREEN_W - H_PAD * 2 - GAP) / 2;

export default function StoreCategoryScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const categoryId = useMemo(() => {
    const n = Number(idParam);
    return Number.isFinite(n) ? n : NaN;
  }, [idParam]);

  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width: contentWidth } = useWindowDimensions();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  const cardBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';
  const mutedColor = colorScheme === 'dark' ? '#9CA3AF' : '#64748B';
  const chipInactiveBg = colorScheme === 'dark' ? '#1F2937' : '#F1F5F9';

  const {
    category,
    subcategories,
    selectedSubcategoryId,
    selectSubcategory,
    products,
    meta,
    pageLoading,
    loading,
    loadingMore,
    refreshing,
    error,
    notFound,
    refresh,
    loadMore,
    retry,
    hasMore,
  } = useStoreCategory(categoryId);

  const goProduct = useCallback(
    (productId: number) => {
      router.push(`/(app)/store/product/${productId}` as Href);
    },
    [router]
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

  const listHeader = useMemo(() => {
    if (!category) return null;
    const heroUri = category.image_url?.trim();

    return (
      <View style={styles.headerBlock}>
        <View style={styles.heroImageWrap}>
          {heroUri ? (
            <Image
              source={{ uri: heroUri }}
              style={styles.heroImage}
              contentFit="cover"
              transition={220}
              placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
            />
          ) : (
            <LinearGradient
              colors={[STORE_ACCENT + '40', STORE_ACCENT + '10']}
              style={styles.heroImage}
            />
          )}
          <LinearGradient
            colors={['transparent', 'rgba(0,0,0,0.65)']}
            style={styles.heroOverlay}
          />
          <View style={styles.heroCaption}>
            <ThemedText style={styles.heroTitle}>{category.name}</ThemedText>
            {category.products_count > 0 ? (
              <ThemedText style={styles.heroCount}>
                {category.products_count} منتج
              </ThemedText>
            ) : null}
          </View>
        </View>

        {category.description?.trim() ? (
          <View style={[styles.descCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <HtmlContent
              html={category.description}
              contentWidth={contentWidth - H_PAD * 2 - 32}
            />
          </View>
        ) : null}

        {subcategories.length > 0 ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipsScroll}>
            <Pressable
              onPress={() => selectSubcategory(null)}
              style={[
                styles.chip,
                {
                  backgroundColor:
                    selectedSubcategoryId == null ? STORE_ACCENT + '22' : chipInactiveBg,
                  borderColor:
                    selectedSubcategoryId == null ? STORE_ACCENT : colors.border,
                },
              ]}>
              <ThemedText
                style={[
                  styles.chipText,
                  selectedSubcategoryId == null && { color: STORE_ACCENT },
                ]}>
                الكل
              </ThemedText>
            </Pressable>
            {subcategories.map((sub) => {
              const active = selectedSubcategoryId === sub.id;
              return (
                <Pressable
                  key={sub.id}
                  onPress={() => selectSubcategory(sub.id)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? STORE_ACCENT + '22' : chipInactiveBg,
                      borderColor: active ? STORE_ACCENT : colors.border,
                    },
                  ]}>
                  <ThemedText style={[styles.chipText, active && { color: STORE_ACCENT }]}>
                    {sub.name}
                  </ThemedText>
                </Pressable>
              );
            })}
          </ScrollView>
        ) : null}

        <ThemedText type="defaultSemiBold" style={styles.productsHeading}>
          المنتجات
          {meta != null ? (
            <ThemedText style={{ color: mutedColor }}> ({meta.total})</ThemedText>
          ) : null}
        </ThemedText>
      </View>
    );
  }, [
    category,
    subcategories,
    selectedSubcategoryId,
    selectSubcategory,
    meta,
    cardBg,
    colors.border,
    chipInactiveBg,
    contentWidth,
    mutedColor,
  ]);

  if (pageLoading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator size="large" color={STORE_ACCENT} />
      </ThemedView>
    );
  }

  if (notFound || !category) {
    return (
      <ThemedView style={styles.container}>
        <HeaderBar
          title="التصنيف"
          showBack
          onBackPress={() => router.back()}
          style={{ paddingTop: insets.top }}
        />
        <View style={styles.centered}>
          <IconSymbol name="cart.fill" size={40} color={mutedColor} />
          <ThemedText style={[styles.notFoundText, { color: mutedColor }]}>
            {error ?? 'التصنيف غير موجود أو غير متاح.'}
          </ThemedText>
          <Pressable onPress={() => router.back()} style={styles.backLink}>
            <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              العودة للمتجر
            </ThemedText>
          </Pressable>
        </View>
      </ThemedView>
    );
  }

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={4} minSize={20} maxSize={70} />
      <HeaderBar
        title={category.name}
        showBack
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <FlatList
        data={products}
        keyExtractor={(p) => String(p.id)}
        renderItem={renderProduct}
        numColumns={2}
        ListHeaderComponent={listHeader}
        columnWrapperStyle={styles.prodRow}
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
        ListEmptyComponent={
          loading && !refreshing ? (
            <ActivityIndicator color={STORE_ACCENT} style={{ marginTop: 24 }} />
          ) : error ? (
            <View style={styles.errorBox}>
              <ThemedText style={styles.errorText}>{error}</ThemedText>
              <Pressable onPress={retry}>
                <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
                  إعادة المحاولة
                </ThemedText>
              </Pressable>
            </View>
          ) : (
            <ThemedText
              style={[styles.emptyProducts, { color: mutedColor }]}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              لا توجد منتجات في هذا التصنيف حالياً.
            </ThemedText>
          )
        }
        ListFooterComponent={
          loadingMore ? (
            <ActivityIndicator color={STORE_ACCENT} style={{ marginVertical: 16 }} />
          ) : (
            <View style={{ height: 8 }} />
          )
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
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 12,
  },
  listContent: {
    paddingHorizontal: H_PAD,
    paddingTop: 8,
  },
  headerBlock: { marginBottom: 8, gap: 12 },
  heroImageWrap: {
    borderRadius: 18,
    overflow: 'hidden',
    height: 180,
    backgroundColor: '#E2E8F0',
  },
  heroImage: {
    width: '100%',
    height: '100%',
  },
  heroOverlay: {
    ...StyleSheet.absoluteFill,
  },
  heroCaption: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: 16,
  },
  heroTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    color: '#FFFFFF',
  },
  heroCount: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    color: 'rgba(255,255,255,0.9)',
    marginTop: 4,
  },
  descCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 16,
  },
  chipsScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 4,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1.5,
  },
  chipText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  productsHeading: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    marginTop: 4,
  },
  prodRow: { gap: GAP, flexDirection: 'row' },
  errorBox: { alignItems: 'center', padding: 20, gap: 10 },
  errorText: { fontFamily: 'Tajawal-Regular', textAlign: 'center' },
  emptyProducts: {
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    paddingVertical: 24,
  },
  notFoundText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 15,
    textAlign: 'center',
  },
  backLink: { padding: 12 },
});
