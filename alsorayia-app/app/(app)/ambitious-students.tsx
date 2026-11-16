import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme } from '@/constants/theme';
import { useThemeColor } from '@/hooks/use-theme-color';
import {
  fetchPersonsBadges,
  type PersonPadge,
  type PersonWithBadges,
} from '@/lib/api/personsBadges';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const TAMOH_PROGRAM_DESCRIPTION =
  'برنامج «طموح» هو أحد برامج «أكاديمية السريّع»، ويعتبر برنامجًا تقنيًا متقدمًا يستهدف جيل المستقبل الذين يسعون إلى تنمية مهاراتهم التقنية والمهنية في مجالات متعددة. تم تصميم البرنامج بشكل شامل ليغطي مجموعة واسعة من المهارات التي تساعد المشاركين على الاستعداد لمستقبل رقمي متقدم. يعتمد البرنامج على أسلوب تعليمي تفاعلي وحديث يمكّن المشاركين من تحقيق طموحاتهم المهنية والتقنية';

function openProfileUrl(url: string | null | undefined) {
  const u = url?.trim();
  if (!u) return;
  WebBrowser.openBrowserAsync(u).catch(() => {});
}

function visiblePadges(person: PersonWithBadges): PersonPadge[] {
  return (person.padges ?? []).filter(
    (p) => p.is_active !== false && p.pivot_is_active !== false
  );
}

function badgeAccentColor(p: PersonPadge): string {
  const c = p.color?.trim();
  if (c && /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(c)) return c;
  return silaTheme.primary;
}

export default function AmbitiousStudentsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const cardBg = useThemeColor({}, 'card');
  const borderColor = useThemeColor({}, 'border');
  const mutedColor = useThemeColor({}, 'icon');
  const backgroundColor = useThemeColor({}, 'background');

  const [persons, setPersons] = useState<PersonWithBadges[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [modalPerson, setModalPerson] = useState<PersonWithBadges | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    const data = await fetchPersonsBadges();
    if (!data || !data.success) {
      setLoadError(true);
      setPersons([]);
    } else {
      const list = data.persons ?? [];
      setPersons(list.filter((p) => visiblePadges(p).length > 0));
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const modalPadges = useMemo(
    () => (modalPerson ? visiblePadges(modalPerson) : []),
    [modalPerson]
  );

  const accentFill = silaTheme.primary;

  const renderItem = useCallback(
    ({ item }: { item: PersonWithBadges }) => {
      const badges = visiblePadges(item);
      const first = badges[0];
      const extra = Math.max(0, badges.length - 1);
      const accent = first ? badgeAccentColor(first) : accentFill;

      return (
        <View
          style={[
            styles.card,
            {
              backgroundColor: cardBg,
              borderColor,
            },
          ]}>
          <TouchableOpacity
            style={styles.cardMainRow}
            activeOpacity={0.75}
            onPress={() => openProfileUrl(item.profile_url)}>
            <View style={styles.avatarWrap}>
              {item.avatar_url ? (
                <Image
                  source={{ uri: item.avatar_url }}
                  style={styles.avatar}
                  contentFit="cover"
                  transition={200}
                />
              ) : (
                <View style={[styles.avatarPlaceholder, { borderColor }]}>
                  <IconSymbol name="person.fill" size={28} color={mutedColor} />
                </View>
              )}
            </View>
            <View style={styles.nameBlock}>
              <ThemedText type="defaultSemiBold" style={styles.personName} numberOfLines={2}>
                {item.full_name}
              </ThemedText>
              <ThemedText
                type="default"
                style={styles.profileHint}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                عرض الملف الشخصي
              </ThemedText>
            </View>
            <View style={styles.chevronWrap}>
              <IconSymbol name="chevron.left" size={18} color={mutedColor} />
            </View>
          </TouchableOpacity>

          {first ? (
            <TouchableOpacity
              style={[styles.badgesRow, { borderTopColor: borderColor }]}
              activeOpacity={0.8}
              onPress={() => setModalPerson(item)}>
              <View style={[styles.firstBadgeChip, { backgroundColor: accent }]}>
                <ThemedText
                  type="defaultSemiBold"
                  style={styles.firstBadgeChipText}
                  numberOfLines={1}>
                  {first.name}
                </ThemedText>
              </View>
              {extra > 0 ? (
                <View style={[styles.plusChip, { borderColor: accent }]}>
                  <ThemedText type="defaultSemiBold" style={[styles.plusN, { color: accent }]}>
                    +{extra}
                  </ThemedText>
                </View>
              ) : (
                <View style={[styles.moreHint, { borderColor }]}>
                  <IconSymbol name="info.circle" size={18} color={mutedColor} />
                </View>
              )}
            </TouchableOpacity>
          ) : null}
        </View>
      );
    },
    [borderColor, cardBg, mutedColor]
  );

  const keyExtractor = useCallback((item: PersonWithBadges) => String(item.id), []);

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={100} />

      <HeaderBar title="طلاب طموح" showBack onBackPress={() => router.back()} />

      <LinearGradient
        colors={
          isDark ? ['#F59E0B18', '#0EA5E914', backgroundColor] : ['#FEF3C7', '#E0F2FE', '#FFFFFF']
        }
        locations={[0, 0.45, 1]}
        style={styles.heroGradient}>
        <View style={styles.heroInner}>
          <ThemedText type="default" style={styles.heroProgramText}>
            {TAMOH_PROGRAM_DESCRIPTION}
          </ThemedText>
        </View>
      </LinearGradient>

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={accentFill} />
          <ThemedText
            type="default"
            style={styles.loadingHint}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            جاري تحميل القائمة…
          </ThemedText>
        </View>
      ) : loadError ? (
        <View style={styles.centered}>
          <IconSymbol name="exclamationmark.triangle.fill" size={40} color={mutedColor} />
          <ThemedText type="defaultSemiBold" style={styles.errorTitle}>
            تعذّر تحميل البيانات
          </ThemedText>
          <ThemedText
            type="default"
            style={styles.errorHint}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            تحقق من الاتصال أو حاول لاحقًا
          </ThemedText>
          <TouchableOpacity
            style={[styles.retryBtn, { backgroundColor: accentFill }]}
            onPress={load}
            activeOpacity={0.88}>
            <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
          </TouchableOpacity>
        </View>
      ) : persons.length === 0 ? (
        <View style={styles.centered}>
          <ThemedText
            type="defaultSemiBold"
            style={styles.emptyHint}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            لا توجد بيانات لعرضها حاليًا
          </ThemedText>
        </View>
      ) : (
        <FlatList
          data={persons}
          keyExtractor={keyExtractor}
          renderItem={renderItem}
          contentContainerStyle={[
            styles.listContent,
            { paddingBottom: insets.bottom + 24 },
          ]}
          showsVerticalScrollIndicator={false}
          ItemSeparatorComponent={() => <View style={{ height: 14 }} />}
        />
      )}

      <Modal
        visible={modalPerson !== null}
        animationType="slide"
        transparent
        onRequestClose={() => setModalPerson(null)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setModalPerson(null)}>
          <Pressable
            style={[styles.modalSheet, { backgroundColor: cardBg, borderColor }]}
            onPress={(e) => e.stopPropagation()}>
            <View style={[styles.modalHandleZone, { borderBottomColor: borderColor }]}>
              <View style={styles.modalGrab} />
              <ThemedText type="subtitle" style={styles.modalTitle}>
                أوسمة {modalPerson?.full_name ?? ''}
              </ThemedText>
              <TouchableOpacity
                hitSlop={12}
                onPress={() => setModalPerson(null)}
                style={styles.modalClose}>
                <IconSymbol name="xmark.circle.fill" size={26} color={mutedColor} />
              </TouchableOpacity>
            </View>
            <ScrollView
              style={styles.modalScroll}
              contentContainerStyle={{
                paddingBottom: insets.bottom + 20,
              }}
              showsVerticalScrollIndicator={false}>
              {modalPadges.map((p) => {
                const accent = badgeAccentColor(p);
                return (
                  <View
                    key={p.id}
                    style={[styles.modalBadgeCard, { borderColor, backgroundColor: cardBg }]}>
                    <View style={[styles.accentBar, { backgroundColor: accent }]} />
                    <View style={styles.modalBadgeBody}>
                      <View style={styles.modalBadgeTop}>
                        {p.image_url ? (
                          <Image
                            source={{ uri: p.image_url }}
                            style={styles.badgeImage}
                            contentFit="contain"
                          />
                        ) : (
                          <View style={[styles.badgeImageFallback, { borderColor }]}>
                            <IconSymbol name="graduationcap.fill" size={32} color={accent} />
                          </View>
                        )}
                        <View style={styles.modalBadgeTitles}>
                          <ThemedText type="defaultSemiBold" style={styles.badgeName}>
                            {p.name}
                          </ThemedText>
                          {p.description ? (
                            <ThemedText
                              type="default"
                              style={styles.badgeDesc}
                              lightColor={mutedColor}
                              darkColor={mutedColor}>
                              {p.description}
                            </ThemedText>
                          ) : null}
                        </View>
                      </View>
                    </View>
                  </View>
                );
              })}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  heroGradient: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 18,
  },
  heroInner: {
    width: '100%',
  },
  heroProgramText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 15,
    lineHeight: 26,
  },
  emptyHint: {
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 10,
  },
  loadingHint: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    marginTop: 8,
  },
  errorTitle: {
    fontFamily: 'Tajawal-Bold',
    marginTop: 8,
  },
  errorHint: {
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    fontSize: 14,
  },
  retryBtn: {
    marginTop: 16,
    paddingHorizontal: 22,
    paddingVertical: 12,
    borderRadius: 12,
  },
  retryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  listContent: {
    paddingHorizontal: 16,
    paddingTop: 8,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.07,
        shadowRadius: 12,
      },
      android: { elevation: 3 },
    }),
  },
  cardMainRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    gap: 12,
  },
  avatarWrap: {
    width: 56,
    height: 56,
    borderRadius: 28,
    overflow: 'hidden',
  },
  avatar: {
    width: '100%',
    height: '100%',
  },
  avatarPlaceholder: {
    width: '100%',
    height: '100%',
    borderRadius: 28,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F1F5F940',
  },
  nameBlock: {
    flex: 1,
    gap: 4,
  },
  personName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  profileHint: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  chevronWrap: {
    opacity: 0.85,
  },
  badgesRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: 10,
  },
  firstBadgeChip: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 14,
    minHeight: 44,
  },
  firstBadgeChipText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  plusChip: {
    borderWidth: 2,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    backgroundColor: 'transparent',
  },
  plusN: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  moreHint: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    maxHeight: '88%',
    overflow: 'hidden',
  },
  modalHandleZone: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 8,
  },
  modalGrab: {
    position: 'absolute',
    top: 8,
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#CBD5E1',
    left: '50%',
    marginLeft: -20,
  },
  modalTitle: {
    flex: 1,
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'center',
    paddingHorizontal: 32,
  },
  modalClose: {
    position: 'absolute',
    end: 12,
    top: 10,
    zIndex: 2,
  },
  modalScroll: {
    maxHeight: 520,
  },
  modalBadgeCard: {
    marginHorizontal: 16,
    marginTop: 14,
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
    flexDirection: 'row',
  },
  accentBar: {
    width: 5,
  },
  modalBadgeBody: {
    flex: 1,
    padding: 14,
  },
  modalBadgeTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 14,
  },
  badgeImage: {
    width: 72,
    height: 72,
    borderRadius: 12,
    backgroundColor: '#F8FAFC',
  },
  badgeImageFallback: {
    width: 72,
    height: 72,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalBadgeTitles: {
    flex: 1,
    gap: 8,
  },
  badgeName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  badgeDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 22,
  },
});
