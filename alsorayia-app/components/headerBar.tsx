import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import type { AuthPerson } from '@/lib/api/authApi';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import React, { useMemo } from 'react';
import {
  ImageSourcePropType,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

export interface HeaderBarProps {
  /** عنوان الهيدر (اختياري إذا تم تمرير user) */
  title?: string;
  /** عنوان فرعي مخصص (اختياري) */
  subtitle?: string;
  /** كائن المستخدم الموثق للتخصيص الذكي والتحية */
  user?: AuthPerson | null;
  /** تفعيل التحية التلقائية بالوقت (الافتراضي: مفعل تلقائياً عند وجود user) */
  showGreeting?: boolean;
  /** إظهار اللوجو/الأفاتار */
  showAvatar?: boolean;
  /** مصدر صورة الأفاتار / الشعار المحلي */
  avatarSource?: ImageSourcePropType;
  /** رابط صورة الأفاتار عبر الإنترنت */
  avatarUri?: string | null;
  /** عنصر مخصص يُعرض كأيقونة أو محتوى بديل للأفاتار (مثل روبوت الذكاء الاصطناعي) */
  avatarCustomElement?: React.ReactNode;
  /** عند الضغط على الأفاتار أو قسم الهوية */
  onAvatarPress?: () => void;
  /** إظهار زر تسجيل الدخول السريع (للضيوف) */
  showQuickAuth?: boolean;
  /** عند الضغط على زر تسجيل الدخول السريع */
  onQuickAuthPress?: () => void;
  /** إظهار زر الرجوع */
  showBack?: boolean;
  /** عند الضغط على زر الرجوع */
  onBackPress?: () => void;
  /** إظهار زر البحث */
  showSearch?: boolean;
  /** عند الضغط على زر البحث */
  onSearchPress?: () => void;
  /** إظهار زر الإشعارات */
  showNotifications?: boolean;
  /** عند الضغط على زر الإشعارات */
  onNotificationsPress?: () => void;
  /** عدد الإشعارات غير المقروءة (يظهر كبادج) */
  notificationCount?: number;
  /** عناصر مخصصة تُعرض في الجانب الأيسر (endSection) من الهيدر */
  children?: React.ReactNode;
  /** تخصيص ستايل الحاوية */
  style?: object;
}

/** تحديد التحية والرمز التعبيري بناءً على التوقيت المحلي */
function getTimeBasedGreeting(): { text: string; icon: string } {
  const hour = new Date().getHours();
  if (hour >= 4 && hour < 12) {
    return { text: 'صباح الخير', icon: '☀️' };
  } else if (hour >= 12 && hour < 17) {
    return { text: 'مساء الخير', icon: '🌤️' };
  } else {
    return { text: 'مساء الخير', icon: '🌙' };
  }
}

/** استخراج الحروف الأولى كـ Fallback عند عدم وجود صورة */
function getInitials(name?: string | null): string {
  if (!name) return 'س';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].substring(0, 2);
  return (parts[0][0] + (parts[1]?.[0] || ''));
}

const HeaderBar = ({
  title,
  subtitle,
  user,
  showGreeting = true,
  showAvatar = false,
  avatarSource,
  avatarUri,
  avatarCustomElement,
  onAvatarPress,
  showQuickAuth = false,
  onQuickAuthPress,
  showBack = false,
  onBackPress,
  showSearch = false,
  onSearchPress,
  showNotifications = false,
  onNotificationsPress,
  notificationCount = 0,
  children,
  style,
}: HeaderBarProps) => {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const greeting = useMemo(() => getTimeBasedGreeting(), []);

  // تحديد اسم العرض والتحية
  const displayName = useMemo(() => {
    if (user) {
      return user.first_name || user.full_name || 'عضو العائلة';
    }
    return title || 'عائلة السريّع';
  }, [user, title]);

  const displaySubtitle = useMemo(() => {
    if (subtitle) return subtitle;
    if (user && showGreeting) {
      return `${greeting.icon} ${greeting.text}`;
    }
    return undefined;
  }, [subtitle, user, showGreeting, greeting]);

  // تحديد رابط الأفاتار
  const finalAvatarUri = avatarUri || user?.avatar;

  const handleAvatarPress = () => {
    if (onAvatarPress) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });
      onAvatarPress();
    }
  };

  const handleNotificationsPress = () => {
    if (onNotificationsPress) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });
      onNotificationsPress();
    }
  };

  const handleSearchPress = () => {
    if (onSearchPress) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });
      onSearchPress();
    }
  };

  const handleBackPress = () => {
    if (onBackPress) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => { });
      onBackPress();
    }
  };

  const handleQuickAuthPress = () => {
    if (onQuickAuthPress) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => { });
      onQuickAuthPress();
    }
  };

  return (
    <View style={[styles.container, style]}>
      {/* جهة البداية: الأفاتار + التحية والاسم */}
      <View style={styles.startSection}>
        {showBack ? (
          <TouchableOpacity
            onPress={handleBackPress}
            style={[styles.iconButton, { backgroundColor: colors.primary + '18' }]}
            activeOpacity={0.7}
            accessibilityLabel="رجوع"
          >
            <IconSymbol name="chevron.right" size={22} color={colors.primary} />
          </TouchableOpacity>
        ) : null}

        {(showAvatar || user || avatarSource || finalAvatarUri || avatarCustomElement) ? (
          <TouchableOpacity
            onPress={handleAvatarPress}
            disabled={!onAvatarPress}
            activeOpacity={onAvatarPress ? 0.7 : 1}
            style={styles.avatarTouchable}
          >
            <View
              style={[
                styles.avatarContainer,
                {
                  backgroundColor: colors.primary + '18',
                  borderColor: user ? '#10B981' : colors.primary + '40',
                },
              ]}
            >
              {finalAvatarUri ? (
                <Image
                  source={{ uri: finalAvatarUri }}
                  style={styles.avatar}
                  contentFit="cover"
                  transition={250}
                />
              ) : avatarSource ? (
                <Image
                  source={avatarSource}
                  style={styles.avatar}
                  contentFit="cover"
                />
              ) : avatarCustomElement ? (
                avatarCustomElement
              ) : (
                <View style={[styles.initialsAvatar, { backgroundColor: colors.primary }]}>
                  <ThemedText style={styles.initialsText}>
                    {getInitials(displayName)}
                  </ThemedText>
                </View>
              )}

              {/* شارة التحقق الحية للأعضاء الموثقين */}
              {user ? (
                <View style={styles.verifiedDot}>
                  <IconSymbol name="checkmark" size={8} color="#FFFFFF" />
                </View>
              ) : null}
            </View>
          </TouchableOpacity>
        ) : null}

        <TouchableOpacity
          onPress={handleAvatarPress}
          disabled={!onAvatarPress}
          activeOpacity={onAvatarPress ? 0.7 : 1}
          style={styles.titleBlock}
        >
          {displaySubtitle ? (
            <ThemedText
              style={[styles.subtitle, { color: user ? colors.primary : colors.icon }]}
              numberOfLines={1}
              ellipsizeMode="tail"
            >
              {displaySubtitle}
            </ThemedText>
          ) : null}
          <ThemedText
            style={[styles.title, user ? styles.userTitle : null]}
            numberOfLines={1}
            ellipsizeMode="tail"
            adjustsFontSizeToFit
            minimumFontScale={0.8}
          >
            {displayName}
          </ThemedText>
        </TouchableOpacity>
      </View>

      {/* جهة النهاية: زر تسجيل الدخول للزوار، البحث، الإشعارات */}
      <View style={styles.endSection}>
        {children}

        {showQuickAuth ? (
          <TouchableOpacity
            onPress={handleQuickAuthPress}
            style={[
              styles.quickAuthButton,
              {
                backgroundColor: isDark ? 'rgba(16, 185, 129, 0.15)' : '#ECFDF5',
                borderColor: '#10B981',
              },
            ]}
            activeOpacity={0.75}
          >
            <IconSymbol name="sparkles" size={14} color="#10B981" />
            <ThemedText style={styles.quickAuthText}>توثيق</ThemedText>
          </TouchableOpacity>
        ) : null}

        {showSearch ? (
          <TouchableOpacity
            onPress={handleSearchPress}
            style={[styles.iconButton, { backgroundColor: colors.primary + '18' }]}
            activeOpacity={0.7}
            accessibilityLabel="بحث"
          >
            <IconSymbol name="magnifyingglass" size={20} color={colors.primary} />
          </TouchableOpacity>
        ) : null}

        {showNotifications ? (
          <TouchableOpacity
            onPress={handleNotificationsPress}
            style={[styles.iconButton, { backgroundColor: colors.primary + '18' }]}
            activeOpacity={0.7}
            accessibilityLabel="الإشعارات"
          >
            <IconSymbol name="bell.fill" size={20} color={colors.primary} />
            {notificationCount > 0 ? (
              <View style={styles.badge}>
                <ThemedText style={styles.badgeText}>
                  {notificationCount > 99 ? '99+' : notificationCount}
                </ThemedText>
              </View>
            ) : null}
          </TouchableOpacity>
        ) : null}
      </View>
    </View>
  );
};

export default HeaderBar;

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 8,
    minHeight: 56,
  },
  startSection: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginRight: 8,
    minWidth: 0,
  },
  titleBlock: {
    flex: 1,
    justifyContent: 'center',
    minWidth: 0,
  },
  endSection: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexShrink: 0,
  },
  iconButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    justifyContent: 'center',
    alignItems: 'center',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    // كان 22 (1.29x) يقصّ نقطة الثاء الثالثة على iOS — Tajawal-Bold تتجاوز الـ ascent
    lineHeight: 27,
  },
  userTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    lineHeight: 26,
  },
  subtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    lineHeight: 18,
    marginBottom: 2,
  },
  avatarTouchable: {
    position: 'relative',
    flexShrink: 0,
  },
  avatarContainer: {
    width: 42,
    height: 42,
    borderRadius: 21,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    position: 'relative',
  },
  avatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
  },
  initialsAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    justifyContent: 'center',
    alignItems: 'center',
  },
  initialsText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    lineHeight: 21,
    color: '#FFFFFF',
  },
  verifiedDot: {
    position: 'absolute',
    bottom: -1,
    right: -1,
    width: 14,
    height: 14,
    borderRadius: 7,
    backgroundColor: '#10B981',
    borderWidth: 2,
    borderColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
  },
  quickAuthButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: 16,
    borderWidth: 1,
  },
  quickAuthText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#10B981',
  },
  badge: {
    position: 'absolute',
    top: -2,
    right: -2,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: '#EF4444',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 3,
    borderWidth: 1.5,
    borderColor: '#FFFFFF',
  },
  badgeText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Bold',
    color: '#FFFFFF',
  },
});

