import { ThemedText } from '@/components/themed-text';
import { type ThemeColors } from '@/constants/theme';
import {
  fetchCandidateTreeNodes,
  type CandidateRules,
  type CandidateTreeNode,
} from '@/lib/api/quiz';
import { resolveInvitationPhotoUrl } from '@/lib/api/invitations';
import { toEnglishDigits } from '@/lib/utils/numberFormat';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import * as Haptics from 'expo-haptics';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  I18nManager,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const { width: SCREEN_W } = Dimensions.get('window');
const CARD_W = 120;
const CARD_H = 186;

interface TreeCandidatePickerModalProps {
  visible: boolean;
  onClose: () => void;
  onSelect: (person: CandidateTreeNode) => void;
  questionId?: number | null;
  candidateRules?: CandidateRules | null;
  selectedIds?: number[];
  colors: ThemeColors;
  isDark: boolean;
}

interface ColumnState {
  parentId: number | null;
  parentName?: string;
  nodes: CandidateTreeNode[];
}

export default function TreeCandidatePickerModal({
  visible,
  onClose,
  onSelect,
  questionId,
  candidateRules,
  selectedIds = [],
  colors,
  isDark,
}: TreeCandidatePickerModalProps) {
  const insets = useSafeAreaInsets();
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [searchResults, setSearchResults] = useState<CandidateTreeNode[]>([]);
  const [activeBranchPath, setActiveBranchPath] = useState<{ id: number; name: string }[]>([]);
  const [columnsData, setColumnsData] = useState<ColumnState[]>([]);
  const [loading, setLoading] = useState(false);
  const [expandingId, setExpandingId] = useState<number | null>(null);

  const searchTimeoutRef = useRef<any>(null);
  const treeScrollRef = useRef<ScrollView>(null);

  const targetGender = candidateRules?.allowed_gender || 'all';
  const requireWhatsapp = !!candidateRules?.require_whatsapp;
  const excludeDeceased = candidateRules?.exclude_deceased !== false;
  const excludeOutsideFamily = candidateRules?.exclude_outside_family;
  const excludeMaternalChildren = candidateRules?.exclude_maternal_children;
  const excludeBreastfed = candidateRules?.exclude_breastfed;
  const excludeOriginalFamily = candidateRules?.exclude_original_family;

  // Header Subtitle Text
  const getScopeSubtitle = () => {
    if (candidateRules?.candidate_scope === 'maternal_only' || (excludeOriginalFamily && !excludeMaternalChildren)) {
      return 'المنافسة مخصصة لأبناء وبنات نساء العائلة (الأنساب) 🌸';
    }
    if (candidateRules?.candidate_scope === 'breastfed_only' || (excludeOriginalFamily && !excludeBreastfed)) {
      return 'المنافسة مخصصة لأبناء وبنات الرضاعة 🍼';
    }
    if (candidateRules?.candidate_scope === 'original_family_only') {
      return 'المنافسة مخصصة لسلالة العائلة الأساسية فقط 🌳';
    }
    if (targetGender === 'male') {
      return 'المنافسة مخصصة لرجال العائلة فقط 👨';
    }
    if (targetGender === 'female') {
      return 'المنافسة مخصصة لنساء العائلة فقط 👩';
    }
    return 'متاح اختيار أي فرد مؤهل من العائلة والأنساب 🌳';
  };

  // Load root ancestors
  const loadRoots = useCallback(async () => {
    try {
      setLoading(true);
      const nodes = await fetchCandidateTreeNodes({
        parentId: null,
        gender: targetGender,
        hasWhatsappOnly: requireWhatsapp,
        excludeDeceased,
        excludeOutsideFamily,
        excludeMaternalChildren,
        excludeBreastfed,
        excludeOriginalFamily,
        questionId,
      });
      setColumnsData([{ parentId: null, parentName: 'الآباء والأجداد', nodes }]);
      setActiveBranchPath([]);
    } catch (err) {
      console.error('Failed to load candidate root nodes:', err);
    } finally {
      setLoading(false);
    }
  }, [targetGender, requireWhatsapp, excludeDeceased, excludeOutsideFamily, excludeMaternalChildren, excludeBreastfed, excludeOriginalFamily, questionId]);

  useEffect(() => {
    if (visible) {
      setSearchQuery('');
      setIsSearching(false);
      setSearchResults([]);
      loadRoots();
    }
  }, [visible, loadRoots]);

  // Handle Search Debounce
  const handleSearchChange = (text: string) => {
    setSearchQuery(text);
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (!text.trim()) {
      setIsSearching(false);
      setSearchResults([]);
      return;
    }

    setIsSearching(true);
    searchTimeoutRef.current = setTimeout(async () => {
      try {
        setLoading(true);
        const results = await fetchCandidateTreeNodes({
          q: text.trim(),
          gender: targetGender,
          hasWhatsappOnly: requireWhatsapp,
          excludeDeceased,
          excludeOutsideFamily,
          excludeMaternalChildren,
          excludeBreastfed,
          excludeOriginalFamily,
          questionId,
        });
        setSearchResults(results);
      } catch (err) {
        console.error('Search candidates failed:', err);
      } finally {
        setLoading(false);
      }
    }, 280);
  };

  // Expand node children into the next column
  const handleNodeExpand = async (person: CandidateTreeNode, level: number) => {
    if (person.children_count === 0) return;

    // If already active at this level, toggle/collapse
    if (activeBranchPath[level]?.id === person.id) {
      setActiveBranchPath((prev) => prev.slice(0, level));
      setColumnsData((prev) => prev.slice(0, level + 1));
      return;
    }

    try {
      setExpandingId(person.id);
      const nextChildren = await fetchCandidateTreeNodes({
        parentId: person.id,
        gender: targetGender,
        hasWhatsappOnly: requireWhatsapp,
        excludeDeceased,
        excludeOutsideFamily,
        excludeMaternalChildren,
        excludeBreastfed,
        excludeOriginalFamily,
        questionId,
      });

      setColumnsData((prev) => {
        const next = prev.slice(0, level + 1);
        next.push({
          parentId: person.id,
          parentName: `أبناء ${person.name || person.first_name}`,
          nodes: nextChildren,
        });
        return next;
      });

      setActiveBranchPath((prev) => {
        const next = prev.slice(0, level);
        next.push({ id: person.id, name: person.name || person.first_name });
        return next;
      });

      // Auto-scroll to end column
      setTimeout(() => {
        treeScrollRef.current?.scrollToEnd({ animated: true });
      }, 150);
    } catch (err) {
      console.error('Failed to load branch children:', err);
    } finally {
      setExpandingId(null);
    }
  };

  // Jump to specific breadcrumb
  const handleBreadcrumbPress = (targetLevel: number) => {
    setActiveBranchPath((prev) => prev.slice(0, targetLevel));
    setColumnsData((prev) => prev.slice(0, targetLevel + 1));
  };

  // Select Candidate
  const handleSelectCandidate = (person: CandidateTreeNode) => {
    const isGenderAllowed =
      !targetGender || targetGender === 'all' || person.gender === targetGender;
    if (!person.selectable || !isGenderAllowed) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      return;
    }

    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    onSelect(person);
    onClose();
  };

  // Render a vertical Sila-style Person Card
  const renderSilaCard = (person: CandidateTreeNode, level: number = 0) => {
    const isSelected = selectedIds.includes(person.id);
    const isBranchActive = activeBranchPath[level]?.id === person.id;
    const isMale = person.gender === 'male';
    const isExpanding = expandingId === person.id;
    const photoUrl = resolveInvitationPhotoUrl(person.photo_url);

    const isGenderAllowed =
      !targetGender || targetGender === 'all' || person.gender === targetGender;
    const isSelectable = Boolean(person.selectable && isGenderAllowed);
    const unselectableReason = !isGenderAllowed
      ? (targetGender === 'male' ? 'غير متاح (رجال فقط)' : 'غير متاح (نساء فقط)')
      : (person.unselectable_reason || 'غير متاح');

    return (
      <View
        key={person.id}
        style={[
          styles.silaCard,
          {
            backgroundColor: isDark ? '#1e293b' : '#ffffff',
            borderColor: isSelected
              ? '#10b981'
              : isBranchActive
              ? '#0d9488'
              : isDark
              ? '#334155'
              : '#e2e8f0',
            opacity: isSelectable ? 1 : 0.68,
          },
        ]}
      >
        {/* Top Badges */}
        {person.is_deceased && (
          <View style={styles.mourningBadge}>
            <ThemedText style={styles.mourningText}>
              {isMale ? 'رحمه الله' : 'رحمها الله'}
            </ThemedText>
          </View>
        )}

        {person.has_whatsapp && (
          <View style={styles.whatsappIconBadge}>
            <Ionicons name="logo-whatsapp" size={11} color="#ffffff" />
          </View>
        )}

        {/* Category Badge (Maternal Children / Breastfed / Outsiders) */}
        {!!person.candidate_category && person.candidate_category !== 'original_family' && (
          <View
            style={[
              styles.categoryBadge,
              {
                backgroundColor:
                  person.candidate_category === 'maternal_children'
                    ? '#db2777'
                    : person.candidate_category === 'breastfed_children'
                    ? '#0284c7'
                    : '#d97706',
              },
            ]}
          >
            <ThemedText style={styles.categoryBadgeText}>
              {person.candidate_category_badge ? `${person.candidate_category_badge} ` : ''}
              {person.candidate_category_label || (person.candidate_category === 'maternal_children' ? 'أبناء نساء العائلة' : person.candidate_category === 'breastfed_children' ? 'بالرضاعة' : 'خارج العائلة')}
            </ThemedText>
          </View>
        )}

        {/* Card Photo / Avatar area (Clickable to expand if has children) */}
        <TouchableOpacity
          activeOpacity={0.82}
          onPress={() => {
            if (person.children_count > 0) {
              handleNodeExpand(person, level);
            }
          }}
          style={styles.cardTopArea}
        >
          {photoUrl ? (
            <>
              <Image source={{ uri: photoUrl }} style={styles.cardPhoto} contentFit="cover" />
              <LinearGradient
                colors={['transparent', 'rgba(0,0,0,0.85)']}
                style={styles.cardGradient}
              />
              <ThemedText style={styles.cardNameAbsolute} numberOfLines={2}>
                {person.name || person.first_name}
              </ThemedText>
            </>
          ) : (
            <View style={styles.avatarFallback}>
              <View style={[styles.avatarCircle, { backgroundColor: isDark ? '#334155' : '#f1f5f9' }]}>
                <Ionicons
                  name={isMale ? 'man' : 'woman'}
                  size={32}
                  color={isMale ? '#3b82f6' : '#ec4899'}
                />
              </View>
              <ThemedText style={[styles.cardNameFallback, { color: isDark ? '#f8fafc' : '#0f172a' }]} numberOfLines={2}>
                {person.name || person.first_name}
              </ThemedText>
            </View>
          )}
        </TouchableOpacity>

        {/* Expand Children Button (if has children) */}
        {person.children_count > 0 && (
          <TouchableOpacity
            activeOpacity={0.7}
            onPress={() => handleNodeExpand(person, level)}
            style={[
              styles.expandBranchBtn,
              {
                backgroundColor: isBranchActive ? '#0d948815' : isDark ? '#0f172a' : '#f8fafc',
                borderTopColor: isDark ? '#334155' : '#e2e8f0',
              },
            ]}
          >
            {isExpanding ? (
              <ActivityIndicator size="small" color="#0d9488" />
            ) : (
              <View style={styles.expandRow}>
                <Ionicons
                  name={isBranchActive ? 'chevron-up' : 'chevron-down'}
                  size={12}
                  color={isBranchActive ? '#0d9488' : isDark ? '#94a3b8' : '#64748b'}
                />
                <ThemedText
                  style={[
                    styles.expandText,
                    { color: isBranchActive ? '#0d9488' : isDark ? '#94a3b8' : '#64748b' },
                  ]}
                >
                  الأبناء ({toEnglishDigits(person.children_count)})
                </ThemedText>
              </View>
            )}
          </TouchableOpacity>
        )}

        {/* Nomination Button or Ineligible Pill */}
        <View style={styles.cardBottomAction}>
          {isSelectable ? (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={() => handleSelectCandidate(person)}
              style={[
                styles.nominateBtn,
                { backgroundColor: isSelected ? '#ef4444' : '#10b981' },
              ]}
            >
              <Ionicons
                name={isSelected ? 'checkmark' : 'add'}
                size={13}
                color="#ffffff"
              />
              <ThemedText style={styles.nominateBtnText}>
                {isSelected ? 'مختار' : 'اختيار'}
              </ThemedText>
            </TouchableOpacity>
          ) : (
            <View style={[styles.ineligiblePill, { backgroundColor: isDark ? '#450a0a50' : '#fee2e2' }]}>
              <ThemedText
                style={[styles.ineligiblePillText, { color: isDark ? '#fca5a5' : '#dc2626' }]}
                numberOfLines={1}
              >
                {unselectableReason}
              </ThemedText>
            </View>
          )}
        </View>
      </View>
    );
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} />
        <View
          style={[
            styles.modalContent,
            {
              backgroundColor: isDark ? '#0f172a' : '#f8fafc',
              paddingBottom: Math.max(insets.bottom, 16),
            },
          ]}
        >
          {/* Header */}
          <View style={[styles.header, { borderBottomColor: isDark ? '#1e293b' : '#e2e8f0' }]}>
            <View style={styles.headerTitles}>
              <View style={styles.titleRow}>
                <Ionicons name="git-network-outline" size={20} color="#10b981" />
                <ThemedText style={[styles.title, { color: isDark ? '#f8fafc' : '#0f172a' }]}>
                  تحديد متوقع للفوز من شجرة العائلة
                </ThemedText>
              </View>
              <ThemedText style={styles.subtitle}>{getScopeSubtitle()}</ThemedText>
            </View>

            <TouchableOpacity onPress={onClose} style={styles.closeBtn} hitSlop={10}>
              <Ionicons name="close" size={22} color={isDark ? '#94a3b8' : '#64748b'} />
            </TouchableOpacity>
          </View>

          {/* Search Box */}
          <View style={styles.searchContainer}>
            <View
              style={[
                styles.searchBox,
                { backgroundColor: isDark ? '#1e293b' : '#ffffff', borderColor: isDark ? '#334155' : '#e2e8f0' },
              ]}
            >
              <Ionicons name="search" size={18} color="#94a3b8" />
              <TextInput
                style={[styles.searchInput, { color: isDark ? '#f8fafc' : '#0f172a' }]}
                placeholder="ابحث بالاسم عن أي شخص في العائلة..."
                placeholderTextColor="#94a3b8"
                value={searchQuery}
                onChangeText={handleSearchChange}
                textAlign="right"
              />
              {searchQuery.length > 0 && (
                <TouchableOpacity onPress={() => handleSearchChange('')}>
                  <Ionicons name="close-circle" size={18} color="#94a3b8" />
                </TouchableOpacity>
              )}
            </View>
          </View>

          {/* Breadcrumbs Navigation Trail (Tree mode only) */}
          {!isSearching && (
            <View style={[styles.breadcrumbsBar, { borderBottomColor: isDark ? '#1e293b' : '#e2e8f0' }]}>
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.breadcrumbsContent}
              >
                <TouchableOpacity
                  onPress={() => handleBreadcrumbPress(0)}
                  style={[styles.crumbItem, activeBranchPath.length === 0 && styles.crumbItemActive]}
                >
                  <Ionicons name="leaf" size={12} color={activeBranchPath.length === 0 ? '#10b981' : colors.icon} />
                  <ThemedText
                    style={[
                      styles.crumbText,
                      { color: activeBranchPath.length === 0 ? '#10b981' : isDark ? '#94a3b8' : '#64748b' },
                    ]}
                  >
                    الرئيسية
                  </ThemedText>
                </TouchableOpacity>

                {activeBranchPath.map((crumb, idx) => {
                  const isCurrent = idx === activeBranchPath.length - 1;
                  return (
                    <React.Fragment key={crumb.id}>
                      <Ionicons name="chevron-back" size={12} color={isDark ? '#475569' : '#cbd5e1'} />
                      <TouchableOpacity
                        onPress={() => handleBreadcrumbPress(idx + 1)}
                        style={[styles.crumbItem, isCurrent && styles.crumbItemActive]}
                      >
                        <ThemedText
                          style={[
                            styles.crumbText,
                            { color: isCurrent ? '#10b981' : isDark ? '#94a3b8' : '#64748b' },
                          ]}
                          numberOfLines={1}
                        >
                          {crumb.name}
                        </ThemedText>
                      </TouchableOpacity>
                    </React.Fragment>
                  );
                })}
              </ScrollView>
            </View>
          )}

          {/* Loading Indicator */}
          {loading && (
            <View style={styles.loadingBar}>
              <ActivityIndicator size="small" color="#10b981" />
            </View>
          )}

          {/* Main Body */}
          {isSearching ? (
            /* Search Results: Multi-column grid of vertical cards */
            <ScrollView
              contentContainerStyle={styles.searchResultsGrid}
              showsVerticalScrollIndicator={false}
            >
              {searchResults.length > 0 ? (
                <View style={styles.cardsGridWrapper}>
                  {searchResults.map((item) => renderSilaCard(item, 0))}
                </View>
              ) : !loading ? (
                <View style={styles.emptyContainer}>
                  <Ionicons name="search-outline" size={42} color="#94a3b8" />
                  <ThemedText style={styles.emptyTitle}>لم نجد أي شخص يطابق بحثك</ThemedText>
                  <ThemedText style={styles.emptySubtitle}>
                    تأكد من كتابة الاسم بصورة صحيحة أو تحقق من شروط المنافسة (الجنس / نشاط المشي).
                  </ThemedText>
                </View>
              ) : null}
            </ScrollView>
          ) : (
            /* Sila 2D Generational Tree View: Multi-column drill down */
            <ScrollView
              ref={treeScrollRef}
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.columnsScroll}
              style={{ flex: 1 }}
            >
              {columnsData.map((col, level) => (
                <View
                  key={col.parentId ?? 'root'}
                  style={[
                    styles.treeColumn,
                    {
                      backgroundColor: isDark ? '#1e293b50' : '#f8fafc',
                      borderColor: isDark ? '#334155' : '#e2e8f0',
                    },
                  ]}
                >
                  {/* Column Header */}
                  <View style={[styles.columnHeader, { borderBottomColor: isDark ? '#334155' : '#e2e8f0' }]}>
                    <ThemedText style={[styles.columnTitle, { color: isDark ? '#f8fafc' : '#0f172a' }]} numberOfLines={1}>
                      {col.parentName || (level === 0 ? 'الآباء والأجداد' : `الجيل ${toEnglishDigits(level + 1)}`)}
                    </ThemedText>
                    <ThemedText style={styles.columnCount}>
                      {toEnglishDigits(col.nodes.length)}
                    </ThemedText>
                  </View>

                  {/* Cards inside Column */}
                  <ScrollView
                    showsVerticalScrollIndicator={false}
                    contentContainerStyle={styles.columnCardsList}
                  >
                    {col.nodes.map((person) => renderSilaCard(person, level))}
                  </ScrollView>
                </View>
              ))}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    height: '90%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    overflow: 'hidden',
  },
  header: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderBottomWidth: 1,
  },
  headerTitles: {
    alignItems: I18nManager.isRTL ? 'flex-start' : 'flex-end',
  },
  titleRow: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 8,
  },
  title: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
  },
  subtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#10b981',
    marginTop: 2,
  },
  closeBtn: {
    padding: 6,
    borderRadius: 50,
  },
  searchContainer: {
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  searchBox: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    paddingHorizontal: 12,
    height: 42,
    borderRadius: 12,
    borderWidth: 1,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    height: 42,
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  breadcrumbsBar: {
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderBottomWidth: 1,
  },
  breadcrumbsContent: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 6,
  },
  crumbItem: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  crumbItemActive: {
    backgroundColor: '#10b98115',
  },
  crumbText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  loadingBar: {
    paddingVertical: 4,
    alignItems: 'center',
  },
  columnsScroll: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    gap: 12,
  },
  treeColumn: {
    width: CARD_W + 28,
    borderRadius: 18,
    borderWidth: 1,
    padding: 10,
  },
  columnHeader: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingBottom: 6,
    marginBottom: 8,
    borderBottomWidth: 1,
  },
  columnTitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    flex: 1,
    textAlign: 'right',
  },
  columnCount: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#10b981',
    marginLeft: 4,
  },
  columnCardsList: {
    gap: 12,
    paddingBottom: 20,
    alignItems: 'center',
  },
  silaCard: {
    width: CARD_W,
    height: CARD_H,
    borderRadius: 16,
    borderWidth: 1.5,
    overflow: 'hidden',
    justifyContent: 'space-between',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
  },
  mourningBadge: {
    position: 'absolute',
    top: 5,
    left: 5,
    zIndex: 6,
    backgroundColor: '#475569',
    borderRadius: 12,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  mourningText: {
    color: '#ffffff',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  whatsappIconBadge: {
    position: 'absolute',
    top: 5,
    right: 5,
    zIndex: 6,
    backgroundColor: '#16a34a',
    borderRadius: 10,
    width: 18,
    height: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  categoryBadge: {
    position: 'absolute',
    top: 5,
    left: 5,
    zIndex: 7,
    paddingHorizontal: 5,
    paddingVertical: 2,
    borderRadius: 6,
  },
  categoryBadgeText: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Bold',
    color: '#ffffff',
  },
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
  },
  cardGradient: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 70,
    zIndex: 2,
  },
  cardNameAbsolute: {
    position: 'absolute',
    bottom: 4,
    left: 4,
    right: 4,
    zIndex: 3,
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#ffffff',
    textAlign: 'center',
    textShadowColor: 'rgba(0, 0, 0, 0.8)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 3,
  },
  avatarFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 6,
  },
  avatarCircle: {
    width: 50,
    height: 50,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  cardNameFallback: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  expandBranchBtn: {
    paddingVertical: 5,
    alignItems: 'center',
    justifyContent: 'center',
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  expandRow: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    gap: 4,
  },
  expandText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
  cardBottomAction: {
    padding: 5,
    width: '100%',
  },
  nominateBtn: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    paddingVertical: 5,
    borderRadius: 8,
    width: '100%',
  },
  nominateBtnText: {
    color: '#ffffff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  ineligiblePill: {
    paddingVertical: 4,
    paddingHorizontal: 4,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
    width: '100%',
  },
  ineligiblePillText: {
    fontSize: 9,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  searchResultsGrid: {
    padding: 16,
    paddingBottom: 32,
  },
  cardsGridWrapper: {
    flexDirection: I18nManager.isRTL ? 'row' : 'row-reverse',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'center',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
    paddingHorizontal: 20,
    gap: 8,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#94a3b8',
    textAlign: 'center',
  },
  emptySubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    color: '#94a3b8',
    textAlign: 'center',
    lineHeight: 18,
  },
});
