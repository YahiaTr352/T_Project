import React, { useState, useMemo } from 'react';
import {
  StyleSheet,
  View,
  Text,
  Modal,
  TouchableOpacity,
  ScrollView,
  useColorScheme,
  LayoutAnimation,
  Platform,
  UIManager,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { silaTheme } from '@/constants/theme';
import type { TrekWaypoint } from '@/lib/api/walkingTrekApi';
import { buildTrekSegments, type TrekRouteSegment } from '@/lib/utils/trekPath';
import { formatEnglishNumber, formatSteps } from '@/lib/utils/numberFormat';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
  UIManager.setLayoutAnimationEnabledExperimental(true);
}

interface Props {
  visible: boolean;
  onClose: () => void;
  waypoints: TrekWaypoint[];
  onSelectWaypoint?: (wp: TrekWaypoint) => void;
  selectedWaypointId?: number | null;
  selectedSegmentId?: string | null;
  onSelectSegment?: (segment: TrekRouteSegment) => void;
}

export function TrekWaypointsSheet({
  visible,
  onClose,
  waypoints,
  onSelectWaypoint,
  selectedWaypointId,
  selectedSegmentId,
  onSelectSegment,
}: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();
  const bottomInset = Math.max(insets.bottom, 20);

  // اشتقاق مراحل المسار الثنائية (الدمام ➔ الرياض + المسافة)
  const segments = useMemo(() => buildTrekSegments(waypoints), [waypoints]);

  // المرحلة الموسعة داخل القائمة لعرض تفاصيل المسافة
  const [expandedId, setExpandedId] = useState<string | null>(null);

  const toggleExpand = (segId: string) => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setExpandedId((prev) => (prev === segId ? null : segId));
  };

  const handleSelectSegmentOnMap = (segment: TrekRouteSegment) => {
    onSelectSegment?.(segment);
    onSelectWaypoint?.(segment.toWaypoint);
    onClose();
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

          {/* Header */}
          <View style={[styles.header, isDark && styles.headerDark]}>
            <View style={styles.headerTitleRow}>
              <View style={[styles.headerIconWrap, isDark && styles.headerIconWrapDark]}>
                <Ionicons name="trail-sign" size={20} color={silaTheme.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.headerTitle, { color: isDark ? '#F9FAFB' : '#111827' }]}>
                  مراحل ومسار الرحلة
                </Text>
                <Text style={[styles.headerSubtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                  اضغط على أي مرحلة لعرض المسافة بين المدينتين وتحديدها على الخريطة
                </Text>
              </View>
            </View>
            <TouchableOpacity
              style={[styles.closeBtn, isDark && styles.closeBtnDark]}
              onPress={onClose}
              activeOpacity={0.7}
              accessibilityLabel="إغلاق"
            >
              <Ionicons name="close" size={20} color={isDark ? '#E5E7EB' : '#4B5563'} />
            </TouchableOpacity>
          </View>

          {/* List of Route Segments */}
          <ScrollView
            contentContainerStyle={[styles.listContainer, { paddingBottom: bottomInset + 30 }]}
            showsVerticalScrollIndicator={false}
          >
            {segments.length === 0 ? (
              <View style={styles.emptyContainer}>
                {waypoints.length === 1 ? (
                  <View style={[styles.singleWpCard, isDark && styles.singleWpCardDark]}>
                    <View style={styles.singleWpIcon}>
                      <Ionicons name="flag" size={22} color="#059669" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.singleWpTitle, { color: isDark ? '#F9FAFB' : '#111827' }]}>
                        نقطة الانطلاق: {waypoints[0].city_name}
                      </Text>
                      <Text style={[styles.singleWpDesc, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                        بانتظار إضافة محطة إضافية لبدء أول مرحلة في المسار.
                      </Text>
                    </View>
                  </View>
                ) : (
                  <Text style={[styles.emptyText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                    لا توجد محطات مسجلة في هذا المسار حالياً.
                  </Text>
                )}
              </View>
            ) : (
              segments.map((seg) => {
                const isSelected = selectedSegmentId === seg.id || selectedWaypointId === seg.toWaypoint.id;
                const isExpanded = expandedId === seg.id;
                const isCompleted = seg.status === 'completed';
                const isCurrent = seg.status === 'current';

                return (
                  <View
                    key={seg.id}
                    style={[
                      styles.segmentCard,
                      isDark && styles.segmentCardDark,
                      isSelected && styles.segmentCardSelected,
                      isCurrent && styles.segmentCardCurrent,
                    ]}
                  >
                    {/* Header Row: Stage Number & Status Pill */}
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={() => toggleExpand(seg.id)}
                      style={styles.cardHeaderTouchable}
                    >
                      <View style={styles.stageIndexPill}>
                        <Text style={styles.stageIndexText}>المرحلة {formatEnglishNumber(seg.index)}</Text>
                      </View>

                      <View style={styles.stageStatusWrap}>
                        {isCompleted && (
                          <View style={styles.statusPillCompleted}>
                            <Ionicons name="checkmark-circle" size={12} color="#10b981" style={{ marginRight: 3 }} />
                            <Text style={styles.statusPillTextCompleted}>تم الوصول</Text>
                          </View>
                        )}
                        {isCurrent && (
                          <View style={styles.statusPillCurrent}>
                            <Ionicons name="time" size={12} color={silaTheme.gold} style={{ marginRight: 3 }} />
                            <Text style={styles.statusPillTextCurrent}>المرحلة الحالية</Text>
                          </View>
                        )}
                        {!isCompleted && !isCurrent && (
                          <View style={[styles.statusPillUpcoming, isDark && styles.statusPillUpcomingDark]}>
                            <Ionicons name="location-outline" size={12} color="#9ca3af" style={{ marginRight: 3 }} />
                            <Text style={styles.statusPillTextUpcoming}>قادمة</Text>
                          </View>
                        )}
                      </View>
                    </TouchableOpacity>

                    {/* Main Cities & Distance Row: From City ➔ To City + Distance */}
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={() => toggleExpand(seg.id)}
                      style={styles.citiesConnectingRow}
                    >
                      {/* From City */}
                      <View style={styles.cityEndpoint}>
                        <Text style={[styles.endpointCityName, { color: isDark ? '#FFFFFF' : '#111827' }]} numberOfLines={1}>
                          {seg.fromCity}
                        </Text>
                      </View>

                      {/* Direction Arrow & Distance Badge */}
                      <View style={styles.distanceBridgeCol}>
                        <View style={[styles.distancePillBadge, isDark && styles.distancePillBadgeDark]}>
                          <Ionicons name="arrow-back" size={11} color={silaTheme.primary} style={{ marginRight: 2 }} />
                          <Text style={styles.distancePillText}>
                            {formatEnglishNumber(seg.distanceKm)} كم
                          </Text>
                        </View>
                        <View style={[styles.connectingLine, isDark && styles.connectingLineDark]} />
                      </View>

                      {/* To City */}
                      <View style={[styles.cityEndpoint, styles.cityEndpointEnd]}>
                        <Text
                          style={[
                            styles.endpointCityName,
                            { color: isCurrent ? silaTheme.gold : isCompleted ? '#10b981' : isDark ? '#FFFFFF' : '#111827' },
                          ]}
                          numberOfLines={1}
                        >
                          {seg.toCity}
                        </Text>
                      </View>
                    </TouchableOpacity>

                    {/* Quick Distance / Steps Info Line */}
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={() => toggleExpand(seg.id)}
                      style={[styles.quickMetricsRow, isDark && styles.quickMetricsRowDark]}
                    >
                      <Text style={[styles.quickMetricText, { color: isDark ? '#9CA3AF' : '#6B7280' }]} numberOfLines={1}>
                        المسافة: <Text style={{ fontWeight: 'bold', color: isDark ? '#F9FAFB' : '#111827' }}>{formatEnglishNumber(seg.distanceKm)} كم</Text>
                        {'  •  '}الخطوات: <Text style={{ fontWeight: 'bold', color: isDark ? '#F9FAFB' : '#111827' }}>~{formatSteps(seg.estimatedSteps)}</Text>
                        {'  •  '}<Text style={{ color: silaTheme.primary, fontWeight: '700' }}>التراكمي: {formatEnglishNumber(seg.cumulativeKm)} كم</Text>
                      </Text>
                    </TouchableOpacity>

                    {/* Expandable Details Box */}
                    {isExpanded && (
                      <View style={[styles.expandedBox, isDark && styles.expandedBoxDark]}>
                        {(seg.fromLocation || seg.toLocation) ? (
                          <View style={styles.expandedLocationsRow}>
                            <Text style={[styles.expandedLocationText, { color: isDark ? '#D1D5DB' : '#4B5563' }]} numberOfLines={1}>
                              {seg.fromLocation || seg.fromCity} ➔ {seg.toLocation || seg.toCity}
                            </Text>
                          </View>
                        ) : null}

                        <View style={styles.expandedDetailsGrid}>
                          <View style={styles.expandedDetailItem}>
                            <MaterialCommunityIcons name="map-marker-distance" size={16} color={silaTheme.primary} />
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.expandedDetailLabel, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                                المسافة المباشرة
                              </Text>
                              <Text style={[styles.expandedDetailValue, { color: isDark ? '#FFFFFF' : '#111827' }]}>
                                {formatEnglishNumber(seg.distanceKm)} كم
                              </Text>
                            </View>
                          </View>

                          <View style={styles.expandedDetailItem}>
                            <Ionicons name="walk" size={16} color="#3b82f6" />
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.expandedDetailLabel, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
                                الخطوات المطلوبة
                              </Text>
                              <Text style={[styles.expandedDetailValue, { color: isDark ? '#FFFFFF' : '#111827' }]}>
                                ~{formatSteps(seg.estimatedSteps)} خطوة
                              </Text>
                            </View>
                          </View>
                        </View>

                        {/* View & Focus on Map CTA Button */}
                        <TouchableOpacity
                          style={styles.viewOnMapBtn}
                          activeOpacity={0.85}
                          onPress={() => handleSelectSegmentOnMap(seg)}
                        >
                          <Ionicons name="navigate-circle" size={18} color="#FFFFFF" style={{ marginRight: 6 }} />
                          <Text style={styles.viewOnMapBtnText}>
                            عرض المسافة والمسار على الخريطة
                          </Text>
                        </TouchableOpacity>
                      </View>
                    )}
                  </View>
                );
              })
            )}
          </ScrollView>
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
    maxHeight: '85%',
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
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 14,
    borderBottomWidth: 1,
    borderColor: 'rgba(0,0,0,0.06)',
  },
  headerDark: {
    borderColor: 'rgba(255,255,255,0.08)',
  },
  headerTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 10,
  },
  headerIconWrap: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: 'rgba(5, 150, 105, 0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerIconWrapDark: {
    backgroundColor: 'rgba(5, 150, 105, 0.2)',
  },
  headerTitle: {
    fontSize: 16.5,
    fontFamily: 'Tajawal-Bold',
  },
  headerSubtitle: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 10,
  },
  closeBtnDark: {
    backgroundColor: '#242a36',
  },
  listContainer: {
    padding: 12,
    gap: 8,
  },
  emptyContainer: {
    paddingVertical: 32,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  singleWpCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 14,
    backgroundColor: '#F3F4F6',
    gap: 12,
  },
  singleWpCardDark: {
    backgroundColor: '#242A36',
  },
  singleWpIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  singleWpTitle: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  singleWpDesc: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  segmentCard: {
    backgroundColor: '#f9fafb',
    borderRadius: 14,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderWidth: 1.5,
    borderColor: '#e5e7eb',
    minHeight: 88,
    justifyContent: 'center',
  },
  segmentCardDark: {
    backgroundColor: '#242a36',
    borderColor: '#2e3544',
  },
  segmentCardSelected: {
    borderColor: '#3b82f6',
    backgroundColor: 'rgba(59, 130, 246, 0.06)',
  },
  segmentCardCurrent: {
    borderColor: silaTheme.goldLight,
    backgroundColor: 'rgba(217, 119, 6, 0.06)',
  },
  cardHeaderTouchable: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  stageIndexPill: {
    backgroundColor: 'rgba(5, 150, 105, 0.1)',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 6,
  },
  stageIndexText: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Bold',
    color: '#059669',
  },
  stageStatusWrap: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  statusPillCompleted: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 6,
  },
  statusPillTextCompleted: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    color: '#10b981',
  },
  statusPillCurrent: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(217, 119, 6, 0.15)',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 6,
  },
  statusPillTextCurrent: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.gold,
  },
  statusPillUpcoming: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e5e7eb',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 6,
  },
  statusPillUpcomingDark: {
    backgroundColor: '#374151',
  },
  statusPillTextUpcoming: {
    fontSize: 10,
    fontFamily: 'Tajawal-Medium',
    color: '#6b7280',
  },
  citiesConnectingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 2,
    gap: 6,
  },
  cityEndpoint: {
    flex: 1,
    alignItems: 'flex-start',
  },
  cityEndpointEnd: {
    alignItems: 'flex-end',
  },
  endpointCityName: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 18,
  },
  distanceBridgeCol: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  distancePillBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: 'rgba(5, 150, 105, 0.25)',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 1,
    elevation: 1,
    zIndex: 2,
  },
  distancePillBadgeDark: {
    backgroundColor: '#1E293B',
    borderColor: 'rgba(5, 150, 105, 0.4)',
  },
  distancePillText: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  connectingLine: {
    width: '100%',
    height: 1,
    backgroundColor: '#e5e7eb',
    position: 'absolute',
    top: '50%',
    zIndex: 1,
  },
  connectingLineDark: {
    backgroundColor: '#374151',
  },
  quickMetricsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
    paddingTop: 4,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(0,0,0,0.06)',
  },
  quickMetricsRowDark: {
    borderColor: 'rgba(255,255,255,0.08)',
  },
  quickMetricText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    lineHeight: 14,
  },
  expandedBox: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderColor: 'rgba(0,0,0,0.06)',
  },
  expandedBoxDark: {
    borderColor: 'rgba(255,255,255,0.08)',
  },
  expandedLocationsRow: {
    marginBottom: 6,
    alignItems: 'center',
  },
  expandedLocationText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  expandedDetailsGrid: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 8,
  },
  expandedDetailItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(0,0,0,0.03)',
    padding: 7,
    borderRadius: 8,
  },
  expandedDetailLabel: {
    fontSize: 9.5,
    fontFamily: 'Tajawal-Regular',
  },
  expandedDetailValue: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Bold',
    marginTop: 1,
  },
  viewOnMapBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: silaTheme.primary,
    paddingVertical: 9,
    paddingHorizontal: 14,
    borderRadius: 10,
    marginTop: 4,
  },
  viewOnMapBtnText: {
    color: '#FFFFFF',
    fontSize: 12.5,
    fontFamily: 'Tajawal-Bold',
  },
});
