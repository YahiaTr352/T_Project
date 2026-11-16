import {
  buildTrekPath,
  computeBearing,
  downsampleCoords,
  getTrekBearing,
  getTrekHeadingSign,
  locateOnTrekPath,
  sliceTrekPathCoords,
  splitTrekPath,
  type TrekPathWaypoint,
} from '@/lib/utils/trekPath';

// Riyadh → Diriyah (north-west) → Al Majma'ah (further north-west): a westward trek.
const westwardTrek: TrekPathWaypoint[] = [
  { latitude: 24.6312, longitude: 46.7133, cumulative_km: 0 },
  { latitude: 24.7335, longitude: 46.5752, cumulative_km: 25 },
  { latitude: 25.9082, longitude: 45.3431, cumulative_km: 200 },
];

const eastwardTrek: TrekPathWaypoint[] = [...westwardTrek].reverse().map((wp, i, arr) => ({
  ...wp,
  cumulative_km: 200 - (arr[i].cumulative_km as number),
}));

describe('trekPath', () => {
  const path = buildTrekPath(westwardTrek);

  it('builds a monotonic path that maps waypoints to their cumulative km', () => {
    expect(path.coords).toHaveLength(3);
    expect(path.waypointIndices).toEqual([0, 1, 2]);
    expect(path.cumKm).toEqual([0, 25, 200]);
    expect(path.totalKm).toBe(200);
  });

  it('returns an empty path for no waypoints', () => {
    const empty = buildTrekPath([]);
    expect(empty.coords).toHaveLength(0);
    expect(locateOnTrekPath(empty, 10)).toBeNull();
    expect(sliceTrekPathCoords(empty, 10)).toEqual([]);
  });

  it('clamps locations to the start and end of the trek', () => {
    expect(locateOnTrekPath(path, -5)?.point).toEqual(path.coords[0]);
    expect(locateOnTrekPath(path, 500)?.point).toEqual(path.coords[2]);
  });

  it('interpolates inside a leg', () => {
    const mid = locateOnTrekPath(path, 12.5)!;
    expect(mid.index).toBe(0);
    expect(mid.point.latitude).toBeCloseTo((24.6312 + 24.7335) / 2, 6);
    expect(mid.point.longitude).toBeCloseTo((46.7133 + 46.5752) / 2, 6);
  });

  it('splits into completed legs, the active leg and the remaining road', () => {
    const split = splitTrekPath(path, 100);
    expect(split.completed).toEqual([path.coords[0], path.coords[1]]);
    expect(split.active[0]).toEqual(path.coords[1]);
    expect(split.active[split.active.length - 1]).toEqual(split.remaining[0]);
    expect(split.remaining[split.remaining.length - 1]).toEqual(path.coords[2]);
  });

  it('treats the whole road as remaining before start and completed after the end', () => {
    expect(splitTrekPath(path, 0)).toEqual({ completed: [], active: [], remaining: path.coords });
    expect(splitTrekPath(path, 250)).toEqual({ completed: path.coords, active: [], remaining: [] });
  });

  it('slices the walked road from start up to the exact head position', () => {
    const walked = sliceTrekPathCoords(path, 100);
    expect(walked[0]).toEqual(path.coords[0]);
    expect(walked[walked.length - 1]).toEqual(locateOnTrekPath(path, 100)!.point);
  });

  it('reports westward heading (-1) so the walker is mirrored to face left', () => {
    expect(getTrekHeadingSign(path, 100, 1)).toBe(-1);
    // At the very start it looks ahead instead of behind.
    expect(getTrekHeadingSign(path, 0, 1)).toBe(-1);
  });

  it('reports eastward heading (+1) for the reversed trek', () => {
    expect(getTrekHeadingSign(buildTrekPath(eastwardTrek), 100, 1)).toBe(1);
  });

  it('computes accurate cardinal bearings', () => {
    // Due North
    expect(computeBearing({ latitude: 24, longitude: 46 }, { latitude: 25, longitude: 46 })).toBeCloseTo(0, 0);
    // Due East
    expect(computeBearing({ latitude: 24, longitude: 46 }, { latitude: 24, longitude: 47 })).toBeCloseTo(90, 0);
    // Due South
    expect(computeBearing({ latitude: 25, longitude: 46 }, { latitude: 24, longitude: 46 })).toBeCloseTo(180, 0);
    // Due West
    expect(computeBearing({ latitude: 24, longitude: 47 }, { latitude: 24, longitude: 46 })).toBeCloseTo(270, 0);
  });

  it('calculates smooth 360-degree road bearing along the trek', () => {
    // Westward trek moving from Riyadh (24.63, 46.71) towards Diriyah (24.73, 46.57) - North-West (~309°)
    const bearing = getTrekBearing(path, 10);
    expect(bearing).toBeGreaterThan(270);
    expect(bearing).toBeLessThan(360);
  });

  it('downsamples dense coordinate arrays while strictly preserving endpoints', () => {
    const dense = Array.from({ length: 200 }, (_, i) => ({
      latitude: 24 + i * 0.01,
      longitude: 46 + i * 0.01,
    }));
    const sampled = downsampleCoords(dense, 50);
    expect(sampled).toHaveLength(50);
    expect(sampled[0]).toEqual(dense[0]);
    expect(sampled[sampled.length - 1]).toEqual(dense[dense.length - 1]);
  });
});
