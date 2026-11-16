import { useAuth } from '@/contexts/auth-context';
import { getDeviceIdentifier, registerDevice } from '@/lib/api/devices';
import { useState, useEffect, useRef } from 'react';
import { Alert, Platform } from 'react-native';
import * as Device from 'expo-device';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import type * as NotificationsType from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import {
  APP_ROUTES,
  extractChatId,
  extractGroupId,
  normalizeNotificationRoute,
} from '@/lib/utils/notificationRoutes';

/**
 * في Expo Go (خصوصاً Android من SDK 53+) أزيلت ميزة Remote Push Notifications من تطبيق Expo Go.
 * لتفادي انهيار التطبيق، نتحقق من بيئة التشغيل ونحمّل الوحدة فقط في الـ Development Build أو الإنتاج.
 */
const isExpoGo =
  Constants.executionEnvironment === ExecutionEnvironment.StoreClient ||
  Constants.appOwnership === 'expo';

const CAN_USE_EXPO_PUSH_RUNTIME = Platform.OS !== 'web' && !isExpoGo;

function getNotificationsModule(): typeof NotificationsType | null {
  if (!CAN_USE_EXPO_PUSH_RUNTIME) return null;
  try {
    // intentionally lazy: expo-notifications remote push is unavailable in Expo Go
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    return require('expo-notifications');
  } catch (e) {
    if (__DEV__) {
      console.warn('[push] expo-notifications could not be loaded:', e);
    }
    return null;
  }
}

export interface PushNotificationState {
  expoPushToken?: string;
  notification?: NotificationsType.Notification;
}

/**
 * وجهة الانتقال بعد النقر على الإشعار:
 * - route: مسار داخلي صالح داخل التطبيق
 * - external: رابط خارجي يُفتح بالمتصفح
 */
type PushTarget =
  | { kind: 'route'; route: any }
  | { kind: 'external'; url: string };

export const usePushNotifications = (): PushNotificationState => {
  const { hasIdentity, isAuthenticated, isLoading, lock, lastAuthTime } = useAuth();
  const [expoPushToken, setExpoPushToken] = useState<string | undefined>();
  const [notification, setNotification] = useState<NotificationsType.Notification | undefined>();

  const notificationListener = useRef<{ remove: () => void } | undefined>(undefined);
  const responseListener = useRef<{ remove: () => void } | undefined>(undefined);
  const pendingRouteRef = useRef<PushTarget | null>(null);
  const handledResponseIdRef = useRef<string | null>(null);

  /**
   * مرجع ثابت لحالة المصادقة ودالة lock حتى نتجنب استدعاءات قديمة أو إعادة بناء المستمعين
   */
  const lockRef = useRef(lock);
  useEffect(() => {
    lockRef.current = lock;
  }, [lock]);

  const authRef = useRef({ isAuthenticated, lastAuthTime });
  useEffect(() => {
    authRef.current = { isAuthenticated, lastAuthTime };
  }, [isAuthenticated, lastAuthTime]);

  /**
   * معالجة إشعار الطرد (FORCE_LOGOUT) بحصانة الطرد الذاتي وفحص صلاحية الإشعار
   */
  const handleForceLogoutNotification = async (data: Record<string, any>) => {
    try {
      const myDeviceUuid = await getDeviceIdentifier();
      const displacedBy = data?.displaced_by_device || data?.displacedByDevice;

      // 1. حصانة الطرد الذاتي (Self-Displacement Immunity):
      // إذا كان الإشعار ناتجاً عن تسجيل دخول هذا الجهاز نفسه، يتم إسقاطه فوراً
      if (displacedBy && myDeviceUuid && displacedBy === myDeviceUuid) {
        if (__DEV__) {
          console.log('[push 🛡️] Self-displacement event received from current device. Ignoring.');
        }
        return;
      }

      // 2. فحص زمن الإشعار (Stale Notification Protection):
      // إذا كان الإشعار قد تم إرساله قبل وقت الجلسة الحالية، فهو إشعار قديم من جلسة ماضية ويتم تجاهله
      const currentAuthTime = authRef.current.lastAuthTime;
      if (data?.displaced_at && currentAuthTime) {
        const displacedTimeMs = Number(data.displaced_at) * 1000;
        if (displacedTimeMs < currentAuthTime) {
          if (__DEV__) {
            console.log('[push 🛡️] Stale FORCE_LOGOUT notification received. Ignoring.');
          }
          return;
        }
      }

      // 3. التحقق من أن الجلسة نشطة ومسجلة حالياً
      if (!authRef.current.isAuthenticated) {
        if (__DEV__) {
          console.log('[push] User is already logged out; skipping redundant logout alert.');
        }
        return;
      }

      if (__DEV__) {
        console.log('[push 🔒] Legitimate FORCE_LOGOUT received from another device — locking session.');
      }

      Alert.alert(
        'تنبيه أمان الجلسة',
        'تم تسجيل الدخول إلى حسابك من جهاز آخر، وتم إنهاء جلستك على هذا الجهاز لحماية خصوصيتك.',
        [{ text: 'حسناً', onPress: () => void lockRef.current() }],
        { cancelable: false }
      );
      await lockRef.current();
    } catch (e) {
      console.warn('[push] Error handling FORCE_LOGOUT notification:', e);
    }
  };

  // استخراج وجهة التوجيه بدقة متناهية مع دعم دردشة التحدي
  const resolveTargetRoute = (response: any): PushTarget | null => {
    const content = response?.notification?.request?.content;
    const data = (content?.data || {}) as Record<string, any>;
    const title = content?.title || '';
    const body = content?.body || '';
    const fullText = `${title} ${body}`.toLowerCase();

    if (__DEV__) {
      console.log('[push] Resolving route for notification data:', JSON.stringify(data));
    }

    // معرّفات مجموعة التحدي / سؤال المسابقة من حمولة الإشعار
    const payloadGroupId = extractGroupId(data);
    const payloadChatId = extractChatId(data);

    // المعرّف الرقمي العام (يُستخدم كملاذ أخير فقط)
    const fallbackId =
      typeof data.id === 'number' || (typeof data.id === 'string' && /^\d+$/.test(data.id))
        ? String(data.id)
        : null;

    const groupTitle = String(
      data.group_name ||
      data.group_title ||
      data.name ||
      data.title ||
      title ||
      'دردشة التحدي'
    );

    const actionUrl = typeof data.action_url === 'string' ? data.action_url : null;

    // فحص ما إذا كان الإشعار لرسالة جديدة في دردشة
    const isChatPayload =
      data.type === 'walking_group_chat' ||
      data.type === 'walking_chat' ||
      data.type === 'chat' ||
      data.type === 'group_chat' ||
      data.type === 'challenge_chat' ||
      data.type === 'chat_message' ||
      data.type === 'question_chat' ||
      data.type === 'quiz_chat' ||
      data.is_chat === true ||
      data.is_chat === '1' ||
      Boolean(data.message_id) ||
      Boolean(data.chat_message_id) ||
      (typeof actionUrl === 'string' && /chat/i.test(actionUrl)) ||
      fullText.includes('دردشة') ||
      fullText.includes('رسالة جديدة') ||
      fullText.includes('أرسل رسالة') ||
      fullText.includes('أرسلت رسالة') ||
      fullText.includes('كتب في المجموعة');

    const isQuizChat =
      data.type === 'question_chat' ||
      data.type === 'quiz_chat' ||
      (typeof actionUrl === 'string' && actionUrl.includes('quiz-chat'));

    // 1) دردشة: نبني المسار الصحيح دائماً حتى لا يحدث 404
    if (isChatPayload) {
      const chatId = payloadChatId || fallbackId;

      if (isQuizChat && chatId) {
        return {
          kind: 'route',
          route: {
            pathname: '/(app)/quiz-chat/[id]',
            params: { id: chatId, title: groupTitle },
          },
        };
      }

      const groupId = payloadGroupId || fallbackId;
      if (!isQuizChat && groupId) {
        return {
          kind: 'route',
          route: {
            pathname: '/(app)/walking-chat/[id]',
            params: { id: groupId, title: groupTitle },
          },
        };
      }

      // قد يكون المعرّف موجوداً داخل action_url نفسه
      const normalized = normalizeNotificationRoute(actionUrl, {
        title: groupTitle,
        groupId: payloadGroupId,
        chatId: payloadChatId,
        chatKind: isQuizChat ? 'quiz' : 'walking',
      });

      if (normalized.route) return { kind: 'route', route: normalized.route };
      if (normalized.url) return { kind: 'external', url: normalized.url };
    }

    // 2) إشعار مجموعة مشي عادية (انضمام / دعوة / تنبيه)
    if (
      (data.type === 'walking_group' ||
        data.type === 'challenge' ||
        data.type === 'walking') &&
      payloadGroupId
    ) {
      return {
        kind: 'route',
        route: {
          pathname: '/(tabs)/walking',
          params: { tab: 'groups', groupId: payloadGroupId },
        },
      };
    }

    if (data.type === 'walking_group_removed') {
      return {
        kind: 'route',
        route: {
          pathname: '/(tabs)/walking',
          params: { tab: 'groups' },
        },
      };
    }

    // 3) action_url — يُطبَّع قبل الاستخدام حتى لا يسبب 404
    if (actionUrl) {
      const normalized = normalizeNotificationRoute(actionUrl, {
        title: groupTitle,
        groupId: payloadGroupId,
        chatId: payloadChatId,
      });

      if (normalized.route) return { kind: 'route', route: normalized.route };
      if (normalized.url) return { kind: 'external', url: normalized.url };
    }

    // 4) فتح صفحة الإشعارات على الإشعار المعني إن أمكن
    const notifId = data.notification_id || (isChatPayload ? null : data.id);
    if (notifId) {
      return {
        kind: 'route',
        route: {
          pathname: '/(app)/notifications',
          params: { id: String(notifId) },
        },
      };
    }

    return { kind: 'route', route: APP_ROUTES.notifications };
  };

  // Effect 1: Initialize local notification settings and fetch Expo push token
  useEffect(() => {
    const Notifications = getNotificationsModule();
    if (!Notifications) {
      if (__DEV__) {
        console.log(
          '[push] Remote push skipped in Expo Go. Use a development build (expo run:android / EAS) for full notifications.'
        );
      }
      return;
    }

    let cancelled = false;

    async function registerForPushNotificationsAsync() {
      if (!Notifications) return undefined;
      let token: string | undefined;

      if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('default', {
          name: 'إشعارات عائلة السريّع',
          importance: Notifications.AndroidImportance.MAX,
          vibrationPattern: [0, 250, 250, 250],
          lightColor: '#37a05c',
          enableVibrate: true,
          showBadge: true,
        });
      }

      if (Device.isDevice) {
        const { status: existingStatus } = await Notifications.getPermissionsAsync();
        let finalStatus = existingStatus;
        if (existingStatus !== 'granted') {
          const { status } = await Notifications.requestPermissionsAsync();
          finalStatus = status;
        }
        if (finalStatus !== 'granted') {
          console.warn('[push] Notification permission not granted on Android/iOS; skipping token.');
          return undefined;
        }

        const projectId =
          Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;

        if (!projectId) {
          console.error('[push] EAS projectId missing in app config; cannot get Expo push token.');
          return undefined;
        }

        try {
          token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
          if (__DEV__) {
            console.log('[push] Expo push token obtained successfully:', token);
          }
          if (token) {
            void SecureStore.setItemAsync('expo_push_token', token).catch(() => {});
          }
        } catch (e) {
          console.error(
            '[push] getExpoPushTokenAsync failed on Android. Ensure google-services.json and FCM credentials are configured in EAS:',
            e
          );
        }
      } else if (__DEV__) {
        console.log('[push] Emulator/Simulator detected. Push notifications require a physical device.');
      }

      return token;
    }

    (async () => {
      // التحقق مما إذا كان العضو قد عطل الإشعارات من إعدادات التطبيق
      const enabled = await SecureStore.getItemAsync('notifications_enabled');
      if (enabled === 'false') {
        if (__DEV__) {
          console.log('[push] Push notifications are disabled in settings. Skipping registration.');
        }
        return;
      }

      if (cancelled) return;

      Notifications.setNotificationHandler({
        handleNotification: async () => ({
          shouldShowBanner: true,
          shouldShowList: true,
          shouldPlaySound: true,
          shouldSetBadge: true,
        }),
      });

      const token = await registerForPushNotificationsAsync();
      if (!cancelled && token) {
        setExpoPushToken(token);
      }

      notificationListener.current = Notifications.addNotificationReceivedListener((n) => {
        // ── تهجير الجلسة عند وصول الإشعار (Foreground) مع حماية الطرد الذاتي ──
        const notifData = (n?.request?.content?.data || {}) as Record<string, any>;
        if (notifData?.type === 'FORCE_LOGOUT' || notifData?.action === 'FORCE_LOGOUT') {
          void handleForceLogoutNotification(notifData);
          return; // لا تُحدِّث state الإشعار — هذا إشعار أمني صامت
        }
        setNotification(n);
      });

      const dispatchRoute = (targetRoute: PushTarget | null) => {
        if (!targetRoute) return;

        if (isLoading || !isAuthenticated) {
          if (__DEV__) {
            console.log('[push] Auth still loading or not authenticated, queueing route:', targetRoute);
          }
          pendingRouteRef.current = targetRoute;
          return;
        }

        setTimeout(() => {
          try {
            if (targetRoute.kind === 'external') {
              void WebBrowser.openBrowserAsync(targetRoute.url).catch((err) => {
                console.warn('[push] Failed to open external url:', err);
              });
              return;
            }

            if (typeof targetRoute.route === 'string') {
              router.push(targetRoute.route as any);
            } else {
              router.push(targetRoute.route);
            }
          } catch (err) {
            console.error('[push] router.push failed:', err);
          }
        }, 150);
      };

      const handleResponse = (response: any) => {
        try {
          const identifier = response?.notification?.request?.identifier;
          if (identifier) {
            if (handledResponseIdRef.current === identifier) {
              return;
            }
            handledResponseIdRef.current = identifier;
          }

          const content = response?.notification?.request?.content;
          const data = (content?.data || {}) as Record<string, any>;
          if (data?.type === 'FORCE_LOGOUT' || data?.action === 'FORCE_LOGOUT') {
            void handleForceLogoutNotification(data);
            return;
          }

          const target = resolveTargetRoute(response);
          if (target !== null) dispatchRoute(target);
        } catch (e) {
          console.error('[push] Failed to handle notification response:', e);
        }
      };

      // 1. الاستماع للنقر على الإشعار أثناء تشغيل التطبيق (Foreground / Background)
      responseListener.current = Notifications.addNotificationResponseReceivedListener((response) => {
        handleResponse(response);
      });

      // 2. معالجة النقر على الإشعار عند إطلاق التطبيق من حالة الإغلاق التام (Cold Start)
      try {
        void Notifications.getLastNotificationResponseAsync().then((coldResponse) => {
          if (coldResponse && !cancelled) {
            if (__DEV__) {
              console.log('[push] Handling cold start notification response');
            }
            handleResponse(coldResponse);
          }
        });
      } catch (err) {
        console.warn('[push] getLastNotificationResponseAsync error:', err);
      }
    })().catch((e) => {
      console.error('[push] Failed to initialize expo-notifications:', e);
    });

    return () => {
      cancelled = true;
      notificationListener.current?.remove();
      responseListener.current?.remove();
      notificationListener.current = undefined;
      responseListener.current = undefined;
    };
  }, [isLoading, isAuthenticated]);

  // تنفيذ أي توجيه معلق فور اكتمال فحص حالة المصادقة والدخول
  useEffect(() => {
    if (!isLoading && isAuthenticated && pendingRouteRef.current) {
      const target = pendingRouteRef.current;
      pendingRouteRef.current = null;
      setTimeout(() => {
        try {
          if (target.kind === 'external') {
            void WebBrowser.openBrowserAsync(target.url).catch((err) => {
              console.warn('[push] Failed to open external url:', err);
            });
            return;
          }

          if (typeof target.route === 'string') {
            router.push(target.route as any);
          } else {
            router.push(target.route);
          }
        } catch (e) {
          console.error('[push] Failed to navigate to queued route:', e);
        }
      }, 350);
    }
  }, [isLoading, isAuthenticated]);

  // Effect 2: تسجيل الجهاز فور التوكن، وإعادة التسجيل بعد الهوية أو تغيّر التوكن
  useEffect(() => {
    if (!expoPushToken || isLoading || !isAuthenticated) {
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const response = await registerDevice(expoPushToken);
        if (cancelled) return;

        if (__DEV__ && response) {
          console.log(
            '[push] Registered token on Laravel backend.',
            'hasIdentity:',
            hasIdentity,
            'Response:',
            JSON.stringify(response, null, 2)
          );
        }
      } catch (err) {
        console.error('[push] Failed to register token on server:', err);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [expoPushToken, hasIdentity, isLoading, isAuthenticated]);

  return {
    expoPushToken,
    notification,
  };
};
