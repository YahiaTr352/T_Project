import React from 'react';
import { StyleSheet, View, Pressable, TouchableOpacity, Dimensions, Alert, I18nManager } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Image } from 'expo-image';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import type { QuizChoice } from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';

interface VoteOptionSelectorProps {
  choices: QuizChoice[];
  selectedIds: number[];
  lockedIds?: number[];
  isMultiple: boolean;
  maxSelections?: number;
  disabled?: boolean;
  onSelect: (ids: number[]) => void;
  onVideoPress: (url: string, title: string) => void;
  colors: any;
  isDark?: boolean;
}

const ARABIC_COUNT_WORDS: Record<number, string> = {
  2: 'الاثنين',
  3: 'الثلاثة',
  4: 'الأربعة',
  5: 'الخمسة',
  6: 'الستة',
  7: 'السبعة',
  8: 'الثمانية',
  9: 'التسعة',
  10: 'العشرة',
};

function getNominationPrompt(count: number): string {
  const word = ARABIC_COUNT_WORDS[count] || `الـ ${toEnglishDigits(count)}`;
  return `توقع ${word} الأوائل للفوز بالترتيب`;
}

function getNominationCompleted(count: number): string {
  const word = ARABIC_COUNT_WORDS[count] || `الـ ${toEnglishDigits(count)}`;
  return `تم اكتمال توقع ${word} الأوائل للفوز بالترتيب (${toEnglishDigits(count)} من ${toEnglishDigits(count)})`;
}

export default function VoteOptionSelector({
  choices,
  selectedIds,
  lockedIds = [],
  isMultiple,
  maxSelections = 1,
  disabled = false,
  onSelect,
  onVideoPress,
  colors,
  isDark = false,
}: VoteOptionSelectorProps) {
  const router = useRouter();

  const handlePressOption = (id: number) => {
    if (disabled) {
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning); } catch {}
      return;
    }

    if (lockedIds && lockedIds.includes(id)) {
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning); } catch {}
      Alert.alert(
        'اختيار مثبت 🔒',
        'هذا الاختيار مثبت من تصويتك السابق ولا يمكن إلغاؤه. يرجى اختيار التوقعات الإضافية فقط.'
      );
      return;
    }

    try { Haptics.selectionAsync(); } catch {}

    if (isMultiple) {
      if (selectedIds.includes(id)) {
        onSelect(selectedIds.filter((selectedId) => selectedId !== id));
      } else {
        if (maxSelections > 1 && selectedIds.length >= maxSelections) {
          try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning); } catch {}
          Alert.alert(
            'تم اكتمال العدد المطلوب',
            `لقد حددت بالفعل ${toEnglishDigits(maxSelections)} خيارات وهو الحد الإلزامي المطلوب. لإضافة هذا الخيار، يرجى إلغاء تحديد أحد اختياراتك الحالية أولاً.`
          );
          return;
        } else {
          onSelect([...selectedIds, id]);
        }
      }
    } else {
      onSelect([id]);
    }
  };

  const hasAnyMedia = choices.some((c) => Boolean(c.avatar_url || c.person_id || c.image_url));
  const showCounter = isMultiple && maxSelections > 1;
  const remaining = Math.max(0, maxSelections - selectedIds.length);
  const isComplete = selectedIds.length === maxSelections;

  return (
    <View style={styles.container}>
      {showCounter && (
        <View
          style={[
            styles.counterBox,
            {
              backgroundColor: isComplete ? (isDark ? '#064e3b30' : '#dcfce7') : (isDark ? '#78350f25' : '#fef3c7'),
              borderColor: isComplete ? '#10b981' : '#f59e0b',
            },
          ]}
        >
          <IconSymbol
            name={isComplete ? 'checkmark.circle.fill' : 'exclamationmark.circle.fill'}
            size={16}
            color={isComplete ? '#10b981' : '#d97706'}
          />
          <ThemedText
            style={[
              styles.counterText,
              { color: isComplete ? (isDark ? '#34d399' : '#15803d') : (isDark ? '#fbbf24' : '#b45309') },
            ]}
          >
            {isComplete ? (
              getNominationCompleted(maxSelections)
            ) : lockedIds && lockedIds.length > 0 ? (
              `مثبت (${toEnglishDigits(lockedIds.length)}) 🔒 · أكمل توقع ${toEnglishDigits(remaining)} إضافيين للفوز (${toEnglishDigits(selectedIds.length)} من ${toEnglishDigits(maxSelections)})`
            ) : selectedIds.length === 0 ? (
              getNominationPrompt(maxSelections)
            ) : (
              `${getNominationPrompt(maxSelections)} (تم تحديد ${toEnglishDigits(selectedIds.length)} من ${toEnglishDigits(maxSelections)} · متبقي ${toEnglishDigits(remaining)})`
            )}
          </ThemedText>
        </View>
      )}
      <View style={styles.gridContainer}>
      {choices.map((choice) => {
        const isSelected = selectedIds.includes(choice.id);
        const isLocked = Boolean(lockedIds && lockedIds.includes(choice.id));
        const effectivePersonId = choice.person_id ?? (choice as any).personId ?? (choice as any).member_id ?? (choice as any).person?.id;
        const hasTreeAvatar = Boolean(choice.avatar_url || effectivePersonId);

        return (
          <Pressable
            key={choice.id}
            style={[
              styles.choiceCard,
              {
                backgroundColor: isSelected
                  ? isLocked
                    ? (isDark ? '#1e3a8a40' : '#eff6ff')
                    : (isDark ? '#064e3b40' : '#ecfdf5')
                  : (isDark ? '#1e293b' : colors.card),
                borderColor: isSelected
                  ? isLocked
                    ? '#3b82f6'
                    : '#10b981'
                  : (isDark ? '#334155' : colors.border),
                opacity: disabled ? 0.7 : 1,
              },
            ]}
            onPress={() => handlePressOption(choice.id)}
            android_ripple={{ color: 'rgba(16, 185, 129, 0.1)' }}
          >
            {/* Top row: Selection indicator and optional video button */}
            <View style={styles.topRow}>
              <View
                style={[
                  styles.indicator,
                  isMultiple ? styles.indicatorSquare : styles.indicatorCircle,
                  {
                    borderColor: isSelected ? '#10b981' : (isDark ? '#475569' : '#a7f3d0'),
                    backgroundColor: isSelected ? '#10b981' : 'transparent',
                  },
                ]}
              >
                {isSelected && (
                  isMultiple ? (
                    <IconSymbol name="checkmark" size={11} color="#fff" />
                  ) : (
                    <View style={styles.innerDot} />
                  )
                )}
              </View>

              {choice.video_url && (
                <TouchableOpacity
                  style={[styles.videoBtn, { borderColor: isDark ? '#334155' : '#cbd5e1' }]}
                  onPress={(e) => {
                    e.stopPropagation();
                    onVideoPress(choice.video_url!, choice.choice_text);
                  }}
                  hitSlop={6}
                  activeOpacity={0.7}
                >
                  <IconSymbol name="play.fill" size={9} color="#10b981" />
                </TouchableOpacity>
              )}
            </View>

            {/* Candidate Avatar or Choice Image */}
            {hasTreeAvatar ? (
              <TouchableOpacity
                activeOpacity={effectivePersonId ? 0.75 : 1}
                disabled={!effectivePersonId}
                onPress={(e) => {
                  if (effectivePersonId) {
                    e.stopPropagation();
                    router.push(`/(app)/person/${effectivePersonId}` as any);
                  }
                }}
                style={styles.avatarWrapper}
              >
                <PersonAvatar
                  uri={choice.avatar_url || choice.image_url}
                  size={52}
                  mutedColor={colors.icon}
                  borderColor={isSelected ? '#10b981' : (isDark ? '#334155' : '#e2e8f0')}
                  name={choice.choice_text}
                />
              </TouchableOpacity>
            ) : choice.image_url ? (
              <View style={styles.avatarWrapper}>
                <Image source={{ uri: choice.image_url }} style={styles.choiceImage} contentFit="cover" />
              </View>
            ) : null}

            {/* Candidate Name / Choice Text */}
            <View style={[styles.textWrapper, !hasAnyMedia && styles.textWrapperSolo]}>
              <ThemedText
                numberOfLines={2}
                style={[
                  styles.choiceText,
                  { color: isDark ? '#f8fafc' : colors.text },
                  isSelected && { color: isDark ? '#34d399' : '#065f46', fontFamily: 'Tajawal-ExtraBold' },
                ]}
              >
                {choice.choice_text}
              </ThemedText>
            </View>
          </Pressable>
        );
      })}
    </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
  },
  counterBox: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    marginBottom: 12,
    width: '100%',
  },
  counterText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    writingDirection: 'rtl',
    lineHeight: 18,
    flex: 1,
  },
  gridContainer: {
    width: '100%',
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    flexWrap: 'wrap',
    gap: 10,
    justifyContent: 'space-between',
  },
  choiceCard: {
    width: '48.5%',
    borderRadius: 14,
    borderWidth: 1.5,
    padding: 10,
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 90,
  },
  topRow: {
    width: '100%',
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  indicator: {
    width: 20,
    height: 20,
    borderWidth: 2,
    justifyContent: 'center',
    alignItems: 'center',
  },
  indicatorCircle: {
    borderRadius: 10,
  },
  indicatorSquare: {
    borderRadius: 6,
  },
  innerDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#fff',
  },
  videoBtn: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#10b98115',
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarWrapper: {
    marginVertical: 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  choiceImage: {
    width: 52,
    height: 52,
    borderRadius: 26,
  },
  textWrapper: {
    width: '100%',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
    minHeight: 28,
  },
  textWrapperSolo: {
    minHeight: 44,
  },
  choiceText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    textAlign: 'center',
    lineHeight: 17,
  },
});
