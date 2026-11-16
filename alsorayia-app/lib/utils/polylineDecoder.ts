export interface LatLng {
  latitude: number;
  longitude: number;
}

/**
 * Decodes a Google-encoded polyline string into an array of { latitude, longitude } points.
 * Standard algorithm: 5-bit chunks with ASCII offset 63.
 */
export function decodePolyline(encoded: string): LatLng[] {
  if (!encoded || typeof encoded !== 'string') {
    return [];
  }

  const poly: LatLng[] = [];
  let index = 0;
  const len = encoded.length;
  let lat = 0;
  let lng = 0;

  while (index < len) {
    let b: number;
    let shift = 0;
    let result = 0;

    do {
      b = encoded.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20 && index < len);

    const dlat = (result & 1) !== 0 ? ~(result >> 1) : result >> 1;
    lat += dlat;

    shift = 0;
    result = 0;

    do {
      b = encoded.charCodeAt(index++) - 63;
      result |= (b & 0x1f) << shift;
      shift += 5;
    } while (b >= 0x20 && index < len);

    const dlng = (result & 1) !== 0 ? ~(result >> 1) : result >> 1;
    lng += dlng;

    poly.push({
      latitude: Number((lat * 1e-5).toFixed(7)),
      longitude: Number((lng * 1e-5).toFixed(7)),
    });
  }

  return poly;
}

/**
 * Calculates Haversine distance in kilometers between two GPS coordinates.
 */
export function haversineKm(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371; // Earth radius in km
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
  return R * c;
}

/**
 * Slices a road polyline into completed and remaining portions based on walked distance along the curve.
 * Also returns the exact intermediate GPS position on the road.
 */
export function slicePolylineByDistance(
  coords: LatLng[],
  walkedKm: number,
  segmentTotalKm: number
): {
  completed: LatLng[];
  remaining: LatLng[];
  midPoint: LatLng;
} {
  if (coords.length === 0) {
    const fallback = { latitude: 0, longitude: 0 };
    return { completed: [], remaining: [], midPoint: fallback };
  }

  if (coords.length === 1) {
    return { completed: [...coords], remaining: [...coords], midPoint: coords[0] };
  }

  const safeWalked = Math.max(0, walkedKm);
  const safeTotal = Math.max(0.001, segmentTotalKm);

  // If completed full segment
  if (safeWalked >= safeTotal) {
    const last = coords[coords.length - 1];
    return {
      completed: [...coords],
      remaining: [],
      midPoint: last,
    };
  }

  // If at start of segment
  if (safeWalked <= 0) {
    const first = coords[0];
    return {
      completed: [first],
      remaining: [...coords],
      midPoint: first,
    };
  }

  // Calculate actual total distance along this polyline
  const distances: number[] = [];
  let polylineLength = 0;
  for (let i = 0; i < coords.length - 1; i++) {
    const d = haversineKm(
      coords[i].latitude,
      coords[i].longitude,
      coords[i + 1].latitude,
      coords[i + 1].longitude
    );
    distances.push(d);
    polylineLength += d;
  }

  // Normalized progress ratio along the polyline
  const progressRatio = Math.min(1, Math.max(0, safeWalked / safeTotal));
  const targetDist = (polylineLength > 0 ? polylineLength : safeTotal) * progressRatio;

  const completed: LatLng[] = [coords[0]];
  const remaining: LatLng[] = [];
  let accum = 0;
  let midPoint: LatLng = coords[0];
  let splitFound = false;

  for (let i = 0; i < distances.length; i++) {
    const d = distances[i];

    if (!splitFound) {
      if (accum + d >= targetDist) {
        const rem = targetDist - accum;
        const subRatio = d > 0 ? rem / d : 0;

        midPoint = {
          latitude: Number((coords[i].latitude + subRatio * (coords[i + 1].latitude - coords[i].latitude)).toFixed(7)),
          longitude: Number((coords[i].longitude + subRatio * (coords[i + 1].longitude - coords[i].longitude)).toFixed(7)),
        };

        completed.push(midPoint);
        remaining.push(midPoint);
        remaining.push(coords[i + 1]);
        splitFound = true;
      } else {
        completed.push(coords[i + 1]);
      }
    } else {
      remaining.push(coords[i + 1]);
    }

    accum += d;
  }

  if (!splitFound) {
    midPoint = coords[coords.length - 1];
    completed.push(midPoint);
    remaining.push(midPoint);
  }

  return { completed, remaining, midPoint };
}
