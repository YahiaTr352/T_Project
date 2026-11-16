import React, { useRef, useState, useEffect, useMemo } from 'react';
import {
  StyleSheet,
  View,
  Text,
  TouchableOpacity,
  Platform,
  useColorScheme,
  ActivityIndicator,
} from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from 'react-native-maps';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import Constants from 'expo-constants';
import { silaTheme } from '@/constants/theme';
import { buildTrekPath, splitTrekPath, downsampleCoords, sliceTrekPathCoords, type TrekRouteSegment } from '@/lib/utils/trekPath';
import { formatEnglishNumber, formatSteps } from '@/lib/utils/numberFormat';
import { useTrekJourneyAnimation } from './useTrekJourneyAnimation';
import type {
  TrekWaypoint,
  TrekCurrentPosition,
  TrekCurrentSegment,
  TrekViewMode,
} from '@/lib/api/walkingTrekApi';

/** ألوان كل نطاق: الكل / جروب / نفسي */
const SCOPE_COLORS: Record<TrekViewMode, string> = {
  community: '#0284c7', // Sky Blue for current active progress pin & active segment
  group: '#8b5cf6',
  personal: '#3b82f6',
};

const SCOPE_PIN_LABEL: Record<TrekViewMode, string> = {
  community: 'طواف العائلة',
  group: 'طواف الجروب',
  personal: 'أنت هنا',
};

interface Props {
  waypoints: TrekWaypoint[];
  currentPosition: TrekCurrentPosition | null;
  currentSegment: TrekCurrentSegment | null;
  /** المسافة الفعلية (كم) التي قطعها النطاق المعروض (الكل/جروب/نفسي) */
  totalWalkedKm: number;
  totalTrekKm: number;
  viewMode?: TrekViewMode;
  scopeLabel?: string;
  onSelectWaypoint?: (wp: TrekWaypoint) => void;
  selectedWaypoint?: TrekWaypoint | null;
  selectedSegment?: TrekRouteSegment | null;
  onSelectSegment?: (segment: TrekRouteSegment | null) => void;
}

// Initial bounding coordinates for Saudi Arabia
const KSA_REGION = {
  latitude: 24.5,
  longitude: 44.0,
  latitudeDelta: 11.5,
  longitudeDelta: 13.0,
};

// Luxury dark map style
const DARK_MAP_STYLE = [
  { elementType: 'geometry', stylers: [{ color: '#1d2228' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#1d2228' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#8e99a2' }] },
  {
    featureType: 'administrative.locality',
    elementType: 'labels.text.fill',
    stylers: [{ color: '#d97706' }],
  },
  {
    featureType: 'poi',
    elementType: 'labels',
    stylers: [{ visibility: 'off' }],
  },
  {
    featureType: 'road',
    elementType: 'geometry',
    stylers: [{ color: '#2b323c' }],
  },
  {
    featureType: 'road',
    elementType: 'geometry.stroke',
    stylers: [{ color: '#21262d' }],
  },
  {
    featureType: 'road.highway',
    elementType: 'geometry',
    stylers: [{ color: '#3b434e' }],
  },
  {
    featureType: 'water',
    elementType: 'geometry',
    stylers: [{ color: '#0f141a' }],
  },
  {
    featureType: 'water',
    elementType: 'labels.text.fill',
    stylers: [{ color: '#3e4e5e' }],
  },
];

interface StaticWaypointMarkersProps {
  waypoints: TrekWaypoint[];
  selectedWaypointId?: number | null;
  isDark: boolean;
  onSelectWaypoint?: (wp: TrekWaypoint) => void;
}

/**
 * دبابيس المحطات الثابتة معزولة في مكون مميز (React.memo):
 * تمنع تماماً إعادة بناء الـ Virtual DOM للمحطات الـ 20+ خلال فريمات الأنيميشن (40fps)،
 * مما يحافظ على سلاسة تامة وتجنب أي ضغط على معالج الأندرويد.
 */
const StaticWaypointMarkers = React.memo(function StaticWaypointMarkers({
  waypoints,
  selectedWaypointId,
  isDark,
  onSelectWaypoint,
}: StaticWaypointMarkersProps) {
  const [tracksViewChanges, setTracksViewChanges] = useState(true);

  useEffect(() => {
    setTracksViewChanges(true);
    const timer = setTimeout(() => {
      setTracksViewChanges(false);
    }, 500);
    return () => clearTimeout(timer);
  }, [waypoints, isDark, selectedWaypointId]);

  return (
    <>
      {waypoints.map((wp, index) => {
        const isStartPoint = index === 0 || wp.sort_order === 1 || wp.status === 'start_point';
        const isCompleted = wp.status === 'completed' && !isStartPoint;
        const isTarget = wp.status === 'current_target';
        const isSelected = selectedWaypointId === wp.id;

        const pinColor = isStartPoint
          ? '#059669'
          : isCompleted
          ? '#10b981'
          : isTarget
          ? silaTheme.gold
          : isDark
          ? '#6b7280'
          : '#9ca3af';

        const titleText = String(wp.city_name || wp.location_title || '');
        const descText = String(wp.location_title || wp.city_name || '');

        return (
          <Marker
            key={wp.id}
            coordinate={{ latitude: wp.latitude, longitude: wp.longitude }}
            title={titleText}
            description={descText}
            onPress={() => onSelectWaypoint?.(wp)}
            anchor={{ x: 0.5, y: 0.88 }}
            tracksViewChanges={tracksViewChanges}
          >
            <View style={styles.pinMarkerWrapper}>
              <Ionicons
                name={isStartPoint ? 'location' : 'location-sharp'}
                size={isSelected ? 32 : 26}
                color={pinColor}
                style={styles.pinDropShadow}
              />
              <View
                style={[
                  styles.markerLabelContainer,
                  isSelected && styles.markerLabelContainerSelected,
                  isStartPoint && styles.markerLabelContainerStart,
                ]}
              >
                <Text style={styles.markerCityLabel} numberOfLines={1}>
                  {titleText}
                </Text>
                {isStartPoint ? (
                  <View style={styles.startPointBadge}>
                    <Text style={styles.startPointBadgeText}>نقطة الانطلاق</Text>
                  </View>
                ) : null}
              </View>
            </View>
          </Marker>
        );
      })}
    </>
  );
});

export function SaudiTrekMap({
  waypoints,
  currentPosition,
  currentSegment,
  totalWalkedKm,
  totalTrekKm,
  viewMode = 'community',
  scopeLabel,
  onSelectWaypoint,
  selectedWaypoint,
  selectedSegment,
  onSelectSegment,
}: Props) {
  const mapRef = useRef<MapView>(null);
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const [mapType, setMapType] = useState<'standard' | 'hybrid'>('standard');
  const [isMapReady, setIsMapReady] = useState(false);

  // Road geometry is decoded once per waypoints change; every animation frame only slices it.
  const trekPath = useMemo(() => buildTrekPath(waypoints), [waypoints]);

  // Hook لإدارة أنيميشن رسم المسار من نقطة الانطلاق حتى آخر نقطة وصلنا لها
  const {
    displayWalkedKm,
    isAnimating,
    walkerPosition,
    walkerFacing,
    startAnimation,
    cancelAnimation,
  } = useTrekJourneyAnimation({
    mapRef,
    isMapReady,
    path: trekPath,
    totalWalkedKm,
    currentPosition,
    viewMode,
  });

  // نقطة الماشي: يتم تفعيل tracksViewChanges عند توفر موقع الماشي أو تغير الاتجاه
  // مع مهلة 1200ms لضمان التقاط البت ماب على هواتف الأندرويد (مثل Realme/Xiaomi) ثم تجميدها لسلاسة 60fps
  const [walkerTracksViewChanges, setWalkerTracksViewChanges] = useState(true);

  useEffect(() => {
    if (!walkerPosition) return;
    setWalkerTracksViewChanges(true);
    const timer = setTimeout(() => {
      setWalkerTracksViewChanges(false);
    }, 1200);
    return () => clearTimeout(timer);
  }, [walkerPosition !== null, walkerFacing, viewMode]);

  // Coordinate array for Polyline and map framing (real road points or fallback to waypoint pins)
  const polylineCoords = trekPath.coords;

  // تقسيم مسار الخريطة: أثناء الحركة نعتمد على Downsampled Trail خفيف جداً لسرعة 60fps بالأندرويد
  const { completedCoords, activeCoords, remainingCoords, animatedDrawnCoords } = useMemo(() => {
    if (isAnimating) {
      const rawTrail = sliceTrekPathCoords(trekPath, displayWalkedKm);
      const smoothTrail = downsampleCoords(rawTrail, 60);
      return {
        completedCoords: [],
        activeCoords: [],
        remainingCoords: [],
        animatedDrawnCoords: smoothTrail,
      };
    }

    const { completed, active, remaining } = splitTrekPath(trekPath, displayWalkedKm);
    return {
      completedCoords: completed,
      activeCoords: active,
      remainingCoords: remaining,
      animatedDrawnCoords: [],
    };
  }, [trekPath, displayWalkedKm, isAnimating]);
  // Fit map to whole Saudi trek (zoom out showing all waypoints & regions across KSA)
  const fitToKSA = () => {
    if (!mapRef.current) return;
    const targetCoords =
      polylineCoords.length > 0
        ? polylineCoords
        : waypoints.map((w) => ({ latitude: w.latitude, longitude: w.longitude }));
    if (!targetCoords.length) return;
    try {
      if (targetCoords.length === 1) {
        mapRef.current.animateToRegion(
          {
            latitude: targetCoords[0].latitude,
            longitude: targetCoords[0].longitude,
            latitudeDelta: 0.12,
            longitudeDelta: 0.12,
          },
          600
        );
        return;
      }
      const isIos = Platform.OS === 'ios';
      mapRef.current.fitToCoordinates(targetCoords, {
        edgePadding: isIos
          ? { top: 70, right: 35, bottom: 220, left: 35 }
          : { top: 90, right: 45, bottom: 260, left: 45 },
        animated: true,
      });
    } catch {
      // Fallback safe catch
    }
  };

  // Focus camera on current family or personal position based on viewMode
  const focusCurrentPosition = () => {
    if (!mapRef.current) return;
    const targetPos = currentPosition;
    if (!targetPos) return;
    try {
      mapRef.current.animateToRegion(
        {
          latitude: targetPos.latitude,
          longitude: targetPos.longitude,
          latitudeDelta: 0.08,
          longitudeDelta: 0.08,
        },
        700
      );
    } catch {
      // safe catch
    }
  };

  // Focus camera on specific waypoint
  const focusWaypoint = (wp: TrekWaypoint) => {
    if (!mapRef.current) return;
    try {
      mapRef.current.animateToRegion(
        {
          latitude: wp.latitude,
          longitude: wp.longitude,
          latitudeDelta: 0.08,
          longitudeDelta: 0.08,
        },
        700
      );
    } catch {
      // safe catch
    }
    onSelectWaypoint?.(wp);
  };

  const prevViewModeRef = useRef(viewMode);
  // Re-center when viewMode switches (only when user actively toggles scope, not on initial load)
  useEffect(() => {
    if (!isMapReady || isAnimating) return;
    if (prevViewModeRef.current !== viewMode) {
      prevViewModeRef.current = viewMode;
      focusCurrentPosition();
    }
  }, [viewMode, isMapReady, isAnimating]);

  // If a waypoint is selected from outside, focus it
  useEffect(() => {
    if (selectedWaypoint && isMapReady && !isAnimating) {
      focusWaypoint(selectedWaypoint);
    }
  }, [selectedWaypoint, isMapReady, isAnimating]);

  // Focus camera to frame the selected segment (both cities & connecting road)
  useEffect(() => {
    if (!selectedSegment || !mapRef.current || !isMapReady) return;
    const coords =
      selectedSegment.polylineCoords && selectedSegment.polylineCoords.length >= 2
        ? selectedSegment.polylineCoords
        : [
            {
              latitude: selectedSegment.fromWaypoint.latitude,
              longitude: selectedSegment.fromWaypoint.longitude,
            },
            {
              latitude: selectedSegment.toWaypoint.latitude,
              longitude: selectedSegment.toWaypoint.longitude,
            },
          ];

    try {
      const isIos = Platform.OS === 'ios';
      mapRef.current.fitToCoordinates(coords, {
        edgePadding: isIos
          ? { top: 130, right: 60, bottom: 270, left: 60 }
          : { top: 150, right: 80, bottom: 310, left: 80 },
        animated: true,
      });
    } catch {
      // safe fallback
    }
  }, [selectedSegment, isMapReady]);

  // When map first loads, mark as ready to trigger journey animation & frame entire KSA trek
  const onMapLayout = () => {
    console.log('[TrekMap] onMapReady fired');
    setIsMapReady(true);
    setTimeout(() => {
      fitToKSA();
    }, 150);
  };

  // Web fallback if running on web browser
  if (Platform.OS === 'web') {
    return (
      <View style={styles.webFallbackContainer}>
        <Ionicons name="map" size={48} color={silaTheme.primary} style={{ marginBottom: 12 }} />
        <Text style={styles.webFallbackTitle}>خريطة مسار المملكة 🇸🇦</Text>
        <Text style={styles.webFallbackDesc}>
          عرض خرائط جوجل Native متاح بالكامل على أجهزة iOS و Android.
        </Text>
        <View style={styles.webStatsBox}>
          <Text style={styles.webStatsText}>
            المسار: {formatEnglishNumber(waypoints.length)} محطة • تم قطع {formatEnglishNumber(totalWalkedKm, { maximumFractionDigits: 1 })} كم من أصل {formatEnglishNumber(totalTrekKm)} كم
          </Text>
        </View>
      </View>
    );
  }

  // On iOS, native Apple Maps (provider=undefined) is lightweight, 60/120fps fluid, and handles unlimited markers cleanly.
  // On Android, Google Maps is the official SDK provider.
  const mapProvider = Platform.OS === 'android' ? PROVIDER_GOOGLE : undefined;

  return (
    <View
      style={styles.container}
      onLayout={(e) => {
        const { width, height } = e.nativeEvent.layout;
        console.log(
          `[TrekMap] layout ${Math.round(width)}x${Math.round(height)} | env=${Constants.executionEnvironment} | provider=${mapProvider ?? 'default'}`
        );
      }}
    >
      <MapView
        ref={mapRef}
        provider={mapProvider}
        style={StyleSheet.absoluteFill}
        onMapLoaded={() => console.log('[TrekMap] onMapLoaded (tiles rendered)')}
        onPanDrag={cancelAnimation}
        initialRegion={KSA_REGION}
        customMapStyle={isDark && mapType === 'standard' ? DARK_MAP_STYLE : undefined}
        mapType={mapType}
        showsCompass={false}
        showsScale={true}
        showsMyLocationButton={false}
        onMapReady={onMapLayout}
      >
        {/* أثناء الحركة: رسم مسار خلفي ثابت (0 تكلفة أداء) وخط وحيد ينمو بسلاسة 60fps لتجنب تجميد معالج الرسوميات */}
        {isAnimating ? (
          <>
            {polylineCoords.length > 1 && (
              <Polyline
                coordinates={polylineCoords}
                strokeColor={isDark ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.15)'}
                strokeWidth={3}
                lineDashPattern={[6, 4]}
                lineCap="round"
              />
            )}
            {animatedDrawnCoords.length > 1 && (
              <Polyline
                coordinates={animatedDrawnCoords}
                strokeColor={SCOPE_COLORS[viewMode] || '#10b981'}
                strokeWidth={5}
                lineCap="round"
                lineJoin="round"
              />
            )}
          </>
        ) : (
          <>
            {completedCoords.length > 1 ? (
              <Polyline
                coordinates={completedCoords}
                strokeColor="#10b981"
                strokeWidth={5}
                lineCap="round"
                lineJoin="round"
              />
            ) : null}

            {activeCoords.length > 1 ? (
              <Polyline
                coordinates={activeCoords}
                strokeColor="#0284c7"
                strokeWidth={5}
                lineCap="round"
                lineJoin="round"
              />
            ) : null}

            {remainingCoords.length > 1 ? (
              <Polyline
                coordinates={remainingCoords}
                strokeColor={isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.22)'}
                strokeWidth={3}
                lineDashPattern={[6, 4]}
                lineCap="round"
              />
            ) : null}
          </>
        )}

        {/* تمييز بصري عريض وساطع للمرحلة المحددة (Selected Segment Highlight Polyline) */}
        {selectedSegment && selectedSegment.polylineCoords.length > 1 && (
          <>
            <Polyline
              coordinates={selectedSegment.polylineCoords}
              strokeColor="rgba(245, 158, 11, 0.45)"
              strokeWidth={11}
              lineCap="round"
              lineJoin="round"
              zIndex={50}
            />
            <Polyline
              coordinates={selectedSegment.polylineCoords}
              strokeColor="#f59e0b"
              strokeWidth={5}
              lineCap="round"
              lineJoin="round"
              zIndex={51}
            />
          </>
        )}

        {/* دبابيس المحطات الثابتة معزولة تماماً في React.memo لتفادي أي فريمات ساقطة أو إعادة رسم */}
        <StaticWaypointMarkers
          waypoints={waypoints}
          selectedWaypointId={selectedWaypoint?.id}
          isDark={isDark}
          onSelectWaypoint={focusWaypoint}
        />

        {/* أيقونة الماشي: نظيفة تماماً بدون أي فقاعات أو زوائد، باصص مع اتجاه المسار وأقدامه على الطريق */}
        {walkerPosition ? (
          <Marker
            key={viewMode}
            coordinate={walkerPosition}
            title={String(scopeLabel || SCOPE_PIN_LABEL[viewMode] || '')}
            description={`${formatEnglishNumber(Number(displayWalkedKm) || 0, { maximumFractionDigits: 1 })} كم مقطوعة`}
            anchor={{ x: 0.5, y: 0.85 }}
            zIndex={1000}
            tracksViewChanges={walkerTracksViewChanges}
          >
            <View style={styles.cleanWalkerWrapper} collapsable={false}>
              <Ionicons
                name="walk"
                size={32}
                color={SCOPE_COLORS[viewMode] || '#0284c7'}
                style={[
                  styles.walkerShadow,
                  walkerFacing === 'left' ? styles.walkerMirrored : undefined,
                ]}
              />
            </View>
          </Marker>
        ) : null}
      </MapView>

      {!isMapReady && (
        <View style={styles.mapLoadingOverlay} pointerEvents="none">
          <ActivityIndicator size="large" color="#10b981" />
          <Text style={styles.mapLoadingText}>جارٍ تحميل الخريطة...</Text>
        </View>
      )}

      {/* شريط معلومات المسافة العائم للمرحلة المحددة (Floating Segment Distance Banner) */}
      {selectedSegment && (
        <View style={[styles.segmentFloatingBanner, isDark && styles.segmentFloatingBannerDark]}>
          <View style={styles.segmentBannerIconWrap}>
            <MaterialCommunityIcons name="map-marker-distance" size={20} color="#f59e0b" />
          </View>
          <View style={styles.segmentBannerTextCol}>
            <Text style={[styles.segmentBannerTitle, { color: isDark ? '#FFFFFF' : '#111827' }]} numberOfLines={1}>
              المرحلة {formatEnglishNumber(selectedSegment.index)}: {selectedSegment.fromCity} ➔ {selectedSegment.toCity}
            </Text>
            <Text style={[styles.segmentBannerSubtitle, { color: isDark ? '#FDE68A' : '#B45309' }]} numberOfLines={1}>
              المسافة بينهما: {formatEnglishNumber(selectedSegment.distanceKm)} كم • (~{formatSteps(selectedSegment.estimatedSteps)} خطوة)
            </Text>
          </View>
          <TouchableOpacity
            style={[styles.segmentBannerCloseBtn, isDark && styles.segmentBannerCloseBtnDark]}
            onPress={() => onSelectSegment?.(null)}
            activeOpacity={0.7}
            accessibilityLabel="إغلاق تفاصيل المرحلة"
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="close" size={16} color={isDark ? '#E5E7EB' : '#4B5563'} />
          </TouchableOpacity>
        </View>
      )}

      {/* أزرار التحكم والتقريب العائمة فائقة الصغر (Minimal Circular Floating Controls) */}
      <View style={styles.controlsContainer}>
        {/* زر إعادة تشغيل الرحلة والأنيميشن (Replay Journey) */}
        <TouchableOpacity
          style={[
            styles.controlBtn,
            isDark && styles.controlBtnDark,
            isAnimating && styles.controlBtnActive,
          ]}
          onPress={() => (isAnimating ? cancelAnimation() : startAnimation(false))}
          activeOpacity={0.75}
          accessibilityLabel={isAnimating ? 'إيقاف الأنيميشن' : 'إعادة تشغيل الرحلة'}
          hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
        >
          <Ionicons
            name={isAnimating ? 'pause' : 'refresh'}
            size={17}
            color={isAnimating ? silaTheme.primary : isDark ? '#fbbf24' : '#d97706'}
          />
        </TouchableOpacity>

        {/* زر التمركز على كامل المسار */}
        <TouchableOpacity
          style={[styles.controlBtn, isDark && styles.controlBtnDark]}
          onPress={fitToKSA}
          activeOpacity={0.75}
          accessibilityLabel="عرض كامل المسار"
          hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
        >
          <MaterialCommunityIcons
            name="map-marker-distance"
            size={18}
            color={isDark ? '#6ee7b7' : '#059669'}
          />
        </TouchableOpacity>

        {/* زر التمركز على موقعنا الحالي */}
        {currentPosition && (
          <TouchableOpacity
            style={[styles.controlBtn, isDark && styles.controlBtnDark]}
            onPress={focusCurrentPosition}
            activeOpacity={0.75}
            accessibilityLabel="موقعنا الحالي"
            hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
          >
            <Ionicons
              name="navigate"
              size={16}
              color={isDark ? '#60a5fa' : '#2563eb'}
            />
          </TouchableOpacity>
        )}

        {/* زر التبديل بين القمر الصناعي والخريطة */}
        <TouchableOpacity
          style={[
            styles.controlBtn,
            isDark && styles.controlBtnDark,
            mapType === 'hybrid' && styles.controlBtnActive,
          ]}
          onPress={() => setMapType((prev) => (prev === 'standard' ? 'hybrid' : 'standard'))}
          activeOpacity={0.75}
          accessibilityLabel={mapType === 'standard' ? 'عرض القمر الصناعي' : 'عرض الخريطة'}
          hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
        >
          <Ionicons
            name={mapType === 'standard' ? 'earth' : 'map'}
            size={17}
            color={mapType === 'hybrid' ? silaTheme.primary : isDark ? '#E5E7EB' : '#374151'}
          />
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  mapLoadingOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#0f172a',
  },
  mapLoadingText: {
    marginTop: 10,
    color: '#e5e7eb',
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  container: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  pinMarkerWrapper: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  pinDropShadow: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.35,
    shadowRadius: 3,
    elevation: 5,
  },
  markerLabelContainer: {
    backgroundColor: 'rgba(15, 23, 42, 0.88)',
    paddingHorizontal: 7,
    paddingVertical: 2.5,
    borderRadius: 6,
    marginTop: -2,
    alignSelf: 'center',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.15)',
    alignItems: 'center',
  },
  markerLabelContainerSelected: {
    borderColor: '#38bdf8',
    backgroundColor: '#0f172a',
  },
  markerLabelContainerStart: {
    borderColor: '#059669',
  },
  startPointBadge: {
    backgroundColor: '#059669',
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 4,
    marginTop: 2,
  },
  startPointBadgeText: {
    color: '#ffffff',
    fontSize: 8.5,
    fontFamily: 'Tajawal-Bold',
  },
  markerCityLabel: {
    color: '#ffffff',
    fontSize: 10.5,
    fontFamily: 'Tajawal-Bold',
  },
  cleanWalkerWrapper: {
    alignItems: 'center',
    justifyContent: 'center',
    width: 36,
    height: 36,
  },
  walkerShadow: {
    textShadowColor: 'rgba(0, 0, 0, 0.85)',
    textShadowOffset: { width: 0, height: 1.5 },
    textShadowRadius: 3.5,
  },
  walkerMirrored: {
    transform: [{ scaleX: -1 }],
  },
  segmentFloatingBanner: {
    position: 'absolute',
    top: 14,
    right: 14,
    left: 64,
    backgroundColor: 'rgba(255, 255, 255, 0.96)',
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 9,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.18,
    shadowRadius: 6,
    elevation: 8,
    borderWidth: 1.5,
    borderColor: '#f59e0b',
    zIndex: 20,
  },
  segmentFloatingBannerDark: {
    backgroundColor: 'rgba(24, 30, 41, 0.96)',
    borderColor: '#f59e0b',
  },
  segmentBannerIconWrap: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  segmentBannerTextCol: {
    flex: 1,
  },
  segmentBannerTitle: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Bold',
  },
  segmentBannerSubtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    marginTop: 1,
  },
  segmentBannerCloseBtn: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  segmentBannerCloseBtnDark: {
    backgroundColor: '#374151',
  },
  controlsContainer: {
    position: 'absolute',
    top: 14,
    left: 14,
    gap: 8,
    zIndex: 10,
  },
  controlBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: 'rgba(255, 255, 255, 0.94)',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 4,
    borderWidth: 1,
    borderColor: 'rgba(0, 0, 0, 0.08)',
  },
  controlBtnDark: {
    backgroundColor: 'rgba(30, 41, 59, 0.92)',
    borderColor: 'rgba(255, 255, 255, 0.12)',
  },
  controlBtnActive: {
    borderColor: silaTheme.primary,
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
  },
  webFallbackContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f3f4f6',
    padding: 24,
  },
  webFallbackTitle: {
    fontSize: 20,
    fontFamily: 'Tajawal-Bold',
    color: '#111827',
    marginBottom: 8,
  },
  webFallbackDesc: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    color: '#6b7280',
    textAlign: 'center',
    lineHeight: 20,
  },
  webStatsBox: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    marginTop: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  webStatsText: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
});
