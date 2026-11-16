import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  StyleSheet,
  View,
  Text,
  Modal,
  TouchableOpacity,
  FlatList,
  Image,
  ActivityIndicator,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { silaTheme } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import {
  getTrekContributors,
  type TrekContributor,
  type MyTrekContribution,
} from '@/lib/api/walkingTrekApi';
import { formatEnglishNumber, formatSteps } from '@/lib/utils/numberFormat';

interface Props {
  visible: boolean;
  onClose: () => void;
  contributors: TrekContributor[];
  myContribution: MyTrekContribution | null;
  trekId?: number;
  groupId?: number;
}

export function TrekContributorsModal({
  visible,
  onClose,
  contributors,
  myContribution,
  trekId,
  groupId,
}: Props) {
  const { user } = useAuth();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();
  const bottomInset = Math.max(insets.bottom, 20);

  // الخصوصية: رجال للرجال ونساء للنساء تلقائياً بحسب جنس المشاهد
  const viewerGender: 'male' | 'female' = user?.gender === 'female' ? 'female' : 'male';

  const [listData, setListData] = useState<TrekContributor[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loadingInitial, setLoadingInitial] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [totalCount, setTotalCount] = useState(0);

  // الفلترة الأولية من البيانات المحلية كـ Fallback سريع
  const fallbackList = useMemo(() => {
    return contributors.filter((c) => !c.gender || c.gender === viewerGender);
  }, [contributors, viewerGender]);

  // جلب الصفحة الأولى فور فتح المودال
  const fetchFirstPage = useCallback(async () => {
    setLoadingInitial(true);
    setPage(1);
    try {
      const res = await getTrekContributors({
        trekId,
        groupId,
        page: 1,
        perPage: 20,
        gender: viewerGender,
      });

      if (res && res.contributors && res.contributors.length > 0) {
        setListData(res.contributors);
        setHasMore(res.pagination.has_more);
        setTotalCount(res.pagination.total);
      } else if (res && res.contributors) {
        setListData([]);
        setHasMore(false);
        setTotalCount(0);
      } else {
        // Fallback للبيانات الممررة
        setListData(fallbackList);
        setHasMore(false);
        setTotalCount(fallbackList.length);
      }
    } catch {
      setListData(fallbackList);
      setHasMore(false);
      setTotalCount(fallbackList.length);
    } finally {
      setLoadingInitial(false);
    }
  }, [trekId, groupId, viewerGender, fallbackList]);

  useEffect(() => {
    if (visible) {
      fetchFirstPage();
    }
  }, [visible, fetchFirstPage]);

  // جلب المزيد عند الوصول لنهاية القائمة (Infinite Scroll)
  const handleLoadMore = useCallback(async () => {
    if (loadingMore || !hasMore || loadingInitial) return;
    setLoadingMore(true);

    try {
      const nextPage = page + 1;
      const res = await getTrekContributors({
        trekId,
        groupId,
        page: nextPage,
        perPage: 20,
        gender: viewerGender,
      });

      if (res && res.contributors && res.contributors.length > 0) {
        setListData((prev) => {
          const existingIds = new Set(prev.map((c) => c.person_id));
          const fresh = res.contributors.filter((c) => !existingIds.has(c.person_id));
          return [...prev, ...fresh];
        });
        setPage(nextPage);
        setHasMore(res.pagination.has_more);
        setTotalCount(res.pagination.total);
      } else {
        setHasMore(false);
      }
    } catch {
      setHasMore(false);
    } finally {
      setLoadingMore(false);
    }
  }, [loadingMore, hasMore, loadingInitial, page, trekId, groupId, viewerGender]);

  // رأس القائمة: بطاقة إسهامي + عنوان القسم وعدد المشاركين الكلي
  const renderListHeader = () => (
    <View style={styles.listHeaderWrapper}>
      {/* My Contribution Card */}
      {myContribution && (
        <View style={[styles.myCard, isDark && styles.myCardDark]}>
          <View style={styles.myCardHeader}>
            <View style={styles.myCardTitleRow}>
              <Ionicons name="flash" size={16} color={silaTheme.gold} style={{ marginRight: 6 }} />
              <Text style={[styles.myCardTitle, { color: isDark ? '#F9FAFB' : '#111827' }]}>
                إسهامك في رحلة العائلة
              </Text>
            </View>
            <View style={styles.myShareBadge}>
              <Text style={styles.myShareBadgeText}>
                {formatEnglishNumber(Number(myContribution.share_percentage) || 0, { maximumFractionDigits: 1 })}% من المسافة
              </Text>
            </View>
          </View>

          <View style={styles.myStatsRow}>
            <View style={styles.myStatCol}>
              <Text style={[styles.myStatVal, { color: isDark ? '#F9FAFB' : '#111827' }]}>
                {formatEnglishNumber(Number(myContribution.total_km) || 0, { maximumFractionDigits: 1 })} كم
              </Text>
              <Text style={[styles.myStatLbl, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>المسافة الكلية</Text>
            </View>
            <View style={[styles.myStatDivider, isDark && styles.myStatDividerDark]} />
            <View style={styles.myStatCol}>
              <Text style={[styles.myStatVal, { color: isDark ? '#F9FAFB' : '#111827' }]}>
                {formatSteps(Number(myContribution.total_steps) || 0)}
              </Text>
              <Text style={[styles.myStatLbl, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>الخطوات الكلية</Text>
            </View>
            <View style={[styles.myStatDivider, isDark && styles.myStatDividerDark]} />
            <View style={styles.myStatCol}>
              <Text style={[styles.myStatVal, { color: silaTheme.primary }]}>
                {formatEnglishNumber(Number(myContribution.today_km) || 0, { maximumFractionDigits: 1 })} كم
              </Text>
              <Text style={[styles.myStatLbl, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>مشيت اليوم</Text>
            </View>
          </View>
        </View>
      )}

      {/* Leaderboard Section Title & Participant Count */}
      <View style={styles.sectionHeaderRow}>
        <Text style={[styles.sectionTitle, { color: isDark ? '#F9FAFB' : '#111827' }]}>
          ترتيب المتصدرين في رحلة المشي
        </Text>
        {totalCount > 0 && (
          <View style={[styles.countBadge, isDark && styles.countBadgeDark]}>
            <Text style={[styles.countBadgeText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
              {formatEnglishNumber(totalCount)} مشارك
            </Text>
          </View>
        )}
      </View>
    </View>
  );

  // ذيل القائمة: مؤشر التحميل اللانهائي أو نهاية القائمة
  const renderListFooter = () => {
    if (loadingMore) {
      return (
        <View style={styles.footerLoader}>
          <ActivityIndicator size="small" color={silaTheme.primary} />
          <Text style={[styles.footerText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
            جارٍ تحميل المزيد من المتصدرين...
          </Text>
        </View>
      );
    }
    if (!hasMore && listData.length > 10) {
      return (
        <View style={styles.footerEnd}>
          <Text style={[styles.footerEndText, { color: isDark ? '#6B7280' : '#9CA3AF' }]}>
            تم عرض جميع المشاركين في الرحلة
          </Text>
        </View>
      );
    }
    return null;
  };

  const renderItem = ({ item: c, index }: { item: TrekContributor; index: number }) => {
    const rank = c.rank || index + 1;
    const isTop3 = rank <= 3;
    const rankColor =
      rank === 1 ? '#f59e0b' : rank === 2 ? '#9ca3af' : rank === 3 ? '#d97706' : '#6b7280';

    return (
      <View style={[styles.contributorRow, isDark && styles.contributorRowDark]}>
        {/* Rank Badge */}
        <View
          style={[
            styles.rankBadge,
            isTop3 ? { backgroundColor: rankColor } : (isDark ? styles.rankBadgeNormalDark : styles.rankBadgeNormal),
          ]}
        >
          {isTop3 ? (
            <Ionicons name="trophy" size={15} color="#FFFFFF" />
          ) : (
            <Text style={[styles.rankText, { color: isDark ? '#D1D5DB' : '#4B5563' }]}>
              {formatEnglishNumber(rank)}
            </Text>
          )}
        </View>

        {/* Avatar */}
        {c.avatar ? (
          <Image source={{ uri: c.avatar }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Text style={styles.avatarPlaceholderText}>
              {c.name ? c.name.charAt(0) : '؟'}
            </Text>
          </View>
        )}

        {/* Name & Steps */}
        <View style={styles.nameCol}>
          <Text style={[styles.contributorName, { color: isDark ? '#F9FAFB' : '#111827' }]} numberOfLines={2}>
            {c.name}
          </Text>
          <Text style={[styles.contributorSub, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
            {formatSteps(Number(c.total_steps) || 0)} خطوة
            {c.today_km > 0 ? ` • اليوم: +${formatEnglishNumber(Number(c.today_km) || 0, { maximumFractionDigits: 1 })} كم` : ''}
          </Text>
        </View>

        {/* Total KM */}
        <View style={styles.kmCol}>
          <Text style={[styles.kmVal, { color: isDark ? '#10B981' : '#059669' }]}>
            {formatEnglishNumber(Number(c.total_km) || 0, { maximumFractionDigits: 1 })}
          </Text>
          <Text style={[styles.kmLbl, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>كم</Text>
        </View>
      </View>
    );
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={styles.modalOverlay}>
        <TouchableOpacity style={styles.backdrop} activeOpacity={1} onPress={onClose} />

        <View style={[styles.sheetContent, isDark && styles.sheetContentDark, { paddingBottom: bottomInset }]}>
          {/* Drag Handle Indicator */}
          <View style={styles.handleContainer}>
            <View style={[styles.handleBar, isDark && styles.handleBarDark]} />
          </View>

          {/* Header Close Button */}
          <View style={[styles.header, isDark && styles.headerDark]}>
            <TouchableOpacity
              style={[styles.closeBtn, isDark && styles.closeBtnDark]}
              onPress={onClose}
              activeOpacity={0.7}
              accessibilityLabel="إغلاق"
            >
              <Ionicons name="close" size={20} color={isDark ? '#E5E7EB' : '#4B5563'} />
            </TouchableOpacity>
          </View>

          {/* Paginated Virtualized List (Infinite Scroll) */}
          {loadingInitial ? (
            <View style={styles.centerLoading}>
              <ActivityIndicator size="large" color={silaTheme.primary} />
              <Text style={[styles.loadingText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                جارٍ تحميل المتصدرين...
              </Text>
            </View>
          ) : (
            <FlatList
              data={listData}
              keyExtractor={(item) => String(item.person_id)}
              renderItem={renderItem}
              ListHeaderComponent={renderListHeader}
              ListFooterComponent={renderListFooter}
              contentContainerStyle={[styles.contentContainer, { paddingBottom: bottomInset + 30 }]}
              showsVerticalScrollIndicator={false}
              onEndReached={handleLoadMore}
              onEndReachedThreshold={0.4}
              initialNumToRender={15}
              maxToRenderPerBatch={10}
              windowSize={7}
              ListEmptyComponent={
                <View style={styles.emptyWrap}>
                  <Ionicons name="footsteps" size={36} color="#9CA3AF" style={{ marginBottom: 8 }} />
                  <Text style={[styles.emptyText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                    لم تُسجل مساهمات حتى الآن. ابدأ بالمشي لتتصدر!
                  </Text>
                </View>
              }
            />
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.55)',
  },
  backdrop: {
    flex: 1,
  },
  sheetContent: {
    backgroundColor: '#ffffff',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    maxHeight: '86%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  sheetContentDark: {
    backgroundColor: '#181E29',
    borderTopColor: '#2E384D',
    borderTopWidth: 1,
  },
  handleContainer: {
    alignItems: 'center',
    paddingTop: 10,
    paddingBottom: 4,
  },
  handleBar: {
    width: 44,
    height: 5,
    borderRadius: 2.5,
    backgroundColor: '#d1d5db',
  },
  handleBarDark: {
    backgroundColor: '#4B5563',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingTop: 6,
    paddingBottom: 6,
  },
  headerDark: {},
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeBtnDark: {
    backgroundColor: '#242a36',
  },
  contentContainer: {
    paddingHorizontal: 16,
    paddingTop: 4,
    gap: 8,
  },
  listHeaderWrapper: {
    marginBottom: 6,
  },
  centerLoading: {
    paddingVertical: 40,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Regular',
  },
  myCard: {
    backgroundColor: 'rgba(217, 119, 6, 0.08)',
    borderRadius: 16,
    padding: 14,
    borderWidth: 1,
    borderColor: 'rgba(217, 119, 6, 0.25)',
    marginBottom: 14,
  },
  myCardDark: {
    backgroundColor: 'rgba(217, 119, 6, 0.12)',
    borderColor: 'rgba(217, 119, 6, 0.3)',
  },
  myCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  myCardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  myCardTitle: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Bold',
  },
  myShareBadge: {
    backgroundColor: silaTheme.gold,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  myShareBadgeText: {
    color: '#ffffff',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  myStatsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
  },
  myStatCol: {
    alignItems: 'center',
    flex: 1,
  },
  myStatVal: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
  },
  myStatLbl: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  myStatDivider: {
    width: 1,
    height: 24,
    backgroundColor: 'rgba(0,0,0,0.08)',
  },
  myStatDividerDark: {
    backgroundColor: 'rgba(255,255,255,0.1)',
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
    paddingHorizontal: 2,
  },
  sectionTitle: {
    fontSize: 14.5,
    fontFamily: 'Tajawal-Bold',
  },
  countBadge: {
    backgroundColor: '#F3F4F6',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  countBadgeDark: {
    backgroundColor: '#242A36',
  },
  countBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  contributorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 14,
    padding: 10,
    borderWidth: 1,
    borderColor: '#f3f4f6',
    marginBottom: 6,
  },
  contributorRowDark: {
    backgroundColor: '#242a36',
    borderColor: '#2e3544',
  },
  rankBadge: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 10,
    marginEnd: 10,
  },
  rankBadgeNormal: {
    backgroundColor: '#e5e7eb',
  },
  rankBadgeNormalDark: {
    backgroundColor: '#374151',
  },
  rankText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#e5e7eb',
    marginLeft: 10,
    marginEnd: 10,
  },
  avatarPlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
  },
  avatarPlaceholderText: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  nameCol: {
    flex: 1,
  },
  contributorName: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },
  contributorSub: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  kmCol: {
    alignItems: 'flex-end',
    paddingRight: 4,
  },
  kmVal: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
  },
  kmLbl: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
  },
  emptyWrap: {
    paddingVertical: 36,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  footerLoader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    gap: 8,
  },
  footerText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Regular',
  },
  footerEnd: {
    alignItems: 'center',
    paddingVertical: 16,
  },
  footerEndText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
  },
});
