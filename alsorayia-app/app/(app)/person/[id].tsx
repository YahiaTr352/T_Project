/** شاشة تفاصيل العضو (صلة) — صفحة مستقلة v3 */

import { KinshipModal } from '@/components/KinshipModal';
import { PersonFriendsModal } from '@/components/person/PersonFriendsModal';
import { PersonGalleryModal } from '@/components/person/PersonGalleryModal';
import { PersonStoriesModal } from '@/components/person/PersonStoriesModal';
import HeaderBar from '@/components/headerBar';
import { useAuth } from '@/contexts/auth-context';


import { ThemedView } from '@/components/themed-view';
import { ImageViewerModal } from '@/components/ui/zoomable-image';
import { Colors, silaTheme as T } from '@/constants/theme';
import {
  fetchPersonById,
  fetchPersonChildrenDetails,
  fetchPersonFriendshipsCount,
  fetchPersonStoriesCount,
  WEB_BASE_URL,
} from '@/lib/api/familyTreeApi';
import { formatDate } from '@/lib/sila/dateFormat';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Linking,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';


// ─────────────────────────────────────────────
// LOCAL THEME HELPER
// ─────────────────────────────────────────────
function useThemeColors() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  return { colors, colorScheme: colorScheme === 'dark' ? 'dark' : 'light' };
}

// ─────────────────────────────────────────────
// IMAGE ZOOM — يستخدم العارض الموحد (تحميل + زوم) من مكوّنات ui
// ─────────────────────────────────────────────

// ─────────────────────────────────────────────
// AVATAR RESOLVER
// ─────────────────────────────────────────────
function resolvePersonAvatar(p: any): string | null {
  if (!p) return null;
  const raw = p.avatar || p.photo_url || p.photo || p.image;
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

// ─────────────────────────────────────────────
// AVATAR
// ─────────────────────────────────────────────
const Avatar = ({
  person, size = 72, style, onZoom,
}: { person: any; size?: number; style?: object; onZoom?: (uri: string) => void }) => {
  const { colors } = useThemeColors();
  const avatarUrl = resolvePersonAvatar(person);
  if (avatarUrl) {
    return (
      <TouchableOpacity
        onPress={() => onZoom?.(avatarUrl)}
        activeOpacity={onZoom ? 0.8 : 1}
        disabled={!onZoom}
        style={{ position: 'relative' }}
      >
        <Image
          source={{ uri: avatarUrl }}
          style={[{ width: size, height: size, borderRadius: size / 2, borderWidth: 2, borderColor: colors.primary + '44' }, style]}
        />
      </TouchableOpacity>
    );
  }
  return (
    <View style={[{ width: size, height: size, borderRadius: size / 2, backgroundColor: colors.primary + '14', alignItems: 'center', justifyContent: 'center' }, style]}>
      <Ionicons name={person?.gender === 'female' ? 'woman' : 'man'} size={size * 0.45} color={colors.primary} />
    </View>
  );
};

// ─────────────────────────────────────────────
// DATA RESOLUTION HELPER
// ─────────────────────────────────────────────
function resolveRelationPerson(item: any, fallbackLabel = 'عضو') {
  if (!item) {
    return {
      id: 0,
      name: fallbackLabel,
      photoUrl: null,
      gender: 'male',
      isDeceased: false,
      isDivorced: false,
      marriedAt: null,
      divorcedAt: null,
      raw: item,
    };
  }

  // دعم البنى المتداخلة: { spouse: {...} }, { wife: {...} }, { husband: {...} }, { person: {...} } أو مسطحة
  const target = item.spouse || item.wife || item.husband || item.person || item;

  const id = target.id ?? item.spouse_id ?? item.wife_id ?? item.husband_id ?? item.id ?? 0;

  const name =
    target.full_name ||
    target.name ||
    [target.first_name, target.last_name].filter(Boolean).join(' ') ||
    target.first_name ||
    item.full_name ||
    item.name ||
    item.spouse_name ||
    item.wife_name ||
    item.husband_name ||
    fallbackLabel;

  const photoUrl = target.photo_url || target.photo || target.avatar || item.photo_url || item.photo || null;
  const gender = target.gender || item.gender || 'female';
  const deathDate = target.death_date || item.death_date;
  const isDeceased = Boolean(deathDate);
  const isDivorced = Boolean(item.is_divorced || item.divorced_at || target.is_divorced || target.divorced_at);
  const marriedAt = item.married_at || target.married_at || null;
  const divorcedAt = item.divorced_at || target.divorced_at || null;

  return {
    id,
    name,
    photoUrl,
    gender,
    isDeceased,
    isDivorced,
    marriedAt,
    divorcedAt,
    raw: target,
  };
}

// ─────────────────────────────────────────────
// RELATION CARD (عمومي للأقارب والأبناء والوالدين)
// ─────────────────────────────────────────────
const RelationCard = ({
  person, label, onPress, isBreastfeeding, onZoom,
}: { person: any; label: string; onPress?: (id: number) => void; isBreastfeeding?: boolean; onZoom?: (uri: string) => void }) => {
  const { colors } = useThemeColors();
  const resolved = resolveRelationPerson(person, label);
  const isDeceased = resolved.isDeceased;

  return (
    <TouchableOpacity
      onPress={() => resolved.id && onPress?.(resolved.id)}
      disabled={!resolved.id}
      style={[
        styles.relationCard,
        { backgroundColor: colors.card, borderColor: colors.border },
        isBreastfeeding && { borderColor: T.pink },
        isDeceased && { borderColor: '#bbb', opacity: 0.85 },
      ]}
      activeOpacity={0.75}
    >
      <Avatar person={{ photo_url: resolved.photoUrl, gender: resolved.gender }} size={44} onZoom={onZoom} />
      <View style={{ flex: 1, marginRight: 10 }}>
        <Text style={[styles.relationName, { color: colors.text }]} numberOfLines={1}>
          {resolved.name}
        </Text>
        <Text style={[styles.relationSub, { color: colors.icon }, isBreastfeeding && { color: T.pink }, isDeceased && { color: '#c0392b' }]}>
          {isDeceased ? (resolved.gender === 'female' ? 'رحمها الله' : 'رحمه الله') : label}
        </Text>
      </View>
      <Ionicons name="chevron-back" size={16} color={colors.icon} />
    </TouchableOpacity>
  );
};

// ─────────────────────────────────────────────
// SPOUSE CARD (مخصص للزوجات / الأزواج بدون ترقيم)
// ─────────────────────────────────────────────
const SpouseCard = ({
  item,
  isFemaleHost,
  onPress,
  onZoom,
  isSingle = false,
}: {
  item: any;
  isFemaleHost: boolean;
  onPress?: (id: number) => void;
  onZoom?: (uri: string) => void;
  isSingle?: boolean;
}) => {
  const { colors, colorScheme } = useThemeColors();
  const isDark = colorScheme === 'dark';
  const resolved = resolveRelationPerson(item, isFemaleHost ? 'الزوج' : 'الزوجة');

  const defaultRelationLabel = isFemaleHost ? 'الزوج' : 'الزوجة';
  const relationTitle = resolved.isDeceased
    ? (resolved.gender === 'female' ? 'رحمها الله' : 'رحمه الله')
    : resolved.isDivorced
    ? (resolved.gender === 'female' ? 'زوجة سابقة' : 'زوج سابق')
    : defaultRelationLabel;

  // في حالة بطاقة واحدة كاملة العرض
  if (isSingle) {
    return (
      <TouchableOpacity
        onPress={() => resolved.id && onPress?.(resolved.id)}
        disabled={!resolved.id}
        style={[
          styles.singleSpouseCard,
          {
            backgroundColor: isDark ? '#23151e' : '#fdf2f8',
            borderColor: isDark ? '#831843' : '#fbcfe8',
          },
          resolved.isDeceased && {
            backgroundColor: isDark ? '#1f1f1f' : '#f3f4f6',
            borderColor: isDark ? '#374151' : '#e5e7eb',
          },
        ]}
        activeOpacity={0.8}
      >
        <Avatar
          person={{ photo_url: resolved.photoUrl, gender: resolved.gender }}
          size={52}
          onZoom={onZoom}
          style={{ borderColor: resolved.isDeceased ? '#9ca3af' : T.pink + '60' }}
        />

        <View style={styles.singleSpouseInfo}>
          <View style={styles.singleSpouseHeaderRow}>
            <View
              style={[
                styles.spouseTag,
                {
                  backgroundColor: resolved.isDeceased
                    ? isDark ? '#374151' : '#e5e7eb'
                    : T.pink + '18',
                },
              ]}
            >
              <Ionicons
                name={resolved.isDeceased ? 'heart-dislike-outline' : 'heart'}
                size={12}
                color={resolved.isDeceased ? '#9ca3af' : T.pink}
              />
              <Text
                style={[
                  styles.spouseTagText,
                  { color: resolved.isDeceased ? (isDark ? '#d1d5db' : '#4b5563') : T.pink },
                ]}
              >
                {relationTitle}
              </Text>
            </View>

            {resolved.marriedAt && !resolved.isDeceased && (
              <Text style={[styles.spouseDateText, { color: colors.icon }]}>
                {formatDate(resolved.marriedAt)}
              </Text>
            )}
          </View>

          <Text style={[styles.singleSpouseName, { color: colors.text }]} numberOfLines={1}>
            {resolved.name}
          </Text>
        </View>

        {resolved.id ? (
          <View style={[styles.spouseArrowCircle, { backgroundColor: isDark ? '#37415144' : '#ffffff88' }]}>
            <Ionicons name="chevron-back" size={16} color={resolved.isDeceased ? colors.icon : T.pink} />
          </View>
        ) : null}
      </TouchableOpacity>
    );
  }

  // في حالة تعدد الزوجات — عرض شبكي منسق بدون ترقيم
  return (
    <TouchableOpacity
      onPress={() => resolved.id && onPress?.(resolved.id)}
      disabled={!resolved.id}
      style={[
        styles.gridSpouseCard,
        {
          backgroundColor: isDark ? '#1a1d24' : '#FFFFFF',
          borderColor: isDark ? '#2d333f' : '#f1f5f9',
          shadowColor: isDark ? '#000' : '#64748b',
        },
        resolved.isDeceased && {
          borderColor: isDark ? '#374151' : '#e2e8f0',
          opacity: 0.9,
        },
      ]}
      activeOpacity={0.8}
    >
      {/* الرأس: شارة مسمى العلاقة */}
      <View style={styles.gridSpouseHeader}>
        <View
          style={[
            styles.gridOrdinalPill,
            {
              backgroundColor: resolved.isDeceased
                ? isDark ? '#374151' : '#f1f5f9'
                : isDark ? '#3b1c2b' : '#fdf2f8',
              borderColor: resolved.isDeceased
                ? isDark ? '#4b5563' : '#e2e8f0'
                : isDark ? '#831843' : '#fbcfe8',
            },
          ]}
        >
          <Ionicons
            name={resolved.isDeceased ? 'heart-dislike' : 'heart'}
            size={11}
            color={resolved.isDeceased ? '#9ca3af' : T.pink}
          />
          <Text
            style={[
              styles.gridOrdinalText,
              { color: resolved.isDeceased ? (isDark ? '#9ca3af' : '#64748b') : (isDark ? '#f472b6' : '#db2777') },
            ]}
          >
            {relationTitle}
          </Text>
        </View>

        {resolved.id ? (
          <Ionicons name="chevron-back" size={14} color={colors.icon} />
        ) : null}
      </View>

      {/* المحتوى: الصورة والاسم الكامل بدون أي تداخل */}
      <View style={styles.gridSpouseBody}>
        <Avatar
          person={{ photo_url: resolved.photoUrl, gender: resolved.gender }}
          size={50}
          onZoom={onZoom}
          style={{
            borderColor: resolved.isDeceased ? '#9ca3af44' : T.pink + '44',
            borderWidth: 2,
          }}
        />

        <Text
          style={[styles.gridSpouseName, { color: colors.text }]}
          numberOfLines={2}
          ellipsizeMode="tail"
        >
          {resolved.name}
        </Text>

        {resolved.marriedAt && !resolved.isDeceased && (
          <Text style={[styles.gridMarriedDate, { color: colors.icon }]}>
            {formatDate(resolved.marriedAt)}
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );
};

// ─────────────────────────────────────────────
// CONTACT ICON
// ─────────────────────────────────────────────
const CONTACT_META: Record<string, { color: string; icon: string }> = {
  whatsapp: { color: '#25D366', icon: 'chatbubble-ellipses' },
  facebook: { color: '#1877F2', icon: 'logo-facebook' },
  instagram: { color: '#E4405F', icon: 'logo-instagram' },
  twitter: { color: '#1DA1F2', icon: 'logo-twitter' },
  telegram: { color: '#0088cc', icon: 'send' },
  linkedin: { color: '#0077B5', icon: 'logo-linkedin' },
};

const ContactIcon = ({ account }: { account: { type: string; url: string } }) => {
  const { colors } = useThemeColors();
  const meta = CONTACT_META[account.type] || { color: colors.primary, icon: 'link' };
  return (
    <TouchableOpacity
      style={[styles.contactIcon, { borderColor: meta.color + '55', backgroundColor: meta.color + '14' }]}
      onPress={() => Linking.openURL(account.url)}
    >
      <Ionicons name={meta.icon as any} size={22} color={meta.color} />
    </TouchableOpacity>
  );
};

// ─────────────────────────────────────────────
// SECTION HEADER
// ─────────────────────────────────────────────
const SectionHeader = ({ title, iconName }: { title: string; iconName: keyof typeof Ionicons.glyphMap }) => {
  const { colors } = useThemeColors();
  return (
    <View style={[styles.sectionHeader, { borderBottomColor: colors.primary + '28' }]}>
      <Ionicons name={iconName} size={16} color={colors.primary} />
      <Text style={[styles.sectionTitle, { color: colors.text }]}>{title}</Text>
    </View>
  );
};

// ─────────────────────────────────────────────
// STAT BADGE
// ─────────────────────────────────────────────
const StatBadge = ({
  iconName,
  label,
  count,
  onPress,
}: {
  iconName: keyof typeof Ionicons.glyphMap;
  label: string;
  count: number;
  onPress: () => void;
}) => {
  const { colors } = useThemeColors();
  return (
    <TouchableOpacity
      style={[styles.statBadge, { backgroundColor: colors.primary + '10', borderColor: colors.primary + '28' }]}
      onPress={onPress}
      activeOpacity={0.7}
    >
      <Ionicons name={iconName} size={22} color={colors.primary} />
      <Text style={[styles.statLabel, { color: colors.primary }]}>{label}</Text>
      <View style={[styles.statCount, { backgroundColor: colors.primary }]}>
        <Text style={styles.statCountText}>{count}</Text>
      </View>
    </TouchableOpacity>
  );
};

// ─────────────────────────────────────────────
// DETAIL GRID
// ─────────────────────────────────────────────
const DetailGrid = ({ children }: { children: React.ReactNode }) => {
  const items = React.Children.toArray(children).filter((c) => c !== null && c !== undefined && (c as any) !== false);
  const rows: React.ReactNode[][] = [];
  for (let i = 0; i < items.length; i += 2) rows.push(items.slice(i, i + 2));
  return (
    <View style={{ gap: 8 }}>
      {rows.map((row, i) => (
        <View key={i} style={{ flexDirection: 'row', gap: 8 }}>
          <View style={{ flex: 1 }}>{row[0]}</View>
          <View style={{ flex: 1 }}>{row[1] ?? null}</View>
        </View>
      ))}
    </View>
  );
};

// ─────────────────────────────────────────────
// DETAIL ROW
// ─────────────────────────────────────────────
const DetailRow = ({
  iconName, label, value, url, accent,
}: { iconName: keyof typeof Ionicons.glyphMap; label: string; value?: string | null; url?: string; accent?: boolean }) => {
  const { colors } = useThemeColors();
  if (!value) return null;
  return (
    <View style={[styles.detailPill, { backgroundColor: accent ? colors.primary + '10' : colors.card, borderColor: accent ? colors.primary + '40' : colors.border }]}>
      <View style={styles.detailPillTop}>
        <Ionicons name={iconName} size={12} color={colors.icon} style={{ marginRight: 4 }} />
        <Text style={[styles.detailLabel, { color: colors.icon }]}>{label}</Text>
      </View>
      {url ? (
        <TouchableOpacity onPress={() => Linking.openURL(url)}>
          <Text style={[styles.detailValue, { color: colors.primary }]}>{value} ↗</Text>
        </TouchableOpacity>
      ) : (
        <Text style={[styles.detailValue, { color: colors.text }]} numberOfLines={2}>{value}</Text>
      )}
    </View>
  );
};

// ─────────────────────────────────────────────
// MAIN SCREEN COMPONENT
// ─────────────────────────────────────────────
export default function PersonDetailsScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const personId = id ? parseInt(id, 10) : null;

  const { colors, colorScheme } = useThemeColors();
  const { hasIdentity, user } = useAuth();
  const [person, setPerson] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [modalChildren, setModalChildren] = useState<any[]>([]);
  const [storiesCount, setStoriesCount] = useState<number | null>(null);
  const [friendshipsCount, setFriendshipsCount] = useState<number | null>(null);
  const [zoomUri, setZoomUri] = useState<string | null>(null);
  const [zoomVisible, setZoomVisible] = useState(false);
  const [kinshipModalVisible, setKinshipModalVisible] = useState(false);
  const [kinshipMode, setKinshipMode] = useState<'compare_with_me' | 'custom_compare'>('compare_with_me');
  const [friendsModalVisible, setFriendsModalVisible] = useState(false);
  const [galleryModalVisible, setGalleryModalVisible] = useState(false);
  const [storiesModalVisible, setStoriesModalVisible] = useState(false);



  const openZoom = (uri: string) => { setZoomUri(uri); setZoomVisible(true); };

  const loadData = useCallback(() => {
    if (!personId) return;
    setPerson(null); setModalChildren([]); setStoriesCount(null); setFriendshipsCount(null);
    setLoading(true);
    fetchPersonById(personId).then((data) => {
      setLoading(false);
      if (data?.person) {
        setPerson(data.person);
        if (data.person.children_count > 0)
          fetchPersonChildrenDetails(personId).then((cd) => { if (cd?.children) setModalChildren(cd.children); });
        Promise.all([fetchPersonStoriesCount(personId), fetchPersonFriendshipsCount(personId)]).then(([st, fr]) => {
          if (st && typeof st.count === 'number') setStoriesCount(st.count);
          if (fr && typeof fr.count === 'number') setFriendshipsCount(fr.count);
        });
      }
    }).catch((err) => {
      console.error(err);
      setLoading(false);
    });
  }, [personId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const isDeceased = !!person?.death_date;
  const isFemale = person?.gender === 'female';

  const navigateToRelation = useCallback((targetId: number) => {
    router.push(`/(app)/person/${targetId}`);
  }, [router]);

  // ── مصفوفة المعلومات الديناميكية: فقط اللي فيها قيمة
  const infoFields = person ? [
    { iconName: 'calendar-outline' as const, label: 'تاريخ الميلاد', value: !isFemale ? formatDate(person.birth_date) : null },
    { iconName: 'hourglass-outline' as const, label: 'العمر', value: !isDeceased && !isFemale && person.age ? `${person.age} سنة` : null },
    { iconName: 'location-outline' as const, label: 'مكان الميلاد', value: person.birth_place },
    { iconName: 'home-outline' as const, label: 'مكان الإقامة', value: person.location?.name || (typeof person.location === 'string' ? person.location : null), url: person.location?.url },
    { iconName: 'briefcase-outline' as const, label: 'المهنة', value: person.occupation },
    { iconName: 'time-outline' as const, label: 'تاريخ الوفاة', value: isDeceased ? formatDate(person.death_date) : null, accent: true },
    { iconName: 'map-outline' as const, label: 'مكان الوفاة', value: isDeceased ? person.death_place : null, accent: true },
    { iconName: 'leaf-outline' as const, label: 'المقبرة', value: isDeceased ? person.cemetery : null, accent: true },
  ].filter((f) => !!f.value) : [];

  return (
    <ThemedView style={[styles.container, { backgroundColor: colors.background }]}>
      <StatusBar barStyle={colorScheme === 'dark' ? 'light-content' : 'dark-content'} backgroundColor={colors.background} translucent={false} />
      <HeaderBar
        title="بطاقة العضو"
        subtitle={person?.full_name || ''}
        showBack
        onBackPress={() => router.back()}
      />

      <ImageViewerModal
        visible={zoomVisible}
        images={zoomUri ? [{ uri: zoomUri }] : []}
        onClose={() => setZoomVisible(false)}
      />
      <KinshipModal
        visible={kinshipModalVisible}
        onClose={() => setKinshipModalVisible(false)}
        targetPerson={person}
        mode={kinshipMode}
        colors={colors}
      />
      <PersonFriendsModal
        visible={friendsModalVisible}
        onClose={() => setFriendsModalVisible(false)}
        friends={person?.friends || []}
        personId={person?.id}
        personName={person?.full_name || person?.first_name}
        colors={colors}
        onSelectFriend={navigateToRelation}
      />
      <PersonGalleryModal
        visible={galleryModalVisible}
        onClose={() => setGalleryModalVisible(false)}
        personId={person?.id}
        personName={person?.full_name || person?.first_name}
        colors={colors}
        initialCount={person?.images_count || 0}
      />
      <PersonStoriesModal
        visible={storiesModalVisible}
        onClose={() => setStoriesModalVisible(false)}
        personId={person?.id}
        personName={person?.full_name || person?.first_name}
        colors={colors}
        initialCount={storiesCount || 0}
      />



      {loading ? (
        <View style={styles.centeredLoader}>
          <ActivityIndicator color={colors.primary} size="large" />
          <Text style={[styles.loadingText, { color: colors.text }]}>جاري التحميل...</Text>
        </View>
      ) : !person ? (
        <View style={styles.centeredLoader}>
          <Ionicons name="warning-outline" size={48} color={colors.icon} />
          <Text style={{ color: colors.icon, fontFamily: 'Tajawal-Regular', marginTop: 8 }}>فشل تحميل البيانات</Text>
        </View>
      ) : (
        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 60 }}>

          {/* ── Hero ── */}
          <View style={[styles.heroSection, { backgroundColor: isDeceased ? (colorScheme === 'dark' ? '#242424' : '#f0f0f0') : colors.primary + '15' }]}>
            {isDeceased && (
              <View style={[styles.deceasedBanner, { backgroundColor: colorScheme === 'dark' ? '#333' : '#555' }]}>
                <Ionicons name="heart-dislike-outline" size={13} color="#ddd" style={{ marginLeft: 5 }} />
                <Text style={styles.deceasedBannerText}>{isFemale ? 'رحمها الله' : 'رحمه الله'}</Text>
              </View>
            )}

            {(() => {
              const heroAvatar = resolvePersonAvatar(person);
              return (
                <TouchableOpacity
                  onPress={() => heroAvatar && openZoom(heroAvatar)}
                  activeOpacity={heroAvatar ? 0.85 : 1}
                  disabled={!heroAvatar}
                  style={styles.heroAvatarWrap}
                >
                  {heroAvatar ? (
                    <Image source={{ uri: heroAvatar }} style={[styles.heroPhoto, isDeceased && { opacity: 0.75 }]} />
                  ) : (
                    <View style={[styles.heroPhoto, { backgroundColor: colors.primary + '20', alignItems: 'center', justifyContent: 'center' }]}>
                      <Ionicons name={isFemale ? 'woman' : 'man'} size={52} color={colors.primary} />
                    </View>
                  )}
                  {heroAvatar && (
                    <View style={[styles.zoomHint, { backgroundColor: colors.primary + 'cc' }]}>
                      <Ionicons name="search" size={10} color="#fff" />
                      <Text style={styles.zoomHintText}>تكبير</Text>
                    </View>
                  )}
                </TouchableOpacity>
              );
            })()}

            <Text style={[styles.heroName, { color: isDeceased ? (colorScheme === 'dark' ? '#ccc' : '#444') : colors.text }]}>
              {person.full_name || person.first_name}
            </Text>

            <View style={[styles.genderBadge, { backgroundColor: colors.primary + '18', borderColor: colors.primary + '30' }]}>
              <Ionicons name={isFemale ? 'female' : 'male'} size={12} color={colors.primary} />
              <Text style={[styles.genderText, { color: colors.primary }]}>{isFemale ? 'أنثى' : 'ذكر'}</Text>
            </View>

            {/* أزرار حساب صلة القرابة والمقارنة */}
            <View style={{ flexDirection: 'row', gap: 8, marginTop: 10, flexWrap: 'wrap', justifyContent: 'center' }}>
              {hasIdentity && person?.id !== user?.id && (
                <TouchableOpacity
                  style={[
                    styles.kinshipBtn,
                    {
                      backgroundColor: (colorScheme === 'dark' ? T.goldLight : T.gold) + '22',
                      borderColor: colorScheme === 'dark' ? T.goldLight : T.gold,
                    },
                  ]}
                  onPress={() => {
                    setKinshipMode('compare_with_me');
                    setKinshipModalVisible(true);
                  }}
                >
                  <Ionicons name="git-network-outline" size={15} color={colorScheme === 'dark' ? T.goldLight : T.gold} />
                  <Text style={[styles.kinshipBtnText, { color: colorScheme === 'dark' ? T.goldLight : T.gold }]}>صلة القرابة معي</Text>
                </TouchableOpacity>
              )}


              <TouchableOpacity
                style={[styles.kinshipBtn, { backgroundColor: colors.primary + '18', borderColor: colors.primary }]}
                onPress={() => {
                  setKinshipMode('custom_compare');
                  setKinshipModalVisible(true);
                }}
              >
                <Ionicons name="search" size={15} color={colors.primary} />
                <Text style={[styles.kinshipBtnText, { color: colors.primary }]}>مقارنة مع عضو آخر...</Text>
              </TouchableOpacity>
            </View>



            {(person.contact_accounts?.length > 0 || person.locations?.some((l: any) => l.url)) && (
              <View style={styles.contactRow}>
                {person.contact_accounts?.map((a: any, i: number) => <ContactIcon key={i} account={a} />)}
                {person.locations?.map((loc: any, i: number) =>
                  loc.url ? (
                    <TouchableOpacity key={`loc-${i}`} style={[styles.contactIcon, { borderColor: '#ea443555', backgroundColor: '#ea433514' }]} onPress={() => Linking.openURL(loc.url)}>
                      <Ionicons name="location" size={22} color="#ea4335" />
                    </TouchableOpacity>
                  ) : null
                )}
              </View>
            )}
          </View>

          {/* ── Stats ── */}
          {(person.images_count > 0 || (storiesCount || 0) > 0 || (friendshipsCount || 0) > 0) && (
            <View style={styles.statsRow}>
              {person.images_count > 0 && (
                <StatBadge
                  iconName="images-outline"
                  label="صور"
                  count={person.images_count}
                  onPress={() => setGalleryModalVisible(true)}
                />
              )}
              {storiesCount != null && storiesCount > 0 && (
                <StatBadge
                  iconName="book-outline"
                  label="قصص"
                  count={storiesCount}
                  onPress={() => setStoriesModalVisible(true)}
                />
              )}
              {friendshipsCount != null && friendshipsCount > 0 && (
                <StatBadge
                  iconName="people-outline"
                  label="أصدقاء"
                  count={friendshipsCount}
                  onPress={() => setFriendsModalVisible(true)}
                />
              )}
            </View>
          )}

          {/* ── Personal Info ── */}
          {infoFields.length > 0 && (
            <View style={styles.section}>
              <SectionHeader title="المعلومات الشخصية" iconName="person-outline" />
              <DetailGrid>
                {infoFields.map((f, i) => (
                  <DetailRow key={i} iconName={f.iconName} label={f.label} value={f.value} url={(f as any).url} accent={(f as any).accent} />
                ))}
              </DetailGrid>
            </View>
          )}

          {/* ── Biography ── */}
          {person.biography?.trim() && (
            <View style={styles.section}>
              <SectionHeader title="نبذة" iconName="document-text-outline" />
              <View style={[styles.biographyBox, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.biographyText, { color: colors.text }]}>{person.biography}</Text>
              </View>
            </View>
          )}

          {/* ── Parents ── */}
          {(person.parent || person.mother) && (
            <View style={styles.section}>
              <SectionHeader title="الوالدان" iconName="people-circle-outline" />
              <View style={styles.relationPair}>
                {person.parent && <View style={{ flex: 1 }}><RelationCard person={person.parent} label="الأب" onPress={navigateToRelation} onZoom={openZoom} /></View>}
                {person.mother && <View style={{ flex: 1 }}><RelationCard person={person.mother} label="الأم" onPress={navigateToRelation} onZoom={openZoom} /></View>}
              </View>
            </View>
          )}

          {/* ── Breastfeeding Mothers ── */}
          {person.breastfeeding_mothers?.length > 0 && (
            <View style={styles.section}>
              <SectionHeader title="أم من الرضاعة" iconName="heart-outline" />
              {person.breastfeeding_mothers.map((m: any) => (
                <RelationCard key={m.id} person={m} label="أم من الرضاعة" isBreastfeeding onPress={navigateToRelation} onZoom={openZoom} />
              ))}
            </View>
          )}

          {/* ── Spouses ── */}
          {person.spouses?.length > 0 && (
            <View style={styles.section}>
              <SectionHeader
                title={
                  isFemale
                    ? person.spouses.length > 1
                      ? `الأزواج (${person.spouses.length})`
                      : 'الزوج'
                    : person.spouses.length > 1
                    ? `الزوجات (${person.spouses.length})`
                    : 'الزوجة'
                }
                iconName="rose-outline"
              />
              {person.spouses.length === 1 ? (
                <SpouseCard
                  item={person.spouses[0]}
                  isFemaleHost={isFemale}
                  onPress={navigateToRelation}
                  onZoom={openZoom}
                  isSingle
                />
              ) : (
                <View style={{ gap: 10 }}>
                  {Array.from(
                    { length: Math.ceil(person.spouses.length / 2) },
                    (_, i) => person.spouses.slice(i * 2, i * 2 + 2)
                  ).map((pair: any[], rowIdx: number) => (
                    <View key={rowIdx} style={styles.spouseGridRow}>
                      {pair.map((sp: any, idx: number) => (
                        <View key={sp.id || `sp-${rowIdx}-${idx}`} style={{ flex: 1 }}>
                          <SpouseCard
                            item={sp}
                            isFemaleHost={isFemale}
                            onPress={navigateToRelation}
                            onZoom={openZoom}
                          />
                        </View>
                      ))}
                      {pair.length === 1 && <View style={{ flex: 1 }} />}
                    </View>
                  ))}
                </View>
              )}
            </View>
          )}

          {/* ── Children ── */}
          {person.children_count > 0 && (
            <View style={styles.section}>
              <SectionHeader title={`الأبناء (${person.children_count})`} iconName="people-outline" />
              {modalChildren.length === 0 ? (
                <ActivityIndicator color={colors.primary} style={{ marginVertical: 12 }} />
              ) : (
                <View style={{ gap: 8 }}>
                  {Array.from({ length: Math.ceil(modalChildren.length / 2) }, (_, i) => modalChildren.slice(i * 2, i * 2 + 2)).map((pair, rowIdx) => (
                    <View key={rowIdx} style={styles.relationPair}>
                      {pair.map((child: any) => (
                        <View key={child.id} style={{ flex: 1 }}>
                          <RelationCard person={child} label={child.gender === 'female' ? 'ابنة' : 'ابن'} isBreastfeeding={child.is_breastfeeding_child} onPress={navigateToRelation} onZoom={openZoom} />
                        </View>
                      ))}
                      {pair.length === 1 && <View style={{ flex: 1 }} />}
                    </View>
                  ))}
                </View>
              )}
            </View>
          )}

        </ScrollView>
      )}
    </ThemedView>
  );
}

// ─────────────────────────────────────────────
// STYLES
// ─────────────────────────────────────────────
const styles = StyleSheet.create({
  container: { flex: 1 },
  centeredLoader: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  loadingText: { fontSize: 15, fontFamily: 'Tajawal-Medium', textAlign: 'center' },

  // Hero
  heroSection: { alignItems: 'center', paddingBottom: 24, paddingTop: 28 },
  deceasedBanner: {
    position: 'absolute', top: 0, left: 0, right: 0,
    paddingVertical: 7, alignItems: 'center',
    flexDirection: 'row', justifyContent: 'center', gap: 4,
  },
  deceasedBannerText: { color: '#ddd', fontSize: 13, fontFamily: 'Tajawal-Bold', lineHeight: 20 },
  heroAvatarWrap: { position: 'relative', marginTop: 20, marginBottom: 12 },
  heroPhoto: { width: 112, height: 112, borderRadius: 56, borderWidth: 3, borderColor: 'rgba(255,255,255,0.5)' },
  zoomHint: {
    position: 'absolute', bottom: 0, left: 0, right: 0,
    borderBottomLeftRadius: 56, borderBottomRightRadius: 56,
    paddingVertical: 4, alignItems: 'center',
    flexDirection: 'row', justifyContent: 'center', gap: 3,
  },
  zoomHintText: { color: '#fff', fontSize: 9, fontFamily: 'Tajawal-Bold' },
  heroName: {
    fontSize: 22, fontFamily: 'Tajawal-Bold',
    // مساحة علوية لمنع قصّ نقاط الثاء/الشين على iOS (Tajawal-Bold yMax يتجاوز ascent)
    lineHeight: 34,
    paddingHorizontal: 20,
    textAlign: 'center',
  },
  genderBadge: {
    flexDirection: 'row', alignItems: 'center', gap: 4,
    borderRadius: 20, borderWidth: 1,
    paddingHorizontal: 10, paddingVertical: 4, marginTop: 6,
  },
  genderText: { fontSize: 12, fontFamily: 'Tajawal-Medium', lineHeight: 18 },
  contactRow: {
    flexDirection: 'row', justifyContent: 'center',
    flexWrap: 'wrap', gap: 10, paddingHorizontal: 20, marginTop: 14,
  },
  contactIcon: { width: 50, height: 50, borderRadius: 14, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },

  // Stats
  statsRow: { flexDirection: 'row', justifyContent: 'center', gap: 10, paddingHorizontal: 16, paddingVertical: 14 },
  statBadge: { flex: 1, alignItems: 'center', borderRadius: 14, borderWidth: 1, paddingVertical: 10, gap: 4, maxWidth: 110 },
  statLabel: { fontSize: 12, fontFamily: 'Tajawal-Medium', lineHeight: 18 },
  statCount: { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 2, minWidth: 24, alignItems: 'center' },
  statCountText: { color: '#fff', fontSize: 11, fontFamily: 'Tajawal-Bold', lineHeight: 16 },

  // Sections
  section: { marginHorizontal: 16, marginBottom: 22 },
  sectionHeader: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    marginBottom: 12, paddingBottom: 8, borderBottomWidth: 1.5,
  },
  sectionTitle: { fontSize: 15, fontFamily: 'Tajawal-Bold', lineHeight: 24 },

  // Detail Pill
  detailPill: { borderRadius: 12, paddingHorizontal: 12, paddingVertical: 10, borderWidth: 1 },
  detailPillTop: { flexDirection: 'row', marginBottom: 4 },
  detailLabel: { fontSize: 11, fontFamily: 'Tajawal-Regular', lineHeight: 17 },
  detailValue: { fontSize: 13, fontFamily: 'Tajawal-Bold', lineHeight: 22 },

  // Biography
  biographyBox: { borderRadius: 14, padding: 14, borderWidth: StyleSheet.hairlineWidth },
  biographyText: { fontSize: 14, lineHeight: 24, fontFamily: 'Tajawal-Regular' },

  // Relation Card
  relationCard: {
    flexDirection: 'row', alignItems: 'center',
    borderRadius: 14, borderWidth: 1, padding: 10, marginBottom: 0, gap: 10,
  },
  relationPair: { flexDirection: 'row', gap: 8, marginBottom: 8 },
  relationName: { fontSize: 13, fontFamily: 'Tajawal-Bold', writingDirection: 'rtl', lineHeight: 21 },
  relationSub: { fontSize: 11, marginTop: 2, fontFamily: 'Tajawal-Regular', lineHeight: 17 },

  kinshipBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    marginTop: 10,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 20,
    borderWidth: 1.5,
  },
  kinshipBtnText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },

  // ── Spouse Card Styles ──
  spouseGridRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 10,
  },
  singleSpouseCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    padding: 12,
    gap: 12,
  },
  singleSpouseInfo: {
    flex: 1,
  },
  singleSpouseHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  singleSpouseName: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    writingDirection: 'rtl',
    textAlign: 'left',
    lineHeight: 24,
  },
  spouseTag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  spouseTagText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 17,
  },
  spouseDateText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 17,
  },
  spouseArrowCircle: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  gridSpouseCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 12,
    alignItems: 'center',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
    minHeight: 145,
    justifyContent: 'space-between',
  },
  gridSpouseHeader: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  gridOrdinalPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 7,
    paddingVertical: 2.5,
    borderRadius: 10,
    borderWidth: 1,
  },
  gridOrdinalText: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 16,
  },
  gridSpouseBody: {
    alignItems: 'center',
    width: '100%',
    gap: 6,
  },
  gridSpouseName: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    writingDirection: 'rtl',
    // كان 18 (1.33x) يقصّ النقطة الثالثة للثاء على iOS
    lineHeight: 22,
    marginTop: 2,
  },
  statusMiniBadge: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 8,
    marginTop: 2,
  },
  statusMiniText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Medium',
  },
  gridMarriedDate: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 15,
    marginTop: 2,
  },
});

