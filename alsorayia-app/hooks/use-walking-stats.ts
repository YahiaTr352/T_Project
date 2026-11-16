/**
 * هوك التوافقية العكسية لدعم الكود القديم.
 * يوجه الاستدعاءات إلى useWalking.
 */
import { useWalking } from './use-walking';

export function useWalkingStats() {
  const walking = useWalking();
  return {
    ...walking,
    refresh: walking.refreshAll,
  };
}

export type UseWalkingStatsResult = ReturnType<typeof useWalkingStats>;
