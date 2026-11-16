import { HtmlContent } from '@/components/html-content';
import { ThemedText } from '@/components/themed-text';
import { CountdownSectionItem } from './CountdownSectionItem';
import type { ThemeColors } from '@/constants/theme';
import type {
  HomeDynamicSection,
  HomeDynamicSectionItem,
} from '@/lib/api/homePage';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as WebBrowser from 'expo-web-browser';
import { useMemo } from 'react';
import {
  Dimensions,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  View
} from 'react-native';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

interface DynamicSectionsProps {
  colors: ThemeColors;
  sections?: HomeDynamicSection[];
}

export function DynamicSections({ colors, sections = [] }: DynamicSectionsProps) {
  const activeSections = useMemo(() => {
    return (sections || [])
      .filter((s) => s && s.is_active !== false && s.items && s.items.length > 0)
      .sort((a, b) => (Number(a.display_order) || 0) - (Number(b.display_order) || 0));
  }, [sections]);

  if (!activeSections.length) return null;

  return (
    <View style={styles.sectionsWrapper}>
      {activeSections.map((section) => (
        <DynamicSectionBlock key={`dyn-sec-${section.id}`} section={section} colors={colors} />
      ))}
    </View>
  );
}

// ─────────────────────────────────────────────
// INDIVIDUAL SECTION BLOCK
// ─────────────────────────────────────────────
function DynamicSectionBlock({
  section,
  colors,
}: {
  section: HomeDynamicSection;
  colors: ThemeColors;
}) {
  const settings = section.settings || {};
  const showTitle = settings.show_title !== false;
  const layoutStyle = settings.layout_style || 'vertical';

  const items = useMemo(() => {
    return (section.items || []).slice().sort((a, b) => (a.display_order || 0) - (b.display_order || 0));
  }, [section.items]);

  if (!items.length) return null;

  const sectionBg = settings.background_color && settings.background_color !== '#ffffff'
    ? settings.background_color
    : undefined;

  const titleColor = settings.title_color || colors.text;
  const subtitleColor = settings.subtitle_color || colors.icon;
  const descriptionColor = settings.description_color || colors.icon;

  return (
    <View
      style={[
        styles.sectionContainer,
        sectionBg ? { backgroundColor: sectionBg, borderRadius: 20, padding: 16 } : null,
      ]}
    >
      {/* Section Header */}
      {showTitle && section.title ? (
        <View style={styles.sectionHeader}>
          <View style={styles.titleRow}>
            <ThemedText style={[styles.sectionTitle, { color: titleColor }]}>
              {section.title}
            </ThemedText>
            {settings.icon ? (
              <Ionicons name="sparkles" size={18} color={colors.primary} />
            ) : null}
          </View>
          {settings.subtitle ? (
            <ThemedText style={[styles.sectionSubtitle, { color: subtitleColor }]}>
              {settings.subtitle}
            </ThemedText>
          ) : null}
          {settings.description ? (
            <ThemedText style={[styles.sectionDescription, { color: descriptionColor }]}>
              {settings.description}
            </ThemedText>
          ) : null}
        </View>
      ) : null}

      {/* Items Container */}
      {layoutStyle === 'horizontal' ? (
        <FlatList
          data={items}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={(item) => `dyn-item-${item.id}`}
          contentContainerStyle={styles.horizontalList}
          renderItem={({ item }) => (
            <View style={styles.horizontalItemWrap}>
              <DynamicItemRenderer item={item} colors={colors} contentWidth={SCREEN_WIDTH * 0.8} />
            </View>
          )}
        />
      ) : layoutStyle === 'grid' && items.length > 1 ? (
        <View style={styles.gridContainer}>
          {items.map((item) => (
            <View key={`dyn-item-${item.id}`} style={styles.gridItemWrap}>
              <DynamicItemRenderer
                item={item}
                colors={colors}
                contentWidth={(SCREEN_WIDTH - 48) / 2}
              />
            </View>
          ))}
        </View>
      ) : (
        <View style={styles.verticalContainer}>
          {items.map((item) => (
            <DynamicItemRenderer
              key={`dyn-item-${item.id}`}
              item={item}
              colors={colors}
              contentWidth={SCREEN_WIDTH - 32}
            />
          ))}
        </View>
      )}
    </View>
  );
}

// ─────────────────────────────────────────────
// UNIVERSAL ITEM RENDERER
// ─────────────────────────────────────────────
function DynamicItemRenderer({
  item,
  colors,
  contentWidth,
}: {
  item: HomeDynamicSectionItem;
  colors: ThemeColors;
  contentWidth: number;
}) {
  const content = typeof item.content === 'object' && item.content !== null ? item.content : {};
  const htmlRaw = content.html || (typeof item.content === 'string' ? item.content : '');

  // 0. Countdown Timer
  if (item.item_type === 'countdown') {
    return <CountdownSectionItem item={item} colors={colors} contentWidth={contentWidth} />;
  }

  // 1. HTML / Rich Text
  if (item.item_type === 'html' || item.item_type === 'rich_text') {
    if (!htmlRaw) return null;
    return (
      <View
        style={[
          styles.richCard,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <HtmlContent html={htmlRaw} contentWidth={contentWidth - 28} />
      </View>
    );
  }

  // 2. Video / YouTube
  if (item.item_type === 'video' || item.youtube_url || item.video_url) {
    const videoSrc = item.youtube_url || item.video_url || '';
    if (videoSrc) {
      return (
        <View
          style={[
            styles.richCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <HtmlContent
            html={`<iframe src="${videoSrc}" title="${content.title || ''}"></iframe>`}
            contentWidth={contentWidth - 28}
          />
        </View>
      );
    }
  }

  // 3. Image
  if (item.item_type === 'image' && item.image_url) {
    const targetUrl = content.link || content.url || item.link_absolute;
    const handlePress = () => {
      if (targetUrl) {
        WebBrowser.openBrowserAsync(targetUrl).catch(() => { });
      }
    };

    return (
      <TouchableOpacity
        style={[styles.imageCard, { borderColor: colors.border }]}
        activeOpacity={targetUrl ? 0.88 : 1}
        onPress={targetUrl ? handlePress : undefined}
      >
        <Image
          source={{ uri: item.image_url }}
          style={styles.imageContent}
          contentFit="cover"
        />
        {content.caption ? (
          <View style={styles.imageCaptionBar}>
            <ThemedText style={styles.imageCaptionText}>{content.caption}</ThemedText>
          </View>
        ) : null}
      </TouchableOpacity>
    );
  }

  // 4. Card
  if (item.item_type === 'card') {
    const targetUrl = content.link || content.url || item.link_absolute;
    const handlePress = () => {
      if (targetUrl) {
        WebBrowser.openBrowserAsync(targetUrl).catch(() => { });
      }
    };

    return (
      <TouchableOpacity
        style={[styles.cardBox, { backgroundColor: colors.card, borderColor: colors.border }]}
        activeOpacity={targetUrl ? 0.88 : 1}
        onPress={targetUrl ? handlePress : undefined}
      >
        {item.image_url ? (
          <Image source={{ uri: item.image_url }} style={styles.cardImage} contentFit="cover" />
        ) : null}
        <View style={styles.cardBody}>
          {content.title ? (
            <ThemedText style={[styles.cardTitle, { color: colors.text }]}>
              {content.title}
            </ThemedText>
          ) : null}
          {content.description ? (
            <ThemedText style={[styles.cardDesc, { color: colors.icon }]} numberOfLines={3}>
              {content.description}
            </ThemedText>
          ) : null}
          {targetUrl ? (
            <View style={styles.cardBtnRow}>
              <ThemedText style={[styles.cardBtnText, { color: colors.primary }]}>
                {content.button_text || 'عرض المزيد'}
              </ThemedText>
              <Ionicons name="arrow-back" size={14} color={colors.primary} />
            </View>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  }

  // 5. Button
  if (item.item_type === 'button' && content.text) {
    const targetUrl = content.url || content.link || item.url_absolute;
    const handlePress = () => {
      if (targetUrl) {
        WebBrowser.openBrowserAsync(targetUrl).catch(() => { });
      }
    };

    return (
      <TouchableOpacity
        style={[styles.actionBtn, { backgroundColor: colors.primary }]}
        activeOpacity={0.85}
        onPress={handlePress}
      >
        <ThemedText style={styles.actionBtnText}>{content.text}</ThemedText>
        <Ionicons name="open-outline" size={16} color="#fff" />
      </TouchableOpacity>
    );
  }

  // 6. Plain Text
  if (item.item_type === 'text' && content.text) {
    return (
      <View
        style={[
          styles.richCard,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <ThemedText style={[styles.plainText, { color: colors.text }]}>
          {content.text}
        </ThemedText>
      </View>
    );
  }

  // 7. Spacer
  if (item.item_type === 'spacer') {
    const height = Number(item.settings?.height) || 20;
    return <View style={{ height }} />;
  }

  return null;
}

// ─────────────────────────────────────────────
// STYLES
// ─────────────────────────────────────────────
const styles = StyleSheet.create({
  sectionsWrapper: {
    width: '100%',
  },
  sectionContainer: {
    marginHorizontal: 16,
    marginBottom: 20,
  },
  sectionHeader: {
    marginBottom: 12,
    gap: 4,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'right',
  },
  sectionSubtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'right',
  },
  sectionDescription: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'right',
    lineHeight: 18,
  },
  verticalContainer: {
    gap: 12,
  },
  horizontalList: {
    gap: 12,
    paddingVertical: 4,
  },
  horizontalItemWrap: {
    width: SCREEN_WIDTH * 0.8,
  },
  gridContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  gridItemWrap: {
    width: (SCREEN_WIDTH - 44) / 2,
  },
  itemWrapper: {
    width: '100%',
  },
  richCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
  },
  plainText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 22,
    textAlign: 'right',
  },
  imageCard: {
    width: '100%',
    aspectRatio: 16 / 9,
    borderRadius: 16,
    overflow: 'hidden',
    borderWidth: 1,
    position: 'relative',
  },
  imageContent: {
    width: '100%',
    height: '100%',
  },
  imageCaptionBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0,0,0,0.65)',
    paddingVertical: 6,
    paddingHorizontal: 10,
  },
  imageCaptionText: {
    color: '#fff',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    textAlign: 'right',
  },
  cardBox: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  cardImage: {
    width: '100%',
    height: 140,
  },
  cardBody: {
    padding: 12,
    gap: 6,
  },
  cardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    textAlign: 'right',
  },
  cardDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'right',
  },
  cardBtnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 4,
  },
  cardBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  actionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 14,
    alignSelf: 'center',
    marginVertical: 6,
  },
  actionBtnText: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
});
