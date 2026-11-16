import React, { useState } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ActivityIndicator,
  TouchableOpacity,
  useColorScheme,
  StatusBar,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { Colors, silaTheme } from '@/constants/theme';
import HeaderBar from '@/components/headerBar';
import { useWalkingTrek } from '@/hooks/use-walking-trek';
import { SaudiTrekMap } from '@/components/walking/trek/SaudiTrekMap';
import { SaudiTrekHUD, type TrekScopeStats } from '@/components/walking/trek/SaudiTrekHUD';
import { useTrekScope } from '@/hooks/use-trek-scope';
import { TrekWaypointsSheet } from '@/components/walking/trek/TrekWaypointsSheet';
import { TrekContributorsModal } from '@/components/walking/trek/TrekContributorsModal';

export default function WalkingTrekScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const {
    trekData,
    loading,
    refreshing,
    error,
    refresh,
    selectedWaypoint,
    setSelectedWaypoint,
    selectedSegment,
    setSelectedSegment,
  } = useWalkingTrek();

  const [showWaypointsSheet, setShowWaypointsSheet] = useState(false);
  const [showContributorsModal, setShowContributorsModal] = useState(false);
  const { viewMode, setViewMode, groups, selectedGroupId, setSelectedGroupId, scope } =
    useTrekScope(trekData);

  const scopeStats: TrekScopeStats | null = scope
    ? { totalKm: scope.km, totalSteps: scope.steps, progressPercentage: scope.progress, label: scope.label }
    : null;

  return (
    <View style={[styles.screenContainer, isDark && styles.screenContainerDark]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* شريط العنوان الموحد للتطبيق */}
      <HeaderBar
        title={
          trekData?.trek?.title && trekData.trek.title !== 'طواف المملكة'
            ? trekData.trek.title
            : 'المشي على الخريطة'
        }
        showBack
        onBackPress={() => router.back()}
      >
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() => refresh()}
          disabled={refreshing}
          style={[styles.headerActionBtn, { backgroundColor: colors.primary + '18' }]}
          accessibilityLabel="تحديث"
        >
          {refreshing ? (
            <ActivityIndicator size="small" color={colors.primary} />
          ) : (
            <Ionicons
              name="reload"
              size={18}
              color={colors.primary}
            />
          )}
        </TouchableOpacity>
      </HeaderBar>

      {/* محتوى الشاشة */}
      {loading && !trekData ? (
        <View style={styles.centerContainer}>
          <ActivityIndicator size="large" color={silaTheme.primary} />
          <Text style={[styles.loadingText, isDark && styles.textDark]}>
            جاري تحميل مسار المسابقة...
          </Text>
        </View>
      ) : trekData && !trekData.trek && trekData.next_scheduled_trek ? (
        <View style={styles.centerContainer}>
          <View style={[styles.emptyIconWrap, isDark && styles.emptyIconWrapDark]}>
            <Ionicons name="time" size={42} color={silaTheme.gold} />
          </View>
          <Text style={[styles.emptyTitle, isDark && styles.textDark]}>
            المسابقة القادمة مجدولة
          </Text>
          <Text style={[styles.headerTitle, { color: silaTheme.primary, marginVertical: 6 }]}>
            «{trekData.next_scheduled_trek.title}»
          </Text>
          <Text style={[styles.emptySubtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
            تبدأ المسابقة رسمياً في:{'\n'}
            <Text style={{ fontWeight: 'bold' }}>{trekData.next_scheduled_trek.scheduled_at}</Text> (توقيت مكة المكرمة)
          </Text>
          <Text style={[styles.emptySubtitle, { color: isDark ? '#9CA3AF' : '#6B7280', marginTop: 2 }]}>
            المسافة الإجمالية: {trekData.next_scheduled_trek.total_distance_km} كم عبر {trekData.next_scheduled_trek.waypoints_count} محطة.{'\n'}
            استعدوا للمشاركة وجمع الخطوات فور انطلاق الرحلة!
          </Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => refresh()} activeOpacity={0.85}>
            <Ionicons name="refresh" size={16} color="#ffffff" style={{ marginRight: 6 }} />
            <Text style={styles.retryBtnText}>تحديث</Text>
          </TouchableOpacity>
        </View>
      ) : error || !trekData || !trekData.trek || !scope || !scopeStats ? (
        <View style={styles.centerContainer}>
          <View style={[styles.emptyIconWrap, isDark && styles.emptyIconWrapDark]}>
            <MaterialCommunityIcons name="map-marker-distance" size={42} color={silaTheme.primary} />
          </View>
          <Text style={[styles.emptyTitle, isDark && styles.textDark]}>
            لا يوجد مسار مسابقة نشط حالياً
          </Text>
          <Text style={[styles.emptySubtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
            مسار المسابقة متوقف مؤقتاً أو سيتم تفعيله قريباً من لوحة التحكم.
          </Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => refresh()} activeOpacity={0.85}>
            <Ionicons name="refresh" size={16} color="#ffffff" style={{ marginRight: 6 }} />
            <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <View style={styles.mapWrapper}>
          {/* 1. الخريطة التفاعلية */}
          <SaudiTrekMap
            waypoints={scope.waypoints}
            currentPosition={scope.position}
            currentSegment={scope.segment}
            totalWalkedKm={scope.km}
            totalTrekKm={trekData.trek.total_distance_km}
            viewMode={viewMode}
            scopeLabel={viewMode === 'group' ? scope.label : undefined}
            selectedWaypoint={selectedWaypoint}
            onSelectWaypoint={(wp) => setSelectedWaypoint(wp)}
            selectedSegment={selectedSegment}
            onSelectSegment={(seg) => setSelectedSegment(seg)}
          />

          {/* 2. بطاقة المعلومات HUD القابلة للطي والإخفاء */}
          <SaudiTrekHUD
            trek={trekData.trek}
            currentSegment={scope.segment}
            scopeStats={scopeStats}
            viewMode={viewMode}
            onToggleViewMode={(mode) => setViewMode(mode)}
            groups={groups}
            selectedGroupId={selectedGroupId}
            onSelectGroup={setSelectedGroupId}
            onOpenWaypoints={() => setShowWaypointsSheet(true)}
            onOpenContributors={() => setShowContributorsModal(true)}
          />

          {/* 3. نافذة قائمة المحطات */}
          <TrekWaypointsSheet
            visible={showWaypointsSheet}
            onClose={() => setShowWaypointsSheet(false)}
            waypoints={scope.waypoints}
            selectedWaypointId={selectedWaypoint?.id}
            onSelectWaypoint={(wp) => setSelectedWaypoint(wp)}
            selectedSegmentId={selectedSegment?.id}
            onSelectSegment={(seg) => setSelectedSegment(seg)}
          />

          {/* 4. نافذة لوحة شرف المتصدرين */}
          <TrekContributorsModal
            visible={showContributorsModal}
            onClose={() => setShowContributorsModal(false)}
            contributors={scope.contributors}
            myContribution={trekData.my_contribution}
            trekId={trekData.trek?.id}
            groupId={viewMode === 'group' && selectedGroupId ? selectedGroupId : undefined}
          />
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screenContainer: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  screenContainerDark: {
    backgroundColor: '#0F172A',
  },
  headerActionBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
  },
  mapWrapper: {
    flex: 1,
    position: 'relative',
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    color: '#6b7280',
  },
  emptyIconWrap: {
    width: 76,
    height: 76,
    borderRadius: 38,
    backgroundColor: 'rgba(5, 150, 105, 0.12)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  emptyIconWrapDark: {
    backgroundColor: 'rgba(5, 150, 105, 0.2)',
  },
  emptyTitle: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
    color: '#111827',
    marginBottom: 6,
  },
  emptySubtitle: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 20,
  },
  textDark: {
    color: '#F9FAFB',
  },
  retryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: silaTheme.primary,
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 12,
  },
  retryBtnText: {
    color: '#ffffff',
    fontSize: 13.5,
    fontFamily: 'Tajawal-Bold',
  },
});
