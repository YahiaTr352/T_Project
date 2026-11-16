import type {
  LeaderboardData,
  LeaderboardPeriod,
  WalkingGroupDetail,
  WalkingGroupItem,
  WalkingGroupMember,
} from '@/lib/api/walkingApi';

export interface SelectedPerson {
  id: number;
  name: string;
  avatar?: string | null;
}

export const GROUP_PERIODS: { key: LeaderboardPeriod; label: string }[] = [
  { key: 'today', label: 'اليوم' },
  { key: 'weekly', label: 'الأسبوع' },
  { key: 'monthly', label: 'الشهر' },
  { key: 'all_time', label: 'الإجمالي' },
];

export type GroupLike = WalkingGroupDetail | WalkingGroupItem | null;

export type ViewerLike = {
  id?: number | null;
  full_name?: string | null;
  avatar?: string | null;
} | null | undefined;

export interface GroupLeaderboardEntry {
  rank: number;
  person_id: number | null;
  membership_id?: number | null;
  total_steps: number;
  person: any;
  in_top?: boolean;
  is_anonymous?: boolean;
  is_self?: boolean;
  role?: string;
  completed_at?: string | null;
  is_goal_reached?: boolean;
  completion_duration_text?: string | null;
  streak_10k?: number;
}

export interface TodayScoringEntry {
  today_steps: number;
  today_rank: number;
  expected_points: number;
}

export interface PointsLeaderboardEntry extends GroupLeaderboardEntry {
  total_points: number;
  expected_points: number;
  today_steps: number;
  projected_total: number;
}

export interface MyGroupStanding {
  today_steps: number;
  today_rank: number;
  expected_points: number;
  current_points: number;
  overall_rank: number;
  projected_total: number;
}

/**
 * Extracts and sorts display members descending by total steps in challenge,
 * tie-breaking by earliest join timestamp then member ID.
 */
export function computeDisplayMembers({
  isMatchingDetail,
  group,
  leaderboard,
}: {
  isMatchingDetail: boolean;
  group: GroupLike;
  leaderboard: LeaderboardData | null;
}): WalkingGroupMember[] {
  if (!isMatchingDetail || !group) return [];
  let list: WalkingGroupMember[] = [];
  if ('members' in group && Array.isArray((group as any).members) && (group as any).members.length > 0) {
    list = [...((group as any).members as WalkingGroupMember[])];
  } else if (leaderboard?.top && Array.isArray(leaderboard.top) && leaderboard.top.length > 0) {
    // Fallback if API hasn't loaded memberships relation yet
    list = leaderboard.top.map((entry) => ({
      id: entry.membership_id || entry.person_id || 0,
      membership_id: entry.membership_id,
      person_id: entry.person_id,
      role: ((group as any)?.created_by_person_id === entry.person_id ? 'admin' : 'member') as 'admin' | 'member',
      joined_at: '',
      total_steps_in_challenge: entry.total_steps,
      is_anonymous: Boolean(entry.is_anonymous),
      person: entry.person,
    }));
  }

  return list.sort((a, b) => {
    const stepsA = Number(a.total_steps_in_challenge) || 0;
    const stepsB = Number(b.total_steps_in_challenge) || 0;
    if (stepsB !== stepsA) {
      return stepsB - stepsA;
    }
    const joinedA = a.joined_at ? new Date(a.joined_at).getTime() : 0;
    const joinedB = b.joined_at ? new Date(b.joined_at).getTime() : 0;
    if (joinedA && joinedB && joinedA !== joinedB) {
      return joinedA - joinedB;
    }
    return (Number(a.id) || 0) - (Number(b.id) || 0);
  });
}

/**
 * Merges server leaderboard rows with all group members (including 0-step members)
 * and injects optimistic todaySteps for the active viewer before dense ranking.
 */
export function computeChallengeLeaderboardEntries({
  leaderboard,
  displayMembers,
  user,
  todaySteps,
  isAnonymousSetting,
  selectedPeriod,
  group,
}: {
  leaderboard: LeaderboardData | null;
  displayMembers: WalkingGroupMember[];
  user: ViewerLike;
  todaySteps?: number;
  isAnonymousSetting?: boolean;
  selectedPeriod: LeaderboardPeriod;
  group: GroupLike;
}): GroupLeaderboardEntry[] {
  const map = new Map<string, GroupLeaderboardEntry>();

  if (leaderboard?.top && Array.isArray(leaderboard.top)) {
    leaderboard.top.forEach((e) => {
      const isViewer = user?.id && (e.person_id === user.id || e.person?.id === user.id);
      const effectiveSteps =
        isViewer && selectedPeriod === 'today' && (todaySteps ?? 0) > 0
          ? Math.max(e.total_steps || 0, todaySteps ?? 0)
          : (e.total_steps || 0);

      const memberObj = displayMembers.find(
        (m) =>
          (e.membership_id && (m.id === e.membership_id || m.membership_id === e.membership_id)) ||
          (e.person_id && (m.person_id === e.person_id || m.person?.id === e.person_id))
      );

      const isGoalReached = Boolean(
        e.is_goal_reached ||
          memberObj?.is_goal_reached ||
          (group?.target_steps && effectiveSteps >= group.target_steps)
      );
      const durationText = e.completion_duration_text || memberObj?.completion_duration_text || null;

      const effectiveMembershipId = e.membership_id || memberObj?.id || memberObj?.membership_id || null;
      const entryKey = effectiveMembershipId
        ? `m_${effectiveMembershipId}`
        : e.person_id
          ? `p_${e.person_id}`
          : e.person?.id
            ? `p_${e.person.id}`
            : `anon_${e.rank}`;

      map.set(entryKey, {
        rank: e.rank,
        person_id: e.person_id,
        membership_id: effectiveMembershipId,
        total_steps: effectiveSteps,
        streak_10k: e.streak_10k,
        person: e.person,
        in_top: e.in_top,
        is_anonymous: Boolean(e.is_anonymous),
        is_self: Boolean(e.is_self || isViewer),
        role: memberObj?.role,
        completed_at: e.completed_at || memberObj?.completed_at || null,
        is_goal_reached: isGoalReached,
        completion_duration_text: durationText,
      });
    });
  }

  displayMembers.forEach((m) => {
    const pid = m.person_id || m.person?.id;
    const memId = m.id || m.membership_id;

    const hasByMemId = memId ? map.has(`m_${memId}`) : false;
    const hasByPersonId = pid ? map.has(`p_${pid}`) : false;

    if (hasByMemId) {
      const existing = map.get(`m_${memId}`);
      if (existing) {
        if (!existing.role && m.role) existing.role = m.role;
        if (!existing.completed_at && m.completed_at) existing.completed_at = m.completed_at;
      }
      return;
    }

    if (hasByPersonId) {
      const existing = map.get(`p_${pid}`);
      if (existing) {
        if (!existing.role && m.role) existing.role = m.role;
        if (!existing.membership_id && memId) existing.membership_id = memId;
      }
      return;
    }

    const entryKey = memId ? `m_${memId}` : pid ? `p_${pid}` : `anon_mem_${m.id}`;
    const isViewer = user?.id && (pid === user.id || m.person?.id === user.id);
    const memberPeriodSteps =
      selectedPeriod === 'all_time'
        ? typeof m.total_steps_in_challenge === 'number'
          ? m.total_steps_in_challenge
          : 0
        : 0;
    const effectiveSteps =
      isViewer && selectedPeriod === 'today' && (todaySteps ?? 0) > 0
        ? Math.max(memberPeriodSteps, todaySteps ?? 0)
        : memberPeriodSteps;

    const isGoalReached = Boolean(
      m.is_goal_reached || (group?.target_steps && effectiveSteps >= group.target_steps)
    );

    map.set(entryKey, {
      rank: 0,
      person_id: pid || null,
      membership_id: memId || null,
      total_steps: effectiveSteps,
      person: m.person,
      is_anonymous: isViewer ? Boolean(isAnonymousSetting ?? m.is_anonymous) : Boolean(m.is_anonymous),
      is_self: Boolean(isViewer),
      role: m.role,
      completed_at: m.completed_at || null,
      is_goal_reached: isGoalReached,
      completion_duration_text: m.completion_duration_text || null,
    });
  });

  const userAlreadyInMap = Array.from(map.values()).some(
    (entry) => entry.is_self || (user?.id && entry.person_id === user.id)
  );
  if (user?.id && !userAlreadyInMap && Boolean((group as any)?.is_member)) {
    map.set(`p_${user.id}`, {
      rank: 0,
      person_id: user.id,
      membership_id: null,
      total_steps: selectedPeriod === 'today' ? (todaySteps ?? 0) : 0,
      person: {
        id: user.id,
        name: user.full_name || 'أنت',
        avatar: user.avatar || null,
      },
      is_anonymous: Boolean(isAnonymousSetting),
      is_self: true,
      role: 'member',
      completed_at: null,
      is_goal_reached: false,
      completion_duration_text: null,
    });
  }

  const list = Array.from(map.values());
  list.sort((a, b) => b.total_steps - a.total_steps);

  let rank = 0;
  let prevSteps: number | null = null;
  return list.map((item) => {
    if (item.total_steps > 0) {
      if (item.total_steps !== prevSteps) {
        rank++;
        prevSteps = item.total_steps;
      }
      return {
        ...item,
        rank,
      };
    }
    return {
      ...item,
      rank: 0,
    };
  });
}

export function computeTodayStepsByPersonId({
  todayLb,
  selectedPeriod,
  challengeLeaderboardEntries,
  userId,
  todaySteps,
}: {
  todayLb: LeaderboardData | null | undefined;
  selectedPeriod: LeaderboardPeriod;
  challengeLeaderboardEntries: GroupLeaderboardEntry[];
  userId?: number | null;
  todaySteps?: number;
}): Map<number, number> {
  const map = new Map<number, number>();

  if (todayLb?.top && Array.isArray(todayLb.top)) {
    todayLb.top.forEach((e) => {
      const pid = e.person_id || e.person?.id;
      if (pid) {
        map.set(pid, Math.max(map.get(pid) || 0, e.total_steps || 0));
      }
    });
  }

  if (selectedPeriod === 'today' && challengeLeaderboardEntries.length > 0) {
    challengeLeaderboardEntries.forEach((e) => {
      const pid = e.person_id || e.person?.id;
      if (pid) {
        map.set(pid, Math.max(map.get(pid) || 0, e.total_steps || 0));
      }
    });
  }

  if (userId) {
    map.set(userId, Math.max(map.get(userId) || 0, todaySteps ?? 0));
  }

  return map;
}

export function computeTotalGroupMembersCount({
  displayMembers,
  userId,
  group,
}: {
  displayMembers: WalkingGroupMember[];
  userId?: number | null;
  group: GroupLike;
}): number {
  const ids = new Set<number>();
  displayMembers.forEach((m) => {
    const pid = m.person_id || m.person?.id;
    if (pid) ids.add(pid);
  });
  if (userId && Boolean((group as any)?.is_member)) {
    ids.add(userId);
  }
  if ((group as any)?.created_by_person_id) {
    ids.add((group as any).created_by_person_id);
  }
  return Math.max(1, ids.size);
}

/**
 * Computes today's intra-group step ranking and tonight's expected challenge points.
 */
export function computeTodayScoringMap({
  displayMembers,
  userId,
  group,
  todayStepsByPersonId,
  totalGroupMembersCount,
}: {
  displayMembers: WalkingGroupMember[];
  userId?: number | null;
  group: GroupLike;
  todayStepsByPersonId: Map<number, number>;
  totalGroupMembersCount: number;
}): Map<number, TodayScoringEntry> {
  const personIds = new Set<number>();
  displayMembers.forEach((m) => {
    const pid = m.person_id || m.person?.id;
    if (pid) personIds.add(pid);
  });
  if (userId && Boolean((group as any)?.is_member)) {
    personIds.add(userId);
  }
  if ((group as any)?.created_by_person_id) {
    personIds.add((group as any).created_by_person_id);
  }

  const list = Array.from(personIds).map((pid) => ({
    person_id: pid,
    steps: todayStepsByPersonId.get(pid) || 0,
  }));

  list.sort((a, b) => {
    if (b.steps !== a.steps) return b.steps - a.steps;
    return a.person_id - b.person_id;
  });

  const map = new Map<number, TodayScoringEntry>();
  let rank = 0;
  let prevSteps: number | null = null;

  list.forEach((item) => {
    if (item.steps > 0) {
      if (item.steps !== prevSteps) {
        rank++;
        prevSteps = item.steps;
      }
      const pts = Math.max(1, totalGroupMembersCount - rank + 1);
      map.set(item.person_id, {
        today_steps: item.steps,
        today_rank: rank,
        expected_points: pts,
      });
    } else {
      map.set(item.person_id, {
        today_steps: 0,
        today_rank: 0,
        expected_points: 0,
      });
    }
  });

  return map;
}

/**
 * Builds the challenge points leaderboard combining accumulated challenge points,
 * projected tonight expected points, and tie-breaking on cumulative steps.
 */
export function computePointsLeaderboardEntries({
  displayMembers,
  leaderboard,
  user,
  isAnonymousSetting,
  group,
  todayScoringMap,
  todaySteps,
}: {
  displayMembers: WalkingGroupMember[];
  leaderboard: LeaderboardData | null;
  user: ViewerLike;
  isAnonymousSetting?: boolean;
  group: GroupLike;
  todayScoringMap: Map<number, TodayScoringEntry>;
  todaySteps?: number;
}): PointsLeaderboardEntry[] {
  const pointsMap = (leaderboard as any)?.points_map || {};

  const list: PointsLeaderboardEntry[] = displayMembers.map((m) => {
    const pid = m.person_id || m.person?.id;
    const memId = m.id || m.membership_id;
    const isViewer = Boolean(user?.id && (pid === user.id || m.person?.id === user.id));
    const isAnon = isViewer ? Boolean(isAnonymousSetting ?? m.is_anonymous) : Boolean(m.is_anonymous);
    const pts =
      typeof m.total_points_in_challenge === 'number'
        ? m.total_points_in_challenge
        : pid && typeof pointsMap[pid] === 'number'
          ? pointsMap[pid]
          : 0;

    const scoring = pid ? todayScoringMap.get(pid) : null;
    const expectedPts = scoring?.expected_points ?? 0;
    const memberTodaySteps = scoring?.today_steps ?? (isViewer ? (todaySteps ?? 0) : 0);

    return {
      rank: 0,
      person_id: pid || null,
      membership_id: memId || null,
      total_points: pts,
      expected_points: expectedPts,
      today_steps: memberTodaySteps,
      projected_total: pts + expectedPts,
      total_steps: typeof m.total_steps_in_challenge === 'number' ? m.total_steps_in_challenge : 0,
      person: m.person,
      is_anonymous: isAnon,
      is_self: Boolean(isViewer),
      role: m.role,
      completed_at: m.completed_at || null,
      is_goal_reached: Boolean(m.is_goal_reached),
      completion_duration_text: m.completion_duration_text || null,
      streak_10k: (m as any).streak_10k ?? 0,
    };
  });

  const userAlreadyInList = list.some(
    (entry) => entry.is_self || (user?.id && entry.person_id === user.id)
  );
  if (user?.id && !userAlreadyInList && Boolean((group as any)?.is_member)) {
    const userScoring = todayScoringMap.get(user.id);
    const userExpectedPts = userScoring?.expected_points ?? 0;
    const userTodaySteps = userScoring?.today_steps ?? (todaySteps ?? 0);
    const userPts = typeof pointsMap[user.id] === 'number' ? pointsMap[user.id] : 0;

    list.push({
      rank: 0,
      person_id: user.id,
      membership_id: null,
      total_points: userPts,
      expected_points: userExpectedPts,
      today_steps: userTodaySteps,
      projected_total: userPts + userExpectedPts,
      total_steps: 0,
      person: {
        id: user.id,
        name: user.full_name || 'أنت',
        avatar: user.avatar || null,
      },
      is_anonymous: Boolean(isAnonymousSetting),
      is_self: true,
      role: 'member',
      completed_at: null,
      is_goal_reached: false,
      completion_duration_text: null,
      streak_10k: 0,
    });
  }

  list.sort((a, b) => {
    if (b.total_points !== a.total_points) {
      return b.total_points - a.total_points;
    }
    if (b.expected_points !== a.expected_points) {
      return b.expected_points - a.expected_points;
    }
    return b.total_steps - a.total_steps;
  });

  let rank = 0;
  let prevScoreKey: number | null = null;
  return list.map((item) => {
    const hasPoints = item.total_points > 0;
    const hasExpected = item.expected_points > 0;
    if (hasPoints || hasExpected) {
      const scoreKey = item.total_points * 100000 + item.expected_points;
      if (scoreKey !== prevScoreKey) {
        rank++;
        prevScoreKey = scoreKey;
      }
      return {
        ...item,
        rank,
      };
    }
    return {
      ...item,
      rank: 0,
    };
  });
}

export function computeMyStanding({
  userId,
  group,
  displayMembers,
  todayScoringMap,
  todaySteps,
  pointsLeaderboardEntries,
}: {
  userId?: number | null;
  group: GroupLike;
  displayMembers: WalkingGroupMember[];
  todayScoringMap: Map<number, TodayScoringEntry>;
  todaySteps?: number;
  pointsLeaderboardEntries: PointsLeaderboardEntry[];
}): MyGroupStanding | null {
  if (!userId) return null;
  const isMember = Boolean(
    (group as any)?.is_member ||
      displayMembers.some((m) => (m.person_id || m.person?.id) === userId)
  );
  if (!isMember) return null;

  const scoring = todayScoringMap.get(userId) || {
    today_steps: todaySteps ?? 0,
    today_rank: 0,
    expected_points: 0,
  };
  const myEntry = pointsLeaderboardEntries.find((e) => e.is_self || e.person_id === userId);
  const currentPoints = myEntry?.total_points ?? 0;
  const expectedPoints = scoring.expected_points;
  const projectedTotal = currentPoints + expectedPoints;

  return {
    today_steps: scoring.today_steps,
    today_rank: scoring.today_rank,
    expected_points: expectedPoints,
    current_points: currentPoints,
    overall_rank: myEntry?.rank ?? 0,
    projected_total: projectedTotal,
  };
}
