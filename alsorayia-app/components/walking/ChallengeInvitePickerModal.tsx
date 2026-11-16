import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import {
  fetchFamilyTree,
  fetchPersonChildren,
  fetchPersonsSearchList,
  WEB_BASE_URL,
} from '@/lib/api/familyTreeApi';
import { searchAndRankPersons } from '@/lib/utils/searchEngine';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import React, {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');

export interface SelectedPerson {
  id: number;
  name: string;
  avatar?: string | null;
}

export interface ChallengeInvitePickerModalProps {
  visible: boolean;
  onClose: () => void;
  selectedMembers: SelectedPerson[];
  onTogglePerson: (person: any) => void;
  onRemovePerson: (personId: number) => void;
  onClearAll?: () => void;
  onConfirm: () => void;
  confirmLabel?: string;
  isSubmitting?: boolean;
  existingMemberIds?: number[];
  title?: string;
}

// ─────────────────────────────────────────────
// CONSTANTS & GEOMETRY FOR FAMILY TREE
// ─────────────────────────────────────────────
const CARD_W = 124;
const CARD_H = 196;
const CARD_GAP = 14;
const COLUMN_STEP = CARD_H + CARD_GAP; // 210px

function getPersonDisplayName(person: any): string {
  if (!person) return 'عضو';
  return (
    person.name ||
    person.full_name ||
    [person.first_name, person.last_name].filter(Boolean).join(' ') ||
    person.first_name ||
    'عضو'
  );
}

function getPersonAvatar(person: any): string | null {
  if (!person) return null;
  const raw = person.avatar || person.photo_url || person.photo || null;
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
// PERSON TREE CARD (مخصص للتحدي وشجرة صلة)
// ─────────────────────────────────────────────
interface PersonTreeCardProps {
  person: any;
  isSelected: boolean;
  isExisting: boolean;
  isExpanded: boolean;
  isLoadingChildren: boolean;
  colors: (typeof Colors)['light'];
  isDark: boolean;
  onToggleSelect: (person: any) => void;
  onToggleExpand: (person: any) => void;
}

const PersonTreeCard = ({
  person,
  isSelected,
  isExisting,
  isExpanded,
  isLoadingChildren,
  colors,
  isDark,
  onToggleSelect,
  onToggleExpand,
}: PersonTreeCardProps) => {
  const [scale] = useState(() => new Animated.Value(1));
  const isDeceased = !!person.death_date;
  const isBreastfeeding = !!person.is_breastfeeding_child;
  const hasChildren = (person.children_count ?? 0) > 0;
  const displayName = getPersonDisplayName(person);
  const avatarUrl = getPersonAvatar(person);

  const onPressIn = () =>
    Animated.spring(scale, { toValue: 0.96, useNativeDriver: true, speed: 60 }).start();
  const onPressOut = () =>
    Animated.spring(scale, { toValue: 1, useNativeDriver: true, speed: 60 }).start();

  // Primary card tap: if has children, toggles expansion; otherwise toggles selection
  const handleCardBodyPress = () => {
    if (isDeceased) return;
    if (isExisting) {
      try {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      } catch { }
      return;
    }
    if (hasChildren) {
      onToggleExpand(person);
    } else {
      onToggleSelect(person);
    }
  };

  const handleSelectPress = () => {
    if (isDeceased || isExisting) return;
    onToggleSelect(person);
  };

  const handleExpandPress = () => {
    if (!hasChildren) return;
    onToggleExpand(person);
  };

  // Dynamic Card Styles
  let cardBorderColor = isDark ? '#374151' : '#E5E7EB';
  let cardBg = isDark ? '#1F2937' : '#FFFFFF';

  if (isExisting) {
    cardBorderColor = isDark ? '#4B5563' : '#D1D5DB';
    cardBg = isDark ? '#11182788' : '#F9FAFB';
  } else if (isSelected) {
    cardBorderColor = silaTheme.primary;
    cardBg = isDark ? '#064E3B33' : '#ECFDF5';
  } else if (isExpanded) {
    cardBorderColor = silaTheme.primaryLight;
  }

  return (
    <Animated.View style={{ transform: [{ scale }] }}>
      <Pressable
        onPressIn={onPressIn}
        onPressOut={onPressOut}
        onPress={handleCardBodyPress}
        style={[
          styles.treeCard,
          {
            backgroundColor: cardBg,
            borderColor: cardBorderColor,
            borderWidth: isSelected ? 2 : 1.5,
          },
        ]}>
        {/* Top Badges Area */}
        <View style={styles.topBadgesRow}>
          {/* Status Badge: Deceased / Breastfeeding / Existing */}
          <View style={{ flex: 1, flexDirection: 'row', gap: 4 }}>
            {isDeceased ? (
              <View style={styles.mourningBadge}>
                <ThemedText style={styles.mourningBadgeText}>
                  {person.gender === 'female' ? 'رحمها الله' : 'رحمه الله'}
                </ThemedText>
              </View>
            ) : isBreastfeeding ? (
              <View style={styles.breastfeedingBadge}>
                <Ionicons name="heart" size={8} color="#FFFFFF" />
                <ThemedText style={styles.breastfeedingBadgeText}>رضاعة</ThemedText>
              </View>
            ) : isExisting ? (
              <View style={styles.existingBadge}>
                <ThemedText style={styles.existingBadgeText}>منضم</ThemedText>
              </View>
            ) : null}
          </View>

          {/* Selection Checkbox Trigger */}
          {!isDeceased && (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={handleSelectPress}
              disabled={isExisting}
              style={[
                styles.selectIndicatorCircle,
                isSelected && {
                  backgroundColor: silaTheme.primary,
                  borderColor: silaTheme.primary,
                },
                isExisting && {
                  backgroundColor: isDark ? '#374151' : '#E5E7EB',
                  borderColor: 'transparent',
                },
              ]}>
              {isSelected ? (
                <Ionicons name="checkmark" size={13} color="#FFFFFF" />
              ) : isExisting ? (
                <Ionicons name="lock-closed" size={10} color={colors.icon} />
              ) : (
                <Ionicons name="add" size={12} color={colors.icon} />
              )}
            </TouchableOpacity>
          )}
        </View>

        {/* Card Body: Photo & Name */}
        <View style={styles.cardVisualArea}>
          {avatarUrl ? (
            <View style={styles.cardPhotoWrapper}>
              <Image source={{ uri: avatarUrl }} style={styles.cardPhoto} contentFit="cover" />
              <LinearGradient
                colors={['transparent', 'rgba(0,0,0,0.85)']}
                style={styles.cardPhotoGradient}
              />
              <ThemedText style={styles.cardPhotoName} numberOfLines={2}>
                {displayName}
              </ThemedText>
            </View>
          ) : (
            <View style={styles.cardNoPhotoWrapper}>
              <View
                style={[
                  styles.cardAvatarCircle,
                  {
                    backgroundColor: isSelected
                      ? silaTheme.primary + '22'
                      : colors.primary + '14',
                  },
                ]}>
                <Ionicons
                  name={person.gender === 'female' ? 'woman' : 'man'}
                  size={28}
                  color={isSelected ? silaTheme.primary : colors.primary}
                />
              </View>
              <ThemedText
                style={[
                  styles.cardNoPhotoName,
                  { color: isSelected ? silaTheme.primary : colors.text },
                ]}
                numberOfLines={2}>
                {displayName}
              </ThemedText>
            </View>
          )}
        </View>

        {/* Card Bottom Controls: Selection & Children Toggle */}
        <View style={[styles.cardBottomControls, { borderTopColor: isDark ? '#374151' : '#E5E7EB' }]}>
          {isDeceased ? (
            <View style={styles.deceasedFooter}>
              <ThemedText style={styles.deceasedFooterText}>رحمه الله</ThemedText>
            </View>
          ) : isExisting ? (
            <View style={styles.existingFooter}>
              <ThemedText style={styles.existingFooterText}>مشترك بالتحدي</ThemedText>
            </View>
          ) : (
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={handleSelectPress}
              style={[
                styles.inviteActionBtn,
                isSelected
                  ? { backgroundColor: silaTheme.primary }
                  : {
                    backgroundColor: isDark ? '#27272A' : '#F3F4F6',
                    borderColor: silaTheme.primary + '66',
                  },
              ]}>
              <ThemedText
                style={[
                  styles.inviteActionBtnText,
                  { color: isSelected ? '#FFFFFF' : silaTheme.primary },
                ]}>
                {isSelected ? '✓ محدد للتحدي' : '+ دعوة للتحدي'}
              </ThemedText>
            </TouchableOpacity>
          )}

          {/* Children Expand Pill */}
          {hasChildren && (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={handleExpandPress}
              style={[
                styles.childrenPillBtn,
                {
                  backgroundColor: isExpanded
                    ? silaTheme.primary + '18'
                    : isDark
                      ? '#1F2937'
                      : '#F9FAFB',
                  borderColor: isExpanded ? silaTheme.primary : colors.border,
                },
              ]}>
              {isLoadingChildren ? (
                <ActivityIndicator size="small" color={silaTheme.primary} style={{ transform: [{ scale: 0.7 }] }} />
              ) : (
                <>
                  <ThemedText
                    style={[
                      styles.childrenPillText,
                      { color: isExpanded ? silaTheme.primary : colors.icon },
                    ]}>
                    الأبناء ({person.children_count})
                  </ThemedText>
                  <Ionicons
                    name={isExpanded ? 'chevron-up' : 'chevron-down'}
                    size={12}
                    color={isExpanded ? silaTheme.primary : colors.icon}
                  />
                </>
              )}
            </TouchableOpacity>
          )}
        </View>
      </Pressable>
    </Animated.View>
  );
};

// ─────────────────────────────────────────────
// RECURSIVE TREE COLUMN (نفس معمارية صلة)
// ─────────────────────────────────────────────
interface TreeColumnProps {
  persons: any[];
  level: number;
  selectedIds: Set<number>;
  existingIds: Set<number>;
  onToggleSelect: (person: any) => void;
  colors: (typeof Colors)['light'];
  isDark: boolean;
}

const TreeColumn = ({
  persons,
  level,
  selectedIds,
  existingIds,
  onToggleSelect,
  colors,
  isDark,
}: TreeColumnProps) => {
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  const [children, setChildren] = useState<Record<number, any[]>>({});
  const [loading, setLoading] = useState<Record<number, boolean>>({});

  const toggleExpand = useCallback(
    async (person: any) => {
      const id = person.id;
      if (!person.children_count) return;

      try {
        Haptics.selectionAsync();
      } catch { }

      // If already open, close it
      if (expanded[id]) {
        setExpanded((prev) => ({ ...prev, [id]: false }));
        return;
      }

      // Open only this branch in current column to maintain vertical generational alignment
      setExpanded({ [id]: true });

      if (!children[id]) {
        setLoading((prev) => ({ ...prev, [id]: true }));
        try {
          const res = await fetchPersonChildren(id);
          if (res?.children) {
            setChildren((prev) => ({ ...prev, [id]: res.children }));
          }
        } catch { }
        setLoading((prev) => ({ ...prev, [id]: false }));
      }
    },
    [expanded, children]
  );

  return (
    <View style={styles.columnRow}>
      {/* Current Column of Persons */}
      <View style={styles.column}>
        {persons.map((person) => (
          <View key={person.id} style={{ height: CARD_H, marginBottom: CARD_GAP }}>
            <PersonTreeCard
              person={person}
              isSelected={selectedIds.has(person.id)}
              isExisting={existingIds.has(person.id)}
              isExpanded={!!expanded[person.id]}
              isLoadingChildren={!!loading[person.id]}
              colors={colors}
              isDark={isDark}
              onToggleSelect={onToggleSelect}
              onToggleExpand={toggleExpand}
            />
          </View>
        ))}
      </View>

      {/* Children Generational Area (branches off to the side) */}
      <View style={styles.childrenArea}>
        {persons.map((person, index) =>
          expanded[person.id] && children[person.id]?.length > 0 ? (
            <View key={`branch-${person.id}`} style={styles.childrenGroup}>
              {/* Vertical alignment offset so children align horizontally with their father */}
              <View style={{ height: index * COLUMN_STEP }} />
              <TreeColumn
                persons={children[person.id]}
                level={level + 1}
                selectedIds={selectedIds}
                existingIds={existingIds}
                onToggleSelect={onToggleSelect}
                colors={colors}
                isDark={isDark}
              />
            </View>
          ) : null
        )}
      </View>
    </View>
  );
};

// ─────────────────────────────────────────────
// MAIN CHALLENGE INVITE PICKER MODAL
// ─────────────────────────────────────────────
export function ChallengeInvitePickerModal({
  visible,
  onClose,
  selectedMembers,
  onTogglePerson,
  onRemovePerson,
  onClearAll,
  onConfirm,
  confirmLabel,
  isSubmitting = false,
  existingMemberIds = [],
  title = 'دعوة أفراد العائلة للتحدي 👥',
}: ChallengeInvitePickerModalProps) {
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const [activeTab, setActiveTab] = useState<'tree' | 'list'>('tree');
  const [searchQuery, setSearchQuery] = useState('');
  const [keyboardVisible, setKeyboardVisible] = useState(false);

  // Tree data state
  const [rootPersons, setRootPersons] = useState<any[]>([]);
  const [loadingTree, setLoadingTree] = useState(false);
  const [treeError, setTreeError] = useState(false);

  // List data state
  const [flatFamilyList, setFlatFamilyList] = useState<any[]>([]);
  const [loadingList, setLoadingList] = useState(false);

  // Sets for O(1) membership lookup
  const selectedIds = useMemo(
    () => new Set(selectedMembers.map((m) => m.id)),
    [selectedMembers]
  );
  const existingIds = useMemo(
    () => new Set(existingMemberIds),
    [existingMemberIds]
  );

  // Keyboard visibility listener
  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSub = Keyboard.addListener(showEvent, () => setKeyboardVisible(true));
    const hideSub = Keyboard.addListener(hideEvent, () => setKeyboardVisible(false));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  // Fetch Tree Root Data
  const loadFamilyTreeData = useCallback(async () => {
    setLoadingTree(true);
    setTreeError(false);
    try {
      const data = await fetchFamilyTree();
      if (data?.tree && Array.isArray(data.tree)) {
        setRootPersons(data.tree);
      } else {
        setTreeError(true);
      }
    } catch {
      setTreeError(true);
    } finally {
      setLoadingTree(false);
    }
  }, []);

  // Fetch Flat Family List for Search Mode
  const loadFlatFamilyData = useCallback(async () => {
    if (flatFamilyList.length > 0) return;
    setLoadingList(true);
    try {
      const res = await fetchPersonsSearchList();
      const list = res?.persons || res?.data || (Array.isArray(res) ? res : []);
      setFlatFamilyList(list);
    } catch { }
    finally {
      setLoadingList(false);
    }
  }, [flatFamilyList.length]);

  // Load appropriate data when modal becomes visible
  useEffect(() => {
    if (visible) {
      if (rootPersons.length === 0) {
        loadFamilyTreeData();
      }
      loadFlatFamilyData();
    }
  }, [visible, rootPersons.length, loadFamilyTreeData, loadFlatFamilyData]);

  // Filtered flat list for Search Mode
  const filteredFlatList = useMemo(() => {
    const available = flatFamilyList.filter((p) => !existingIds.has(p.id));
    if (!searchQuery.trim()) {
      return available.slice(0, 50);
    }
    return searchAndRankPersons(available, searchQuery, 50);
  }, [searchQuery, flatFamilyList, existingIds]);

  const handleTabSwitch = (tab: 'tree' | 'list') => {
    try {
      Haptics.selectionAsync();
    } catch { }
    Keyboard.dismiss();
    setActiveTab(tab);
  };

  const handleTogglePersonWithHaptics = (person: any) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    onTogglePerson(person);
  };

  const handleConfirmPress = () => {
    Keyboard.dismiss();
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch { }
    onConfirm();
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.keyboardContainer}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 10 : 0}>
        <View style={styles.modalOverlay}>
          <View
            style={[
              styles.modalContent,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}>
            {/* Modal Header */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={() => {
                  Keyboard.dismiss();
                  try {
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  } catch { }
                  onClose();
                }}
                style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={colors.icon} />
              </TouchableOpacity>

              <View style={{ alignItems: 'center' }}>
                <ThemedText style={styles.headerTitle}>{title}</ThemedText>
                <ThemedText style={[styles.headerSub, { color: colors.icon }]}>
                  اختر من شجرة العائلة أو ابحث بالاسم مباشرة
                </ThemedText>
              </View>

              <View style={{ width: 36 }} />
            </View>

            {/* Mode Switcher Tabs (شجرة صلة VS القائمة والبحث) */}
            <View
              style={[
                styles.tabSwitcherWrap,
                { backgroundColor: isDark ? '#27272A' : '#F3F4F6' },
              ]}>
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={() => handleTabSwitch('tree')}
                style={[
                  styles.tabSwitcherBtn,
                  activeTab === 'tree' && [
                    styles.tabSwitcherBtnActive,
                    { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabSwitcherText,
                    { color: activeTab === 'tree' ? silaTheme.primary : colors.icon },
                    activeTab === 'tree' && styles.tabSwitcherTextActive,
                  ]}>
                  🌳 شجرة العائلة (صلة)
                </ThemedText>
              </TouchableOpacity>

              <TouchableOpacity
                activeOpacity={0.85}
                onPress={() => handleTabSwitch('list')}
                style={[
                  styles.tabSwitcherBtn,
                  activeTab === 'list' && [
                    styles.tabSwitcherBtnActive,
                    { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabSwitcherText,
                    { color: activeTab === 'list' ? silaTheme.primary : colors.icon },
                    activeTab === 'list' && styles.tabSwitcherTextActive,
                  ]}>
                  📋 البحث والقائمة
                </ThemedText>
              </TouchableOpacity>
            </View>

            {/* Selected Members Chips Strip (Always Accessible) */}
            {selectedMembers.length > 0 && (
              <View style={[styles.selectedStrip, { borderBottomColor: isDark ? '#27272A' : '#F3F4F6' }]}>
                <View style={styles.selectedStripHeader}>
                  <ThemedText style={[styles.selectedCountText, { color: silaTheme.primary }]}>
                    تم تحديد ({selectedMembers.length}) من أفراد العائلة
                  </ThemedText>
                  {onClearAll && (
                    <TouchableOpacity onPress={onClearAll}>
                      <ThemedText style={styles.clearAllText}>مسح الكل</ThemedText>
                    </TouchableOpacity>
                  )}
                </View>

                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.chipsScroll}>
                  {selectedMembers.map((member) => (
                    <View
                      key={member.id}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: isDark ? '#064E3B' : '#ECFDF5',
                          borderColor: silaTheme.primary,
                        },
                      ]}>
                      {member.avatar ? (
                        <Image source={{ uri: member.avatar }} style={styles.chipAvatar} />
                      ) : (
                        <View
                          style={[
                            styles.chipAvatarPlaceholder,
                            { backgroundColor: silaTheme.primary },
                          ]}>
                          <ThemedText style={styles.chipInitialText}>
                            {member.name.slice(0, 1)}
                          </ThemedText>
                        </View>
                      )}
                      <ThemedText
                        style={[
                          styles.chipName,
                          { color: isDark ? '#A7F3D0' : '#065F46' },
                        ]}>
                        {member.name}
                      </ThemedText>
                      <TouchableOpacity
                        onPress={() => onRemovePerson(member.id)}
                        hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                        style={styles.chipRemoveBtn}>
                        <Ionicons
                          name="close-circle"
                          size={16}
                          color={isDark ? '#34D399' : '#059669'}
                        />
                      </TouchableOpacity>
                    </View>
                  ))}
                </ScrollView>
              </View>
            )}

            {/* TAB CONTENT */}
            {activeTab === 'tree' ? (
              // ─────────────────────────────────────────────
              // TAB 1: TREE VIEW (صلة)
              // ─────────────────────────────────────────────
              <View style={styles.tabBodyContainer}>
                {/* Tree Helper Hint Banner */}
                <View
                  style={[
                    styles.treeHintBanner,
                    {
                      backgroundColor: isDark ? '#064E3B22' : '#F0FDF4',
                      borderColor: isDark ? '#065F46' : '#BBF7D0',
                    },
                  ]}>
                  <Ionicons name="git-branch-outline" size={16} color={silaTheme.primary} />
                  <ThemedText
                    style={[
                      styles.treeHintText,
                      { color: isDark ? '#A7F3D0' : '#065F46' },
                    ]}>
                    انقر على كرت الشخص لعرض أبنائه، واضغط «+ دعوة للتحدي» لتحديده للمنافسة 🏃‍♂️
                  </ThemedText>
                </View>

                {loadingTree ? (
                  <View style={styles.centeredState}>
                    <ActivityIndicator size="large" color={silaTheme.primary} />
                    <ThemedText style={[styles.loadingLabel, { color: colors.text }]}>
                      جاري تحميل شجرة العائلة...
                    </ThemedText>
                  </View>
                ) : treeError ? (
                  <View style={styles.centeredState}>
                    <Ionicons name="cloud-offline-outline" size={48} color={colors.icon} />
                    <ThemedText style={[styles.errorTitle, { color: colors.text }]}>
                      تعذر تحميل شجرة العائلة
                    </ThemedText>
                    <ThemedText style={[styles.errorSub, { color: colors.icon }]}>
                      تأكد من اتصالك بالإنترنت ثم حاول مرة أخرى
                    </ThemedText>
                    <TouchableOpacity
                      onPress={loadFamilyTreeData}
                      style={[styles.retryBtn, { backgroundColor: silaTheme.primary }]}>
                      <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
                    </TouchableOpacity>
                  </View>
                ) : rootPersons.length === 0 ? (
                  <View style={styles.centeredState}>
                    <Ionicons name="leaf-outline" size={48} color={colors.icon} />
                    <ThemedText style={{ color: colors.icon, fontFamily: 'Tajawal-Regular', marginTop: 12 }}>
                      لا توجد بيانات لشجرة العائلة حالياً
                    </ThemedText>
                  </View>
                ) : (
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.treeScrollContent}
                    style={{ direction: 'rtl' }}>
                    <ScrollView
                      showsVerticalScrollIndicator={false}
                      contentContainerStyle={{ paddingVertical: 16 }}>
                      <TreeColumn
                        persons={rootPersons}
                        level={0}
                        selectedIds={selectedIds}
                        existingIds={existingIds}
                        onToggleSelect={handleTogglePersonWithHaptics}
                        colors={colors}
                        isDark={isDark}
                      />
                    </ScrollView>
                  </ScrollView>
                )}
              </View>
            ) : (
              // ─────────────────────────────────────────────
              // TAB 2: SEARCH & FLAT LIST VIEW
              // ─────────────────────────────────────────────
              <View style={styles.tabBodyContainer}>
                {/* Search Box */}
                <View
                  style={[
                    styles.searchBox,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                      borderColor: colors.border,
                    },
                  ]}>
                  <Ionicons name="search" size={16} color={colors.icon} />
                  <TextInput
                    value={searchQuery}
                    onChangeText={setSearchQuery}
                    placeholder="ابحث بالاسم... (أو اختر من القائمة مباشرة)"
                    placeholderTextColor={colors.icon}
                    style={[styles.searchInput, { color: colors.text }]}
                  />
                  {loadingList && (
                    <ActivityIndicator size="small" color={silaTheme.primary} />
                  )}
                  {searchQuery.length > 0 && (
                    <TouchableOpacity onPress={() => setSearchQuery('')}>
                      <Ionicons name="close-circle" size={16} color={colors.icon} />
                    </TouchableOpacity>
                  )}
                </View>

                {/* Keyboard Dismiss bar inside picker if active */}
                {keyboardVisible && (
                  <TouchableOpacity
                    activeOpacity={0.8}
                    onPress={() => Keyboard.dismiss()}
                    style={[
                      styles.keyboardDismissBar,
                      { backgroundColor: isDark ? '#27272A' : '#F3F4F6' },
                    ]}>
                    <ThemedText style={[styles.keyboardDismissText, { color: silaTheme.primary }]}>
                      إخفاء لوحة المفاتيح ⌄
                    </ThemedText>
                  </TouchableOpacity>
                )}

                {/* Scrollable Members List */}
                <ScrollView
                  showsVerticalScrollIndicator={false}
                  keyboardShouldPersistTaps="handled"
                  style={styles.listScroll}>
                  {filteredFlatList.length > 0 ? (
                    filteredFlatList.map((person) => {
                      const isSelected = selectedIds.has(person.id);
                      const isExisting = existingIds.has(person.id);
                      const pName = getPersonDisplayName(person);
                      const pAvatar = getPersonAvatar(person);

                      return (
                        <TouchableOpacity
                          key={person.id}
                          activeOpacity={0.7}
                          onPress={() => handleTogglePersonWithHaptics(person)}
                          disabled={isExisting}
                          style={[
                            styles.listRow,
                            isSelected && {
                              backgroundColor: isDark ? '#064E3B44' : '#D1FAE5',
                            },
                            isExisting && { opacity: 0.6 },
                          ]}>
                          <View style={styles.listRowLeft}>
                            {pAvatar ? (
                              <Image source={{ uri: pAvatar }} style={styles.listAvatar} />
                            ) : (
                              <View
                                style={[
                                  styles.listAvatarPlaceholder,
                                  {
                                    backgroundColor: isSelected
                                      ? silaTheme.primary
                                      : '#9CA3AF',
                                  },
                                ]}>
                                <ThemedText style={styles.listInitialText}>
                                  {pName.slice(0, 1)}
                                </ThemedText>
                              </View>
                            )}

                            <View>
                              <ThemedText style={[styles.listName, { color: colors.text }]}>
                                {pName}
                              </ThemedText>
                              <ThemedText style={[styles.listSubName, { color: colors.icon }]}>
                                {isExisting ? 'مشترك بالفعل بالتحدي' : 'عضو في العائلة'}
                              </ThemedText>
                            </View>
                          </View>

                          <View
                            style={[
                              styles.listCheckBadge,
                              {
                                backgroundColor: isSelected
                                  ? silaTheme.primary
                                  : 'transparent',
                                borderColor: isSelected
                                  ? silaTheme.primary
                                  : colors.icon,
                              },
                              isExisting && {
                                backgroundColor: isDark ? '#374151' : '#E5E7EB',
                                borderColor: 'transparent',
                              },
                            ]}>
                            {isSelected ? (
                              <Ionicons name="checkmark" size={13} color="#FFFFFF" />
                            ) : isExisting ? (
                              <Ionicons name="lock-closed" size={10} color={colors.icon} />
                            ) : (
                              <Ionicons name="add" size={12} color={colors.icon} />
                            )}
                          </View>
                        </TouchableOpacity>
                      );
                    })
                  ) : (
                    <View style={styles.emptyListState}>
                      <ThemedText style={[styles.emptyListText, { color: colors.icon }]}>
                        {searchQuery
                          ? 'لم يتم العثور على أفراد بهذا الاسم'
                          : 'جميع الأفراد مضافون بالفعل'}
                      </ThemedText>
                    </View>
                  )}
                </ScrollView>
              </View>
            )}

            {/* Pinned Bottom Footer Action Button */}
            <View
              style={[
                styles.footer,
                {
                  backgroundColor: colors.card,
                  borderTopColor: colors.border,
                  paddingBottom: Math.max(insets.bottom + 8, Platform.OS === 'ios' ? 28 : 18),
                },
              ]}>
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={handleConfirmPress}
                disabled={isSubmitting}
                style={[
                  styles.confirmBtn,
                  {
                    backgroundColor:
                      selectedMembers.length > 0
                        ? silaTheme.primary
                        : isDark
                          ? '#374151'
                          : '#E5E7EB',
                  },
                ]}>
                {isSubmitting ? (
                  <ActivityIndicator color="#FFFFFF" size="small" />
                ) : (
                  <ThemedText
                    style={[
                      styles.confirmBtnText,
                      {
                        color:
                          selectedMembers.length > 0
                            ? '#FFFFFF'
                            : isDark
                              ? '#9CA3AF'
                              : '#6B7280',
                      },
                    ]}>
                    {confirmLabel
                      ? confirmLabel
                      : selectedMembers.length > 0
                        ? `✅ تأكيد واختيار (${selectedMembers.length}) أفراد`
                        : '✅ متابعة بدون إضافة أعضاء'}
                  </ThemedText>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

// ─────────────────────────────────────────────
// STYLES
// ─────────────────────────────────────────────
const styles = StyleSheet.create({
  keyboardContainer: {
    flex: 1,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.55)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    height: '92%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    borderWidth: 1,
    borderBottomWidth: 0,
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 10,
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  headerSub: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    marginTop: 2,
  },

  // Tab Switcher
  tabSwitcherWrap: {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: 4,
    marginBottom: 8,
    borderRadius: 14,
    padding: 3,
  },
  tabSwitcherBtn: {
    flex: 1,
    paddingVertical: 9,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 11,
  },
  tabSwitcherBtnActive: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  tabSwitcherText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  tabSwitcherTextActive: {
    fontFamily: 'Tajawal-Bold',
  },

  // Selected Chips Strip
  selectedStrip: {
    paddingHorizontal: 16,
    paddingBottom: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  selectedStripHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  selectedCountText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  clearAllText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    color: '#EF4444',
  },
  chipsScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 2,
  },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 20,
    borderWidth: 1,
    gap: 6,
  },
  chipAvatar: {
    width: 20,
    height: 20,
    borderRadius: 10,
  },
  chipAvatarPlaceholder: {
    width: 20,
    height: 20,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipInitialText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  chipName: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    maxWidth: 90,
  },
  chipRemoveBtn: {
    marginLeft: 2,
  },

  // Tab Body
  tabBodyContainer: {
    flex: 1,
  },

  // Tree View Styles
  treeHintBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginVertical: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    borderWidth: 1,
  },
  treeHintText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    flex: 1,
    lineHeight: 16,
  },
  treeScrollContent: {
    paddingHorizontal: 16,
    flexGrow: 1,
  },
  columnRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  column: {
    flexDirection: 'column',
  },
  childrenArea: {
    flexDirection: 'column',
  },
  childrenGroup: {
    paddingRight: 16,
    marginRight: 8,
  },

  // Tree Card
  treeCard: {
    width: CARD_W,
    height: CARD_H,
    marginHorizontal: 4,
    borderRadius: 18,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 6,
    elevation: 3,
    justifyContent: 'space-between',
  },
  topBadgesRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 6,
    paddingTop: 6,
    zIndex: 10,
  },
  mourningBadge: {
    backgroundColor: '#4B5563',
    borderRadius: 12,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  mourningBadgeText: {
    color: '#FFFFFF',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  breastfeedingBadge: {
    backgroundColor: '#EC4899',
    borderRadius: 12,
    paddingHorizontal: 5,
    paddingVertical: 2,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  breastfeedingBadgeText: {
    color: '#FFFFFF',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  existingBadge: {
    backgroundColor: '#6B7280',
    borderRadius: 12,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  existingBadgeText: {
    color: '#FFFFFF',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  selectIndicatorCircle: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1.5,
    borderColor: '#9CA3AF',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.7)',
  },

  // Visual Area
  cardVisualArea: {
    flex: 1,
    width: '100%',
    position: 'relative',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardPhotoWrapper: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'flex-end',
  },
  cardPhoto: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    width: '100%',
    height: '100%',
  },
  cardPhotoGradient: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 65,
    zIndex: 1,
  },
  cardPhotoName: {
    position: 'absolute',
    bottom: 4,
    left: 4,
    right: 4,
    zIndex: 2,
    color: '#FFFFFF',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    textShadowColor: 'rgba(0,0,0,0.8)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 3,
  },
  cardNoPhotoWrapper: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  cardAvatarCircle: {
    width: 50,
    height: 50,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  cardNoPhotoName: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    lineHeight: 14,
  },

  // Bottom Controls
  cardBottomControls: {
    borderTopWidth: StyleSheet.hairlineWidth,
    padding: 5,
    gap: 4,
    backgroundColor: 'rgba(0,0,0,0.02)',
  },
  inviteActionBtn: {
    paddingVertical: 5,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  inviteActionBtnText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  childrenPillBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    paddingVertical: 3,
    borderRadius: 6,
    borderWidth: 1,
  },
  childrenPillText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Medium',
  },
  deceasedFooter: {
    paddingVertical: 4,
    alignItems: 'center',
  },
  deceasedFooterText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Regular',
    color: '#6B7280',
  },
  existingFooter: {
    paddingVertical: 4,
    alignItems: 'center',
  },
  existingFooterText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Bold',
    color: '#6B7280',
  },

  // Loading & Error states
  centeredState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    gap: 10,
  },
  loadingLabel: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    marginTop: 8,
  },
  errorTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    marginTop: 6,
  },
  errorSub: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  retryBtn: {
    marginTop: 10,
    paddingHorizontal: 24,
    paddingVertical: 10,
    borderRadius: 20,
  },
  retryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },

  // Search & Flat List
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    marginVertical: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    borderWidth: 1,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    paddingVertical: 2,
  },
  keyboardDismissBar: {
  marginHorizontal: 16,
  marginBottom: 6,
  paddingVertical: 6,
  borderRadius: 8,
  alignItems: 'center',
},
  keyboardDismissText: {
  fontSize: 11,
  fontFamily: 'Tajawal-Medium',
},
  listScroll: {
  flex: 1,
  paddingHorizontal: 16,
},
  listRow: {
  flexDirection: 'row',
  alignItems: 'center',
  justifyContent: 'space-between',
  paddingVertical: 10,
  paddingHorizontal: 10,
  borderRadius: 12,
  marginBottom: 4,
},
  listRowLeft: {
  flexDirection: 'row',
  alignItems: 'center',
  gap: 12,
},
  listAvatar: {
  width: 40,
  height: 40,
  borderRadius: 20,
},
  listAvatarPlaceholder: {
  width: 40,
  height: 40,
  borderRadius: 20,
  alignItems: 'center',
  justifyContent: 'center',
},
  listInitialText: {
  color: '#FFFFFF',
  fontSize: 15,
  fontFamily: 'Tajawal-Bold',
},
  listName: {
  fontSize: 13,
  fontFamily: 'Tajawal-Bold',
},
  listSubName: {
  fontSize: 11,
  fontFamily: 'Tajawal-Regular',
  marginTop: 1,
},
  listCheckBadge: {
  width: 24,
  height: 24,
  borderRadius: 12,
  borderWidth: 1.5,
  alignItems: 'center',
  justifyContent: 'center',
},
  emptyListState: {
  paddingVertical: 40,
  alignItems: 'center',
},
  emptyListText: {
  fontSize: 13,
  fontFamily: 'Tajawal-Regular',
},

  // Pinned Footer
  footer: {
  paddingHorizontal: 16,
  paddingTop: 12,
  borderTopWidth: StyleSheet.hairlineWidth,
},
  confirmBtn: {
  paddingVertical: 14,
  borderRadius: 14,
  alignItems: 'center',
  justifyContent: 'center',
},
  confirmBtnText: {
  fontSize: 14,
  fontFamily: 'Tajawal-Bold',
},
});
