import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useCallback, useMemo } from 'react';
import {
  Platform,
  StyleSheet,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';

interface QuestionChatSectionProps {
  questionId: number;
  questionTitle?: string;
  initialCount?: number;
  answerType?: string;
}

export const QuestionChatSection = React.memo(function QuestionChatSection({
  questionId,
  answerType,
}: QuestionChatSectionProps) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const { width } = useWindowDimensions();
  const isCompact = width < 380;

  // وصف موجز ومباشر (من كلمتين إلى 3 كلمات فقط)
  const chatSubtitle = useMemo(() => {
    if (answerType === 'vote') return 'حوار وتصويت مباشر';
    if (answerType === 'survey') return 'نقاش واستطلاع حي';
    return '  مباشر';
  }, [answerType]);

  // الانتقال المباشر لشاشة الشات المخصصة
  const handleOpenFullChat = useCallback(() => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }

    router.push({
      pathname: '/(app)/quiz-chat/[id]',
      params: {
        id: String(questionId),
        title: 'منتدى الحوار والتحدي',
      },
    });
  }, [router, questionId]);

  return (
    <TouchableOpacity
      activeOpacity={0.88}
      onPress={handleOpenFullChat}
      style={styles.touchableWrapper}
      accessibilityRole="button"
      accessibilityLabel="منتدى الحوار والتحدي">
      <LinearGradient
        colors={
          isDark
            ? ['#142B21', '#0F2119', '#0A1711']
            : ['#F0FDF4', '#E8F8EE', '#DCFCE7']
        }
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={[
          styles.cardContainer,
          {
            borderColor: isDark ? 'rgba(52, 211, 153, 0.35)' : '#86EFAC',
          },
        ]}>

        {/* شريط الإضاءة العلوي الناعم للتمييز الجمالي */}
        <LinearGradient
          colors={
            isDark
              ? ['transparent', 'rgba(52, 211, 153, 0.5)', 'transparent']
              : ['transparent', 'rgba(16, 185, 129, 0.45)', 'transparent']
          }
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={styles.topShimmer}
        />

        <View style={[styles.innerContent, isCompact && styles.innerContentCompact]}>
          {/* الأيقونة المميزة مع تأثير التوهج ومؤشر النشاط */}
          <View style={styles.iconWrapper}>
            <LinearGradient
              colors={
                isDark
                  ? ['#1F4733', '#163526']
                  : ['#DCFCE7', '#BBF7D0']
              }
              style={[styles.iconBadge, isCompact && styles.iconBadgeCompact]}>
              <Ionicons name="chatbubbles" size={isCompact ? 18 : 20} color={silaTheme.primary} />
            </LinearGradient>
            <View
              style={[
                styles.statusDot,
                { borderColor: isDark ? '#142B21' : '#F0FDF4' },
              ]}
            />
          </View>

          {/* نصوص القسم: العنوان الرئيسي مع الوصف القصير المباشر */}
          <View style={[styles.contentWrap, isCompact && styles.contentWrapCompact]}>
            <View style={styles.titleRow}>
              <ThemedText
                style={[
                  styles.titleText,
                  isCompact && styles.titleTextCompact,
                  { color: isDark ? '#F9FAFB' : '#064E3B' },
                ]}
                numberOfLines={2}>
                منتدى الحوار والتحدي
              </ThemedText>

            </View>
          </View>

          {/* زر الانتقال التفاعلي المميز */}
          <View style={styles.ctaButtonWrap}>
            <LinearGradient
              colors={['#059669', '#10B981']}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={[styles.ctaButton, isCompact && styles.ctaButtonCompact]}>
              <ThemedText style={[styles.ctaButtonText, isCompact && styles.ctaButtonTextCompact]}>
                {isCompact ? 'دخول' : 'دخول المنتدى'}
              </ThemedText>
              <Ionicons name="chevron-back" size={13} color="#FFFFFF" />
            </LinearGradient>
          </View>
        </View>
      </LinearGradient>
    </TouchableOpacity>
  );
});

const styles = StyleSheet.create({
  touchableWrapper: {
    marginTop: 14,
    borderRadius: 16,
    ...Platform.select({
      ios: {
        shadowColor: '#059669',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.14,
        shadowRadius: 6,
      },
      android: {
        elevation: 3,
      },
    }),
  },
  cardContainer: {
    borderRadius: 16,
    borderWidth: 1.2,
    overflow: 'hidden',
    position: 'relative',
  },
  topShimmer: {
    position: 'absolute',
    top: 0,
    left: '10%',
    right: '10%',
    height: 1.8,
  },
  innerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  innerContentCompact: {
    paddingVertical: 10,
    paddingHorizontal: 10,
  },
  iconWrapper: {
    position: 'relative',
  },
  iconBadge: {
    width: 42,
    height: 42,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.3)',
  },
  iconBadgeCompact: {
    width: 38,
    height: 38,
    borderRadius: 12,
  },
  statusDot: {
    position: 'absolute',
    top: -2,
    left: -2,
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#10B981',
    borderWidth: 2,
  },
  contentWrap: {
    flex: 1,
    marginHorizontal: 10,
    justifyContent: 'center',
  },
  contentWrapCompact: {
    marginHorizontal: 8,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 2,
  },
  titleText: {
    flexShrink: 1,
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    letterSpacing: -0.2,
  },
  titleTextCompact: {
    fontSize: 13,
  },

  ctaButtonWrap: {
    borderRadius: 20,
    overflow: 'hidden',
  },
  ctaButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    gap: 2,
  },
  ctaButtonCompact: {
    paddingHorizontal: 8,
    paddingVertical: 6,
  },
  ctaButtonText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#FFFFFF',
  },
  ctaButtonTextCompact: {
    fontSize: 10,
  },
});
