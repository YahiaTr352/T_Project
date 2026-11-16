import { useEffect, useState } from 'react';
import { BackHandler, Linking, Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { checkAppVersionApi } from '@/lib/api/appVersion';

const SNOOZE_KEY = '@app_update_snoozed_until';
const SNOOZE_DURATION = 24 * 60 * 60 * 1000; // 24 ساعة

export interface MaintenanceGateState {
  visible: boolean;
  title: string;
  message: string;
  supportWhatsapp?: string;
}

export interface UpdateGateState {
  visible: boolean;
  isMandatory: boolean;
  title: string;
  description: string;
  versionLabel: string;
  storeUrl?: string;
}

export interface AppVersionGateState extends UpdateGateState {
  isMaintenance?: boolean;
}

export function useAppVersionGate() {
  const [maintenanceState, setMaintenanceState] = useState<MaintenanceGateState>({
    visible: false,
    title: 'التطبيق تحت الصيانة',
    message: 'نعمل حالياً على إجراء أعمال صيانة وترقية دورية لتحسين الخدمات وتوفير تجربة أفضل. سنعود للعمل في أقرب وقت.',
  });

  const [updateState, setUpdateState] = useState<UpdateGateState>({
    visible: false,
    isMandatory: false,
    title: 'تحديث جديد متاح للتطبيق',
    description: 'يتوفر إصدار جديد يحتوي على تحسينات وإصلاحات هامة.',
    versionLabel: 'الإصدار الأحدث',
  });

  const [isCheckingMaintenance, setIsCheckingMaintenance] = useState(false);

  useEffect(() => {
    async function verify() {
      try {
        const result = await checkAppVersionApi();
        if (!result) return;

        // 1️⃣ حالة وضع الصيانة — عزل كامل عن مسار التحديث
        if (result.action === 'MAINTENANCE') {
          setMaintenanceState({
            visible: true,
            title: result.title || 'التطبيق تحت الصيانة',
            message:
              result.message ||
              result.release_notes ||
              'نعمل حالياً على إجراء أعمال صيانة وترقية دورية لتحسين الخدمات وتوفير تجربة أفضل. سنعود للعمل في أقرب وقت.',
          });
          setUpdateState((prev) => ({ ...prev, visible: false }));
          return;
        }

        // عند انتهاء الصيانة أو عدم وجودها
        setMaintenanceState((prev) => ({ ...prev, visible: false }));

        // 2️⃣ حالة التحديث الإجباري
        if (result.action === 'FORCE_UPDATE') {
          setUpdateState({
            visible: true,
            isMandatory: true,
            title: result.title || 'تحديث إجباري مطلوب',
            description: result.release_notes || result.message || 'يرجى تحديث التطبيق الآن للاستمرار في استخدامه.',
            versionLabel: result.latest_version ? `إصدار ${result.latest_version}` : 'الإصدار الأحدث',
            storeUrl: result.store_url,
          });
        } else if (result.action === 'OPTIONAL_UPDATE') {
          // 3️⃣ حالة التحديث الاختياري مع احترام التأجيل (24 ساعة)
          let snoozedUntilStr: string | null = null;
          try {
            if (Platform.OS === 'web') {
              snoozedUntilStr = typeof localStorage !== 'undefined' ? localStorage.getItem(SNOOZE_KEY) : null;
            } else {
              snoozedUntilStr = await SecureStore.getItemAsync(SNOOZE_KEY);
            }
          } catch {}

          const snoozedUntil = snoozedUntilStr ? parseInt(snoozedUntilStr, 10) : 0;
          if (Date.now() > snoozedUntil) {
            setUpdateState({
              visible: true,
              isMandatory: false,
              title: result.title || 'تحديث جديد متاح!',
              description: result.release_notes || result.message || 'يتوفر إصدار جديد يحتوي على ميزات وتحسينات جديدة لتجربة أفضل.',
              versionLabel: result.latest_version ? `إصدار ${result.latest_version}` : 'الإصدار الأحدث',
              storeUrl: result.store_url,
            });
          }
        } else if (result.action === 'UP_TO_DATE') {
          setUpdateState((prev) => ({ ...prev, visible: false }));
        }
      } catch (e) {
        console.warn('[useAppVersionGate] Verification error:', e);
      }
    }

    void verify();
  }, []);

  // منع زر الرجوع في أندرويد في حال كان التطبيق تحت الصيانة أو التحديث إجبارياً
  useEffect(() => {
    const isBlocking = maintenanceState.visible || (updateState.visible && updateState.isMandatory);
    if (isBlocking) {
      const backAction = () => true;
      const backHandler = BackHandler.addEventListener('hardwareBackPress', backAction);
      return () => backHandler.remove();
    }
  }, [maintenanceState.visible, updateState.visible, updateState.isMandatory]);

  // إعادة فحص الصيانة مباشرة عند ضغط المستخدم على زر "التحقق من حالة النظام"
  const recheckMaintenance = async (): Promise<'still_maintenance' | 'resolved' | 'error'> => {
    setIsCheckingMaintenance(true);
    try {
      const result = await checkAppVersionApi();
      if (!result) return 'error';

      if (result.action === 'MAINTENANCE') {
        setMaintenanceState({
          visible: true,
          title: result.title || 'التطبيق تحت الصيانة',
          message:
            result.message ||
            result.release_notes ||
            'نعمل حالياً على إجراء أعمال صيانة وترقية دورية لتحسين الخدمات وتوفير تجربة أفضل. سنعود للعمل في أقرب وقت.',
        });
        return 'still_maintenance';
      }

      // إذا انتهت الصيانة، نغلق النافذة ونفتح التطبيق مباشرة
      setMaintenanceState((prev) => ({ ...prev, visible: false }));

      // التحقق مما إذا كان هناك تحديث مستحق فور اكتمال الصيانة
      if (result.action === 'FORCE_UPDATE' || result.action === 'OPTIONAL_UPDATE') {
        setUpdateState({
          visible: true,
          isMandatory: result.action === 'FORCE_UPDATE',
          title: result.title || (result.action === 'FORCE_UPDATE' ? 'تحديث إجباري مطلوب' : 'تحديث جديد متاح!'),
          description: result.release_notes || result.message || 'يتوفر إصدار جديد للتطبيق.',
          versionLabel: result.latest_version ? `إصدار ${result.latest_version}` : 'الإصدار الأحدث',
          storeUrl: result.store_url,
        });
      }

      return 'resolved';
    } catch (err) {
      console.warn('[useAppVersionGate] Recheck error:', err);
      return 'error';
    } finally {
      setIsCheckingMaintenance(false);
    }
  };

  const handleUpdate = async () => {
    if (updateState.storeUrl) {
      try {
        const canOpen = await Linking.canOpenURL(updateState.storeUrl);
        if (canOpen) {
          await Linking.openURL(updateState.storeUrl);
          return;
        }
      } catch (e) {
        console.warn('Cannot open store URL directly, falling back:', e);
      }
      void Linking.openURL(updateState.storeUrl);
    }
  };

  const handleDismiss = async () => {
    const nextPrompt = (Date.now() + SNOOZE_DURATION).toString();
    try {
      if (Platform.OS === 'web') {
        if (typeof localStorage !== 'undefined') localStorage.setItem(SNOOZE_KEY, nextPrompt);
      } else {
        await SecureStore.setItemAsync(SNOOZE_KEY, nextPrompt);
      }
    } catch {}

    setUpdateState((prev) => ({ ...prev, visible: false }));
  };

  return {
    modalState: {
      ...updateState,
      isMaintenance: maintenanceState.visible,
    },
    updateState,
    maintenanceState,
    isCheckingMaintenance,
    recheckMaintenance,
    handleUpdate,
    handleDismiss,
  };
}
