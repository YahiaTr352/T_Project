import type { LeaderboardData, LeaderboardPeriod, WalkingClub } from '@/lib/api/walkingApi';
import { dateKey, type WalkingLedger } from '@/lib/walking/ledger';

export type LeaderboardCategory = 'steps' | 'club_5k' | 'club_10k' | 'club_20k' | 'streak' | string;

export function getMinStepsForCategory(
  category?: LeaderboardCategory,
  dynamicClubs?: WalkingClub[]
): number | undefined {
  if (!category || category === 'steps') return undefined;
  if (dynamicClubs && dynamicClubs.length > 0) {
    const found = dynamicClubs.find((c) => c.slug === category || c.key === category);
    if (found) return found.min_steps;
  }
  switch (category) {
    case 'club_5k':
      return 5000;
    case 'club_10k':
      return 10000;
    case 'club_20k':
      return 20000;
    default:
      return undefined;
  }
}

export function getMaxStepsForCategory(
  category?: LeaderboardCategory,
  dynamicClubs?: WalkingClub[]
): number | undefined {
  if (!category || category === 'steps') return undefined;
  if (dynamicClubs && dynamicClubs.length > 0) {
    const found = dynamicClubs.find((c) => c.slug === category || c.key === category);
    if (found) return found.max_steps ?? undefined;
  }
  switch (category) {
    case 'club_5k':
      return 9999;
    case 'club_10k':
      return 19999;
    case 'club_20k':
      return undefined;
    default:
      return undefined;
  }
}

/**
 * Generates deterministic 365-day synthetic ledger data for guest/demo previews
 * so Day, Week, Month, and Year charts remain representative offline.
 */
export function buildDemoLedger(): WalkingLedger {
  const daily: Record<string, number> = {};
  const hourly: Record<string, Record<number, number>> = {};
  const today = new Date();

  for (let i = 365; i >= 0; i -= 1) {
    const d = new Date(today);
    d.setDate(d.getDate() - i);
    const k = dateKey(d);

    const dayOfWeek = d.getDay();
    const isWeekend = dayOfWeek === 5 || dayOfWeek === 6;
    const base = isWeekend ? 9400 : 7600;
    const wave = Math.sin(i * 0.15) * 2400;
    const noise = ((i * 43) % 1600) - 800;
    const steps = Math.max(1500, Math.round(base + wave + noise));

    daily[k] = steps;

    if (i <= 14) {
      hourly[k] = {};
      const activeHours = [6, 7, 8, 9, 10, 11, 12, 14, 16, 17, 18, 19, 20, 21];
      let remaining = steps;
      for (const h of activeHours) {
        if (remaining <= 0) break;
        const portion = Math.min(remaining, Math.round(steps * (0.05 + ((i * 7 + h) % 10) * 0.008)));
        hourly[k][h] = portion;
        remaining -= portion;
      }
      if (remaining > 0) {
        hourly[k][17] = (hourly[k][17] ?? 0) + remaining;
      }
    }
  }
  return { daily, hourly };
}

/**
 * Generates deterministic demo leaderboard standings when the viewer is in guest mode
 * or testing club qualification filters locally.
 */
export function buildDemoLeaderboard(period: LeaderboardPeriod, minSteps?: number): LeaderboardData {
  let list = [
    {
      rank: 1,
      person_id: 101,
      total_steps: period === 'today' ? 22450 : period === 'weekly' ? 98900 : 320000,
      streak_10k: 7,
      in_top: true,
      person: {
        id: 101,
        name: 'عبدالله بن فهد بن محمد',
        avatar: null,
      },
    },
    {
      rank: 2,
      person_id: 102,
      total_steps: period === 'today' ? 14820 : period === 'weekly' ? 71400 : 262000,
      streak_10k: 5,
      in_top: true,
      person: {
        id: 102,
        name: 'سلطان بن إبراهيم السريّع',
        avatar: null,
      },
    },
    {
      rank: 3,
      person_id: 103,
      total_steps: period === 'today' ? 10540 : period === 'weekly' ? 58200 : 229000,
      streak_10k: 2,
      in_top: true,
      person: {
        id: 103,
        name: 'خالد بن أحمد بن عبدالعزيز',
        avatar: null,
      },
    },
    {
      rank: 4,
      person_id: 104,
      total_steps: period === 'today' ? 8400 : period === 'weekly' ? 48300 : 194000,
      streak_10k: 4,
      in_top: true,
      person: {
        id: 104,
        name: 'فيصل بن ناصر السريّع',
        avatar: null,
      },
    },
    {
      rank: 5,
      person_id: 105,
      total_steps: period === 'today' ? 5350 : period === 'weekly' ? 44100 : 182000,
      streak_10k: 0,
      in_top: true,
      person: {
        id: 105,
        name: 'محمد بن تركي السريّع',
        avatar: null,
      },
    },
  ];

  if (minSteps && minSteps > 0) {
    list = list.filter((e) => e.total_steps >= minSteps);
  }

  const viewerSteps = period === 'today' ? 8400 : period === 'weekly' ? 48300 : 194000;
  const isQualified = !minSteps || viewerSteps >= minSteps;
  const stepsNeeded = minSteps && viewerSteps < minSteps ? minSteps - viewerSteps : 0;
  const viewerIndex = list.findIndex((e) => e.person_id === 104);

  return {
    period,
    min_steps: minSteps,
    top: list.map((e, idx) => ({ ...e, rank: idx + 1 })),
    viewer_rank: {
      rank: isQualified && viewerIndex !== -1 ? viewerIndex + 1 : 0,
      person_id: 104,
      total_steps: viewerSteps,
      streak_10k: 4,
      in_top: isQualified,
      is_qualified: isQualified,
      steps_needed: stepsNeeded,
      min_steps: minSteps,
      person: {
        id: 104,
        name: 'فيصل بن ناصر السريّع',
        avatar: null,
      },
    },
  };
}
