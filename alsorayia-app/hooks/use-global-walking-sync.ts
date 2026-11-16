import { useAuth } from '@/contexts/auth-context';
import { performSilentWalkingSync } from '@/lib/walking/syncService';
import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus, Platform } from 'react-native';

/**
 * خطاف (Hook) للمزامنة الصامتة لخطوات المشي في خلفية التطبيق.
 * يُستدعى على مستوى الجذر (Root Layout) للمستخدمين المسجلين.
 * يقوم بمزامنة الخطوات:
 * 1. بعد تشغيل التطبيق واكتمال تحميل الجلسة بهدوء.
 * 2. عند عودة المستخدم للتطبيق من الخلفية (Foreground / AppState Active).
 */
export function useGlobalWalkingSync() {
  const { isAuthenticated, hasIdentity } = useAuth();
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);

  // 1. مزامنة استهلالية بعد الإقلاع وتوفر هوية المستخدم
  useEffect(() => {
    if (!isAuthenticated || !hasIdentity || Platform.OS === 'web') return;

    // تأخير طفيف (1.5 ثانية) لعدم منافسة عمليات التحميل المبدئية للشاشة الرئيسية
    const timer = setTimeout(() => {
      void performSilentWalkingSync().catch(() => {});
    }, 1500);

    return () => clearTimeout(timer);
  }, [isAuthenticated, hasIdentity]);

  // 2. مزامنة عند عودة التطبيق للواجهة (AppState: background -> active)
  useEffect(() => {
    if (!isAuthenticated || !hasIdentity || Platform.OS === 'web') return;

    const sub = AppState.addEventListener('change', (nextState) => {
      const isComingToForeground =
        appStateRef.current.match(/inactive|background/) && nextState === 'active';

      if (isComingToForeground) {
        void performSilentWalkingSync().catch(() => {});
      }
      appStateRef.current = nextState;
    });

    return () => sub.remove();
  }, [isAuthenticated, hasIdentity]);
}
