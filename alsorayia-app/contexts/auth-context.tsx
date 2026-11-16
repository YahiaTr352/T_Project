import {
  setOnSessionExpired,
  setOnSitePasswordRequired,
  setOnTokenRefreshed,
  setSiteAccessToken,
} from '@/lib/api/alsorayiaClient';
import {
  getSitePasswordStatus,
  revokeSitePassword,
} from '@/lib/api/sitePasswordApi';
import * as SecureStore from 'expo-secure-store';
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { Alert, Platform } from 'react-native';

import { refreshAuthToken, type AuthPerson } from '@/lib/api/authApi';
import { fetchMyProfile } from '@/lib/api/myProfile';
import { logger } from '@/lib/utils/logger';
import { clearWalkingLedger } from '@/lib/walking/ledger';
import { resetWalkingSyncState } from '@/lib/walking/syncService';

// ==================== الأنواع ====================

export interface AuthenticateOptions {
  accessToken?: string;
  refreshToken?: string;
  expiresInSeconds?: number;
  user?: AuthPerson | null;
}

interface AuthContextType {
  isAuthenticated: boolean;
  isLoading: boolean;
  hasIdentity: boolean;
  authenticate: (opts?: AuthenticateOptions) => Promise<void>;
  lock: () => Promise<void>;
  lastAuthTime: number | null;
  logout: () => Promise<void>;
  user: AuthPerson | null;
  refreshIdentityProfile: () => Promise<boolean>;
}

// ==================== الثوابت ====================

const SITE_ACCESS_TOKEN_KEY = 'site_access_token';
const SITE_REFRESH_TOKEN_KEY = 'site_refresh_token';
const SITE_TOKEN_EXPIRES_AT_KEY = 'site_token_expires_at';
const USER_DATA_KEY = 'authenticated_user_data';
const SITE_PASSWORD_STATUS_CACHE_KEY = 'site_password_status_cache';
/** مفاتيح قديمة — تُزال عند القفل */
const LEGACY_AUTH_KEY = 'app_authenticated';
const LEGACY_AUTH_TIME_KEY = 'app_auth_time';

// ==================== دوال التخزين الآمن ====================

export const storage = {
  async getItem(key: string): Promise<string | null> {
    try {
      if (Platform.OS === 'web') {
        return localStorage.getItem(key);
      }
      return await SecureStore.getItemAsync(key);
    } catch (error) {
      logger.warn('Storage getItem error:', error);
      return null;
    }
  },

  async setItem(key: string, value: string): Promise<boolean> {
    try {
      if (Platform.OS === 'web') {
        localStorage.setItem(key, value);
        return true;
      }
      await SecureStore.setItemAsync(key, value);
      return true;
    } catch (error) {
      logger.warn('Storage setItem error:', error);
      return false;
    }
  },

  async removeItem(key: string): Promise<boolean> {
    try {
      if (Platform.OS === 'web') {
        localStorage.removeItem(key);
        return true;
      }
      await SecureStore.deleteItemAsync(key);
      return true;
    } catch (error) {
      logger.warn('Storage removeItem error:', error);
      return false;
    }
  },
};

// ==================== السياق ====================

const AuthContext = createContext<AuthContextType | undefined>(undefined);

function mapToAuthPerson(p: any, fallbackPhone?: string | null): AuthPerson {
  return {
    id: p.id,
    first_name: p.first_name,
    last_name: p.last_name ?? '',
    full_name: p.full_name,
    avatar: p.avatar ?? null,
    phone: p.phone ?? fallbackPhone ?? null,
    birth_date: p.birth_date ?? null,
    gender: p.gender ?? null,
  };
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [lastAuthTime, setLastAuthTime] = useState<number | null>(null);
  const [user, setUser] = useState<AuthPerson | null>(null);

  const hasIdentity = user?.id != null;

  const clearLocalSession = useCallback(async () => {
    await storage.removeItem(SITE_ACCESS_TOKEN_KEY);
    await storage.removeItem(SITE_REFRESH_TOKEN_KEY);
    await storage.removeItem(SITE_TOKEN_EXPIRES_AT_KEY);
    await storage.removeItem(USER_DATA_KEY);
    await storage.removeItem(LEGACY_AUTH_KEY);
    await storage.removeItem(LEGACY_AUTH_TIME_KEY);
    await clearWalkingLedger();
    resetWalkingSyncState();
    setSiteAccessToken(null);
    setLastAuthTime(null);
    setUser(null);
    setIsAuthenticated(false);
  }, []);

  const lock = useCallback(async () => {
    await clearLocalSession();
  }, [clearLocalSession]);

  const lockRef = useRef(lock);
  useEffect(() => {
    lockRef.current = lock;
  }, [lock]);

  useEffect(() => {
    setOnSitePasswordRequired(() => {
      void lockRef.current();
    });
    setOnSessionExpired((reason?: string) => {
      if (reason === 'SESSION_DISPLACED') {
        // الطرد بسبب تسجيل دخول من جهاز آخر — نقفل الجلسة فوراً ثم نُعلم المستخدم
        void lockRef.current().then(() => {
          Alert.alert(
            'تنبيه أمان الجلسة',
            'تم تسجيل الدخول إلى حسابك من جهاز آخر، وتم إنهاء جلستك على هذا الجهاز لحماية خصوصيتك.',
            [{ text: 'حسناً' }],
            { cancelable: false }
          );
        });
      } else {
        void lockRef.current();
      }
    });
    setOnTokenRefreshed((newToken, expiresIn) => {
      setLastAuthTime(Date.now());
      void fetchMyProfile()
        .then((res) => {
          if (res?.success && res.data?.person) {
            const mapped = mapToAuthPerson(res.data.person);
            setUser(mapped);
            void storage.setItem(USER_DATA_KEY, JSON.stringify(mapped));
          }
        })
        .catch(() => {});
    });
    return () => {
      setOnSitePasswordRequired(null);
      setOnSessionExpired(null);
      setOnTokenRefreshed(null);
    };
  }, []);

  const checkAuthStatus = useCallback(async () => {
    try {
      const token = await storage.getItem(SITE_ACCESS_TOKEN_KEY);
      const refreshToken = await storage.getItem(SITE_REFRESH_TOKEN_KEY);
      const expStr = await storage.getItem(SITE_TOKEN_EXPIRES_AT_KEY);
      const cachedUserStr = await storage.getItem(USER_DATA_KEY);

      if (cachedUserStr) {
        try {
          setUser(JSON.parse(cachedUserStr));
        } catch {}
      }

      if (token) {
        let isExpired = false;
        if (expStr) {
          const exp = parseInt(expStr, 10);
          if (!Number.isNaN(exp) && Date.now() >= exp) {
            isExpired = true;
          }
        }

        if (!isExpired) {
          setSiteAccessToken(token);
          setIsAuthenticated(true);
          setLastAuthTime(Date.now());

          // التحقق الخلفي الآمن لتحديث بيانات المستخدم وتجديد الجلسة عند الحاجة دون تسجيل خروج مفاجئ
          void (async () => {
            try {
              // فقط إذا كان المستخدم موثقاً بهوية (وجه أو واتساب) نحاول جلب ملفه
              if (cachedUserStr || refreshToken) {
                const prof = await fetchMyProfile();
                if (prof?.success && prof?.data?.person) {
                  const mapped = mapToAuthPerson(prof.data.person);
                  setUser(mapped);
                  await storage.setItem(USER_DATA_KEY, JSON.stringify(mapped));
                } else if (!prof?.success && refreshToken) {
                  try {
                    const refreshed = await refreshAuthToken(refreshToken);
                    if (refreshed?.success && refreshed.data?.access_token) {
                      const newAccess = refreshed.data.access_token;
                      const newExp =
                        Date.now() + (refreshed.data.expires_in ?? 30 * 24 * 3600) * 1000;
                      await storage.setItem(SITE_ACCESS_TOKEN_KEY, newAccess);
                      await storage.setItem(SITE_TOKEN_EXPIRES_AT_KEY, newExp.toString());
                      setSiteAccessToken(newAccess);

                      const updatedProf = await fetchMyProfile();
                      if (updatedProf?.success && updatedProf?.data?.person) {
                        const mapped = mapToAuthPerson(updatedProf.data.person);
                        setUser(mapped);
                        await storage.setItem(USER_DATA_KEY, JSON.stringify(mapped));
                      }
                    } else if (refreshed && !refreshed.success) {
                      // إذا رفض السيرفر التجديد، يجب مسح الجلسة فوراً لمنع بقاء بيانات المستخدم شبحية
                      await clearLocalSession();
                    }
                  } catch {
                    // خطأ شبكة مؤقت — نحافظ على الجلسة الحالية
                  }
                } else if (!prof?.success && !refreshToken) {
                  await clearLocalSession();
                }
              }
            } catch {
              // انقطاع مؤقت في الشبكة — نحافظ على الجلسة
            }
          })();

          return;
        }
      }

      // محاولة تجديد الجلسة تلقائياً عبر refresh_token إن وجد وكان الرمز منتهي الصلاحية
      if (refreshToken) {
        try {
          const refreshed = await refreshAuthToken(refreshToken);
          if (refreshed?.success && refreshed.data?.access_token) {
            const newAccess = refreshed.data.access_token;
            const newExp = Date.now() + (refreshed.data.expires_in ?? 30 * 24 * 3600) * 1000;
            await storage.setItem(SITE_ACCESS_TOKEN_KEY, newAccess);
            await storage.setItem(SITE_TOKEN_EXPIRES_AT_KEY, newExp.toString());
            setSiteAccessToken(newAccess);
            setIsAuthenticated(true);
            setLastAuthTime(Date.now());

            const prof = await fetchMyProfile();
            if (prof?.success && prof?.data?.person) {
              const mapped = mapToAuthPerson(prof.data.person);
              setUser(mapped);
              await storage.setItem(USER_DATA_KEY, JSON.stringify(mapped));
            }
            return;
          } else if (refreshed && !refreshed.success) {
            await clearLocalSession();
          }
        } catch (e) {
          logger.warn('[AuthContext] فشل تجديد الرمز تلقائياً:', e);
        }
      }

      // إذا وصلنا هنا، لا يوجد رمز صالح ولا يمكن التجديد: مسح الجلسة والبيانات بالكامل
      await clearLocalSession();

      let requiresPassword = true;
      try {
        const status = await getSitePasswordStatus();
        if (status?.success) {
          requiresPassword = status.requires_password;
          await storage.setItem(
            SITE_PASSWORD_STATUS_CACHE_KEY,
            JSON.stringify({
              requires_password: requiresPassword,
              password_length: status.password_length,
              timestamp: Date.now(),
            })
          );
        }
      } catch (err) {
        logger.warn(
          '[AuthContext] فشل جلب حالة الحماية من الشبكة، محاولة استخدام الكاش المحلي:',
          err
        );
        const cachedStr = await storage.getItem(SITE_PASSWORD_STATUS_CACHE_KEY);
        if (cachedStr) {
          try {
            const cached = JSON.parse(cachedStr);
            requiresPassword = cached.requires_password;
          } catch {
            // تجاهل خطأ التحليل واستمر بالافتراضي
          }
        }
      }

      if (!requiresPassword) {
        setIsAuthenticated(true);
        setLastAuthTime(Date.now());
        return;
      }

      setIsAuthenticated(false);
    } catch {
      setSiteAccessToken(null);
      setIsAuthenticated(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setIsLoading(true);
      await checkAuthStatus();
      if (!cancelled) setIsLoading(false);
    })();
    return () => {
      cancelled = true;
    };
  }, [checkAuthStatus]);

  const authenticate = useCallback(async (opts?: AuthenticateOptions) => {
    if (opts?.accessToken != null) {
      await storage.setItem(SITE_ACCESS_TOKEN_KEY, opts.accessToken);
      setSiteAccessToken(opts.accessToken);
    }
    if (opts?.refreshToken != null) {
      await storage.setItem(SITE_REFRESH_TOKEN_KEY, opts.refreshToken);
    }
    const THIRTY_DAYS_SEC = 30 * 24 * 3600;
    const durationSeconds =
      opts?.expiresInSeconds != null
        ? Math.max(opts.expiresInSeconds, THIRTY_DAYS_SEC)
        : THIRTY_DAYS_SEC;

    if (opts?.accessToken != null) {
      const expiresAt = Date.now() + durationSeconds * 1000;
      await storage.setItem(SITE_TOKEN_EXPIRES_AT_KEY, expiresAt.toString());
    }

    if (opts?.user) {
      setUser(opts.user);
      await storage.setItem(USER_DATA_KEY, JSON.stringify(opts.user));
    }

    const now = Date.now();
    setLastAuthTime(now);
    setIsAuthenticated(true);
  }, []);

  const logout = useCallback(async () => {
    const token = await storage.getItem(SITE_ACCESS_TOKEN_KEY);
    if (token) {
      try {
        await revokeSitePassword(token);
      } catch {
        /* أفضل جهد */
      }
    }
    await clearLocalSession();
  }, [clearLocalSession]);

  const refreshIdentityProfile = useCallback(async (): Promise<boolean> => {
    try {
      const res = await fetchMyProfile();
      if (!res.success || !res.data?.person) {
        return false;
      }
      const p = res.data.person;
      const nextUser: AuthPerson = {
        id: p.id,
        first_name: p.first_name,
        last_name: p.last_name ?? '',
        full_name: p.full_name,
        avatar: p.avatar ?? null,
        phone: user?.phone ?? null,
        birth_date: p.birth_date ?? null,
        gender: (p as any).gender ?? user?.gender ?? null,
      };
      setUser(nextUser);
      await storage.setItem(USER_DATA_KEY, JSON.stringify(nextUser));
      return true;
    } catch {
      return false;
    }
  }, [user?.phone]);

  return (
    <AuthContext.Provider
      value={{
        isAuthenticated,
        isLoading,
        hasIdentity,
        authenticate,
        lock,
        lastAuthTime,
        logout,
        user,
        refreshIdentityProfile,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
