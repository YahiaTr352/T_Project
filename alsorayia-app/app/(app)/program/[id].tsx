import HeaderBar from '@/components/headerBar';
import { HtmlContent } from '@/components/html-content';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme } from '@/constants/theme';
import { useThemeColor } from '@/hooks/use-theme-color';
import {
  displayProgramTitle,
  fetchProgramShow,
  orderedGalleryMedia,
  orderedProgramGalleries,
  orderedProgramLinks,
  type ProgramCompetition,
  type ProgramShowCategoryRef,
  type ProgramShowResponse,
  type ProgramVideoMediaItem,
  type SubProgramItem,
} from '@/lib/api/programShow';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { type Href, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  Pressable,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const SCREEN_W = Dimensions.get('window').width;
const GAP = 10;
const GRID_COLS = 2;
const CELL = (SCREEN_W - 40 - GAP * (GRID_COLS - 1)) / GRID_COLS;

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function openUrl(url: string | null | undefined) {
  const u = url?.trim();
  if (!u) return;
  WebBrowser.openBrowserAsync(u).catch(() => {});
}

function formatDateYmd(iso: string | null | undefined): string {
  if (!iso?.trim()) return '—';
  try {
    return new Date(iso.trim()).toLocaleDateString('ar-SA', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return iso;
  }
}

function SectionTitle({ children }: { children: string }) {
  const border = useThemeColor({}, 'border');
  return (
    <View style={[styles.sectionTitleRow, { borderBottomColor: border }]}>
      <ThemedText type="subtitle" style={styles.sectionTitleText}>
        {children}
      </ThemedText>
    </View>
  );
}

export default function ProgramDetailScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { id: idParam, category: catParam } = useLocalSearchParams<{ id: string; category?: string }>();

  const programId = useMemo(() => {
    const n = Number(idParam);
    return Number.isFinite(n) ? n : NaN;
  }, [idParam]);

  const initialCategory = useMemo(() => {
    const n = catParam != null ? Number(catParam) : NaN;
    return Number.isFinite(n) ? n : null;
  }, [catParam]);

  const [categoryFilter, setCategoryFilter] = useState<number | null>(initialCategory);
  const [data, setData] = useState<ProgramShowResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setCategoryFilter(initialCategory);
  }, [programId, initialCategory]);

  const cardBg = useThemeColor({}, 'card');
  const borderColor = useThemeColor({}, 'border');
  const muted = useThemeColor({}, 'icon');
  const accent = silaTheme.primary;

  const load = useCallback(async () => {
    if (!Number.isFinite(programId)) {
      setData(null);
      setFailed(true);
      setLoading(false);
      return;
    }
    setLoading(true);
    setFailed(false);
    const res = await fetchProgramShow(programId, categoryFilter);
    if (!res) {
      setData(null);
      setFailed(true);
    } else {
      setData(res);
    }
    setLoading(false);
  }, [programId, categoryFilter]);

  useEffect(() => {
    load();
  }, [load]);

  const program = data?.program;
  const heroUri = program?.hero_image_url?.trim() || program?.thumbnail_url?.trim() || null;
  const pageTitle = program ? displayProgramTitle(program) : 'البرنامج';

  const gallerySorted = useMemo(
    () => orderedGalleryMedia(data?.gallery_media ?? []),
    [data?.gallery_media]
  );
  const linksSorted = useMemo(
    () => orderedProgramLinks(data?.program_links ?? []),
    [data?.program_links]
  );
  const nestedGalleries = useMemo(
    () => orderedProgramGalleries(data?.program_galleries ?? []),
    [data?.program_galleries]
  );
  const filterCats: ProgramShowCategoryRef[] = data?.competition_filter_categories ?? [];
  const competitions: ProgramCompetition[] = data?.competitions ?? [];
  const videos: ProgramVideoMediaItem[] = data?.video_media ?? [];
  const subPrograms: SubProgramItem[] = (data?.sub_programs ?? []).filter(
    (s) => s.program_is_active !== false
  );

  const showGallery = gallerySorted.some((g) => g.image_url || g.thumbnail_url);
  const showVideos = videos.some((v) => v.youtube_url?.trim());
  const showLinks = linksSorted.length > 0;
  const showNestedGalleries = nestedGalleries.some((g) => (g.images?.length ?? 0) > 0);
  const showSubs = subPrograms.length > 0;
  const showCompetitions = competitions.length > 0 || filterCats.length > 0;

  if (!Number.isFinite(programId)) {
    return (
      <ThemedView style={styles.flex1}>
        <HeaderBar title="برنامج" showBack onBackPress={() => router.back()} />
        <View style={styles.centered}>
          <ThemedText type="defaultSemiBold">معرّف غير صالح</ThemedText>
        </View>
      </ThemedView>
    );
  }

  return (
    <ThemedView style={styles.flex1}>
      <HeaderBar title={loading ? '…' : pageTitle} showBack onBackPress={() => router.back()} />

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={accent} />
          <ThemedText type="default" style={styles.mutedHint} lightColor={muted} darkColor={muted}>
            جاري التحميل…
          </ThemedText>
        </View>
      ) : failed || !program ? (
        <View style={styles.centered}>
          <IconSymbol name="exclamationmark.triangle.fill" size={40} color={muted} />
          <ThemedText type="defaultSemiBold" style={styles.errTitle}>
            لم يُعثر على البرنامج
          </ThemedText>
          <ThemedText type="default" style={styles.errHint} lightColor={muted} darkColor={muted}>
            قد يكون الرابط غير صحيح أو المحتوى غير متاح
          </ThemedText>
          <TouchableOpacity style={[styles.retryBtn, { backgroundColor: accent }]} onPress={load} activeOpacity={0.88}>
            <ThemedText style={styles.retryText}>إعادة المحاولة</ThemedText>
          </TouchableOpacity>
        </View>
      ) : (
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={{ paddingBottom: insets.bottom + 28 }}
          showsVerticalScrollIndicator={false}>
          {heroUri ? (
            <View style={[styles.heroWrap, { backgroundColor: borderColor }]}>
              <Image source={{ uri: heroUri }} style={styles.heroImg} contentFit="cover" transition={200} />
              <LinearGradient
                colors={['transparent', 'rgba(0,0,0,0.65)']}
                style={styles.heroGrad}
              />
              <View style={styles.heroTitleBox}>
                <ThemedText style={styles.heroTitle}>{pageTitle}</ThemedText>
                {program.program_category?.name ? (
                  <ThemedText style={styles.heroCat}>{program.program_category.name}</ThemedText>
                ) : null}
              </View>
            </View>
          ) : (
            <View style={[styles.heroPlaceholder, { backgroundColor: borderColor }]}>
              <LinearGradient colors={[accent + '55', accent]} style={StyleSheet.absoluteFill} />
              <ThemedText style={styles.heroTitlePlain}>{pageTitle}</ThemedText>
            </View>
          )}

          <View style={styles.bodyPad}>
            {program?.program_description ? (
              <HtmlContent
                html={program.program_description}
                contentWidth={SCREEN_W - 32}
                textColor={muted}
              />
            ) : null}

            <TouchableOpacity
              style={[styles.openWebBtn, { borderColor, backgroundColor: cardBg }]}
              onPress={() => openUrl(program.page_url)}
              activeOpacity={0.85}>
              <IconSymbol name="arrow.up.right.square.fill" size={20} color={accent} />
              <ThemedText type="defaultSemiBold" style={[styles.openWebLabel, { color: accent }]}>
                عرض الصفحة على الموقع
              </ThemedText>
            </TouchableOpacity>
          </View>

          {showLinks ? (
            <View style={styles.block}>
              <SectionTitle>روابط</SectionTitle>
              <View style={styles.bodyPad}>
                {linksSorted.map((link, i) => (
                  <TouchableOpacity
                    key={`${link.url}-${i}`}
                    style={[styles.linkCard, { backgroundColor: cardBg, borderColor }]}
                    onPress={() => openUrl(link.url)}
                    activeOpacity={0.82}>
                    <View style={styles.linkCardInner}>
                      <IconSymbol name="link" size={22} color={accent} />
                      <View style={styles.linkTextCol}>
                        <ThemedText type="defaultSemiBold" numberOfLines={2}>
                          {link.title?.trim() || 'رابط'}
                        </ThemedText>
                        {link.description ? (
                          <ThemedText type="default" style={[styles.smallMuted, { color: muted }]} numberOfLines={3}>
                            {stripHtml(link.description)}
                          </ThemedText>
                        ) : null}
                      </View>
                      <IconSymbol name="chevron.left" size={18} color={muted} />
                    </View>
                  </TouchableOpacity>
                ))}
              </View>
            </View>
          ) : null}

          {showVideos ? (
            <View style={styles.block}>
              <SectionTitle>فيديو</SectionTitle>
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.hScrollPad}>
                {videos.filter((v) => v.youtube_url?.trim()).map((v) => {
                  const thumb = v.youtube_thumbnail_url?.trim() || null;
                  const url = v.youtube_url!.trim();
                  return (
                    <TouchableOpacity
                      key={v.id}
                      style={[styles.videoCard, { borderColor, backgroundColor: cardBg }]}
                      onPress={() => openUrl(url)}
                      activeOpacity={0.88}>
                      {thumb ? (
                        <Image source={{ uri: thumb }} style={styles.videoThumb} contentFit="cover" />
                      ) : (
                        <View style={[styles.videoThumb, styles.videoThumbPh, { borderColor }]}>
                          <IconSymbol name="photo.fill" size={36} color={muted} />
                        </View>
                      )}
                      <View style={styles.videoPlay}>
                        <IconSymbol name="play.circle.fill" size={44} color="#FFFFFF" />
                      </View>
                      {v.name ? (
                        <ThemedText type="defaultSemiBold" style={styles.videoCap} numberOfLines={2}>
                          {v.name}
                        </ThemedText>
                      ) : null}
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            </View>
          ) : null}

          {showNestedGalleries
            ? nestedGalleries.map((gal, gi) => {
                const imgs = gal.images ?? [];
                if (!imgs.length) return null;
                return (
                  <View key={`gal-${gal.title}-${gi}`} style={styles.block}>
                    <SectionTitle>{gal.title?.trim() || 'معرض'}</SectionTitle>
                    {gal.description ? (
                      <ThemedText type="default" style={[styles.galDesc, { color: muted }]}>
                        {stripHtml(gal.description)}
                      </ThemedText>
                    ) : null}
                    <ScrollView
                      horizontal
                      showsHorizontalScrollIndicator={false}
                      contentContainerStyle={styles.hScrollPad}>
                      {imgs.map((im) => {
                        const uri = im.image_url?.trim() || im.thumbnail_url?.trim();
                        if (!uri) return null;
                        return (
                          <Pressable
                            key={im.id}
                            style={[styles.nestedImg, { borderColor, backgroundColor: borderColor }]}
                            onPress={() => openUrl(uri)}>
                            <Image source={{ uri }} style={styles.nestedImgInner} contentFit="cover" />
                          </Pressable>
                        );
                      })}
                    </ScrollView>
                  </View>
                );
              })
            : null}

          {showGallery ? (
            <View style={styles.block}>
              <SectionTitle>{program?.name || ''}</SectionTitle>
              <View style={[styles.grid, styles.bodyPad]}>
                {gallerySorted.map((g) => {
                  const uri = g.image_url?.trim() || g.thumbnail_url?.trim();
                  if (!uri) return null;
                  const displayName = g.full_name?.trim() || g.name?.trim();
                  return (
                    <Pressable
                      key={g.id}
                      style={[styles.staffCard, { width: CELL }]}
                      onPress={() => openUrl(uri)}>
                      <View style={[styles.staffImgBox, { backgroundColor: borderColor }]}>
                        <Image source={{ uri }} style={StyleSheet.absoluteFill} contentFit="cover" />
                      </View>
                      {displayName ? (
                        <ThemedText type="defaultSemiBold" style={styles.staffName} numberOfLines={2}>
                          {displayName}
                        </ThemedText>
                      ) : null}
                    </Pressable>
                  );
                })}
              </View>
            </View>
          ) : null}

          {showSubs ? (
            <View style={styles.block}>
              <SectionTitle>برامج فرعية</SectionTitle>
              <View style={styles.bodyPad}>
                {subPrograms.map((sub) => {
                  const t = sub.program_title?.trim() || 'برنامج';
                  const uri = sub.image_url?.trim();
                  return (
                    <TouchableOpacity
                      key={sub.id}
                      style={[styles.subCard, { borderColor, backgroundColor: cardBg }]}
                      activeOpacity={0.85}
                      onPress={() =>
                        router.push({
                          pathname: '/program/[id]',
                          params: { id: String(sub.id) },
                        } as Href)
                      }>
                      {uri ? (
                        <Image source={{ uri }} style={styles.subThumb} contentFit="cover" />
                      ) : (
                        <View style={[styles.subThumb, styles.subThumbPh, { borderColor }]}>
                          <IconSymbol name="folder.fill" size={28} color={muted} />
                        </View>
                      )}
                      <View style={styles.subText}>
                        <ThemedText type="defaultSemiBold" numberOfLines={2}>
                          {t}
                        </ThemedText>
                        {sub.program_description ? (
                          <ThemedText type="default" style={[styles.smallMuted, { color: muted }]} numberOfLines={2}>
                            {stripHtml(sub.program_description)}
                          </ThemedText>
                        ) : null}
                      </View>
                      <IconSymbol name="chevron.left" size={20} color={muted} />
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>
          ) : null}

          {showCompetitions ? (
            <View style={styles.block}>
              <SectionTitle>مسابقات</SectionTitle>
              {filterCats.length > 0 ? (
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.filterRow}>
                  <FilterChip
                    label="الكل"
                    active={categoryFilter == null}
                    onPress={() => setCategoryFilter(null)}
                    accent={accent}
                    muted={muted}
                    borderColor={borderColor}
                    cardBg={cardBg}
                  />
                  {filterCats.map((c) => (
                    <FilterChip
                      key={c.id}
                      label={c.name}
                      active={categoryFilter === c.id}
                      onPress={() => setCategoryFilter(c.id)}
                      accent={accent}
                      muted={muted}
                      borderColor={borderColor}
                      cardBg={cardBg}
                    />
                  ))}
                </ScrollView>
              ) : null}

              <View style={styles.bodyPad}>
                {!competitions.length ? (
                  <ThemedText type="default" style={{ color: muted }}>
                    لا توجد مسابقات ضمن هذا التصنيف
                  </ThemedText>
                ) : (
                  competitions.map((c, idx) => (
                    <CompetitionCard
                      key={c.id ?? idx}
                      c={c}
                      cardBg={cardBg}
                      borderColor={borderColor}
                      muted={muted}
                      accent={accent}
                    />
                  ))
                )}
              </View>
            </View>
          ) : null}
        </ScrollView>
      )}
    </ThemedView>
  );
}

function FilterChip({
  label,
  active,
  onPress,
  accent,
  muted,
  borderColor,
  cardBg,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
  accent: string;
  muted: string;
  borderColor: string;
  cardBg: string;
}) {
  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.85}
      style={[
        styles.chip,
        {
          borderColor: active ? accent : borderColor,
          backgroundColor: active ? accent + '22' : cardBg,
        },
      ]}>
      <ThemedText
        type="defaultSemiBold"
        style={[styles.chipLabel, { color: active ? accent : muted }]}>
        {label}
      </ThemedText>
    </TouchableOpacity>
  );
}

function CompetitionCard({
  c,
  cardBg,
  borderColor,
  muted,
  accent,
}: {
  c: ProgramCompetition;
  cardBg: string;
  borderColor: string;
  muted: string;
  accent: string;
}) {
  const desc = c.description ? stripHtml(c.description) : '';
  const cats = c.categories ?? [];
  const ind = c.participants?.individuals ?? [];
  const teams = c.participants?.teams ?? [];

  return (
    <View style={[styles.compCard, { backgroundColor: cardBg, borderColor }]}>
      <ThemedText type="defaultSemiBold" style={styles.compTitle}>
        {c.title}
      </ThemedText>
      {cats.length > 0 ? (
        <View style={styles.compCatRow}>
          {cats.map((x) => (
            <View key={x.id} style={[styles.miniTag, { borderColor }]}>
              <ThemedText type="default" style={[styles.miniTagText, { color: muted }]}>
                {x.name}
              </ThemedText>
            </View>
          ))}
        </View>
      ) : null}
      {desc ? (
        <ThemedText type="default" style={[styles.compDesc, { color: muted }]}>
          {desc}
        </ThemedText>
      ) : null}
      <View style={styles.compMetaRow}>
        <MetaPill icon="calendar" label={`${formatDateYmd(c.start_date)} → ${formatDateYmd(c.end_date)}`} muted={muted} />
        {c.is_registration_open ? (
          <MetaPill icon="checkmark.circle.fill" label="التسجيل مفتوح" muted={accent} />
        ) : (
          <MetaPill icon="lock.fill" label="التسجيل" muted={muted} />
        )}
      </View>
      {(ind.length > 0 || teams.length > 0) && (
        <View style={[styles.participantsBox, { borderTopColor: borderColor }]}>
          <ThemedText type="defaultSemiBold" style={[styles.partHead, { color: muted }]}>
            المشاركون
          </ThemedText>
          {ind.map((p) => (
            <ThemedText key={p.user_id} type="default" style={styles.partLine}>
              • {p.name}
            </ThemedText>
          ))}
          {teams.map((t) => (
            <View key={t.id} style={styles.teamBlock}>
              <ThemedText type="defaultSemiBold" style={styles.teamName}>
                فريق: {t.name}
              </ThemedText>
              {(t.members ?? []).map((m) => (
                <ThemedText key={m.user_id} type="default" style={[styles.partLine, { marginRight: 8 }]}>
                  — {m.name}
                  {m.role === 'captain' ? ' (قائد)' : ''}
                </ThemedText>
              ))}
            </View>
          ))}
        </View>
      )}
    </View>
  );
}

function MetaPill({ icon, label, muted }: { icon: 'calendar' | 'checkmark.circle.fill' | 'lock.fill'; label: string; muted: string }) {
  return (
    <View style={styles.metaPill}>
      <IconSymbol name={icon} size={14} color={muted} />
      <ThemedText type="default" style={[styles.metaPillText, { color: muted }]} numberOfLines={2}>
        {label}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  flex1: { flex: 1 },
  scroll: { flex: 1 },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, gap: 10 },
  mutedHint: { marginTop: 8, fontFamily: 'Tajawal-Medium' },
  errTitle: { marginTop: 8, fontFamily: 'Tajawal-Bold' },
  errHint: { textAlign: 'center', fontFamily: 'Tajawal-Regular', fontSize: 14 },
  retryBtn: { marginTop: 12, paddingHorizontal: 22, paddingVertical: 12, borderRadius: 12 },
  retryText: { color: '#fff', fontFamily: 'Tajawal-Bold' },
  heroWrap: { width: '100%', height: 220, position: 'relative' },
  heroImg: { width: '100%', height: '100%' },
  heroGrad: { ...StyleSheet.absoluteFill },
  heroTitleBox: { position: 'absolute', left: 16, right: 16, bottom: 16 },
  heroTitle: { color: '#fff', fontFamily: 'Tajawal-Bold', fontSize: 22, textShadowColor: 'rgba(0,0,0,0.35)', textShadowRadius: 8 },
  heroCat: { color: 'rgba(255,255,255,0.9)', fontFamily: 'Tajawal-Medium', fontSize: 13, marginTop: 4 },
  heroPlaceholder: { height: 160, justifyContent: 'center', paddingHorizontal: 20 },
  heroTitlePlain: { color: '#fff', fontFamily: 'Tajawal-Bold', fontSize: 20, zIndex: 1 },
  bodyPad: { paddingHorizontal: 16, paddingTop: 16 },
  bodyText: { fontFamily: 'Tajawal-Regular', fontSize: 15, lineHeight: 26 },
  openWebBtn: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  openWebLabel: { fontFamily: 'Tajawal-Bold', flex: 1 },
  block: { marginTop: 8 },
  sectionTitleRow: {
    marginHorizontal: 16,
    marginTop: 20,
    paddingBottom: 8,
    borderBottomWidth: 1,
  },
  sectionTitleText: { fontFamily: 'Tajawal-Bold', fontSize: 18 },
  linkCard: { borderRadius: 14, borderWidth: 1, marginBottom: 10, overflow: 'hidden' },
  linkCardInner: { flexDirection: 'row', alignItems: 'center', padding: 14, gap: 12 },
  linkTextCol: { flex: 1, gap: 4 },
  smallMuted: { fontSize: 13, lineHeight: 20, fontFamily: 'Tajawal-Regular' },
  hScrollPad: { paddingHorizontal: 16, paddingVertical: 12, gap: 12 },
  videoCard: { width: 200, borderRadius: 14, borderWidth: 1, overflow: 'hidden' },
  videoThumb: { width: '100%', height: 112 },
  videoThumbPh: { justifyContent: 'center', alignItems: 'center' },
  videoPlay: { ...StyleSheet.absoluteFill, justifyContent: 'center', alignItems: 'center', top: 0, height: 112 },
  videoCap: { padding: 10, fontFamily: 'Tajawal-Medium', fontSize: 13 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: GAP },
  gridCell: { borderRadius: 12, overflow: 'hidden' },
  staffCard: {
    marginBottom: 12,
    alignItems: 'center',
  },
  staffImgBox: {
    width: '100%',
    aspectRatio: 1,
    borderRadius: 12,
    overflow: 'hidden',
    position: 'relative',
  },
  staffName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    textAlign: 'center',
    marginTop: 6,
  },
  galDesc: { paddingHorizontal: 16, paddingBottom: 8, fontFamily: 'Tajawal-Regular', fontSize: 14, lineHeight: 22 },
  nestedImg: {
    width: 160,
    height: 120,
    borderRadius: 12,
    borderWidth: 1,
    overflow: 'hidden',
    marginRight: 12,
  },
  nestedImgInner: { width: '100%', height: '100%' },
  subCard: { flexDirection: 'row', alignItems: 'center', borderRadius: 14, borderWidth: 1, padding: 10, marginBottom: 10, gap: 12 },
  subThumb: { width: 64, height: 64, borderRadius: 12 },
  subThumbPh: { justifyContent: 'center', alignItems: 'center', borderWidth: 1 },
  subText: { flex: 1, gap: 4 },
  filterRow: { paddingHorizontal: 16, paddingTop: 8, gap: 8, flexDirection: 'row' },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, borderWidth: 1 },
  chipLabel: { fontSize: 13, fontFamily: 'Tajawal-Bold' },
  compCard: { borderRadius: 16, borderWidth: 1, padding: 14, marginBottom: 12 },
  compTitle: { fontFamily: 'Tajawal-Bold', fontSize: 16 },
  compCatRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 8 },
  miniTag: { borderWidth: 1, borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4 },
  miniTagText: { fontSize: 11, fontFamily: 'Tajawal-Medium' },
  compDesc: { marginTop: 8, fontFamily: 'Tajawal-Regular', fontSize: 14, lineHeight: 22 },
  compMetaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 10 },
  metaPill: { flexDirection: 'row', alignItems: 'center', gap: 6, maxWidth: '100%' },
  metaPillText: { fontSize: 12, fontFamily: 'Tajawal-Medium', flex: 1 },
  participantsBox: { marginTop: 12, paddingTop: 10, borderTopWidth: StyleSheet.hairlineWidth },
  partHead: { fontSize: 13, marginBottom: 6, fontFamily: 'Tajawal-Bold' },
  partLine: { fontSize: 13, fontFamily: 'Tajawal-Regular', marginBottom: 4 },
  teamBlock: { marginTop: 8 },
  teamName: { fontSize: 13, fontFamily: 'Tajawal-Bold', marginBottom: 4 },
});
