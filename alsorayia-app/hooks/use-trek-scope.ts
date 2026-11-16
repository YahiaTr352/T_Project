import { useEffect, useMemo, useState } from 'react';
import type {
  ActiveTrekResponseData,
  GroupTrekProgress,
  TrekContributor,
  TrekCurrentPosition,
  TrekCurrentSegment,
  TrekViewMode,
  TrekWaypoint,
} from '@/lib/api/walkingTrekApi';

export interface TrekScope {
  km: number;
  steps: number;
  progress: number;
  position: TrekCurrentPosition | null;
  segment: TrekCurrentSegment | null;
  waypoints: TrekWaypoint[];
  contributors: TrekContributor[];
  label: string;
}

export interface UseTrekScopeResult {
  viewMode: TrekViewMode;
  setViewMode: (mode: TrekViewMode) => void;
  groups: GroupTrekProgress[];
  selectedGroupId: number | null;
  setSelectedGroupId: (groupId: number) => void;
  scope: TrekScope | null;
}

/**
 * يحوّل بيانات الرحلة إلى نطاق العرض الحالي (الكل | جروب | نفسي).
 * كل نطاق يحمل مسافته الفعلية وموقعه ومراحله وحالة محطاته كما حسبها الباك إند،
 * بحيث لا يصل أي خط أو دبوس لمحطة إلا بعد قطع المسافة الحقيقية إليها.
 */
export function useTrekScope(trekData: ActiveTrekResponseData | null): UseTrekScopeResult {
  const [viewMode, setViewMode] = useState<TrekViewMode>('community');
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);

  const groups = useMemo(() => trekData?.groups_progress ?? [], [trekData?.groups_progress]);

  useEffect(() => {
    if (groups.length === 0) {
      setSelectedGroupId(null);
    } else if (selectedGroupId === null || !groups.some((g) => g.group_id === selectedGroupId)) {
      setSelectedGroupId(groups[0].group_id);
    }
  }, [groups, selectedGroupId]);

  const scope = useMemo<TrekScope | null>(() => {
    if (!trekData || !trekData.trek) return null;
    const { trek, current_segment, current_position, waypoints, top_contributors, my_contribution } =
      trekData;

    if (viewMode === 'personal') {
      return {
        km: Number(my_contribution?.total_km) || 0,
        steps: Number(my_contribution?.total_steps) || 0,
        progress: Number(my_contribution?.my_progress_percentage) || 0,
        position: my_contribution?.current_position ?? current_position,
        segment: my_contribution?.current_segment ?? null,
        waypoints: my_contribution?.waypoints ?? waypoints,
        contributors: [],
        label: 'نفسي',
      };
    }

    const selectedGroup = groups.find((g) => g.group_id === selectedGroupId);
    if (viewMode === 'group' && selectedGroup) {
      return {
        km: Number(selectedGroup.total_km) || 0,
        steps: Number(selectedGroup.total_steps) || 0,
        progress: Number(selectedGroup.overall_progress_percentage) || 0,
        position: selectedGroup.current_position,
        segment: selectedGroup.current_segment,
        waypoints: selectedGroup.waypoints,
        contributors: selectedGroup.top_contributors,
        label: selectedGroup.name,
      };
    }

    return {
      km: Number(trek.total_walked_km) || 0,
      steps: Number(trek.total_walked_steps) || 0,
      progress: Number(trek.overall_progress_percentage) || 0,
      position: current_position,
      segment: current_segment,
      waypoints,
      contributors: top_contributors,
      label: 'الكل',
    };
  }, [trekData, viewMode, groups, selectedGroupId]);

  return { viewMode, setViewMode, groups, selectedGroupId, setSelectedGroupId, scope };
}
