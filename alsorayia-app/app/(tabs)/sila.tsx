/** شجرة العائلة (السِّلة) — تصميم محسّن v3 */

import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedView } from '@/components/themed-view';
import { Colors, silaTheme as T } from '@/constants/theme';
import {
  API_BASE_URL,
  fetchFamilyTree,
  fetchPersonChildren,
} from '@/lib/api/familyTreeApi';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  Image,
  Pressable,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  useColorScheme,
  View
} from 'react-native';

const { width: SCREEN_W } = Dimensions.get('window');

type AppThemeColors = (typeof Colors)['light'];

const SilaThemeContext = createContext<{
  colors: AppThemeColors;
  colorScheme: 'light' | 'dark';
} | null>(null);

function useSilaTheme() {
  const ctx = useContext(SilaThemeContext);
  if (!ctx) throw new Error('useSilaTheme must be used within Sila screen');
  return ctx;
}

// ─────────────────────────────────────────────
// PERSON CARD
// ─────────────────────────────────────────────
const CARD_W = 112;

const PersonCard = ({
  person, onPress, onDetailsPress,
}: { person: any; onPress: (p: any) => void; onDetailsPress: (id: number) => void }) => {
  const { colors, colorScheme } = useSilaTheme();
  const [scale] = useState(() => new Animated.Value(1));
  const isDeceased = !!person.death_date;
  const isBreastfeeding = !!person.is_breastfeeding_child;
  const hasChildren = person.children_count > 0;

  const cardBg = isBreastfeeding ? '#fce4ec' : isDeceased ? (colorScheme === 'dark' ? '#252525' : '#f5f5f5') : colors.card;
  const borderColor = isBreastfeeding ? T.pink : isDeceased ? '#999' : colors.primary + '40';

  const onPressIn = () => Animated.spring(scale, { toValue: 0.95, useNativeDriver: true, speed: 50 }).start();
  const onPressOut = () => Animated.spring(scale, { toValue: 1, useNativeDriver: true, speed: 50 }).start();

  return (
    <Animated.View style={{ transform: [{ scale }] }}>
      <Pressable
        onPressIn={onPressIn} onPressOut={onPressOut} onPress={() => onPress(person)}
        style={[styles.personCard, { backgroundColor: cardBg, borderColor }]}
      >
        {isDeceased && (
          <View style={styles.mourningBadge}>
            <Text style={styles.mourningText}>{person.gender === 'female' ? 'رحمها الله' : 'رحمه الله'}</Text>
          </View>
        )}
        {isBreastfeeding && (
          <View style={styles.breastfeedingBadge}>
            <Ionicons name="heart" size={8} color="#fff" />
            <Text style={styles.breastfeedingText}>رضاعة</Text>
          </View>
        )}
        {person.photo_url ? (
          <View style={styles.cardTopArea}>
            <Image source={{ uri: person.photo_url }} style={styles.cardPhoto} />
            <LinearGradient
              colors={['transparent', 'rgba(0,0,0,0.85)']}
              style={styles.cardGradient}
            />
            <Text style={[styles.cardName, styles.cardNameAbsolute, { color: '#fff' }]} numberOfLines={2}>
              {person.first_name}
            </Text>
          </View>
        ) : (
          <View style={styles.cardTopArea}>
            <View style={[styles.cardAvatarWrap, { backgroundColor: colors.primary + '14' }]}>
              <Ionicons name={person.gender === 'female' ? 'woman' : 'man'} size={30} color={colors.primary} />
            </View>
            <Text style={[styles.cardName, { color: colors.text }]} numberOfLines={2}>{person.first_name}</Text>
          </View>
        )}
        <TouchableOpacity
          style={[styles.detailsBtn, { borderTopColor: colors.border }]}
          onPress={() => onDetailsPress(person.id)}
          hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
        >
          <Text style={[styles.detailsBtnText, { color: colors.primary }]}>التفاصيل</Text>
        </TouchableOpacity>
        {/* {hasChildren && (
          <View style={[styles.childrenBadge, { backgroundColor: colors.primary }]}>
            <Text style={styles.childrenBadgeText}>{person.children_count}</Text>
          </View>
        )} */}
      </Pressable>
    </Animated.View>
  );
};

// ─────────────────────────────────────────────
// TREE COLUMN
// ─────────────────────────────────────────────
const TreeColumn = ({
  persons, level, onDetailsPress,
}: { persons: any[]; level: number; onDetailsPress: (id: number) => void }) => {
  const { colors } = useSilaTheme();
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  const [children, setChildren] = useState<Record<number, any[]>>({});
  const [loading, setLoading] = useState<Record<number, boolean>>({});

  const toggle = useCallback(async (person: any) => {
    const id = person.id;
    if (!person.children_count) return;
    if (expanded[id]) { setExpanded((p) => ({ ...p, [id]: false })); return; }
    setExpanded({ [id]: true });
    if (!children[id]) {
      setLoading((p) => ({ ...p, [id]: true }));
      const data = await fetchPersonChildren(id);
      setLoading((p) => ({ ...p, [id]: false }));
      if (data?.children) setChildren((p) => ({ ...p, [id]: data.children }));
    }
  }, [expanded, children]);

  return (
    <View style={styles.columnRow}>
      <View style={styles.column}>
        {persons.map((p) => (
          <View key={p.id}>
            <PersonCard person={p} onPress={toggle} onDetailsPress={onDetailsPress} />
            {loading[p.id] && <ActivityIndicator color={colors.primary} size="small" style={{ marginTop: 6 }} />}
          </View>
        ))}
      </View>
      <View style={styles.childrenArea}>
        {persons.map((p, index) =>
          expanded[p.id] && children[p.id]?.length > 0 ? (
            <View key={`col-${p.id}`} style={styles.childrenGroup}>
              {/* فاصل رأسي محاذاة الأبناء مع الأب بدقة */}
              <View style={{ height: index * 181 }} />
              <TreeColumn persons={children[p.id]} level={level + 1} onDetailsPress={onDetailsPress} />
            </View>
          ) : null
        )}
      </View>
    </View>
  );
};

// ─────────────────────────────────────────────
// LOADING / EMPTY / DIAGNOSTIC
// ─────────────────────────────────────────────
const LoadingState = () => {
  const { colors } = useSilaTheme();
  return (
    <View style={styles.centeredLoader}>
      <ActivityIndicator color={colors.primary} size="large" />
      <Text style={[styles.loadingText, { color: colors.text }]}>جاري تحميل شجرة العائلة...</Text>
    </View>
  );
};

const EmptyState = () => {
  const { colors } = useSilaTheme();
  return (
    <View style={styles.centeredLoader}>
      <Ionicons name="leaf-outline" size={52} color={colors.icon} />
      <Text style={{ color: colors.icon, fontFamily: 'Tajawal-Regular', marginTop: 12 }}>لا توجد بيانات لعرضها</Text>
    </View>
  );
};

const DiagnosticScreen = ({ url, onRetry }: { url: string; onRetry: () => void }) => {
  const { colors } = useSilaTheme();
  return (
    <View style={styles.diagnosticWrap}>
      <Ionicons name="warning-outline" size={52} color={colors.icon} />
      <Text style={{ fontSize: 18, fontFamily: 'Tajawal-Bold', color: colors.text, textAlign: 'center', marginTop: 8 }}>تعذّر الاتصال بالسيرفر</Text>
      <Text style={{ fontSize: 14, fontFamily: 'Tajawal-Regular', color: colors.icon, textAlign: 'center', lineHeight: 22 }}>
        السيرفر أرجع HTML بدلاً من JSON.{'\n'}تحقق من الرابط أو من تسجيل الدخول.
      </Text>
      <View style={[styles.diagUrlBox, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <Text style={{ fontSize: 11, color: colors.icon, fontFamily: 'Tajawal-Medium' }}>الرابط:</Text>
        <Text style={{ fontSize: 12, color: '#c0392b', fontFamily: 'Tajawal-Bold' }} selectable>{url}</Text>
      </View>
      <TouchableOpacity style={[styles.retryBtn, { backgroundColor: colors.primary }]} onPress={onRetry}>
        <Text style={{ color: '#fff', fontSize: 15, fontFamily: 'Tajawal-Bold' }}>إعادة المحاولة</Text>
      </TouchableOpacity>
    </View>
  );
};

// ─────────────────────────────────────────────
// MAIN APP
// ─────────────────────────────────────────────
export default function FamilyTreeApp() {
  const router = useRouter();
  const { personId } = useLocalSearchParams<{ personId: string }>();
  const colorScheme = useColorScheme();
  const themeColors = Colors[colorScheme ?? 'light'] as AppThemeColors;
  const scheme: 'light' | 'dark' = colorScheme === 'dark' ? 'dark' : 'light';

  const [rootPersons, setRootPersons] = useState<any[]>([]);
  const [loadingRoot, setLoadingRoot] = useState(true);
  const [apiError, setApiError] = useState(false);

  const loadRoot = useCallback(() => {
    setLoadingRoot(true); setApiError(false);
    fetchFamilyTree().then((data) => {
      setLoadingRoot(false);
      if (data === null) setApiError(true);
      else if (data?.tree) setRootPersons(data.tree);
    });
  }, []);

  useEffect(() => { loadRoot(); }, []);

  // التوجيه لشاشة التفاصيل المستقلة عند الضغط على كرت شخص
  const openPersonDetails = useCallback((id: number) => {
    router.push(`/(app)/person/${id}`);
  }, [router]);

  // إعادة التوجيه الفوري لصفحة التفاصيل المستقلة إذا كان الرابط الخارجي يحمل معرف العضو
  useEffect(() => {
    if (personId) {
      router.replace(`/(app)/person/${personId}`);
    }
  }, [personId, router]);

  return (
    <SilaThemeContext.Provider value={{ colors: themeColors, colorScheme: scheme }}>
      <ThemedView style={styles.silaRoot}>
        <AnimatedBubbles bubbleCount={6} minSize={40} maxSize={115} />
        <StatusBar barStyle={scheme === 'dark' ? 'light-content' : 'dark-content'} backgroundColor={themeColors.background} translucent={false} />
        <HeaderBar title="صلة" subtitle="شجرة العائلة والتواصل" showAvatar avatarSource={require('@/assets/images/logo.png')} showSearch onSearchPress={() => router.push('/(app)/search')} />

        <View style={{ flex: 1 }}>
          {loadingRoot ? <LoadingState />
            : apiError ? <DiagnosticScreen url={API_BASE_URL} onRetry={loadRoot} />
              : rootPersons.length === 0 ? <EmptyState />
                : (
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.treeScrollContent}
                    style={{ direction: 'rtl' }}
                  >
                    <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingVertical: 24 }}>
                      <TreeColumn persons={rootPersons} level={0} onDetailsPress={openPersonDetails} />
                    </ScrollView>
                  </ScrollView>
                )}
        </View>
      </ThemedView>
    </SilaThemeContext.Provider>
  );
}

// ─────────────────────────────────────────────
// STYLES
// ─────────────────────────────────────────────
const styles = StyleSheet.create({
  silaRoot: { flex: 1 },

  // Tree
  treeScrollContent: { paddingHorizontal: 16, flexGrow: 1 },
  columnRow: { flexDirection: 'row', alignItems: 'flex-start' },
  column: { flexDirection: 'column', gap: 16 },
  childrenArea: { flexDirection: 'column', gap: 16 },
  childrenGroup: { paddingRight: 16, marginRight: 8 },

  // Person Card
  personCard: {
    width: CARD_W,
    height: 165,
    marginHorizontal: 6,
    borderRadius: 16,
    borderWidth: 1.5,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 6,
    elevation: 3,
    justifyContent: 'space-between',
  },
  mourningBadge: {
    position: 'absolute', top: 5, left: 5, zIndex: 5,
    backgroundColor: '#666', borderRadius: 20,
    paddingHorizontal: 5, paddingVertical: 2,
  },
  mourningText: { color: '#fff', fontSize: 7, fontFamily: 'Tajawal-Bold' },
  breastfeedingBadge: {
    position: 'absolute', top: 5, right: 5, zIndex: 5,
    backgroundColor: '#e91e8c', borderRadius: 20,
    paddingHorizontal: 5, paddingVertical: 2,
    flexDirection: 'row', gap: 2, alignItems: 'center',
  },
  breastfeedingText: { color: '#fff', fontSize: 7, fontFamily: 'Tajawal-Bold' },
  cardTopArea: {
    flex: 1,
    width: '100%',
    position: 'relative',
    justifyContent: 'center',
  },
  cardPhoto: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  cardGradient: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 70,
    zIndex: 1,
  },
  cardAvatarWrap: {
    width: 60, height: 60, borderRadius: 30,
    alignItems: 'center', justifyContent: 'center',
    alignSelf: 'center', marginTop: 12, marginBottom: 4,
  },
  cardName: {
    fontSize: 11, fontFamily: 'Tajawal-Bold',
    // lineHeight موسّع لمنع قصّ نقاط الثاء على iOS
    lineHeight: 18,
    textAlign: 'center', paddingHorizontal: 6, paddingVertical: 6,
  },
  cardNameAbsolute: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    zIndex: 2,
    paddingBottom: 8,
    textShadowColor: 'rgba(0, 0, 0, 0.6)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 3,
  },
  detailsBtn: { paddingVertical: 7, alignItems: 'center', borderTopWidth: StyleSheet.hairlineWidth },
  detailsBtnText: { fontSize: 11, fontFamily: 'Tajawal-Bold', lineHeight: 17 },
  childrenBadge: {
    position: 'absolute', bottom: 34, right: 5,
    width: 18, height: 18, borderRadius: 9,
    alignItems: 'center', justifyContent: 'center',
    zIndex: 10,
  },
  childrenBadgeText: { fontSize: 8, color: '#fff', fontFamily: 'Tajawal-Bold' },

  // Loading / Empty
  centeredLoader: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  loadingText: { fontSize: 15, fontFamily: 'Tajawal-Medium', textAlign: 'center' },

  // Diagnostic
  diagnosticWrap: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, gap: 12 },
  diagUrlBox: { borderRadius: 12, padding: 14, width: '100%', borderWidth: 1, gap: 4, marginTop: 4 },
  retryBtn: { marginTop: 8, paddingHorizontal: 32, paddingVertical: 14, borderRadius: 50 },
});