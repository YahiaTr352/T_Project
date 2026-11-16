import { decodePolyline, haversineKm, type LatLng } from './polylineDecoder';
import type { TrekWaypoint } from '@/lib/api/walkingTrekApi';

/** مرحلة مسار بين مدينتين/محطتين متعاقبتين */
export interface TrekRouteSegment {
  id: string;
  index: number;
  fromWaypoint: TrekWaypoint;
  toWaypoint: TrekWaypoint;
  fromCity: string;
  toCity: string;
  fromLocation: string;
  toLocation: string;
  distanceKm: number;
  cumulativeKm: number;
  estimatedSteps: number;
  status: 'completed' | 'current' | 'upcoming';
  polylineCoords: LatLng[];
}

/** الحد الأدنى من بيانات المحطة اللازمة لبناء مسار الطريق */
export interface TrekPathWaypoint {
  latitude: number;
  longitude: number;
  cumulative_km: number;
  route_polyline?: string | null;
}

/**
 * مسار الرحلة الكامل مفكوكاً مرة واحدة، مع المسافة التراكمية (كم) لكل نقطة.
 * يسمح بإيجاد أي موقع على الطريق بالبحث الثنائي بدلاً من فك الـ polylines في كل frame.
 */
export interface TrekPath {
  coords: LatLng[];
  /** المسافة التراكمية عند كل نقطة في `coords` (متزايدة دائماً) */
  cumKm: number[];
  /** موقع كل محطة داخل `coords` */
  waypointIndices: number[];
  startKm: number;
  totalKm: number;
}

export const EMPTY_TREK_PATH: TrekPath = {
  coords: [],
  cumKm: [],
  waypointIndices: [],
  startKm: 0,
  totalKm: 0,
};

const toKm = (value: unknown): number => {
  const km = Number(value);
  return Number.isFinite(km) ? km : 0;
};

const toLatLng = (wp: TrekPathWaypoint): LatLng => ({
  latitude: wp.latitude,
  longitude: wp.longitude,
});

export function buildTrekPath(waypoints: readonly TrekPathWaypoint[]): TrekPath {
  if (waypoints.length === 0) return EMPTY_TREK_PATH;

  const first = waypoints[0];
  const startKm = toKm(first.cumulative_km);
  const coords: LatLng[] = [toLatLng(first)];
  const cumKm: number[] = [startKm];
  const waypointIndices: number[] = [0];

  for (let w = 1; w < waypoints.length; w++) {
    const from = waypoints[w - 1];
    const to = waypoints[w];
    const kmFrom = cumKm[cumKm.length - 1];
    // Guards against malformed server data so cumKm stays monotonic (binary search relies on it).
    const kmTo = Math.max(kmFrom, toKm(to.cumulative_km));

    const decoded = to.route_polyline ? decodePolyline(to.route_polyline) : [];
    const road = decoded.length >= 2 ? decoded : [toLatLng(from), toLatLng(to)];

    const stepLengths: number[] = [];
    let roadLength = 0;
    for (let j = 1; j < road.length; j++) {
      const d = haversineKm(road[j - 1].latitude, road[j - 1].longitude, road[j].latitude, road[j].longitude);
      stepLengths.push(d);
      roadLength += d;
    }

    // The official leg distance (cumulative_km) is distributed along the road geometry proportionally.
    let travelled = 0;
    for (let j = 1; j < road.length; j++) {
      travelled += stepLengths[j - 1];
      const ratio = roadLength > 0 ? travelled / roadLength : j / (road.length - 1);
      coords.push(road[j]);
      cumKm.push(kmFrom + (kmTo - kmFrom) * ratio);
    }
    waypointIndices.push(coords.length - 1);
  }

  return { coords, cumKm, waypointIndices, startKm, totalKm: cumKm[cumKm.length - 1] };
}

export interface TrekPathLocation {
  /** فهرس آخر نقطة في `coords` قبل الموقع (أو عنده) */
  index: number;
  point: LatLng;
}

export function locateOnTrekPath(path: TrekPath, km: number): TrekPathLocation | null {
  const { coords, cumKm } = path;
  const n = coords.length;
  if (n === 0) return null;
  if (n === 1 || km <= path.startKm) return { index: 0, point: coords[0] };
  if (km >= path.totalKm) return { index: n - 1, point: coords[n - 1] };

  let lo = 0;
  let hi = n - 2;
  while (lo < hi) {
    const mid = (lo + hi + 1) >> 1;
    if (cumKm[mid] <= km) lo = mid;
    else hi = mid - 1;
  }

  const span = cumKm[lo + 1] - cumKm[lo];
  const t = span > 0 ? (km - cumKm[lo]) / span : 0;
  const a = coords[lo];
  const b = coords[lo + 1];
  return {
    index: lo,
    point: {
      latitude: a.latitude + (b.latitude - a.latitude) * t,
      longitude: a.longitude + (b.longitude - a.longitude) * t,
    },
  };
}

/** نقاط الطريق من نقطة الانطلاق حتى المسافة المطلوبة (شاملة الموقع الدقيق) */
export function sliceTrekPathCoords(path: TrekPath, km: number): LatLng[] {
  const location = locateOnTrekPath(path, km);
  if (!location) return [];
  return [...path.coords.slice(0, location.index + 1), location.point];
}

export interface TrekPathSplit {
  /** المراحل المكتملة بالكامل */
  completed: LatLng[];
  /** المرحلة الجارية من آخر محطة حتى الموقع الحالي */
  active: LatLng[];
  /** الطريق المتبقي حتى النهاية */
  remaining: LatLng[];
}

export function splitTrekPath(path: TrekPath, km: number): TrekPathSplit {
  const { coords, cumKm, waypointIndices } = path;
  if (coords.length < 2) return { completed: [], active: [], remaining: coords };
  if (km <= path.startKm) return { completed: [], active: [], remaining: coords };
  if (km >= path.totalKm) return { completed: coords, active: [], remaining: [] };

  let leg = 0;
  for (let w = 0; w < waypointIndices.length - 1; w++) {
    if (cumKm[waypointIndices[w]] <= km) leg = w;
    else break;
  }

  const legStart = waypointIndices[leg];
  const location = locateOnTrekPath(path, km) as TrekPathLocation;

  return {
    completed: legStart > 0 ? coords.slice(0, legStart + 1) : [],
    active: [...coords.slice(legStart, location.index + 1), location.point],
    remaining: [location.point, ...coords.slice(location.index + 1)],
  };
}

/**
 * اتجاه الحركة الأفقي على الشاشة عند مسافة معينة: 1 = شرقاً (يمين)، -1 = غرباً (يسار)، 0 = غير محسوم.
 * يُقاس على نافذة مسافة وليس على آخر قطعة فقط، حتى لا تتذبذب الأيقونة مع تعرجات الطريق.
 */
export function getTrekHeadingSign(path: TrekPath, km: number, windowKm: number): -1 | 0 | 1 {
  const head = locateOnTrekPath(path, km);
  if (!head) return 0;

  const backKm = km - windowKm;
  const [from, to] =
    backKm >= path.startKm
      ? [locateOnTrekPath(path, backKm)?.point ?? head.point, head.point]
      : [head.point, locateOnTrekPath(path, Math.min(path.totalKm, km + windowKm))?.point ?? head.point];

  const deltaLng = to.longitude - from.longitude;
  if (Math.abs(deltaLng) < 1e-6) return 0;
  return deltaLng > 0 ? 1 : -1;
}

/**
 * يحسب زاوية السير (Bearing/Azimuth) بالدرجات من 0 إلى 360 بين نقطتين جيوفضائيتين
 * 0° = شمال، 90° = شرق، 180° = جنوب، 270° = غرب
 */
export function computeBearing(from: LatLng, to: LatLng): number {
  const lat1 = (from.latitude * Math.PI) / 180;
  const lat2 = (to.latitude * Math.PI) / 180;
  const dLon = ((to.longitude - from.longitude) * Math.PI) / 180;

  const y = Math.sin(dLon) * Math.cos(lat2);
  const x =
    Math.cos(lat1) * Math.sin(lat2) -
    Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);

  const brng = (Math.atan2(y, x) * 180) / Math.PI;
  return (brng + 360) % 360;
}

/**
 * يعيد زاوية ميل الطريق (360 درجة) عند أي مسافة مقطوعة بالاعتماد على مماس الطريق المستقبلي
 */
export function getTrekBearing(path: TrekPath, km: number, lookaheadKm = 0.4): number {
  const { coords } = path;
  if (coords.length < 2) return 0;

  const head = locateOnTrekPath(path, km);
  if (!head) return 0;

  // فحص نقطة للأمام على الطريق لمعرفة اتجاه الحركة بدقة
  const aheadKm = Math.min(path.totalKm, km + lookaheadKm);
  const ahead = locateOnTrekPath(path, aheadKm);

  if (ahead && haversineKm(head.point.latitude, head.point.longitude, ahead.point.latitude, ahead.point.longitude) > 0.005) {
    return computeBearing(head.point, ahead.point);
  }

  // إذا كنا في نهاية المسار، نأخذ زاوية المماس من النقطة السابقة مباشرة
  const behindKm = Math.max(path.startKm, km - lookaheadKm);
  const behind = locateOnTrekPath(path, behindKm);
  if (behind) {
    return computeBearing(behind.point, head.point);
  }

  return 0;
}

/**
 * تقليل كثافة النقاط لمسار الأنيميشن المباشر (Downsampling) لضمان أداء 60fps كامل
 * وتفادي تجميد أو قفزات معالج رسوميات الخريطة (OpenGL) على أجهزة الأندرويد
 */
export function downsampleCoords(coords: readonly LatLng[], maxPoints = 80): LatLng[] {
  const n = coords.length;
  if (n <= maxPoints) return [...coords];
  const result: LatLng[] = [coords[0]];
  const step = (n - 1) / (maxPoints - 1);
  for (let i = 1; i < maxPoints - 1; i++) {
    result.push(coords[Math.round(i * step)]);
  }
  result.push(coords[n - 1]);
  return result;
}

/**
 * اشتقاق مراحل المسار الثنائية (Segments / Legs) بين كل محطتين متعاقبتين
 * لعرض المسار كوصلات بين المدن (مثل: الدمام ➔ الرياض مع المسافة بينهما)
 */
export function buildTrekSegments(waypoints: readonly TrekWaypoint[]): TrekRouteSegment[] {
  if (!waypoints || waypoints.length < 2) return [];

  const segments: TrekRouteSegment[] = [];

  for (let i = 0; i < waypoints.length - 1; i++) {
    const fromWp = waypoints[i];
    const toWp = waypoints[i + 1];

    const distanceKm = Number(toWp.distance_from_prev_km) || 0;
    const cumulativeKm = Number(toWp.cumulative_km) || 0;
    const estimatedSteps =
      Number(toWp.steps_from_prev) || Math.round(distanceKm / 0.00075);

    let status: 'completed' | 'current' | 'upcoming' = 'upcoming';
    if (toWp.status === 'completed') {
      status = 'completed';
    } else if (toWp.status === 'current_target') {
      status = 'current';
    }

    const decoded = toWp.route_polyline ? decodePolyline(toWp.route_polyline) : [];
    const road =
      decoded.length >= 2
        ? decoded
        : [
            { latitude: fromWp.latitude, longitude: fromWp.longitude },
            { latitude: toWp.latitude, longitude: toWp.longitude },
          ];

    segments.push({
      id: `segment-${fromWp.id}-${toWp.id}`,
      index: i + 1,
      fromWaypoint: fromWp,
      toWaypoint: toWp,
      fromCity: fromWp.city_name || fromWp.location_title || 'نقطة الانطلاق',
      toCity: toWp.city_name || toWp.location_title || `المحطة ${i + 2}`,
      fromLocation: fromWp.location_title || fromWp.city_name || '',
      toLocation: toWp.location_title || toWp.city_name || '',
      distanceKm,
      cumulativeKm,
      estimatedSteps,
      status,
      polylineCoords: road,
    });
  }

  return segments;
}



