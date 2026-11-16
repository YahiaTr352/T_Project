import { AnimatedBubbles } from '@/components/animated-bubbles';
import { HtmlContent, hasHtmlContent } from '@/components/html-content';
import HeaderBar from '@/components/headerBar';
import { STORE_ACCENT } from '@/components/store/constants';
import { StoreContactBar } from '@/components/store/StoreContactBar';
import { StoreMediaGallery } from '@/components/store/StoreMediaGallery';
import { StoreProductCard } from '@/components/store/StoreProductCard';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import {
  fetchStoreProduct,
  formatDetailPrice,
  type ProductListItem,
  type StoreProductDetail,
} from '@/lib/api/store';
import { type Href, useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  Pressable,
  ScrollView,
  StyleSheet,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const RELATED_W = (SCREEN_W - 56) / 2.2;

export default function StoreProductScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const productId = useMemo(() => {
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

  const [product, setProduct] = useState<StoreProductDetail | null>(null);
  const [related, setRelated] = useState<ProductListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const load = useCallback(async () => {
    if (!Number.isFinite(productId) || productId <= 0) {
      setNotFound(true);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    setNotFound(false);

    const res = await fetchStoreProduct(productId);
    setLoading(false);

    if (!res.ok) {
      setError(res.message);
      setNotFound(res.status === 404);
      setProduct(null);
      setRelated([]);
      return;
    }

    setProduct(res.product);
    setRelated(res.related);
  }, [productId]);

  useEffect(() => {
    void load();
  }, [load]);

  const goProduct = useCallback(
    (id: number) => {
      router.push(`/(app)/store/product/${id}` as Href);
    },
    [router]
  );

  if (loading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator size="large" color={STORE_ACCENT} />
      </ThemedView>
    );
  }

  if (notFound || !product) {
    return (
      <ThemedView style={styles.container}>
        <HeaderBar
          title="المنتج"
          showBack
          onBackPress={() => router.back()}
          style={{ paddingTop: insets.top }}
        />
        <View style={styles.centered}>
          <ThemedText style={{ color: mutedColor, textAlign: 'center' }}>
            {error ?? 'المنتج غير موجود.'}
          </ThemedText>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              رجوع
            </ThemedText>
          </Pressable>
        </View>
      </ThemedView>
    );
  }

  const metaParts = [
    product.category?.name,
    product.subcategory?.name,
    product.location?.name,
  ].filter(Boolean);

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={3} minSize={18} maxSize={60} />
      <HeaderBar
        title={product.name}
        showBack
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 24 },
        ]}>
        <StoreMediaGallery
          media={product.media}
          mainImageUrl={product.main_image_url}
        />

        <View style={styles.titleBlock}>
          <ThemedText type="title" style={styles.productName}>
            {product.name}
          </ThemedText>
          <ThemedText style={styles.price}>
            {formatDetailPrice(product.price_formatted, product.price)}
          </ThemedText>
          {metaParts.length > 0 ? (
            <ThemedText
              style={[styles.metaLine, { color: mutedColor }]}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              {metaParts.join(' · ')}
            </ThemedText>
          ) : null}
        </View>

        {product.owner?.name ? (
          <View style={[styles.infoCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <IconSymbol name="person.fill" size={18} color={STORE_ACCENT} />
            <ThemedText type="defaultSemiBold" style={styles.infoText}>
              {product.owner.name}
            </ThemedText>
          </View>
        ) : null}

        {product.features.length > 0 ? (
          <View style={[styles.featuresCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
              المميزات
            </ThemedText>
            {product.features.map((f, i) => (
              <View key={`${i}-${f}`} style={styles.featureRow}>
                <View style={[styles.bullet, { backgroundColor: STORE_ACCENT }]} />
                <ThemedText style={styles.featureText}>{f}</ThemedText>
              </View>
            ))}
          </View>
        ) : null}

        {(hasHtmlContent(product.description) || product.description) && (
          <View style={[styles.descCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
              الوصف
            </ThemedText>
            <HtmlContent
              html={product.description}
              contentWidth={contentWidth - 40}
            />
          </View>
        )}

        <View style={[styles.contactCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
          <StoreContactBar
            contact={product.contact}
            cardBg={cardBg}
            borderColor={colors.border}
          />
        </View>

        {related.length > 0 ? (
          <View style={styles.relatedSection}>
            <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
              منتجات ذات صلة
            </ThemedText>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.relatedScroll}>
              {related.map((item) => (
                <StoreProductCard
                  key={item.id}
                  product={item}
                  onPress={() => goProduct(item.id)}
                  style={{ width: RELATED_W }}
                  cardBg={cardBg}
                  borderColor={colors.border}
                  mutedColor={mutedColor}
                />
              ))}
            </ScrollView>
          </View>
        ) : null}

        {error ? (
          <Pressable onPress={load} style={styles.retryWrap}>
            <ThemedText style={{ color: STORE_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              إعادة التحميل
            </ThemedText>
          </Pressable>
        ) : null}
      </ScrollView>
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
  scroll: {
    paddingHorizontal: 20,
    paddingTop: 8,
    gap: 16,
  },
  titleBlock: {
    gap: 8,
  },
  productName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
  },
  price: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    color: STORE_ACCENT,
  },
  metaLine: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  infoCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  infoText: {
    fontFamily: 'Tajawal-Bold',
    flex: 1,
  },
  featuresCard: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
  },
  featureRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  bullet: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginTop: 8,
  },
  featureText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    flex: 1,
    lineHeight: 22,
  },
  descCard: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
  },
  contactCard: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
  },
  blockTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  relatedSection: { gap: 12 },
  relatedScroll: {
    flexDirection: 'row',
    gap: 12,
    paddingVertical: 4,
  },
  retryWrap: { alignItems: 'center', padding: 12 },
});
