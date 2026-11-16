import { useCallback } from 'react';
import { ReviewService } from '@/lib/review/reviewService';

/**
 * Hook لإدارة التقييم الذكي واستدعاء الـ Delight Moments بسهولة من أي شاشة
 */
export function useInAppReview() {
  const recordDelightMoment = useCallback(async (actionTag?: string): Promise<boolean> => {
    return await ReviewService.recordDelightMilestone(actionTag);
  }, []);

  const openStoreDirectly = useCallback(async (): Promise<boolean> => {
    return await ReviewService.openStorePageManually();
  }, []);

  const requestReviewIfEligible = useCallback(async (): Promise<boolean> => {
    return await ReviewService.requestReviewIfEligible();
  }, []);

  return {
    recordDelightMoment,
    openStoreDirectly,
    requestReviewIfEligible,
  };
}
