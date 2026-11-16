import React, { useState } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ActivityIndicator,
  TouchableOpacity,
  useColorScheme,
} from 'react-native';
import { silaTheme } from '@/constants/theme';
import { useWalkingTrek } from '@/hooks/use-walking-trek';
import { useTrekScope } from '@/hooks/use-trek-scope';
import { SaudiTrekMap } from './SaudiTrekMap';
import { SaudiTrekHUD, type TrekScopeStats } from './SaudiTrekHUD';
import { TrekWaypointsSheet } from './TrekWaypointsSheet';
import { TrekContributorsModal } from './TrekContributorsModal';
import type { ActiveTrekResponseData } from '@/lib/api/walkingTrekApi';

interface Props {
  groupId?: number;
  initialData?: ActiveTrekResponseData | null;
}

export function SaudiTrekTab({ groupId, initialData }: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const {
    trekData,
    loading,
    error,
    refresh,
    selectedWaypoint,
    setSelectedWaypoint,
    selectedSegment,
    setSelectedSegment,
  } = useWalkingTrek(groupId, initialData);

  const [showWaypointsSheet, setShowWaypointsSheet] = useState(false);
  const [showContributorsModal, setShowContributorsModal] = useState(false);
  const { viewMode, setViewMode, groups, selectedGroupId, setSelectedGroupId, scope } =
    useTrekScope(trekData);

  if (loading && !trekData) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color={silaTheme.primary} />
        <Text style={[styles.loadingText, isDark && styles.textDark]}>
          جاري تحميل مسار المسابقة...
        </Text>
      </View>
    );
  }

  if (error || !trekData) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.emptyIcon}>🗺️</Text>
        <Text style={[styles.emptyTitle, isDark && styles.textDark]}>
          تعذر تحميل بيانات مسار المسابقة
        </Text>
        <Text style={styles.emptySubtitle}>
          يرجى التحقق من اتصال الإنترنت والمحاولة مرة أخرى.
        </Text>
        <TouchableOpacity style={styles.retryBtn} onPress={() => refresh()}>
          <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (!trekData.trek) {
    if (trekData.next_scheduled_trek) {
      const scheduled = trekData.next_scheduled_trek;
      return (
        <View style={styles.centerContainer}>
          <Text style={styles.emptyIcon}>⏳</Text>
          <Text style={[styles.emptyTitle, isDark && styles.textDark]}>
            المسابقة القادمة مجدولة
          </Text>
          <Text style={[styles.emptyTitle, { color: silaTheme.primary, fontSize: 16, marginTop: 4 }]}>
            «{scheduled.title}»
          </Text>
          <Text style={styles.emptySubtitle}>
            تبدأ المسابقة رسمياً في:{'\n'}
            <Text style={{ fontWeight: 'bold' }}>{scheduled.scheduled_at}</Text> (توقيت مكة المكرمة)
          </Text>
          <Text style={[styles.emptySubtitle, { marginTop: 2 }]}>
            المسافة: {scheduled.total_distance_km} كم • {scheduled.waypoints_count} محطة.{'\n'}
            استعدوا لجمع الخطوات فور انطلاق الرحلة!
          </Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => refresh()}>
            <Text style={styles.retryBtnText}>تحديث</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return (
      <View style={styles.centerContainer}>
        <Text style={styles.emptyIcon}>🗺️</Text>
        <Text style={[styles.emptyTitle, isDark && styles.textDark]}>
          لا يوجد مسار مسابقة نشط حالياً
        </Text>
        <Text style={styles.emptySubtitle}>
          مسار المسابقة متوقف مؤقتاً أو سيتم تفعيله قريباً من لوحة التحكم.
        </Text>
        <TouchableOpacity style={styles.retryBtn} onPress={() => refresh()}>
          <Text style={styles.retryBtnText}>إعادة المحاولة</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const { trek, my_contribution } = trekData;

  if (!scope) return null;

  const scopeStats: TrekScopeStats = {
    totalKm: scope.km,
    totalSteps: scope.steps,
    progressPercentage: scope.progress,
    label: scope.label,
  };
  return (
    <View style={styles.container}>
      {/* 1. Google Maps View */}
      <View style={styles.mapContainer}>
        <SaudiTrekMap
          waypoints={scope.waypoints}
          currentPosition={scope.position}
          currentSegment={scope.segment}
          totalWalkedKm={scope.km}
          totalTrekKm={trek.total_distance_km}
          viewMode={viewMode}
          scopeLabel={viewMode === 'group' ? scope.label : undefined}
          selectedWaypoint={selectedWaypoint}
          onSelectWaypoint={(wp) => setSelectedWaypoint(wp)}
          selectedSegment={selectedSegment}
          onSelectSegment={(seg) => setSelectedSegment(seg)}
        />
      </View>

      {/* 2. Floating Telemetry HUD Card */}
      <SaudiTrekHUD
        trek={trek}
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

      {/* 3. Waypoints List Sheet */}
      <TrekWaypointsSheet
        visible={showWaypointsSheet}
        onClose={() => setShowWaypointsSheet(false)}
        waypoints={scope.waypoints}
        selectedWaypointId={selectedWaypoint?.id}
        onSelectWaypoint={(wp) => {
          setSelectedWaypoint(wp);
        }}
        selectedSegmentId={selectedSegment?.id}
        onSelectSegment={(seg) => setSelectedSegment(seg)}
      />

      {/* 4. Contributors Leaderboard Modal */}
      <TrekContributorsModal
        visible={showContributorsModal}
        onClose={() => setShowContributorsModal(false)}
        contributors={scope.contributors}
        myContribution={my_contribution}
        trekId={trek.id}
        groupId={viewMode === 'group' && selectedGroupId ? selectedGroupId : undefined}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    position: 'relative',
  },
  mapContainer: {
    flex: 1,
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
    color: '#6b7280',
    fontWeight: '600',
  },
  emptyIcon: {
    fontSize: 48,
    marginBottom: 12,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 6,
  },
  emptySubtitle: {
    fontSize: 13,
    color: '#6b7280',
    textAlign: 'center',
    marginBottom: 18,
    lineHeight: 20,
  },
  textDark: {
    color: '#f9fafb',
  },
  retryBtn: {
    backgroundColor: silaTheme.primary,
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 12,
  },
  retryBtnText: {
    color: '#ffffff',
    fontSize: 13.5,
    fontWeight: '700',
  },
});
