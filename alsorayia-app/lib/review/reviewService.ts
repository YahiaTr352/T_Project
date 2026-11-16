import * as StoreReview from 'expo-store-review';
import * as SecureStore from 'expo-secure-store';
import Constants from 'expo-constants';
import { Linking, Platform } from 'react-native';

const STORAGE_KEYS = {
  FIRST_LAUNCH: 'app_review_first_launch_timestamp',
  SESSION_COUNT: 'app_review_session_count',
  LAST_PROMPTED: 'app_review_last_prompted_timestamp',
  PROMPTED_VERSION: 'app_review_prompted_app_version',
  DELIGHT_MILESTONES: 'app_review_delight_milestones_count',
} as const;

/**
 * معايير الأهلية القياسية للتقييم
 */
export const REVIEW_ELIGIBILITY_RULES = {
  MIN_DAYS_SINCE_FIRST_LAUNCH: 5,   // 5 أيام على الأقل من أول فتح
  MIN_SESSIONS: 4,                  // 4 جلسات استخدام على الأقل
  MIN_DELIGHT_MILESTONES: 2,        // إنجاز عمليتين ناجحتين (مثل تحقيق خطوات، إتمام مسار، إلخ)
  COOLDOWN_DAYS: 90,                // 90 يوماً فترة تهدئة بين كل محاولة
  APPLE_APP_ID: '6761764975',       // معرف التطبيق على App Store
  ANDROID_PACKAGE: 'com.alsorayia.app',
};

async function getStorageItem(key: string): Promise<string | null> {
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage === 'undefined') return null;
      return localStorage.getItem(key);
    }
    return await SecureStore.getItemAsync(key);
  } catch {
    return null;
  }
}

async function setStorageItem(key: string, value: string): Promise<void> {
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(key, value);
      }
      return;
    }
    await SecureStore.setItemAsync(key, value);
  } catch {
    // Ignore storage errors gracefully
  }
}

export class ReviewService {
  /**
   * تسجيل فتح التطبيق وتحديث عداد الجلسات وتاريخ أول فتح
   */
  static async recordAppLaunch(): Promise<void> {
    try {
      const now = Date.now();
      const firstLaunch = await getStorageItem(STORAGE_KEYS.FIRST_LAUNCH);
      if (!firstLaunch) {
        await setStorageItem(STORAGE_KEYS.FIRST_LAUNCH, now.toString());
      }

      const sessions = Number((await getStorageItem(STORAGE_KEYS.SESSION_COUNT)) || '0');
      await setStorageItem(STORAGE_KEYS.SESSION_COUNT, (sessions + 1).toString());
    } catch {
      // صامت
    }
  }

  /**
   * تسجيل لحظة نجاح / إنجاز إيجابي للمستخدم (Delight Moment)
   * مع التحقق التلقائي من الأهلية واستدعاء نافذة التقييم الأصلية عند استيفاء المعايير
   */
  static async recordDelightMilestone(actionTag?: string): Promise<boolean> {
    try {
      const currentMilestones = Number((await getStorageItem(STORAGE_KEYS.DELIGHT_MILESTONES)) || '0');
      const updatedMilestones = currentMilestones + 1;
      await setStorageItem(STORAGE_KEYS.DELIGHT_MILESTONES, updatedMilestones.toString());

      return await this.requestReviewIfEligible();
    } catch {
      return false;
    }
  }

  /**
   * التحقق من استيفاء جميع شروط الأهلية قبل استدعاء نافذة المتجر الأصلية
   */
  static async isEligibleForInAppReview(): Promise<{ eligible: boolean; reason?: string }> {
    try {
      const isAvailable = await StoreReview.isAvailableAsync();
      if (!isAvailable) {
        return { eligible: false, reason: 'store_review_not_available_on_device' };
      }

      const currentVersion = Constants.expoConfig?.version || '1.0.0';
      const lastVersionPrompted = await getStorageItem(STORAGE_KEYS.PROMPTED_VERSION);
      if (lastVersionPrompted === currentVersion) {
        return { eligible: false, reason: 'already_prompted_for_this_version' };
      }

      const firstLaunchStr = await getStorageItem(STORAGE_KEYS.FIRST_LAUNCH);
      if (!firstLaunchStr) {
        return { eligible: false, reason: 'first_launch_not_recorded' };
      }
      const daysSinceInstall = (Date.now() - Number(firstLaunchStr)) / (1000 * 60 * 60 * 24);
      if (daysSinceInstall < REVIEW_ELIGIBILITY_RULES.MIN_DAYS_SINCE_FIRST_LAUNCH) {
        return { eligible: false, reason: 'not_enough_days_since_install' };
      }

      const sessionCount = Number((await getStorageItem(STORAGE_KEYS.SESSION_COUNT)) || '0');
      if (sessionCount < REVIEW_ELIGIBILITY_RULES.MIN_SESSIONS) {
        return { eligible: false, reason: 'not_enough_sessions' };
      }

      const milestones = Number((await getStorageItem(STORAGE_KEYS.DELIGHT_MILESTONES)) || '0');
      if (milestones < REVIEW_ELIGIBILITY_RULES.MIN_DELIGHT_MILESTONES) {
        return { eligible: false, reason: 'not_enough_delight_milestones' };
      }

      const lastPromptedStr = await getStorageItem(STORAGE_KEYS.LAST_PROMPTED);
      if (lastPromptedStr) {
        const daysSinceLastPrompt = (Date.now() - Number(lastPromptedStr)) / (1000 * 60 * 60 * 24);
        if (daysSinceLastPrompt < REVIEW_ELIGIBILITY_RULES.COOLDOWN_DAYS) {
          return { eligible: false, reason: 'in_cooldown_period' };
        }
      }

      return { eligible: true };
    } catch {
      return { eligible: false, reason: 'error_evaluating_eligibility' };
    }
  }

  /**
   * طلب التقييم الذكي الأصلي (Native StoreKit / Google Review) فقط إذا كان مؤهلاً
   */
  static async requestReviewIfEligible(): Promise<boolean> {
    try {
      const eligibility = await this.isEligibleForInAppReview();
      if (!eligibility.eligible) {
        return false;
      }

      // استدعاء واجهة النظام الأصلية للتقييم دون إخراج المستخدم من التطبيق
      await StoreReview.requestReview();

      // حفظ بيانات التهدئة ورقم الإصدار
      const currentVersion = Constants.expoConfig?.version || '1.0.0';
      await setStorageItem(STORAGE_KEYS.LAST_PROMPTED, Date.now().toString());
      await setStorageItem(STORAGE_KEYS.PROMPTED_VERSION, currentVersion);

      return true;
    } catch {
      return false;
    }
  }

  /**
   * فتح صفحة المتجر مباشرة للتقييم اليدوي (مثلاً عند الضغط على زر "قيّمنا" في الإعدادات)
   */
  static async openStorePageManually(): Promise<boolean> {
    const { APPLE_APP_ID, ANDROID_PACKAGE } = REVIEW_ELIGIBILITY_RULES;

    try {
      if (Platform.OS === 'ios') {
        const iosStoreUrl = `itms-apps://itunes.apple.com/app/viewContentsUserReviews/id${APPLE_APP_ID}?action=write-review`;
        const webFallback = `https://apps.apple.com/app/id${APPLE_APP_ID}?action=write-review`;
        const canOpen = await Linking.canOpenURL(iosStoreUrl);
        if (canOpen) {
          await Linking.openURL(iosStoreUrl);
          return true;
        }
        await Linking.openURL(webFallback);
        return true;
      }

      if (Platform.OS === 'android') {
        const androidMarketUrl = `market://details?id=${ANDROID_PACKAGE}&showAllReviews=true`;
        const webFallback = `https://play.google.com/store/apps/details?id=${ANDROID_PACKAGE}`;
        const canOpen = await Linking.canOpenURL(androidMarketUrl);
        if (canOpen) {
          await Linking.openURL(androidMarketUrl);
          return true;
        }
        await Linking.openURL(webFallback);
        return true;
      }

      // منصات الويب أو غيرها
      await Linking.openURL(`https://apps.apple.com/app/id${APPLE_APP_ID}`);
      return true;
    } catch {
      return false;
    }
  }
}
