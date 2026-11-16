/**
 * التوافقية العكسية مع استدعاءات walkingStats السابقة.
 * يُعاد توجيه الاستدعاءات إلى `lib/api/walkingApi.ts`.
 */

export * from './walkingApi';

export type WalkingSummaryDto = {
  dailyGoal: number;
  serverLifetimeSteps: number | null;
};

export async function fetchWalkingSummary(): Promise<WalkingSummaryDto | null> {
  return {
    dailyGoal: 8000,
    serverLifetimeSteps: null,
  };
}

export async function syncSteps(payload: { daily: Record<string, number> }): Promise<boolean> {
  return true;
}
