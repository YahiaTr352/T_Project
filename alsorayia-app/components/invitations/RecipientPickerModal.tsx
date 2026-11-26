import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme, type ThemeColors } from '@/constants/theme';
import {
  fetchInvitationTreePicker,
  resolveInvitationPhotoUrl,
  searchInvitationPersons,
  type InvitationPersonBrief,
  type InvitationSearchPerson,
  type InvitationTreeNode,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  FlatList,
  Keyboard,
  KeyboardAvoidingView,
  LayoutAnimation,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const SEARCH_DEBOUNCE_MS = 350;
const WHATSAPP_GREEN = '#25D366';

// ─────────────────────────────────────────────
// CARD GEOMETRY FOR FAMILY TREE CANVAS
// ─────────────────────────────────────────────
const CARD_W = 124;
const CARD_H = 196;
const CARD_GAP = 14;
const COLUMN_STEP = CARD_H + CARD_GAP; // 210px

export interface RecipientPickerModalProps {
  visible: boolean;
  onClose: () => void;
  onConfirm: (persons: InvitationPersonBrief[]) => void;
  initialSelected?: InvitationPersonBrief[];
  excludedPersonId?: number;
  title?: string;
  colors: ThemeColors;
  isDark: boolean;
}

function toBrief(person: {
  id: number;
  first_name: string;
  photo_url?: string | null;
}): InvitationPersonBrief {
  return {
    id: person.id,
    first_name: person.first_name,
    photo_url: resolveInvitationPhotoUrl(person.photo_url),
  };
}

function excludeFromTree(nodes: InvitationTreeNode[], personId: number): InvitationTreeNode[] {
  return nodes.flatMap((node) => {
    const children = excludeFromTree(node.children, personId);
    if (node.id === personId) return children;

    return [{
      ...node,
      children,
      expandable: children.length > 0,
      whatsapp_descendant_count: children.reduce((count, child) => count + Number(child.has_whatsapp) + child.whatsapp_descendant_count, 0),
    }];
  });
}

// ─────────────────────────────────────────────
// PERSON TREE CARD (تصميم كرت شجرة صلة - نظام الدعوات)
// ─────────────────────────────────────────────
interface PersonTreeCardProps {
  node: InvitationTreeNode;
  isSelected: boolean;
  isExpanded: boolean;
  colors: ThemeColors;
  isDark: boolean;
  onToggleSelect: (person: InvitationPersonBrief, selectable: boolean) => void;
  onToggleExpand: (id: number) => void;
}

function PersonTreeCard({
  node,
  isSelected,
  isExpanded,
  colors,
  isDark,
  onToggleSelect,
  onToggleExpand,
}: PersonTreeCardProps) {
  const [scale] = useState(() => new Animated.Value(1));
  const isDeceased = !!node.is_deceased;
  const selectable = node.selectable;
  const hasWhatsapp = node.has_whatsapp;
  const descendantCount = node.whatsapp_descendant_count;
  const hasChildren = node.expandable || descendantCount > 0 || (node.children && node.children.length > 0);
  const photoUri = resolveInvitationPhotoUrl(node.photo_url);

  const onPressIn = () =>
    Animated.spring(scale, { toValue: 0.96, useNativeDriver: true, speed: 60 }).start();
  const onPressOut = () =>
    Animated.spring(scale, { toValue: 1, useNativeDriver: true, speed: 60 }).start();

  const handleCardBodyPress = () => {
    if (isDeceased) return;
    if (!selectable) {
      if (hasChildren) {
        onToggleExpand(node.id);
      }
      return;
    }
    if (hasChildren) {
      onToggleExpand(node.id);
    } else {
      onToggleSelect({ id: node.id, first_name: node.first_name, photo_url: node.photo_url }, selectable);
    }
  };

  const handleSelectPress = () => {
    if (isDeceased || !selectable) return;
    onToggleSelect({ id: node.id, first_name: node.first_name, photo_url: node.photo_url }, selectable);
  };

  const handleExpandPress = () => {
    if (!hasChildren) return;
    onToggleExpand(node.id);
  };

  // Dynamic Card Border & Background
  let cardBorderColor = isDark ? '#334155' : '#E2E8F0';
  let cardBg = isDark ? '#1E293B' : '#FFFFFF';

  if (!selectable && !hasWhatsapp) {
    cardBorderColor = isDark ? '#475569' : '#CBD5E1';
    cardBg = isDark ? '#0F172A99' : '#F8FAFC';
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
          <View style={{ flex: 1, flexDirection: 'row', gap: 4, flexWrap: 'wrap' }}>
            {isDeceased ? (
              <View style={styles.mourningBadge}>
                <ThemedText style={styles.mourningBadgeText}>
                  {node.gender === 'female' ? 'رحمها الله' : 'رحمه الله'}
                </ThemedText>
              </View>
            ) : hasWhatsapp ? (
              <View style={[styles.miniBadge, { backgroundColor: WHATSAPP_GREEN + '22' }]}>
                <Ionicons name="logo-whatsapp" size={9} color={WHATSAPP_GREEN} />
                <ThemedText style={[styles.miniBadgeText, { color: WHATSAPP_GREEN }]}>واتساب</ThemedText>
              </View>
            ) : (
              <View style={[styles.miniBadge, { backgroundColor: '#64748B22' }]}>
                <ThemedText style={[styles.miniBadgeText, { color: colors.icon }]}>بلا رقم</ThemedText>
              </View>
            )}
          </View>

          {/* Selection Checkbox Trigger */}
          {!isDeceased && (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={handleSelectPress}
              disabled={!selectable}
              style={[
                styles.selectIndicatorCircle,
                isSelected && {
                  backgroundColor: silaTheme.primary,
                  borderColor: silaTheme.primary,
                },
                !selectable && {
                  backgroundColor: isDark ? '#334155' : '#E2E8F0',
                  borderColor: 'transparent',
                },
              ]}>
              {isSelected ? (
                <Ionicons name="checkmark" size={13} color="#FFFFFF" />
              ) : !selectable ? (
                <Ionicons name="lock-closed" size={10} color={colors.icon} />
              ) : (
                <Ionicons name="add" size={12} color={colors.icon} />
              )}
            </TouchableOpacity>
          )}
        </View>

        {/* Card Visual Area (Photo or Gender Avatar) */}
        <View style={styles.cardVisualArea}>
          {photoUri ? (
            <View style={styles.cardPhotoWrapper}>
              <Image source={{ uri: photoUri }} style={styles.cardPhoto} contentFit="cover" />
              <LinearGradient
                colors={['transparent', 'rgba(0,0,0,0.85)']}
                style={styles.cardPhotoGradient}
              />
              <ThemedText style={styles.cardPhotoName} numberOfLines={2}>
                {node.first_name}
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
                  name={node.gender === 'female' ? 'woman' : 'man'}
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
                {node.first_name}
              </ThemedText>
            </View>
          )}
        </View>

        {/* Card Bottom Controls */}
        <View style={[styles.cardBottomControls, { borderTopColor: isDark ? '#334155' : '#E2E8F0' }]}>
          {isDeceased ? (
            <View style={styles.deceasedFooter}>
              <ThemedText style={styles.deceasedFooterText}>رحمه الله</ThemedText>
            </View>
          ) : !selectable ? (
            <View style={styles.disabledFooter}>
              <ThemedText style={styles.disabledFooterText}>غير متاح للدعوة</ThemedText>
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
                      backgroundColor: isDark ? '#334155' : '#F1F5F9',
                      borderColor: silaTheme.primary + '66',
                    },
              ]}>
              <ThemedText
                style={[
                  styles.inviteActionBtnText,
                  { color: isSelected ? '#FFFFFF' : silaTheme.primary },
                ]}>
                {isSelected ? '✓ محدد للدعوة' : '+ إضافة للدعوة'}
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
                      ? '#1E293B'
                      : '#F8FAFC',
                  borderColor: isExpanded ? silaTheme.primary : colors.border,
                },
              ]}>
              <ThemedText
                style={[
                  styles.childrenPillText,
                  { color: isExpanded ? silaTheme.primary : colors.icon },
                ]}>
                الأبناء ({descendantCount || node.children?.length || 0})
              </ThemedText>
              <Ionicons
                name={isExpanded ? 'chevron-up' : 'chevron-down'}
                size={12}
                color={isExpanded ? silaTheme.primary : colors.icon}
              />
            </TouchableOpacity>
          )}
        </View>
      </Pressable>
    </Animated.View>
  );
}

// ─────────────────────────────────────────────
// RECURSIVE TREE COLUMN (معمارية صلة)
// ─────────────────────────────────────────────
interface TreeColumnProps {
  nodes: InvitationTreeNode[];
  level: number;
  selectedMap: Map<number, InvitationPersonBrief>;
  expandedSet: Set<number>;
  colors: ThemeColors;
  isDark: boolean;
  onToggleSelect: (person: InvitationPersonBrief, selectable: boolean) => void;
  onToggleExpand: (id: number) => void;
}

function TreeColumn({
  nodes,
  level,
  selectedMap,
  expandedSet,
  colors,
  isDark,
  onToggleSelect,
  onToggleExpand,
}: TreeColumnProps) {
  return (
    <View style={styles.columnRow}>
      {/* Current Column of Persons */}
      <View style={styles.column}>
        {nodes.map((node) => (
          <View key={node.id} style={{ height: CARD_H, marginBottom: CARD_GAP }}>
            <PersonTreeCard
              node={node}
              isSelected={selectedMap.has(node.id)}
              isExpanded={expandedSet.has(node.id)}
              colors={colors}
              isDark={isDark}
              onToggleSelect={onToggleSelect}
              onToggleExpand={onToggleExpand}
            />
          </View>
        ))}
      </View>

      {/* Children Generational Area (branches off to the side) */}
      <View style={styles.childrenArea}>
        {nodes.map((node, index) =>
          expandedSet.has(node.id) && node.children && node.children.length > 0 ? (
            <View key={`branch-${node.id}`} style={styles.childrenGroup}>
              {/* Vertical alignment offset so children align horizontally with their father */}
              <View style={{ height: index * COLUMN_STEP }} />
              <TreeColumn
                nodes={node.children}
                level={level + 1}
                selectedMap={selectedMap}
                expandedSet={expandedSet}
                colors={colors}
                isDark={isDark}
                onToggleSelect={onToggleSelect}
                onToggleExpand={onToggleExpand}
              />
            </View>
          ) : null
        )}
      </View>
    </View>
  );
}

// ─────────────────────────────────────────────
// MAIN RECIPIENT PICKER MODAL (نظام الدعوات)
// ─────────────────────────────────────────────
export function RecipientPickerModal({
  visible,
  onClose,
  onConfirm,
  initialSelected = [],
  excludedPersonId,
  title = 'اختيار المستلمين من العائلة 👥',
  colors,
  isDark,
}: RecipientPickerModalProps) {
  const insets = useSafeAreaInsets();
  const [activeTab, setActiveTab] = useState<'tree' | 'list'>('tree');
  const [tree, setTree] = useState<InvitationTreeNode[]>([]);
  const [treeLoading, setTreeLoading] = useState(false);
  const [treeError, setTreeError] = useState<string | null>(null);
  const [expanded, setExpanded] = useState<Set<number>>(new Set());
  const [selected, setSelected] = useState<Map<number, InvitationPersonBrief>>(new Map());
  const [query, setQuery] = useState('');
  const [searchResults, setSearchResults] = useState<InvitationSearchPerson[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [keyboardVisible, setKeyboardVisible] = useState(false);
  const visibleTree = useMemo(
    () => excludedPersonId === undefined ? tree : excludeFromTree(tree, excludedPersonId),
    [tree, excludedPersonId],
  );
  const visibleSearchResults = searchResults.filter((person) => person.id !== excludedPersonId);

  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const loadedOnce = useRef(false);

  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;

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

  const wasVisible = useRef(false);
  useEffect(() => {
    if (visible && !wasVisible.current) {
      setSelected(new Map(initialSelected.filter((person) => person.id !== excludedPersonId).map((person) => [person.id, toBrief(person)])));
      setQuery('');
      setSearchResults([]);
    }
    wasVisible.current = visible;
  }, [visible, initialSelected, excludedPersonId]);

  const loadTree = useCallback(async () => {
    setTreeLoading(true);
    setTreeError(null);
    const res = await fetchInvitationTreePicker();
    if (!res.success || !res.data?.tree) {
      setTreeError(res.message ?? 'تعذّر جلب شجرة العائلة.');
      setTree([]);
    } else {
      setTree(res.data.tree);
    }
    setTreeLoading(false);
    loadedOnce.current = true;
  }, []);

  useEffect(() => {
    if (visible && !loadedOnce.current) {
      void loadTree();
    }
  }, [visible, loadTree]);

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    const trimmed = query.trim();
    if (!trimmed) {
      setSearchResults([]);
      setSearchLoading(false);
      return;
    }
    setSearchLoading(true);
    searchTimer.current = setTimeout(async () => {
      const res = await searchInvitationPersons(trimmed);
      setSearchResults(res.data?.persons ?? []);
      setSearchLoading(false);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    };
  }, [query]);

  const toggleExpand = useCallback((id: number) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const toggleSelect = useCallback((person: InvitationPersonBrief, selectable: boolean) => {
    if (!selectable || person.id === excludedPersonId) return;
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelected((prev) => {
      const next = new Map(prev);
      if (next.has(person.id)) next.delete(person.id);
      else next.set(person.id, toBrief(person));
      return next;
    });
  }, [excludedPersonId]);

  const handleRemovePerson = useCallback((id: number) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelected((prev) => {
      const next = new Map(prev);
      next.delete(id);
      return next;
    });
  }, []);

  const handleClearAll = useCallback(() => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelected(new Map());
  }, []);

  const handleTabSwitch = (tab: 'tree' | 'list') => {
    try {
      Haptics.selectionAsync();
    } catch {}
    Keyboard.dismiss();
    setActiveTab(tab);
  };

  const selectedList = Array.from(selected.values()).filter((person) => person.id !== excludedPersonId);
  const selectedCount = selectedList.length;

  const handleConfirm = () => {
    Keyboard.dismiss();
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    onConfirm(selectedList);
    onClose();
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.keyboardContainer}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 10 : 0}>
        <View style={styles.modalOverlay}>
          <View
            style={[
              styles.modalContent,
              { backgroundColor: cardBg, borderColor: border },
            ]}>
            {/* Modal Header */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={() => {
                  Keyboard.dismiss();
                  try {
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  } catch {}
                  onClose();
                }}
                style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={muted} />
              </TouchableOpacity>

              <View style={{ alignItems: 'center' }}>
                <ThemedText style={styles.headerTitle}>{title}</ThemedText>
                <ThemedText style={[styles.headerSub, { color: muted }]}>
                  اختر من شجرة العائلة أو ابحث بالاسم مباشرة
                </ThemedText>
              </View>

              <View style={{ width: 36 }} />
            </View>

            {/* Mode Switcher Tabs (شجرة العائلة VS البحث والقائمة) */}
            <View
              style={[
                styles.tabSwitcherWrap,
                { backgroundColor: isDark ? '#1E293B' : '#F1F5F9' },
              ]}>
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={() => handleTabSwitch('tree')}
                style={[
                  styles.tabSwitcherBtn,
                  activeTab === 'tree' && [
                    styles.tabSwitcherBtnActive,
                    { backgroundColor: isDark ? '#334155' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabSwitcherText,
                    { color: activeTab === 'tree' ? silaTheme.primary : muted },
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
                    { backgroundColor: isDark ? '#334155' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabSwitcherText,
                    { color: activeTab === 'list' ? silaTheme.primary : muted },
                    activeTab === 'list' && styles.tabSwitcherTextActive,
                  ]}>
                  📋 البحث والقائمة
                </ThemedText>
              </TouchableOpacity>
            </View>

            {/* Selected Members Chips Strip (Always Accessible) */}
            {selectedCount > 0 && (
              <View style={[styles.selectedStrip, { borderBottomColor: isDark ? '#334155' : '#F1F5F9' }]}>
                <View style={styles.selectedStripHeader}>
                  <ThemedText style={[styles.selectedCountText, { color: silaTheme.primary }]}>
                    تم تحديد ({selectedCount}) من أفراد العائلة
                  </ThemedText>
                  <TouchableOpacity onPress={handleClearAll}>
                    <ThemedText style={styles.clearAllText}>مسح الكل</ThemedText>
                  </TouchableOpacity>
                </View>

                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.chipsScroll}>
                  {selectedList.map((person) => (
                    <View
                      key={person.id}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: isDark ? '#064E3B' : '#ECFDF5',
                          borderColor: silaTheme.primary,
                        },
                      ]}>
                      <PersonAvatar
                        uri={resolveInvitationPhotoUrl(person.photo_url)}
                        size={20}
                        mutedColor={muted}
                        borderColor="transparent"
                      />
                      <ThemedText
                        style={[
                          styles.chipName,
                          { color: isDark ? '#A7F3D0' : '#065F46' },
                        ]}>
                        {person.first_name}
                      </ThemedText>
                      <TouchableOpacity
                        onPress={() => handleRemovePerson(person.id)}
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
              // TAB 1: TREE VIEW (كروت صلة التفاعلية)
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
                    انقر على كرت الشخص لعرض أبنائه، واضغط «+ إضافة للدعوة» لتحديده لإرسال الدعوة 📩
                  </ThemedText>
                </View>

                {treeLoading ? (
                  <View style={styles.centeredState}>
                    <ActivityIndicator size="large" color={silaTheme.primary} />
                    <ThemedText style={[styles.loadingLabel, { color: colors.text }]}>
                      جاري تحميل شجرة العائلة...
                    </ThemedText>
                  </View>
                ) : treeError ? (
                  <View style={styles.centeredState}>
                    <Ionicons name="cloud-offline-outline" size={48} color={muted} />
                    <ThemedText style={[styles.errorTitle, { color: colors.text }]}>
                      تعذر تحميل شجرة العائلة
                    </ThemedText>
                    <ThemedText style={[styles.errorSub, { color: muted }]}>
                      {treeError}
                    </ThemedText>
                    <TouchableOpacity
                      onPress={() => void loadTree()}
                      style={[styles.retryBtn, { backgroundColor: silaTheme.primary }]}>
                      <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
                    </TouchableOpacity>
                  </View>
                ) : visibleTree.length === 0 ? (
                  <View style={styles.centeredState}>
                    <Ionicons name="leaf-outline" size={48} color={muted} />
                    <ThemedText style={{ color: muted, fontFamily: 'Tajawal-Regular', marginTop: 12 }}>
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
                        nodes={visibleTree}
                        level={0}
                        selectedMap={selected}
                        expandedSet={expanded}
                        colors={colors}
                        isDark={isDark}
                        onToggleSelect={toggleSelect}
                        onToggleExpand={toggleExpand}
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
                      backgroundColor: isDark ? '#1E293B' : '#F8FAFC',
                      borderColor: border,
                    },
                  ]}>
                  <Ionicons name="search" size={16} color={muted} />
                  <TextInput
                    value={query}
                    onChangeText={setQuery}
                    placeholder="ابحث بالاسم..."
                    placeholderTextColor={muted}
                    style={[styles.searchInput, { color: colors.text }]}
                  />
                  {searchLoading && (
                    <ActivityIndicator size="small" color={silaTheme.primary} />
                  )}
                  {query.length > 0 && (
                    <TouchableOpacity onPress={() => setQuery('')}>
                      <Ionicons name="close-circle" size={16} color={muted} />
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
                      { backgroundColor: isDark ? '#1E293B' : '#F1F5F9' },
                    ]}>
                    <ThemedText style={[styles.keyboardDismissText, { color: silaTheme.primary }]}>
                      إخفاء لوحة المفاتيح ⌄
                    </ThemedText>
                  </TouchableOpacity>
                )}

                {/* Scrollable Members List */}
                {searchLoading ? (
                  <View style={styles.centeredState}>
                    <ActivityIndicator size="small" color={silaTheme.primary} />
                  </View>
                ) : (
                  <FlatList
                    data={visibleSearchResults}
                    keyExtractor={(item) => String(item.id)}
                    keyboardShouldPersistTaps="handled"
                    contentContainerStyle={
                      visibleSearchResults.length === 0 ? styles.emptyListState : styles.listScroll
                    }
                    ListEmptyComponent={
                      <View style={styles.emptyListState}>
                        <ThemedText style={[styles.emptyListText, { color: muted }]}>
                          {query ? 'لا توجد نتائج مطابقة' : 'اكتب اسماً في مربع البحث أعلاه 🔍'}
                        </ThemedText>
                      </View>
                    }
                    renderItem={({ item }) => {
                      const isSelected = selected.has(item.id);
                      const photoUri = resolveInvitationPhotoUrl(item.photo_url);
                      const name = item.full_name || item.first_name;

                      return (
                        <TouchableOpacity
                          key={item.id}
                          activeOpacity={0.7}
                          onPress={() =>
                            toggleSelect(
                              { id: item.id, first_name: item.first_name, photo_url: item.photo_url },
                              item.selectable
                            )
                          }
                          disabled={!item.selectable}
                          style={[
                            styles.listRow,
                            isSelected && {
                              backgroundColor: isDark ? '#064E3B44' : '#D1FAE5',
                            },
                            !item.selectable && { opacity: 0.5 },
                          ]}>
                          <View style={styles.listRowLeft}>
                            <PersonAvatar
                              uri={photoUri}
                              size={40}
                              mutedColor={muted}
                              borderColor={isDark ? '#334155' : colors.border}
                              grayscale={!item.has_whatsapp}
                            />
                            <View>
                              <ThemedText style={[styles.listName, { color: item.selectable ? colors.text : muted }]}>
                                {name}
                              </ThemedText>
                              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 }}>
                                {item.has_whatsapp ? (
                                  <View style={[styles.miniBadge, { backgroundColor: WHATSAPP_GREEN + '22' }]}>
                                    <Ionicons name="logo-whatsapp" size={10} color={WHATSAPP_GREEN} />
                                    <ThemedText style={[styles.miniBadgeText, { color: WHATSAPP_GREEN }]}>واتساب</ThemedText>
                                  </View>
                                ) : (
                                  <ThemedText style={[styles.listSubName, { color: muted }]}>
                                    بلا رقم واتساب
                                  </ThemedText>
                                )}
                              </View>
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
                                  : muted,
                              },
                              !item.selectable && {
                                backgroundColor: isDark ? '#334155' : '#E2E8F0',
                                borderColor: 'transparent',
                              },
                            ]}>
                            {isSelected ? (
                              <Ionicons name="checkmark" size={13} color="#FFFFFF" />
                            ) : !item.selectable ? (
                              <Ionicons name="lock-closed" size={10} color={muted} />
                            ) : (
                              <Ionicons name="add" size={12} color={muted} />
                            )}
                          </View>
                        </TouchableOpacity>
                      );
                    }}
                  />
                )}
              </View>
            )}

            {/* Pinned Bottom Footer Action Button */}
            <View
              style={[
                styles.footer,
                {
                  backgroundColor: cardBg,
                  borderTopColor: border,
                  paddingBottom: Math.max(insets.bottom + 8, Platform.OS === 'ios' ? 28 : 18),
                },
              ]}>
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={handleConfirm}
                style={[
                  styles.confirmBtn,
                  {
                    backgroundColor:
                      selectedCount > 0
                        ? silaTheme.primary
                        : isDark
                          ? '#334155'
                          : '#E2E8F0',
                  },
                ]}>
                <ThemedText
                  style={[
                    styles.confirmBtnText,
                    {
                      color:
                        selectedCount > 0
                          ? '#FFFFFF'
                          : isDark
                            ? '#94A3B8'
                            : '#64748B',
                    },
                  ]}>
                  {selectedCount > 0
                    ? `✅ تأكيد واختيار (${selectedCount}) أفراد`
                    : '✅ متابعة بدون اختيار أفراد'}
                </ThemedText>
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
    backgroundColor: '#475569',
    borderRadius: 12,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  mourningBadgeText: {
    color: '#FFFFFF',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  miniBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    paddingHorizontal: 5,
    paddingVertical: 2,
    borderRadius: 10,
  },
  miniBadgeText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Bold',
  },
  selectIndicatorCircle: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1.5,
    borderColor: '#94A3B8',
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
    color: '#64748B',
  },
  disabledFooter: {
    paddingVertical: 4,
    alignItems: 'center',
  },
  disabledFooterText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Medium',
    color: '#64748B',
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
    textAlign: 'right',
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
    paddingHorizontal: 16,
    paddingBottom: 16,
  },
  listRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 12,
    marginBottom: 6,
  },
  listRowLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  listName: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },
  listSubName: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
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

