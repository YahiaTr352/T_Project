import { AnimatedBubbles } from '@/components/animated-bubbles';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { QURAN_ACCENT } from '@/components/quran/constants';
import { useQuranNavigation } from '@/hooks/use-quran-navigation';
import type { QuranNavItem } from '@/lib/api/quranCompetitions';
import { useBottomTabBarHeight } from 'expo-router/js-tabs';
import { LinearGradient } from 'expo-linear-gradient';
import { type Href, useRouter } from 'expo-router';
import { CustomAlert } from '@/components/ui/AlertModal';
import { isUnder20 } from '@/lib/utils/age';
import { canViewInvitesButton } from '@/lib/utils/invitationEligibility';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Image,
  LayoutAnimation,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  useColorScheme,
  View,

} from 'react-native';


interface MenuItem {
  id: string;
  title: string;
  icon: string;
  color: string;
  type?: 'link' | 'dropdown';
  subItems?: { id: string; title: string; icon: string }[];
}

const menuItems: MenuItem[] = [
  {
    id: 'reports',
    title: 'التقارير',
    icon: 'doc.fill',
    color: '#3B82F6',
    type: 'link',
  },
  {
    id: 'certificates',
    title: 'شهادات وأبحاث',
    icon: 'graduationcap.fill',
    color: '#8B5CF6',
    type: 'link',
  },
  {
    id: 'ambitious_students',
    title: 'طلاب طموح',
    icon: 'star.fill',
    color: '#F59E0B',
    type: 'link',
  },
  {
    id: 'invites',
    title: 'الدعوات',
    icon: 'envelope.fill',
    color: '#10B981',
    type: 'link',
  },
  {
    id: 'quran_sunnah',
    title: 'القرآن والسنة',
    icon: 'book.fill',
    color: '#10B981',
    type: 'dropdown',
  },
  {
    id: 'breastfeeding',
    title: 'الرضاعة',
    icon: 'person.2.fill',
    color: '#8B5CF6',
    type: 'link',
  },
  {
    id: 'productive_families',
    title: 'الأسر المنتجة',
    icon: 'cart.fill',
    color: '#14B8A6',
    type: 'link',
  },
];

function navigateQuranNavItem(router: ReturnType<typeof useRouter>, item: QuranNavItem) {
  if (item.route_type === 'competition' && item.competition_id) {
    router.push(`/(app)/quran-competitions/${item.competition_id}` as Href);
  } else {
    router.push(`/(app)/quran-competitions/category/${item.category_id}` as Href);
  }
}

export default function MoreScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const router = useRouter();
  const tabBarHeight = useBottomTabBarHeight();
  const quranNav = useQuranNavigation();

  const [expandedId, setExpandedId] = useState<string | null>(null);

  const menuBaseBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';
  const menuPressedBg = colorScheme === 'dark' ? '#262626' : '#EFF4F8';

  const menuRowShadow = Platform.select({
    ios: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.06,
      shadowRadius: 6,
    },
    android: { elevation: 2 },
    default: {},
  });

  const handlePress = (item: MenuItem) => {
    if (item.type === 'dropdown') {
      LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
      setExpandedId(expandedId === item.id ? null : item.id);
    } else if (item.type === 'link') {
      if (item.id === 'ambitious_students') {
        router.push('/(app)/ambitious-students');
      } else if (item.id === 'breastfeeding') {
        router.push('/(app)/breastfeeding');
      } else if (item.id === 'reports') {
        router.push('/(app)/reports');
      } else if (item.id === 'certificates') {
        router.push('/(app)/articles');
      } else if (item.id === 'productive_families') {
        router.push('/(app)/store' as Href);
      } else if (item.id === 'invites') {
        router.push('/(app)/invites' as Href);
      }
    }
  };

  const { isAuthenticated, hasIdentity, user, lock, logout } = useAuth();

  // «الدعوات»: مخفي عن دخول رمز الموقع/الزائر (بلا هوية — يشمل 112) + أقل من 20 سنة.
  // ناقص البيانات الأساسية يرى الزر لكن يُمنع من الإرسال داخل الشاشة مع توجيه لإكمال بياناته.
  const under20 = isUnder20(user?.birth_date);
  const canViewInvites = canViewInvitesButton(hasIdentity, user);
  const visibleMenuItems = menuItems.filter(
    (item) => !(item.id === 'invites' && (!canViewInvites || under20))
  );

  const handleLogout = () => {
    CustomAlert.alert(
      'تسجيل الخروج',
      'هل أنت متأكد من إغلاق الجلسة الحالية وتسجيل الخروج؟',
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'تسجيل الخروج',
          style: 'destructive',
          onPress: async () => {
            await logout();
          },
        },
      ]
    );
  };


  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={8} minSize={30} maxSize={120} />
      
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={[styles.scrollContent, { paddingBottom: tabBarHeight + 24 }]}>
        
        {/* User Identity & Profile Banner */}
        <Pressable
          style={styles.header}
          onPress={() => {
            if (isAuthenticated && user) {
              router.push('/(app)/my-profile' as Href);
            }
          }}
          disabled={!(isAuthenticated && user)}>
          <LinearGradient
            colors={
              isAuthenticated && user
                ? colorScheme === 'dark'
                  ? ['#065F46', '#047857']
                  : ['#D1FAE5', '#A7F3D0']
                : colorScheme === 'dark'
                ? ['#1F2937', '#111827']
                : ['#F1F5F9', '#E2E8F0']
            }
            style={styles.headerCard}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}>
            <View style={styles.headerContent}>
              <View style={styles.headerTextContainer}>
                {isAuthenticated && user ? (
                  <>
                    <View style={styles.badgeRow}>
                      <View style={styles.verifiedBadgePill}>
                        <ThemedText style={styles.verifiedBadgeText}>🟢 عضو موثق بالبصمة</ThemedText>
                      </View>
                    </View>
                    <ThemedText type="title" style={styles.headerTitle}>
                      {user.full_name || (user.first_name ? `${user.first_name} ${user.last_name || ''}` : 'عضو عائلة السريّع')}
                    </ThemedText>
                    <ThemedText style={[styles.headerSubtitle, { color: colorScheme === 'dark' ? '#D1D5DB' : '#047857' }]}>
                      أهلاً بك في منصّتكم العائلية والتفاعلية
                    </ThemedText>
                  </>
                ) : (
                  <>
                    <View style={styles.badgeRow}>
                      <View style={styles.guestBadgePill}>
                        <ThemedText style={styles.guestBadgeText}>⚪ زائر (مجهول)</ThemedText>
                      </View>
                    </View>
                    <ThemedText type="title" style={styles.headerTitle}>
                      زائر محترم
                    </ThemedText>
                    <ThemedText style={[styles.headerSubtitle, { color: colorScheme === 'dark' ? '#9CA3AF' : '#64748B' }]}>
                      لم تقم بتوثيق هويتك بيومترياً بعد
                    </ThemedText>
                    <Pressable
                      style={styles.authNowBtn}
                      onPress={() => void lock()}
                    >
                      <ThemedText style={styles.authNowBtnText}>توثيق الهوية بالوجه / الواتساب 🔓</ThemedText>
                    </Pressable>
                  </>
                )}
              </View>

              <View style={[styles.headerIconWrapper, { backgroundColor: colorScheme === 'dark' ? '#1E293B' : '#ECFDF5' }]}>
                {isAuthenticated && user?.avatar ? (
                  <Image source={{ uri: user.avatar }} style={{ width: 52, height: 52, borderRadius: 26 }} />
                ) : isAuthenticated ? (
                  <IconSymbol name="person.crop.circle.fill" size={44} color="#059669" />
                ) : (
                  <IconSymbol name="person.crop.circle.fill" size={44} color="#64748B" />
                )}
              </View>
            </View>
          </LinearGradient>
        </Pressable>

        {/* Menu Items */}
        <View style={styles.menuContainer}>
          {visibleMenuItems.map((item) => {
            const isExpanded = expandedId === item.id;
            return (
              <View key={item.id} style={styles.menuItemWrapper}>
                <Pressable
                  android_ripple={{ color: colorScheme === 'dark' ? '#FFFFFF18' : '#0F172A12' }}
                  onPress={() => handlePress(item)}
                  style={({ pressed }) => [
                    styles.menuItem,
                    menuRowShadow,
                    {
                      backgroundColor: pressed ? menuPressedBg : menuBaseBg,
                      borderColor: colors.border,
                      borderBottomLeftRadius: isExpanded ? 0 : 16,
                      borderBottomRightRadius: isExpanded ? 0 : 16,
                    },
                    pressed && Platform.OS === 'android' ? { elevation: 1 } : null,
                  ]}>
                  <View style={styles.menuItemLeft}>
                    <View style={[styles.iconContainer, { backgroundColor: item.color + '20' }]}>
                      <IconSymbol name={item.icon as any} size={22} color={item.color} />
                    </View>
                    <ThemedText style={styles.menuItemTitle}>{item.title}</ThemedText>
                  </View>
                  
                  {item.type === 'dropdown' ? (
                    <View style={[
                      styles.chevronContainer,
                      { transform: [{ rotate: isExpanded ? '-90deg' : '180deg' }] }
                    ]}>
                      <IconSymbol name="chevron.left" size={20} color={colors.icon} />
                    </View>
                  ) : (
                    <IconSymbol name="chevron.left" size={20} color={colors.icon} />
                  )}
                </Pressable>

                {/* Dropdown Content — القرآن والسنة من API */}
                {item.type === 'dropdown' && isExpanded && item.id === 'quran_sunnah' && (
                  <View
                    style={[
                      styles.subItemsContainer,
                      {
                        backgroundColor: colorScheme === 'dark' ? '#1F293780' : '#F8FAFC',
                        borderColor: colors.border,
                      },
                    ]}>
                    {quranNav.loading ? (
                      <View style={styles.subItemLoading}>
                        <ActivityIndicator size="small" color={QURAN_ACCENT} />
                        <ThemedText style={[styles.subItemTitle, { color: colors.icon }]}>
                          جاري التحميل…
                        </ThemedText>
                      </View>
                    ) : quranNav.error ? (
                      <View style={styles.subItemLoading}>
                        <ThemedText
                          style={[styles.subItemTitle, { color: colors.icon, flex: 1 }]}
                          numberOfLines={2}>
                          {quranNav.error}
                        </ThemedText>
                        <Pressable onPress={() => void quranNav.reload()}>
                          <ThemedText style={{ color: QURAN_ACCENT, fontFamily: 'Tajawal-Bold', fontSize: 13 }}>
                            إعادة المحاولة
                          </ThemedText>
                        </Pressable>
                      </View>
                    ) : quranNav.items.length === 0 ? (
                      <View style={styles.subItemLoading}>
                        <ThemedText style={[styles.subItemTitle, { color: colors.icon }]}>
                          لا توجد عناصر متاحة
                        </ThemedText>
                      </View>
                    ) : (
                      quranNav.items.map((navItem, index) => (
                        <Pressable
                          key={navItem.category_id}
                          onPress={() => navigateQuranNavItem(router, navItem)}
                          android_ripple={{ color: colorScheme === 'dark' ? '#FFFFFF14' : '#0F172A0C' }}
                          style={({ pressed }) => [
                            styles.subItem,
                            index !== quranNav.items.length - 1 && {
                              borderBottomWidth: 1,
                              borderBottomColor: colors.border,
                            },
                            pressed && { backgroundColor: menuPressedBg },
                          ]}>
                          <View style={styles.subItemLeft}>
                            <View style={[styles.subItemIcon, { backgroundColor: QURAN_ACCENT + '18' }]}>
                              <IconSymbol name="book.fill" size={16} color={QURAN_ACCENT} />
                            </View>
                            <ThemedText style={styles.subItemTitle}>{navItem.name}</ThemedText>
                          </View>
                          <IconSymbol name="chevron.left" size={14} color={colors.icon} />
                        </Pressable>
                      ))
                    )}
                  </View>
                )}
              </View>
            );
          })}
        </View>

        {/* System & Settings Section */}
        <ThemedText style={[styles.sectionTitle, { color: colors.icon }]}>الإعدادات والنظام</ThemedText>
        <View style={styles.menuContainer}>
          <View style={styles.menuItemWrapper}>
            <Pressable
              android_ripple={{ color: colorScheme === 'dark' ? '#FFFFFF18' : '#0F172A12' }}
              onPress={() => router.push('/(app)/settings')}
              style={({ pressed }) => [
                styles.menuItem,
                menuRowShadow,
                {
                  backgroundColor: pressed ? menuPressedBg : menuBaseBg,
                  borderColor: colors.border,
                },
                pressed && Platform.OS === 'android' ? { elevation: 1 } : null,
              ]}>
              <View style={styles.menuItemLeft}>
                <View style={[styles.iconContainer, { backgroundColor: '#64748B20' }]}>
                  <IconSymbol name="gearshape.fill" size={22} color="#64748B" />
                </View>
                <ThemedText style={styles.menuItemTitle}>إعدادات التطبيق والتنقل</ThemedText>
              </View>
              <IconSymbol name="chevron.left" size={20} color={colors.icon} />
            </Pressable>
          </View>
        </View>

        {/* Logout Section */}
        {isAuthenticated && (
          <View style={[styles.menuContainer, { marginTop: 14 }]}>
            <View style={styles.menuItemWrapper}>
              <Pressable
                android_ripple={{ color: '#EF444418' }}
                onPress={handleLogout}
                style={({ pressed }) => [
                  styles.menuItem,
                  menuRowShadow,
                  {
                    backgroundColor: pressed
                      ? colorScheme === 'dark'
                        ? '#3F1818'
                        : '#FEE2E2'
                      : colorScheme === 'dark'
                      ? colors.card
                      : '#FFFFFF',
                    borderColor: colorScheme === 'dark' ? '#7F1D1D' : '#FEE2E2',
                  },
                  pressed && Platform.OS === 'android' ? { elevation: 1 } : null,
                ]}>
                <View style={styles.menuItemLeft}>
                  <View style={[styles.iconContainer, { backgroundColor: '#FEE2E2' }]}>
                    <IconSymbol name="arrow.right.square.fill" size={20} color="#EF4444" />
                  </View>
                  <View>
                    <ThemedText style={[styles.menuItemTitle, { color: '#EF4444', fontFamily: 'Tajawal-Bold' }]}>
                      تسجيل الخروج
                    </ThemedText>
                    <ThemedText style={{ color: colors.icon, fontSize: 12, fontFamily: 'Tajawal-Regular', marginTop: 2 }}>
                      إنهاء الجلسة الحالية وإغلاق الحساب
                    </ThemedText>
                  </View>
                </View>
                <IconSymbol name="chevron.left" size={20} color="#EF4444" />
              </Pressable>
            </View>
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
  scrollContent: {
    padding: 20,
    paddingTop: 40,
  },
  header: {
    marginBottom: 30,
  },
  headerCard: {
    borderRadius: 24,
    padding: 24,
    ...Platform.select({
      ios: {
        shadowColor: '#0EA5E9',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.15,
        shadowRadius: 12,
      },
      android: {
        elevation: 4,
      },
    }),
  },
  headerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  headerTextContainer: {
    flex: 1,
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    marginBottom: 8,
  },
  headerSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 20,
  },
  headerIconWrapper: {
    width: 60,
    height: 60,
    borderRadius: 30,
    justifyContent: 'center',
    alignItems: 'center',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
      },
      android: {
        elevation: 3,
      },
    }),
  },
  badgeRow: {
    marginBottom: 6,
  },
  verifiedBadgePill: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  verifiedBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#065F46',
  },
  guestBadgePill: {
    backgroundColor: 'rgba(100, 116, 139, 0.15)',
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  guestBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#475569',
  },
  authNowBtn: {
    marginTop: 8,
    backgroundColor: '#10B981',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 10,
    alignSelf: 'flex-start',
  },
  authNowBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    marginBottom: 16,
    marginTop: 10,
    paddingHorizontal: 4,
  },
  menuContainer: {
    gap: 12,
    marginBottom: 24,
  },
  menuItemWrapper: {
    overflow: 'hidden',
    borderRadius: 16,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    borderWidth: 1,
    borderRadius: 16,
    zIndex: 2,
  },
  menuItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  menuItemTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  chevronContainer: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  subItemsContainer: {
    borderWidth: 1,
    borderTopWidth: 0,
    borderBottomLeftRadius: 16,
    borderBottomRightRadius: 16,
    paddingHorizontal: 16,
  },
  subItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
  },
  subItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  subItemIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#9CA3AF20',
  },
  subItemTitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
  subItemLoading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 16,
    paddingHorizontal: 4,
  },
});
