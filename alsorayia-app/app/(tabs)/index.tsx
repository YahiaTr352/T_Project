import HeaderBar from '@/components/headerBar';
import {
  ExpandableDataTable,
  TABLE_HTML_CONTENT_WIDTH,
  TableMapButton,
  TableMutedText,
  TablePrimaryText,
} from '@/components/home/ExpandableDataTable';
import { HtmlContent } from '@/components/html-content';
import { DynamicSections } from '@/components/home/DynamicSections';
import HomeContactWidget from '@/components/home/HomeContactWidget';
import QuizCompetitionWidget, { type QuizCompetitionWidgetRef } from '@/components/quiz/QuizCompetitionWidget';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { Colors, type ThemeColors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { useHomePage } from '@/hooks/use-home-page';
import { fetchNotifications, fetchUnreadCount } from '@/lib/api/notifications';
import type {
  HomeCouncil,
  HomeCourse,
  HomeEvent,
  HomeFamilyNewsItem,
  HomeGalleryItem,
  HomeImportantLink,
  HomeImportantLinkCategory,
  HomeProgramCategoryGroup,
  HomeProgramImage,
  HomeSlide,
} from '@/lib/api/homePage';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { type Href, useIsFocused, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { appStorage } from '@/lib/storage/appStorage';
import { HomeScreenSkeleton } from '@/components/ui/skeleton';
import { ImageViewerModal, type ViewerImage } from '@/components/ui/zoomable-image';
import {
  Dimensions,
  FlatList,
  Keyboard,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

const AR_GREGORIAN_MONTHS = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
] as const;

function formatEventDate(iso: string | null): string {
  if (!iso) return '—';
  try {
    const raw = String(iso).trim().replace(/\//g, '-');
    const d = new Date(raw.includes('T') ? raw : `${raw}T12:00:00Z`);
    if (Number.isNaN(d.getTime())) return iso;
    const day = d.getUTCDate();
    const month = AR_GREGORIAN_MONTHS[d.getUTCMonth()];
    const year = d.getUTCFullYear();
    return month ? `${day} ${month} ${year}` : `${day}/${d.getUTCMonth() + 1}/${year}`;
  } catch {
    return iso;
  }
}

/** مجموعة برامج تحت عنوان التصنيف كما في الـ API */
type ProgramSection = { categoryTitle: string; programs: HomeProgramImage[] };

function isUsableProgram(p: unknown): p is HomeProgramImage {
  if (p == null || typeof p !== 'object') return false;
  const o = p as Partial<HomeProgramImage>;
  if (typeof o.id !== 'number') return false;
  if (o.is_program === false) return false;
  return !!(o.image_url || o.program_title?.trim() || o.name?.trim());
}

function filterPrograms(list: unknown[]): HomeProgramImage[] {
  return list.filter(isUsableProgram);
}

/**
 * يعرض البرامج حسب `program_categories` عند وجود عناصر صالحة؛
 * وإلا يستخدم المسطّح `programs`. يتجاهل `{}` والصفوف الفارغة.
 */
function buildProgramSections(
  flatPrograms: HomeProgramImage[],
  categories: HomeProgramCategoryGroup[] | undefined
): ProgramSection[] {
  const sections: ProgramSection[] = [];
  const cats = Array.isArray(categories) ? categories : [];

  for (let i = 0; i < cats.length; i++) {
    const cat = cats[i];
    if (cat == null || typeof cat !== 'object') continue;
    const title = typeof cat.title === 'string' ? cat.title.trim() : '';
    const raw = Array.isArray(cat.programs) ? cat.programs : [];
    const valid = filterPrograms(raw);
    if (!valid.length) continue;
    sections.push({
      categoryTitle: title,
      programs: valid,
    });
  }

  if (sections.length > 0) return sections;

  const flat = filterPrograms(flatPrograms as unknown[]);
  if (!flat.length) return [];
  return [{ categoryTitle: '', programs: flat }];
}

function councilOrder(c: HomeCouncil): number {
  const o = c.display_order;
  if (typeof o === 'number' && !Number.isNaN(o)) return o;
  const n = parseInt(String(o ?? ''), 10);
  return Number.isNaN(n) ? 0 : n;
}

function councilWorkingDays(c: HomeCouncil): string {
  const a = c.working_days_from?.trim();
  const b = c.working_days_to?.trim();
  if (a && b) return `${a} – ${b}`;
  return a || b || '—';
}

function eventDisplayOrder(e: HomeEvent): number {
  const o = e.display_order;
  if (typeof o === 'number' && !Number.isNaN(o)) return o;
  const n = parseInt(String(o ?? ''), 10);
  return Number.isNaN(n) ? 0 : n;
}

function eventMapUrl(e: HomeEvent): string | null {
  const loc = e.location?.trim();
  if (loc && /^https?:\/\//i.test(loc)) return loc;
  return null;
}

function eventPlaceLabel(e: HomeEvent): string {
  return (
    e.location_name?.trim() ||
    e.city?.trim() ||
    (e.location && !/^https?:\/\//i.test(e.location.trim()) ? e.location.trim() : '') ||
    '—'
  );
}

function importantLinkOrder(l: HomeImportantLink): number {
  const o = l.order;
  if (typeof o === 'number' && !Number.isNaN(o)) return o;
  const n = parseInt(String(o ?? ''), 10);
  return Number.isNaN(n) ? 0 : n;
}

function openExternal(url: string | null | undefined) {
  if (!url) return;
  WebBrowser.openBrowserAsync(url).catch(() => { });
}

// ==================== أقسام الواجهة ====================

function HeroSlider({
  colors,
  slides,
}: {
  colors: ThemeColors;
  slides: HomeSlide[];
}) {
  const list = useMemo(() => {
    if (slides.length) return slides;
    return [
      {
        id: -1,
        title: 'مرحباً بكم في عائلة السريّع',
        description: 'صلة الرحم ومجتمع متماسك',
        link: null as string | null,
        image_url: null as string | null,
      },
    ];
  }, [slides]);

  const listKey = useMemo(() => list.map((s) => s.id).join(','), [list]);
  const [activeIndex, setActiveIndex] = useState(0);
  const flatListRef = useRef<FlatList>(null);
  const activeIndexRef = useRef(0);

  const [viewerVisible, setViewerVisible] = useState(false);
  const [viewerIndex, setViewerIndex] = useState(0);

  const imageSlides = useMemo(
    () => list.filter((s): s is (typeof list)[0] & { image_url: string } => Boolean(s.image_url)),
    [list]
  );

  const viewerImages: ViewerImage[] = useMemo(
    () => imageSlides.map((s) => ({ uri: s.image_url, title: s.title })),
    [imageSlides]
  );

  useEffect(() => {
    setActiveIndex(0);
    activeIndexRef.current = 0;
    requestAnimationFrame(() => {
      flatListRef.current?.scrollToOffset({ offset: 0, animated: false });
    });
  }, [listKey]);

  useEffect(() => {
    if (list.length <= 1) return undefined;
    const interval = setInterval(() => {
      const next = (activeIndexRef.current + 1) % list.length;
      activeIndexRef.current = next;
      setActiveIndex(next);
      flatListRef.current?.scrollToIndex({ index: next, animated: true });
    }, 4000);
    return () => clearInterval(interval);
  }, [listKey, list.length]);

  const handlePressSlide = (item: (typeof list)[0]) => {
    if (item.image_url) {
      const idx = imageSlides.findIndex((s) => s.id === item.id);
      setViewerIndex(idx >= 0 ? idx : 0);
      setViewerVisible(true);
    } else if (item.link) {
      openExternal(item.link);
    }
  };

  const renderSlide = ({ item }: { item: (typeof list)[0] }) => {
    const isInteractive = Boolean(item.image_url || item.link);

    return (
      <TouchableOpacity
        style={[styles.slideContainer, item.image_url ? styles.slideContainerWithImage : null]}
        activeOpacity={isInteractive ? 0.88 : 1}
        onPress={() => handlePressSlide(item)}
        disabled={!isInteractive}
      >
        {item.image_url ? (
          <Image source={{ uri: item.image_url }} style={styles.slideImage} contentFit="contain" />
        ) : (
          <LinearGradient
            colors={[colors.primary, '#0f172a']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.slideImage}
          />
        )}
        <LinearGradient
          colors={['transparent', 'rgba(0,0,0,0.72)']}
          style={styles.slideGradient}
        />

        {/* Visual Zoom Hint badge when slide has an image */}
        {item.image_url ? (
          <View style={styles.slideZoomBadge}>
            <IconSymbol name="magnifyingglass" size={13} color="#ffffff" />
            <ThemedText style={styles.slideZoomBadgeText}>تكبير الصورة</ThemedText>
          </View>
        ) : null}

        <View style={styles.slideContent}>
          <ThemedText style={styles.slideTitle}>{item.title}</ThemedText>
          <ThemedText style={styles.slideSubtitle}>
            {item.description?.trim() || ' '}
          </ThemedText>

          {/* External Link button if slide has a link */}
          {item.link ? (
            <TouchableOpacity
              style={styles.slideLinkChip}
              onPress={(e) => {
                e.stopPropagation();
                openExternal(item.link);
              }}
              activeOpacity={0.8}
            >
              <IconSymbol name="arrow.up.right.square.fill" size={13} color="#ffffff" />
              <ThemedText style={styles.slideLinkChipText}>فتح الرابط</ThemedText>
            </TouchableOpacity>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.sliderWrapper}>
      <FlatList
        ref={flatListRef}
        data={list}
        renderItem={renderSlide}
        keyExtractor={(item) => String(item.id)}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => {
          const index = Math.round(e.nativeEvent.contentOffset.x / SCREEN_WIDTH);
          const clamped = Math.min(Math.max(0, index), list.length - 1);
          activeIndexRef.current = clamped;
          setActiveIndex(clamped);
        }}
        onScrollToIndexFailed={({ index }) => {
          requestAnimationFrame(() => {
            flatListRef.current?.scrollToIndex({ index, animated: true });
          });
        }}
        getItemLayout={(_, index) => ({
          length: SCREEN_WIDTH,
          offset: SCREEN_WIDTH * index,
          index,
        })}
      />
      <View style={styles.pagination}>
        {list.map((_, index) => (
          <View
            key={index}
            style={[
              styles.paginationDot,
              {
                backgroundColor: index === activeIndex ? '#fff' : 'rgba(255,255,255,0.4)',
                width: index === activeIndex ? 24 : 8,
              },
            ]}
          />
        ))}
      </View>

      <ImageViewerModal
        visible={viewerVisible}
        images={viewerImages}
        initialIndex={viewerIndex}
        onClose={() => setViewerVisible(false)}
      />
    </View>
  );
}

function PhotoGallery({
  colors,
  items,
  onSeeAll,
}: {
  colors: ThemeColors;
  items: HomeGalleryItem[];
  onSeeAll: () => void;
}) {
  const renderImage = ({ item }: { item: HomeGalleryItem }) => (
    <TouchableOpacity
      style={[styles.galleryItem, { backgroundColor: colors.border }]}
      activeOpacity={0.92}
      onPress={onSeeAll}
    >
      {item.image_url ? (
        <Image source={{ uri: item.image_url }} style={styles.galleryImage} contentFit="contain" />
      ) : (
        <View style={[styles.galleryImage, { backgroundColor: colors.border }]} />
      )}
    </TouchableOpacity>
  );

  if (!items.length) return null;

  return (
    <View style={styles.sectionContainer}>
      <View style={styles.sectionHeader}>
        <ThemedText style={[styles.sectionTitle, { color: colors.text }]}>معرض الصور</ThemedText>
        <TouchableOpacity onPress={onSeeAll} hitSlop={12}>
          <ThemedText style={[styles.seeAll, { color: colors.primary }]}>عرض الكل</ThemedText>
        </TouchableOpacity>
      </View>
      <FlatList
        data={items}
        renderItem={renderImage}
        keyExtractor={(item) => `g-${item.type}-${item.id}`}
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.galleryList}
      />
    </View>
  );
}

// Helper for mapping FontAwesome icons from the API to IconSymbolNames
function mapFaIconToSymbol(faIcon: string | null | undefined): IconSymbolName {
  if (!faIcon) return 'link';
  const name = faIcon.toLowerCase();
  if (name.includes('university') || name.includes('bank') || name.includes('government') || name.includes('landmark')) return 'house.fill';
  if (name.includes('id-card') || name.includes('user') || name.includes('profile') || name.includes('address-card')) return 'person.fill';
  if (name.includes('globe') || name.includes('website') || name.includes('internet')) return 'link';
  if (name.includes('link')) return 'link';
  if (name.includes('folder')) return 'folder.fill';
  if (name.includes('book')) return 'book.fill';
  if (name.includes('graduation') || name.includes('school') || name.includes('academic')) return 'graduationcap.fill';
  if (name.includes('heart')) return 'heart.fill';
  if (name.includes('gift')) return 'gift.fill';
  if (name.includes('star')) return 'star.fill';
  if (name.includes('bell')) return 'bell.fill';
  if (name.includes('gear') || name.includes('cog')) return 'gearshape.fill';
  if (name.includes('calendar') || name.includes('event')) return 'calendar';
  return 'link';
}

function ImportantLinksSection({
  colors,
  links = [],
  categories = [],
}: {
  colors: ThemeColors;
  links?: HomeImportantLink[];
  categories?: HomeImportantLinkCategory[];
}) {
  const router = useRouter();

  // Active categories containing at least one active link
  const activeCategories = useMemo(() => {
    if (!categories || categories.length === 0) return [];
    return [...categories]
      .filter((c) => c.is_active)
      .sort((a, b) => a.sort_order - b.sort_order)
      .map((cat) => {
        const catLinks = (cat.links ?? [])
          .filter((l) => l.is_active !== false && (!l.status || l.status === 'approved'))
          .sort((a, b) => importantLinkOrder(a) - importantLinkOrder(b));
        return {
          ...cat,
          links: catLinks,
        };
      })
      .filter((cat) => cat.links.length > 0);
  }, [categories]);

  // Fallback flat active links if no categories are present
  const flatActiveLinks = useMemo(() => {
    return [...links]
      .filter((l) => l.is_active !== false && (!l.status || l.status === 'approved'))
      .sort((a, b) => importantLinkOrder(a) - importantLinkOrder(b));
  }, [links]);

  // Render a category section
  const renderCategorySection = (title: string, catLinks: HomeImportantLink[], key: string, categoryIcon?: string | null) => {
    const symbol = categoryIcon ? mapFaIconToSymbol(categoryIcon) : null;

    return (
      <View key={key} style={styles.sectionContainer}>
        <View style={[styles.sectionHeader, { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-start', gap: 8 }]}>
          {symbol && (
            <IconSymbol name={symbol} size={20} color={colors.primary} />
          )}
          <ThemedText style={[styles.sectionTitle, { color: colors.text }]}>{title}</ThemedText>
        </View>
        <FlatList
          data={catLinks}
          horizontal
          keyExtractor={(item) => `ilink-${item.id}`}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.importantLinksList}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={[styles.importantLinkCard, { backgroundColor: colors.card, borderColor: colors.border }]}
              activeOpacity={0.88}
              onPress={() =>
                router.push(`/(app)/important-link/${item.id}` as Href)
              }
            >
              <View style={[styles.importantLinkImageWrap, { backgroundColor: colors.border }]}>
                {item.image_url ? (
                  <Image
                    source={{ uri: item.image_url }}
                    style={StyleSheet.absoluteFill}
                    contentFit="contain"
                  />
                ) : (
                  <LinearGradient
                    colors={[colors.primary + '44', colors.primary]}
                    style={StyleSheet.absoluteFill}
                  />
                )}
              </View>
              <ThemedText style={[styles.importantLinkTitle, { color: colors.text }]} numberOfLines={2}>
                {item.title}
              </ThemedText>
            </TouchableOpacity>
          )}
        />
      </View>
    );
  };

  // If categories are present, render each category as a section
  if (activeCategories.length > 0) {
    return (
      <>
        {activeCategories.map((cat, index) =>
          renderCategorySection(cat.name, cat.links, `cat-sec-${cat.id ?? index}`, cat.icon)
        )}
      </>
    );
  }

  // Fallback: render the original flat "تطبيقات تهمك" section
  if (flatActiveLinks.length > 0) {
    return renderCategorySection('تطبيقات تهمك', flatActiveLinks, 'flat-sec', 'star.fill');
  }

  return null;
}

function openProgramDetail(router: ReturnType<typeof useRouter>, programId: number) {
  router.push({
    pathname: '/program/[id]',
    params: { id: String(programId) },
  } as Href);
}

function FamilyPrograms({
  colors,
  sections,
}: {
  colors: ThemeColors;
  sections: ProgramSection[];
}) {
  const router = useRouter();
  const total = sections.reduce((n, s) => n + s.programs.length, 0);
  if (!total) return null;

  return (
    <View style={styles.sectionContainer}>
      {sections.map((sec, secIdx) => (
        <View key={`${sec.categoryTitle}-${secIdx}`} style={secIdx > 0 ? styles.programCategoryBlock : undefined}>
          {sec.categoryTitle.trim() ? (
            <View style={styles.programSectionHeader}>
              <ThemedText style={[styles.programCategoryTitle, { color: colors.text }]}>
                {sec.categoryTitle.trim()}
              </ThemedText>
            </View>
          ) : null}
          <View style={styles.programsGrid}>
            {sec.programs.map((program) => {
              return (
                <Pressable
                  key={`${secIdx}-${program.id}`}
                  style={({ pressed }) => [
                    styles.programCard,
                    { backgroundColor: colors.card, borderColor: colors.border },
                    pressed ? { opacity: 0.88 } : null,
                  ]}
                  onPress={() => openProgramDetail(router, program.id)}
                  android_ripple={{ color: 'rgba(0,0,0,0.12)' }}
                >
                  {program.image_url ? (
                    <View style={[styles.programCardImage, { backgroundColor: colors.border }]} pointerEvents="none">
                      <Image
                        source={{ uri: program.image_url }}
                        style={StyleSheet.absoluteFill}
                        contentFit="contain"
                        pointerEvents="none"
                      />
                    </View>
                  ) : (
                    <LinearGradient
                      colors={[colors.primary + '55', colors.primary]}
                      style={styles.programCardImage}
                      pointerEvents="none"
                    />
                  )}
                </Pressable>
              );
            })}
          </View>
        </View>
      ))}
    </View>
  );
}

function FamilyCouncils({
  colors,
  councils,
}: {
  colors: ThemeColors;
  councils: HomeCouncil[];
}) {
  const rows = useMemo(
    () => [...councils].filter((c) => c.is_active).sort((a, b) => councilOrder(a) - councilOrder(b)),
    [councils]
  );

  return (
    <ExpandableDataTable
      colors={colors}
      sectionTitle="مجالس العائلة"
      data={rows}
      keyExtractor={(c) => c.id}
      primaryColumnKey="name"
      columns={[
        {
          key: 'name',
          title: 'المجلس',
          flex: 2,
          render: (c) => <TablePrimaryText color={colors.text}>{c.name}</TablePrimaryText>,
        },
        {
          key: 'address',
          title: 'العنوان',
          flex: 2,
          render: (c) => (
            <TableMutedText color={colors.icon}>{c.address?.trim() || '—'}</TableMutedText>
          ),
        },
        {
          key: 'days',
          title: 'أيام العمل',
          flex: 1.2,
          render: (c) => (
            <TableMutedText color={colors.icon}>{councilWorkingDays(c)}</TableMutedText>
          ),
        },
        {
          key: 'map',
          title: 'الخريطة',
          width: 52,
          render: (c) => (
            <TableMapButton
              colors={colors}
              url={c.google_map_url}
              onPress={() => openExternal(c.google_map_url)}
            />
          ),
        },
      ]}
      renderExpanded={(c) => (
        <HtmlContent
          html={c.description}
          contentWidth={TABLE_HTML_CONTENT_WIDTH}
          textColor={colors.text}
        />
      )}
    />
  );
}

function FamilyEvents({
  colors,
  events,
}: {
  colors: ThemeColors;
  events: HomeEvent[];
}) {
  const rows = useMemo(
    () =>
      [...events]
        .filter((e) => e.is_active !== false)
        .sort((a, b) => eventDisplayOrder(a) - eventDisplayOrder(b))
        .slice(0, 12),
    [events]
  );

  return (
    <ExpandableDataTable
      colors={colors}
      sectionTitle="مناسبات العائلة"
      data={rows}
      keyExtractor={(e) => e.id}
      primaryColumnKey="title"
      columns={[
        {
          key: 'title',
          title: 'المناسبة',
          flex: 2,
          render: (e) => <TablePrimaryText color={colors.text}>{e.title}</TablePrimaryText>,
        },
        {
          key: 'date',
          title: 'التاريخ',
          flex: 1.2,
          render: (e) => (
            <TableMutedText color={colors.icon}>{formatEventDate(e.event_date)}</TableMutedText>
          ),
        },
        {
          key: 'place',
          title: 'المكان',
          flex: 1.8,
          render: (e) => (
            <TableMutedText color={colors.icon}>{eventPlaceLabel(e)}</TableMutedText>
          ),
        },
        {
          key: 'map',
          title: 'الخريطة',
          width: 52,
          render: (e) => {
            const mapUrl = eventMapUrl(e);
            return (
              <TableMapButton
                colors={colors}
                url={mapUrl}
                onPress={() => openExternal(mapUrl)}
              />
            );
          },
        },
      ]}
      renderExpanded={(e) => (
        <HtmlContent
          html={e.description}
          contentWidth={TABLE_HTML_CONTENT_WIDTH}
          textColor={colors.text}
        />
      )}
    />
  );
}

function FamilyCourses({
  colors,
  courses,
  onSeeAll,
}: {
  colors: ThemeColors;
  courses: HomeCourse[];
  onSeeAll: () => void;
}) {
  const active = courses.filter((c) => c.is_active !== false).slice(0, 12);

  if (!active.length) return null;

  const renderCourse = ({ item }: { item: HomeCourse }) => (
    <TouchableOpacity
      style={[styles.courseCard, { backgroundColor: colors.card }]}
      activeOpacity={0.8}
      onPress={() => openExternal(item.link)}
      disabled={!item.link}
    >
      {item.image_url ? (
        <View style={[styles.courseImage, { backgroundColor: colors.border }]}>
          <Image
            source={{ uri: item.image_url }}
            style={StyleSheet.absoluteFill}
            contentFit="contain"
          />
        </View>
      ) : (
        <LinearGradient
          colors={[colors.primary + '99', colors.primary]}
          style={styles.courseImage}
        />
      )}
      <View style={styles.courseContent}>
        <ThemedText style={[styles.courseTitle, { color: colors.text }]} numberOfLines={2}>
          {item.title}
        </ThemedText>
        <ThemedText
          style={[styles.courseDescription, { color: colors.icon }]}
          numberOfLines={2}
        >
          {stripHtml(item.description || '').trim() || '—'}
        </ThemedText>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.sectionContainer}>
      <View style={styles.sectionHeader}>
        <ThemedText style={[styles.sectionTitle, { color: colors.text }]}>دورات العائلة</ThemedText>
        <TouchableOpacity onPress={onSeeAll} hitSlop={12}>
          <ThemedText style={[styles.seeAll, { color: colors.primary }]}>عرض الكل</ThemedText>
        </TouchableOpacity>
      </View>
      <FlatList
        data={active}
        renderItem={renderCourse}
        keyExtractor={(item) => String(item.id)}
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.coursesList}
      />
    </View>
  );
}

function IntroCard({
  colors,
  familyBrief,
  whatsNew,
}: {
  colors: ThemeColors;
  familyBrief: string | null;
  whatsNew: string;
}) {
  const brief = familyBrief?.trim();
  const rawNews = (whatsNew || '').trim();
  const isHtmlNews = /<[a-z][\s\S]*>/i.test(rawNews);
  const isPlaceholder =
    rawNews === 'آخر أخبار عائلة السريع' ||
    rawNews === 'ما الجديد' ||
    rawNews === 'اخبار العائلة' ||
    rawNews === 'أخبار العائلة';
  const news = !isPlaceholder ? (isHtmlNews ? rawNews : stripHtml(rawNews)) : '';
  if (!brief && !news) return null;

  return (
    <View style={styles.sectionContainer}>
      {brief ? (
        <View style={[styles.introCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <LinearGradient
            colors={[colors.primary + '18', 'transparent']}
            style={StyleSheet.absoluteFill}
            start={{ x: 1, y: 0 }}
            end={{ x: 0, y: 1 }}
          />
          <ThemedText style={[styles.introLabel, { color: colors.primary }]}>عن العائلة</ThemedText>
          <ThemedText style={[styles.introBody, { color: colors.text }]}>{brief}</ThemedText>
        </View>
      ) : null}
      {news ? (
        <View
          style={[
            styles.introCard,
            { backgroundColor: colors.card, borderColor: colors.border, marginTop: brief ? 12 : 0 },
          ]}
        >
          <ThemedText style={[styles.introLabel, { color: colors.primary }]}>ما الجديد</ThemedText>
          {isHtmlNews ? (
            <HtmlContent html={rawNews} contentWidth={SCREEN_WIDTH - 64} />
          ) : (
            <ThemedText style={[styles.introBody, { color: colors.text }]}>{news}</ThemedText>
          )}
        </View>
      ) : null}
    </View>
  );
}

function FamilyNewsSection({
  colors,
  items,
}: {
  colors: ThemeColors;
  items: HomeFamilyNewsItem[];
}) {
  const row = (items || [])
    .filter((item) => item && item.is_active !== false && item.title?.trim())
    .slice(0, 8);
  if (!row.length) return null;

  return (
    <View style={styles.sectionContainer}>
      <View style={styles.sectionHeader}>
        <ThemedText style={[styles.sectionTitle, { color: colors.text }]}>أخبار العائلة</ThemedText>
      </View>
      <FlatList
        data={row}
        horizontal
        showsHorizontalScrollIndicator={false}
        keyExtractor={(item) => `news-${item.id}`}
        contentContainerStyle={styles.newsList}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[styles.newsCard, { backgroundColor: colors.card, borderColor: colors.border }]}
            activeOpacity={0.85}
            onPress={() => openExternal(item.url)}
          >
            {item.main_image_url ? (
              <View style={[styles.newsImage, { backgroundColor: colors.border }]}>
                <Image
                  source={{ uri: item.main_image_url }}
                  style={StyleSheet.absoluteFill}
                  contentFit="contain"
                />
              </View>
            ) : (
              <View style={[styles.newsImage, { backgroundColor: colors.border }]} />
            )}
            <ThemedText style={[styles.newsTitle, { color: colors.text }]} numberOfLines={2}>
              {item.title}
            </ThemedText>
            {item.summary ? (
              <ThemedText style={[styles.newsSummary, { color: colors.icon }]} numberOfLines={2}>
                {stripHtml(item.summary)}
              </ThemedText>
            ) : null}
          </TouchableOpacity>
        )}
      />
    </View>
  );
}

// ==================== الصفحة الرئيسية ====================

export default function HomeScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const { user, hasIdentity, lock } = useAuth();

  const { data, loading, error, refreshing, refresh } = useHomePage();
  const quizWidgetRef = useRef<QuizCompetitionWidgetRef>(null);
  const [quizRefreshing, setQuizRefreshing] = useState(false);

  const isFocused = useIsFocused();
  const [unreadCount, setUnreadCount] = useState(0);

  const mainScrollRef = useRef<ScrollView>(null);
  const [keyboardHeight, setKeyboardHeight] = useState(0);
  // الشاشة تبقى mounted في الخلفية عند التنقل — نتجاهل أحداث الكيبورد الصادرة من شاشات أخرى
  // (كان keyboardWillShow في شاشة أخرى يعمل scrollToEnd للرئيسية في الخلفية، فيرجع المستخدم
  //  للرئيسية فيجد نفسه في الأسفل مع padding ضخم عالق يمنع الصعود على iOS).
  const isFocusedRef = useRef(isFocused);
  useEffect(() => {
    isFocusedRef.current = isFocused;
  }, [isFocused]);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSub = Keyboard.addListener(showEvent, (e) => {
      if (!isFocusedRef.current) return;
      const h = e?.endCoordinates?.height ?? 300;
      setKeyboardHeight(h);
      // لا يوجد إدخال نصي في الرئيسية (ودجت التواصل مجرد زر) — لا scrollToEnd هنا.
    });

    const hideSub = Keyboard.addListener(hideEvent, () => {
      setKeyboardHeight(0);
    });

    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  // عند مغادرة الشاشة صفّر ارتفاع الكيبورد حتى لا يعلق padding ضخم عند الرجوع (iOS).
  useEffect(() => {
    if (!isFocused) setKeyboardHeight(0);
  }, [isFocused]);

  const loadUnreadCount = useCallback(async () => {
    try {
      if (hasIdentity) {
        const count = await fetchUnreadCount();
        if (count != null) setUnreadCount(count);
        return;
      }

      const localReadStr = await appStorage.getItem('read_notification_ids');
      const localReadIds: string[] = localReadStr ? JSON.parse(localReadStr) : [];
      const items = await fetchNotifications();
      const count = items.filter((n) => {
        const idStr = String(n.id);
        return !n.read && !localReadIds.includes(idStr);
      }).length;
      setUnreadCount(count);
    } catch {
      // Silent fail on background fetch
    }
  }, [hasIdentity]);

  useEffect(() => {
    if (!isFocused) return;
    void loadUnreadCount();
  }, [isFocused, loadUnreadCount]);

  const handleRefresh = useCallback(async () => {
    setQuizRefreshing(true);
    try {
      await Promise.all([
        refresh(),
        quizWidgetRef.current?.refresh(),
        loadUnreadCount(),
      ]);
    } finally {
      setQuizRefreshing(false);
    }
  }, [refresh, loadUnreadCount]);

  const slides = useMemo(() => data?.slides?.filter((s) => s.is_active !== false) ?? [], [data]);
  const gallery = data?.gallery ?? [];
  const programSections = useMemo(
    () => buildProgramSections(data?.programs ?? [], data?.program_categories),
    [data]
  );
  const councils = data?.councils ?? [];
  const events = data?.events ?? [];
  const courses = data?.courses ?? [];
  const familyNews = useMemo(
    () =>
      (data?.familyNews ?? []).filter(
        (item) => item && item.is_active !== false && Boolean(item.title?.trim())
      ),
    [data?.familyNews]
  );
  const dynamicSections = data?.dynamicSections ?? [];
  const importantLinks = data?.importantLinks ?? [];

  const goGallery = () => router.push('/gallery');

  return (
    <ThemedView style={styles.container}>
      <HeaderBar
        user={hasIdentity && user ? user : null}
        title={hasIdentity && user ? undefined : 'عائلة السريّع'}
        subtitle={hasIdentity && user ? undefined : 'صلة الرحم ومجتمع متماسك'}
        showAvatar
        avatarSource={hasIdentity && user?.avatar ? undefined : require('@/assets/images/logo.png')}
        onAvatarPress={() => {
          if (hasIdentity && user) {
            router.push('/(app)/my-profile');
          }
        }}
        showQuickAuth={!hasIdentity}
        onQuickAuthPress={() => void lock()}
        showSearch
        onSearchPress={() => router.push('/(app)/search')}
        showNotifications
        notificationCount={unreadCount}
        onNotificationsPress={() => router.push('/(app)/notifications')}
      />

      {error && !data ? (
        <View style={styles.errorBanner}>
          <IconSymbol name="exclamationmark.triangle.fill" size={22} color="#F59E0B" />
          <ThemedText style={styles.errorText}>{error}</ThemedText>
          <TouchableOpacity style={[styles.retryBtn, { backgroundColor: colors.primary }]} onPress={() => void handleRefresh()}>
            <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
          </TouchableOpacity>
        </View>
      ) : null}

      {error && data ? (
        <View style={[styles.refreshErrorStrip, { borderColor: colors.border, backgroundColor: colors.card }]}>
          <ThemedText style={[styles.refreshErrorText, { color: colors.icon }]} numberOfLines={2}>
            تعذر التحديث — اسحب للأسفل أو أعد المحاولة
          </ThemedText>
          <TouchableOpacity onPress={() => void handleRefresh()} hitSlop={8}>
            <ThemedText style={[styles.seeAll, { color: colors.primary }]}>تحديث</ThemedText>
          </TouchableOpacity>
        </View>
      ) : null}

      <ScrollView
        ref={mainScrollRef}
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
        contentContainerStyle={[
          styles.scrollContent,
          { paddingBottom: keyboardHeight > 0 ? keyboardHeight + 80 : 30 },
        ]}
        refreshControl={
          <RefreshControl
            refreshing={refreshing || quizRefreshing}
            onRefresh={handleRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {loading && !data ? (
          <HomeScreenSkeleton colors={colors} />
        ) : data ? (
          <>
            <HeroSlider colors={colors} slides={slides} />
            <IntroCard
              colors={colors}
              familyBrief={data.familyBrief}
              whatsNew={data.whatsNew}
            />
            <DynamicSections colors={colors} sections={dynamicSections} />
            <QuizCompetitionWidget ref={quizWidgetRef} colors={colors} />
            {familyNews.length > 0 ? (
              <FamilyNewsSection colors={colors} items={familyNews} />
            ) : null}
            <FamilyCouncils colors={colors} councils={councils} />
            <FamilyEvents colors={colors} events={events} />
            <FamilyPrograms colors={colors} sections={programSections} />
            <ImportantLinksSection colors={colors} links={importantLinks} categories={data?.importantLinkCategories} />
            <FamilyCourses colors={colors} courses={courses} onSeeAll={goGallery} />
            <HomeContactWidget
              colors={colors}
              isDark={colorScheme === 'dark'}
            />
          </>
        ) : null}

        <View style={{ height: 20 }} />
      </ScrollView>
    </ThemedView>
  );
}

// ==================== الأنماط ====================

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  errorBanner: {
    marginHorizontal: 16,
    marginBottom: 12,
    padding: 14,
    borderRadius: 14,
    backgroundColor: 'rgba(245, 158, 11, 0.12)',
    gap: 10,
    alignItems: 'center',
  },
  errorText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    color: '#92400e',
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 10,
  },
  retryBtnText: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  refreshErrorStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginHorizontal: 16,
    marginBottom: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
    gap: 8,
  },
  refreshErrorText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 20,
  },

  sliderWrapper: {
    height: 200,
    marginBottom: 20,
  },
  slideContainer: {
    width: SCREEN_WIDTH,
    height: 200,
  },
  slideContainerWithImage: {
    backgroundColor: '#0b1220',
  },
  slideImage: {
    width: '100%',
    height: '100%',
  },
  slideGradient: {
    ...StyleSheet.absoluteFill,
  },
  slideContent: {
    position: 'absolute',
    bottom: 40,
    left: 20,
    right: 20,
  },
  slideTitle: {
    fontSize: 22,
    fontFamily: 'Tajawal-Bold',
    color: '#fff',
    marginBottom: 4,
  },
  slideSubtitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Regular',
    color: 'rgba(255,255,255,0.9)',
  },
  slideZoomBadge: {
    position: 'absolute',
    top: 12,
    left: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0,0,0,0.5)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  slideZoomBadgeText: {
    color: '#ffffff',
    fontSize: 10.5,
    fontFamily: 'Tajawal-Medium',
  },
  slideLinkChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(16, 185, 129, 0.85)',
    paddingHorizontal: 9,
    paddingVertical: 3.5,
    borderRadius: 6,
    marginTop: 6,
  },
  slideLinkChipText: {
    color: '#ffffff',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  pagination: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    position: 'absolute',
    bottom: 12,
    left: 0,
    right: 0,
    gap: 6,
  },
  paginationDot: {
    height: 8,
    borderRadius: 4,
  },

  sectionContainer: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
  },
  seeAll: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
  programCategoryBlock: {
    marginTop: 20,
  },
  programSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  programCategoryTitle: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
    flex: 1,
  },

  introCard: {
    marginHorizontal: 16,
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  introLabel: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 8,
  },
  introBody: {
    fontSize: 15,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 24,
  },

  galleryList: {
    paddingHorizontal: 12,
    gap: 12,
  },
  galleryItem: {
    width: 140,
    height: 100,
    borderRadius: 12,
    overflow: 'hidden',
  },
  galleryImage: {
    width: '100%',
    height: '100%',
  },

  importantLinksList: {
    paddingHorizontal: 12,
    gap: 12,
    paddingBottom: 4,
  },
  importantLinkCard: {
    width: 132,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    paddingBottom: 10,
  },
  importantLinkImageWrap: {
    width: '100%',
    height: 96,
  },
  importantLinkTitle: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    paddingHorizontal: 8,
    marginTop: 8,
    textAlign: 'center',
  },

  programsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: 12,
    gap: 12,
  },
  programCard: {
    width: (SCREEN_WIDTH - 48) / 2,
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1,
  },
  programCardImage: {
    width: '100%',
    height: 112,
  },
  programCardTitle: {
    paddingHorizontal: 10,
    paddingVertical: 10,
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },

  coursesList: {
    paddingHorizontal: 12,
    paddingBottom: 20,
    gap: 14,
  },
  courseCard: {
    width: 220,
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 4,
  },
  courseImage: {
    width: '100%',
    height: 120,
  },
  courseContent: {
    padding: 14,
  },
  courseTitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 6,
    lineHeight: 22,
  },
  courseDescription: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 18,
  },

  newsList: {
    paddingHorizontal: 12,
    gap: 12,
    paddingBottom: 4,
  },
  newsCard: {
    width: 200,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    paddingBottom: 10,
  },
  newsImage: {
    width: '100%',
    height: 100,
  },
  newsTitle: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    marginTop: 8,
    paddingHorizontal: 10,
  },
  newsSummary: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 4,
    paddingHorizontal: 10,
  },

});
