import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { HtmlContent } from '@/components/html-content';
import { ThemedView } from '@/components/themed-view';
import { Colors, silaTheme } from '@/constants/theme';
import { fetchArticleDetail, type ArticleDetail, type RelatedArticle } from '@/lib/api/articles';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W, height: SCREEN_H } = Dimensions.get('window');
const IMAGE_PLACEHOLDER_BLURHASH = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

export default function ArticleDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const articleId = Number(id);
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const insets = useSafeAreaInsets();
  const isDark = colorScheme === 'dark';

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [article, setArticle] = useState<ArticleDetail | null>(null);
  const [relatedArticles, setRelatedArticles] = useState<RelatedArticle[]>([]);
  const [activeImageIndex, setActiveImageIndex] = useState(0);

  const loadDetail = useCallback(async (isRef = false) => {
    if (!isRef) setLoading(true);
    setError(null);
    const res = await fetchArticleDetail(articleId);
    setLoading(false);
    setRefreshing(false);
    if (!res.ok) {
      setError(res.message);
      return;
    }
    setArticle(res.article);
    setRelatedArticles(res.related_articles);
  }, [articleId]);

  useEffect(() => {
    loadDetail();
  }, [loadDetail]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadDetail(true);
  }, [loadDetail]);

  const handleRelatedPress = useCallback((relId: number) => {
    Haptics.selectionAsync().catch(() => { });
    // التوجيه لنفس الصفحة ولكن بمعرف مختلف
    router.setParams({ id: relId.toString() });
  }, []);

  const openVideo = useCallback((url: string | null) => {
    if (url) {
      Haptics.selectionAsync().catch(() => { });
      WebBrowser.openBrowserAsync(url).catch(() => { });
    }
  }, []);

  const handleScroll = useCallback((event: any) => {
    const slide = Math.ceil(event.nativeEvent.contentOffset.x / SCREEN_W);
    if (slide !== activeImageIndex) {
      setActiveImageIndex(slide);
    }
  }, [activeImageIndex]);

  if (loading && !refreshing) {
    return (
      <ThemedView style={styles.centerContainer}>
        <ActivityIndicator size="large" color={silaTheme.primary} />
        <Text style={[styles.loadingText, { color: colors.text }]}>جاري تحميل المقال...</Text>
      </ThemedView>
    );
  }

  if (error || !article) {
    return (
      <ThemedView style={styles.centerContainer}>
        <Ionicons name="alert-circle-outline" size={64} color={silaTheme.textMuted} />
        <Text style={[styles.errorTitle, { color: colors.text }]}>عذراً، حدث خطأ ما</Text>
        <Text style={styles.errorBody}>{error || 'لم يتم العثور على المقال.'}</Text>
        <TouchableOpacity onPress={() => loadDetail()} style={[styles.retryBtn, { backgroundColor: silaTheme.primary }]}>
          <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
        </TouchableOpacity>
      </ThemedView>
    );
  }

  const imagesCount = article.images?.length ?? 0;
  const formattedDate = new Date(article.created_at).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={8} />

      <HeaderBar
        title="تفاصيل المقال"
        showBack={true}
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={[styles.scrollContent, { paddingBottom: insets.bottom + 40 }]}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            colors={[silaTheme.primary]}
            tintColor={silaTheme.primary}
          />
        }
      >
        {/* معرض الصور العلوي */}
        {imagesCount > 0 && (
          <View style={styles.sliderWrapper}>
            <ScrollView
              horizontal
              pagingEnabled
              showsHorizontalScrollIndicator={false}
              onScroll={handleScroll}
              scrollEventThrottle={16}
              style={{ width: SCREEN_W }}
            >
              {article.images.map((img) => (
                <View key={img.id} style={{ width: SCREEN_W, height: SCREEN_H * 0.3 }}>
                  <Image
                    source={{ uri: img.path }}
                    style={StyleSheet.absoluteFill}
                    contentFit="cover"
                    placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
                  />
                  <LinearGradient
                    colors={['transparent', 'rgba(0,0,0,0.65)']}
                    style={StyleSheet.absoluteFill}
                  />
                </View>
              ))}
            </ScrollView>

            {imagesCount > 1 && (
              <View style={styles.dotsRow}>
                {article.images.map((_, idx) => (
                  <View
                    key={idx}
                    style={[
                      styles.dot,
                      activeImageIndex === idx ? styles.dotActive : styles.dotInactive
                    ]}
                  />
                ))}
              </View>
            )}
          </View>
        )}

        {/* محتوى المقال */}
        <View style={styles.contentCard}>
          {/* التصنيف والتاريخ */}
          <View style={styles.metaRow}>
            <View style={styles.categoryTag}>
              <Text style={styles.categoryTagText}>{article.category?.name || 'مقال'}</Text>
            </View>
            <View style={styles.dateRow}>
              <Ionicons name="calendar-outline" size={14} color="#7a9490" />
              <Text style={styles.dateText}>{formattedDate}</Text>
            </View>
          </View>

          {/* العنوان الرئيسي */}
          <Text style={[styles.title, { color: colors.text }]}>{article.title}</Text>

          {/* الكاتب */}
          <View style={[styles.authorCard, { backgroundColor: isDark ? 'rgba(255,255,255,0.03)' : '#f8fafa', borderColor: isDark ? 'rgba(255,255,255,0.05)' : '#e2ebe7' }]}>
            {article.author?.avatar ? (
              <Image source={{ uri: article.author.avatar }} style={styles.authorAvatar} />
            ) : (
              <View style={styles.authorAvatarPlaceholder}>
                <Ionicons name="person" size={18} color="#fff" />
              </View>
            )}
            <View style={styles.authorInfo}>
              <Text style={[styles.authorLabel, { color: isDark ? '#aaa' : '#7a9490' }]}>كاتب المقال</Text>
              <Text style={[styles.authorName, { color: colors.text }]}>{article.author?.name || 'غير معروف'}</Text>
            </View>
          </View>

          {/* متن المقال بالـ HTML */}
          <View style={styles.htmlWrapper}>
            <HtmlContent
              html={article.content}
              contentWidth={SCREEN_W - 32}
              textColor={colors.text}
            />
          </View>
        </View>

        {/* الفيديوهات المرفقة */}
        {article.videos && article.videos.length > 0 && (
          <View style={styles.sectionWrapper}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>الفيديوهات المرتبطة</Text>
            <View style={styles.videosList}>
              {article.videos.map((vid) => (
                <TouchableOpacity
                  key={vid.id}
                  onPress={() => openVideo(vid.youtube_url)}
                  style={[styles.videoItem, { backgroundColor: isDark ? '#1a2e2a' : '#fff', borderColor: isDark ? 'rgba(255,255,255,0.05)' : '#e2ebe7' }]}
                >
                  <Ionicons name="logo-youtube" size={32} color="#dc2626" />
                  <View style={styles.videoInfo}>
                    <Text style={[styles.videoTitle, { color: colors.text }]} numberOfLines={1}>
                      شاهد الفيديو على يوتيوب
                    </Text>
                    <Text style={styles.videoSubtitle} numberOfLines={1}>
                      رمز الفيديو: {vid.video_id}
                    </Text>
                  </View>
                  <Ionicons name="open-outline" size={20} color={silaTheme.textMuted} />
                </TouchableOpacity>
              ))}
            </View>
          </View>
        )}

        {/* المقالات ذات الصلة */}
        {relatedArticles.length > 0 && (
          <View style={styles.sectionWrapper}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>مقالات ذات صلة</Text>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.relatedScroll}
            >
              <View style={styles.innerRow}>
                {relatedArticles.map((item) => (
                  <TouchableOpacity
                    key={item.id}
                    onPress={() => handleRelatedPress(item.id)}
                    style={[styles.relatedCard, { backgroundColor: isDark ? '#1a2e2a' : '#fff', borderColor: isDark ? 'rgba(255,255,255,0.05)' : '#e2ebe7' }]}
                    activeOpacity={0.8}
                  >
                    <View style={styles.relatedCardImageWrapper}>
                      {item.image ? (
                        <Image source={{ uri: item.image }} style={styles.relatedCardImage} />
                      ) : (
                        <View style={[styles.relatedPlaceholder, { backgroundColor: isDark ? '#142521' : '#f0fdf4' }]}>
                          <Ionicons name="document-text" size={24} color={silaTheme.primary} />
                        </View>
                      )}
                    </View>
                    <View style={styles.relatedCardInfo}>
                      <Text style={[styles.relatedCardTitle, { color: colors.text }]} numberOfLines={2}>
                        {item.title}
                      </Text>
                      <Text style={styles.relatedCardAuthor} numberOfLines={1}>
                        بقلم: {item.author?.name || 'غير معروف'}
                      </Text>
                    </View>
                  </TouchableOpacity>
                ))}
              </View>
            </ScrollView>
          </View>
        )}
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 15,
    fontFamily: 'Tajawal-Medium',
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
  scrollContent: {
    paddingBottom: 40,
  },
  sliderWrapper: {
    width: SCREEN_W,
    height: SCREEN_H * 0.3,
    position: 'relative',
  },
  dotsRow: {
    position: 'absolute',
    bottom: 12,
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 6,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  dotActive: {
    backgroundColor: silaTheme.primary,
    width: 14,
  },
  dotInactive: {
    backgroundColor: 'rgba(255,255,255,0.45)',
  },
  contentCard: {
    paddingHorizontal: 16,
    paddingTop: 20,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  categoryTag: {
    backgroundColor: 'rgba(55,160,92,0.12)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  categoryTagText: {
    color: silaTheme.primary,
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  dateText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
  },
  title: {
    fontSize: 22,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 28,

    marginBottom: 16,
  },
  authorCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
    gap: 12,
    marginBottom: 20,
  },
  authorAvatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
  },
  authorAvatarPlaceholder: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: silaTheme.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  authorInfo: {
    flex: 1,
    alignItems: 'flex-start',
  },
  authorLabel: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
  },
  authorName: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    marginTop: 2,
  },
  htmlWrapper: {
    marginTop: 8,
  },
  sectionWrapper: {
    marginTop: 28,
    paddingHorizontal: 16,
  },
  sectionTitle: {
    fontSize: 17,
    fontFamily: 'Tajawal-Bold',

    marginBottom: 12,
  },
  videosList: {
    gap: 10,
  },
  videoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    gap: 12,
  },
  videoInfo: {
    flex: 1,
    alignItems: 'flex-start',
  },
  videoTitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  videoSubtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    marginTop: 2,
  },
  relatedScroll: {
    paddingLeft: 2,
    paddingRight: 16,
  },
  innerRow: {
    flexDirection: 'row',
    gap: 12,
  },
  relatedCard: {
    width: 160,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#145147',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
      },
      android: { elevation: 1 },
    }),
  },
  relatedCardImageWrapper: {
    width: '100%',
    height: 90,
  },
  relatedCardImage: {
    width: '100%',
    height: '100%',
  },
  relatedPlaceholder: {
    width: '100%',
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  relatedCardInfo: {
    padding: 8,
  },
  relatedCardTitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 16,
    height: 32,

  },
  relatedCardAuthor: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
    color: '#7a9490',
    marginTop: 6,

  },
});
