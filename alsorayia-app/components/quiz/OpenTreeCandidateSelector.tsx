import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { ThemedText } from '@/components/themed-text';
import { type ThemeColors } from '@/constants/theme';
import { resolveInvitationPhotoUrl } from '@/lib/api/invitations';
import { fetchPersonById } from '@/lib/api/familyTreeApi';
import {
  type CandidateRules,
  type CandidateTreeNode,
} from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import React, { useEffect, useState } from 'react';
import {
  Alert,
  Dimensions,
  I18nManager,
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
  useWindowDimensions,
} from 'react-native';
import { IconSymbol } from '@/components/ui/icon-symbol';
import TreeCandidatePickerModal from './TreeCandidatePickerModal';

interface OpenTreeCandidateSelectorProps {
  questionId?: number | null;
  candidateRules?: CandidateRules | null;
  selectedIds: number[];
  lockedIds?: number[];
  maxSelections: number;
  onSelect: (ids: number[]) => void;
  colors: ThemeColors;
  isDark?: boolean;
}

const ARABIC_ORDINALS = [
  'الأول',
  'الثاني',
  'الثالث',
  'الرابع',
  'الخامس',
  'السادس',
  'السابع',
  'الثامن',
  'التاسع',
  'العاشر',
];

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

export default function OpenTreeCandidateSelector({
  questionId,
  candidateRules,
  selectedIds,
  lockedIds = [],
  maxSelections,
  onSelect,
  colors,
  isDark = false,
}: OpenTreeCandidateSelectorProps) {
  const router = useRouter();
  const { width: screenWidth } = useWindowDimensions();
  const [modalVisible, setModalVisible] = useState(false);
  const [activeSlotIndex, setActiveSlotIndex] = useState<number | null>(null);
  const [slots, setSlots] = useState<(CandidateTreeNode | null)[]>(() =>
    Array(Math.max(1, maxSelections)).fill(null)
  );

  useEffect(() => {
    const targetLen = Math.max(1, maxSelections);
    setSlots((prev) => {
      if (prev.length === targetLen) return prev;
      if (prev.length < targetLen) {
        return [...prev, ...Array(targetLen - prev.length).fill(null)];
      }
      return prev.slice(0, targetLen);
    });
  }, [maxSelections]);

  // When editing or on initial mount with selectedIds, resolve tree nodes for filled slots
  useEffect(() => {
    if (!selectedIds || selectedIds.length === 0) return;

    const missingIds = selectedIds.filter((id) => !slots.some((s) => s?.id === id));
    if (missingIds.length === 0) return;

    let isMounted = true;
    (async () => {
      try {
        const promises = missingIds.map(async (id) => {
          try {
            const data = await fetchPersonById(id);
            if (data?.person) {
              const p = data.person;
              const node: CandidateTreeNode = {
                id: p.id,
                name: p.full_name || p.name || `مرشح #${toEnglishDigits(p.id)}`,
                first_name: p.first_name || p.name || '',
                gender: p.gender || 'male',
                photo_url: p.photo_url || p.photo || p.avatar || null,
                has_whatsapp: Boolean(p.has_whatsapp || p.phone),
                children_count: p.children_count || 0,
                is_deceased: Boolean(p.death_date),
                selectable: true,
              };
              return { id, node };
            }
          } catch {
            // fallback
          }
          return {
            id,
            node: {
              id,
              name: `مرشح #${toEnglishDigits(id)}`,
              first_name: `مرشح #${toEnglishDigits(id)}`,
              gender: 'male' as const,
              photo_url: null,
              has_whatsapp: false,
              children_count: 0,
              is_deceased: false,
              selectable: true,
            },
          };
        });

        const resolved = await Promise.all(promises);
        if (!isMounted) return;

        const nodeMap = new Map<number, CandidateTreeNode>();
        resolved.forEach((item) => nodeMap.set(item.id, item.node));

        setSlots((prev) => {
          const next = [...prev];
          selectedIds.forEach((id, idx) => {
            if (idx < next.length && (!next[idx] || next[idx]?.id !== id) && nodeMap.has(id)) {
              next[idx] = nodeMap.get(id)!;
            }
          });
          return next;
        });
      } catch (err) {
        console.warn('Error resolving candidate slots:', err);
      }
    })();

    return () => {
      isMounted = false;
    };
  }, [selectedIds]);

  const handleOpenSlot = (index: number) => {
    if (lockedIds && index < lockedIds.length && lockedIds[index]) {
      Alert.alert(
        'اختيار مثبت 🔒',
        'هذا الاختيار مثبت من تصويتك السابق ولا يمكن تغييره. يرجى اختيار أو تعديل الخانات المتبقية.'
      );
      return;
    }
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setActiveSlotIndex(index);
    setModalVisible(true);
  };

  const handleCandidatePicked = (person: CandidateTreeNode) => {
    if (activeSlotIndex === null) return;

    // Duplicate check
    const isAlreadyPickedInOtherSlot = slots.some(
      (slotPerson, idx) => idx !== activeSlotIndex && slotPerson?.id === person.id
    );

    if (isAlreadyPickedInOtherSlot) {
      Alert.alert('تنبيه', 'لقد قمت باختيار هذا المتسابق بالفعل في خانة أخرى.');
      return;
    }

    const nextSlots = [...slots];
    nextSlots[activeSlotIndex] = person;
    setSlots(nextSlots);

    const activeIds = nextSlots
      .filter((p): p is CandidateTreeNode => p !== null)
      .map((p) => p.id);

    onSelect(activeIds);
    setModalVisible(false);
    setActiveSlotIndex(null);
  };

  const handleRemoveSlot = (slotIdx: number) => {
    if (lockedIds && slotIdx < lockedIds.length && lockedIds[slotIdx]) {
      return;
    }
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    const nextSlots = [...slots];
    nextSlots[slotIdx] = null;
    setSlots(nextSlots);

    const activeIds = nextSlots
      .filter((p): p is CandidateTreeNode => p !== null)
      .map((p) => p.id);

    onSelect(activeIds);
  };

  const [containerWidth, setContainerWidth] = useState<number>(() => {
    const { width } = Dimensions.get('window');
    return Math.max(260, width - 64);
  });

  const selectedCount = slots.filter((p) => p !== null).length;

  const GAP = 6;
  // 4 columns layout: For 10 selections, renders exactly 4, 4, 2!
  const numColumns =
    maxSelections === 1 ? 1 : maxSelections === 2 ? 2 : maxSelections === 3 ? 3 : 4;

  // Exact responsive width strictly bound by measured container (guarantees zero overflow outside parent card)
  const cardWidth =
    numColumns === 1
      ? '100%'
      : Math.floor((containerWidth - GAP * (numColumns - 1)) / numColumns);

  // Chunk candidate slots into explicit rows of numColumns (Row 1: 4, Row 2: 4, Row 3: 2)
  const slotRows: number[][] = [];
  for (let i = 0; i < maxSelections; i += numColumns) {
    const row: number[] = [];
    for (let j = i; j < Math.min(i + numColumns, maxSelections); j++) {
      row.push(j);
    }
    slotRows.push(row);
  }

  const isComplete = selectedCount === maxSelections;
  const remaining = Math.max(0, maxSelections - selectedCount);

  return (
    <View style={styles.container}>
      {/* ── Modern Flat Segmented Progress / Counter Header (No Outer Frame) ── */}
      <View style={styles.progressCard}>
        <View style={styles.progressHeaderRow}>
          <View style={styles.progressTitleGroup}>
            <View
              style={[
                styles.progressIconWrap,
                {
                  backgroundColor: isComplete
                    ? (isDark ? '#064e3b' : '#dcfce7')
                    : (lockedIds && lockedIds.length > 0 ? (isDark ? '#1e3a8a' : '#dbeafe') : (isDark ? '#334155' : '#f1f5f9')),
                },
              ]}
            >
              <IconSymbol
                name={isComplete ? 'checkmark.circle.fill' : (lockedIds && lockedIds.length > 0 ? 'lock.fill' : 'person.2.fill')}
                size={16}
                color={isComplete ? '#10b981' : (lockedIds && lockedIds.length > 0 ? '#2563eb' : colors.primary)}
              />
            </View>
            <View>
              <ThemedText style={[styles.progressTitle, { color: isDark ? '#f8fafc' : '#0f172a' }]}>
                {maxSelections > 1 ? `توقع ${toEnglishDigits(maxSelections)} مرشحين للفوز` : 'توقع الفائز'}
              </ThemedText>
              <ThemedText style={[styles.progressSubtitle, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                {isComplete
                  ? 'اكتملت جميع التوقعات المطلوبة بنجاح'
                  : lockedIds && lockedIds.length > 0
                    ? `تم تثبيت ${toEnglishDigits(lockedIds.length)} 🔒 · متبقي ${toEnglishDigits(remaining)}`
                    : `تم اختيار ${toEnglishDigits(selectedCount)} من ${toEnglishDigits(maxSelections)} (فاضل ${toEnglishDigits(remaining)})`}
              </ThemedText>
            </View>
          </View>

          <View
            style={[
              styles.countPill,
              {
                backgroundColor: isComplete
                  ? (isDark ? '#064e3b' : '#dcfce7')
                  : (isDark ? '#334155' : '#f1f5f9'),
              },
            ]}
          >
            <ThemedText
              style={[
                styles.countPillText,
                { color: isComplete ? '#10b981' : (isDark ? '#cbd5e1' : '#334155') },
              ]}
            >
              {toEnglishDigits(selectedCount)}/{toEnglishDigits(maxSelections)}
            </ThemedText>
          </View>
        </View>

        {/* Segmented visual progress bar */}
        {maxSelections > 1 && (
          <View style={styles.segmentBarContainer}>
            {Array.from({ length: maxSelections }).map((_, segIdx) => {
              const isSlotFilled = Boolean(slots[segIdx]);
              const isLockedSlot = Boolean(lockedIds && segIdx < lockedIds.length && lockedIds[segIdx]);

              return (
                <View
                  key={`seg-${segIdx}`}
                  style={[
                    styles.segmentBarItem,
                    {
                      backgroundColor: isLockedSlot
                        ? '#3b82f6'
                        : isSlotFilled
                          ? '#10b981'
                          : (isDark ? '#334155' : '#e2e8f0'),
                    },
                  ]}
                />
              );
            })}
          </View>
        )}
      </View>

      {/* ── Candidate Slots Responsive 4-Column Grid (4, 4, 2) ── */}
      <View
        style={styles.gridContainer}
        onLayout={(e) => {
          const w = e.nativeEvent.layout.width;
          if (w > 0 && Math.abs(w - containerWidth) > 1) {
            setContainerWidth(w);
          }
        }}
      >
        {slotRows.map((row, rowIdx) => (
          <View key={`grid-row-${rowIdx}`} style={styles.gridRow}>
            {row.map((index) => {
              const person = slots[index];
              const isSlotLocked = Boolean(
                lockedIds && index < lockedIds.length && lockedIds[index]
              );
              const slotOrdinalText = ARABIC_ORDINALS[index] || toEnglishDigits(index + 1);

              if (person) {
                // Filled Slot Card (4-Column Layout)
                return (
                  <View
                    key={`slot-${index}-${person.id}`}
                    style={[
                      styles.slotCardFilled,
                      {
                        width: cardWidth,
                        maxWidth: cardWidth,
                        backgroundColor: isSlotLocked
                          ? (isDark ? '#1e293b' : '#f8faff')
                          : (isDark ? '#1e293b' : '#ffffff'),
                        borderColor: isSlotLocked
                          ? (isDark ? '#3b82f6' : '#93c5fd')
                          : (isDark ? '#059669' : '#10b981'),
                      },
                    ]}
                  >
                    {/* Header Row: Slot Number Badge + Lock / Trash Button */}
                    <View style={styles.filledTopRow}>
                      <View
                        style={[
                          styles.slotNumberBadge,
                          {
                            backgroundColor: isSlotLocked
                              ? (isDark ? '#1e3a8a' : '#dbeafe')
                              : (isDark ? '#064e3b' : '#d1fae5'),
                          },
                        ]}
                      >
                        <ThemedText
                          numberOfLines={1}
                          style={[
                            styles.slotNumberBadgeText,
                            {
                              color: isSlotLocked
                                ? (isDark ? '#93c5fd' : '#1d4ed8')
                                : (isDark ? '#34d399' : '#065f46'),
                            },
                          ]}
                        >
                          {numColumns >= 4 ? toEnglishDigits(index + 1) : `${toEnglishDigits(index + 1)}. ${slotOrdinalText}`}
                        </ThemedText>
                      </View>

                      {isSlotLocked ? (
                        <View
                          style={[
                            styles.compactLockIconWrap,
                            {
                              backgroundColor: isDark ? '#1e3a8a80' : '#eff6ff',
                              borderColor: isDark ? '#3b82f6' : '#bfdbfe',
                            },
                          ]}
                        >
                          <Ionicons
                            name="lock-closed"
                            size={9}
                            color={isDark ? '#93c5fd' : '#2563eb'}
                          />
                        </View>
                      ) : (
                        <TouchableOpacity
                          activeOpacity={0.7}
                          onPress={() => handleRemoveSlot(index)}
                          style={[
                            styles.compactRemoveBtn,
                            { backgroundColor: isDark ? '#450a0a80' : '#fef2f2' },
                          ]}
                          hitSlop={6}
                        >
                          <Ionicons name="trash-outline" size={10} color="#ef4444" />
                        </TouchableOpacity>
                      )}
                    </View>

                    {/* Candidate Content (Avatar + Name) */}
                    <TouchableOpacity
                      activeOpacity={isSlotLocked ? 0.95 : 0.75}
                      onPress={() => handleOpenSlot(index)}
                      style={styles.filledCenterContent}
                    >
                      <TouchableOpacity
                        onPress={(e) => {
                          e.stopPropagation();
                          router.push(`/(app)/person/${person.id}` as any);
                        }}
                        activeOpacity={0.75}
                        hitSlop={5}
                        style={styles.avatarWrap}
                      >
                        <PersonAvatar
                          uri={resolveInvitationPhotoUrl(person.photo_url)}
                          size={numColumns >= 4 ? 32 : 36}
                          mutedColor={colors.primary}
                          name={person.name}
                        />
                      </TouchableOpacity>

                      <ThemedText
                        style={[
                          styles.personName,
                          { color: isDark ? '#f8fafc' : '#0f172a' },
                        ]}
                        numberOfLines={2}
                      >
                        {person.name || person.first_name}
                      </ThemedText>

                      {!isSlotLocked ? (
                        <View
                          style={[
                            styles.changePill,
                            { backgroundColor: isDark ? '#334155' : '#f1f5f9' },
                          ]}
                        >
                          <Ionicons
                            name="swap-horizontal"
                            size={7.5}
                            color={isDark ? '#cbd5e1' : '#64748b'}
                          />
                          <ThemedText
                            style={[
                              styles.changePillText,
                              { color: isDark ? '#cbd5e1' : '#64748b' },
                            ]}
                          >
                            تغيير
                          </ThemedText>
                        </View>
                      ) : (
                        <View style={styles.lockedPillPlaceholder} />
                      )}
                    </TouchableOpacity>
                  </View>
                );
              }

              // Empty Slot Card (4-Column Layout)
              return (
                <TouchableOpacity
                  key={`empty-slot-${index}`}
                  activeOpacity={0.8}
                  onPress={() => handleOpenSlot(index)}
                  style={[
                    styles.slotCardEmpty,
                    {
                      width: cardWidth,
                      maxWidth: cardWidth,
                      backgroundColor: isDark ? '#1e293b50' : '#f8fafc',
                      borderColor: isDark ? '#059669' : '#a7f3d0',
                    },
                  ]}
                >
                  <View style={styles.emptyTopRow}>
                    <View
                      style={[
                        styles.slotNumberBadge,
                        { backgroundColor: isDark ? '#064e3b40' : '#ecfdf5' },
                      ]}
                    >
                      <ThemedText
                        numberOfLines={1}
                        style={[
                          styles.slotNumberBadgeText,
                          { color: isDark ? '#34d399' : '#047857' },
                        ]}
                      >
                        {numColumns >= 4 ? toEnglishDigits(index + 1) : `${toEnglishDigits(index + 1)}. ${slotOrdinalText}`}
                      </ThemedText>
                    </View>
                  </View>

                  <View
                    style={[
                      styles.emptyIconCircle,
                      { backgroundColor: isDark ? '#064e3b50' : '#dcfce7' },
                    ]}
                  >
                    <Ionicons name="add" size={14} color="#10b981" />
                  </View>

                  <ThemedText
                    style={[
                      styles.emptyTitle,
                      { color: isDark ? '#34d399' : '#059669' },
                    ]}
                    numberOfLines={1}
                  >
                    اختيار
                  </ThemedText>
                </TouchableOpacity>
              );
            })}
          </View>
        ))}
      </View>

      {/* Candidate Tree Picker Modal */}
      <TreeCandidatePickerModal
        visible={modalVisible}
        onClose={() => {
          setModalVisible(false);
          setActiveSlotIndex(null);
        }}
        onSelect={handleCandidatePicked}
        questionId={questionId}
        candidateRules={candidateRules}
        selectedIds={selectedIds}
        colors={colors}
        isDark={isDark}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 10,
    marginVertical: 4,
    width: '100%',
  },
  progressCard: {
    paddingVertical: 6,
    paddingHorizontal: 2,
    marginBottom: 4,
    width: '100%',
  },
  progressHeaderRow: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  progressTitleGroup: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  progressIconWrap: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  progressTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    textAlign: 'right',
  },
  progressSubtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
    marginTop: 1,
    textAlign: 'right',
  },
  countPill: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countPillText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  segmentBarContainer: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    gap: 4,
    marginTop: 10,
    width: '100%',
  },
  segmentBarItem: {
    flex: 1,
    height: 5,
    borderRadius: 3,
  },
  gridContainer: {
    gap: 6,
    width: '100%',
  },
  gridRow: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    gap: 6,
    width: '100%',
    alignItems: 'stretch',
    justifyContent: 'flex-start',
  },
  slotCardEmpty: {
    borderRadius: 11,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    paddingVertical: 7,
    paddingHorizontal: 3,
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 100,
    overflow: 'hidden',
  },
  emptyTopRow: {
    width: '100%',
    alignItems: 'flex-start',
  },
  slotNumberBadge: {
    paddingHorizontal: 4,
    paddingVertical: 1.5,
    borderRadius: 4,
    alignSelf: 'flex-start',
    flexShrink: 1,
    maxWidth: '85%',
  },
  slotNumberBadgeText: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Bold',
  },
  emptyIconCircle: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 1,
  },
  emptyTitle: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  slotCardFilled: {
    borderRadius: 11,
    borderWidth: 1.5,
    paddingVertical: 6,
    paddingHorizontal: 3,
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 100,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.04,
        shadowRadius: 2,
      },
      android: {
        elevation: 1,
      },
    }),
  },
  filledTopRow: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
    marginBottom: 2,
  },
  compactLockIconWrap: {
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  compactRemoveBtn: {
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filledCenterContent: {
    alignItems: 'center',
    justifyContent: 'center',
    width: '100%',
    gap: 1.5,
  },
  avatarWrap: {
    marginBottom: 1,
  },
  personName: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    lineHeight: 11.5,
    width: '100%',
  },
  changePill: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 1.5,
    paddingHorizontal: 4,
    paddingVertical: 1,
    borderRadius: 4,
    marginTop: 1.5,
  },
  changePillText: {
    fontSize: 7.5,
    fontFamily: 'Tajawal-Bold',
  },
  lockedPillPlaceholder: {
    height: 3,
  },
});
