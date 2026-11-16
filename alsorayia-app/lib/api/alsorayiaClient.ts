import axios, {
  isCancel,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from 'axios';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { logger } from '@/lib/utils/logger';

declare module 'axios' {
  export interface AxiosRequestConfig {
    /** عند true لا يُحوَّل الخطأ إلى `null` — للمسارات التي تحتاج رسالة الخادم */
    skipGlobalErrorHandler?: boolean;
    /** عند true لا يُعاد إرسال الطلب تلقائياً عند فشل الشبكة */
    skipRetry?: boolean;
    /** عدد مرات إعادة المحاولة الحالية للطلب */
    retryCount?: number;
    /** علم داخلي لمنع تكرار محاولة تجديد التوكن لنفس الطلب */
    _isTokenRetry?: boolean;
  }
}

/**
 * قاعدة الـ API — بدون شرطة مائلة في النهاية.
 * مثال: `https://alsorayia.com/api`
 */
export const API_BASE_URL = (
  process.env.EXPO_PUBLIC_API_BASE_URL || 'https://alsorayia.com/api'
).replace(/\/+$/, '');

/** جذر الموقع لروابط الويب العميقة (بدون `/api`). */
export const WEB_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, '');

let siteAccessToken: string | null = null;
let onSitePasswordRequired: (() => void) | null = null;
let onTokenRefreshed: ((newToken: string, expiresIn?: number) => void) | null = null;
let onSessionExpired: ((reason?: string) => void) | null = null;

// Helpers للتخزين الآمن
async function getStoredItem(key: string): Promise<string | null> {
  try {
    if (Platform.OS === 'web') {
      return typeof localStorage !== 'undefined' ? localStorage.getItem(key) : null;
    }
    return await SecureStore.getItemAsync(key);
  } catch {
    return null;
  }
}

async function setStoredItem(key: string, value: string): Promise<void> {
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage !== 'undefined') localStorage.setItem(key, value);
    } else {
      await SecureStore.setItemAsync(key, value);
    }
  } catch {}
}

async function removeStoredItem(key: string): Promise<void> {
  try {
    if (Platform.OS === 'web') {
      if (typeof localStorage !== 'undefined') localStorage.removeItem(key);
    } else {
      await SecureStore.deleteItemAsync(key);
    }
  } catch {}
}

export function setSiteAccessToken(token: string | null) {
  siteAccessToken = token;
  if (__DEV__) {
    if (token) {
      logger.debug(
        '[SiteAccessToken] للتجربة في Postman — انسخ الرؤوس:\n' +
          `Authorization: Bearer ${token}\n` +
          `X-Site-Access-Token: ${token}`
      );
    } else {
      logger.debug('[SiteAccessToken] تم مسح الرمز من الذاكرة');
    }
  }
}

export function setOnSitePasswordRequired(cb: (() => void) | null) {
  onSitePasswordRequired = cb;
}

export function setOnTokenRefreshed(cb: ((newToken: string, expiresIn?: number) => void) | null) {
  onTokenRefreshed = cb;
}

export function setOnSessionExpired(cb: ((reason?: string) => void) | null) {
  onSessionExpired = cb;
}

const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Accept-Language': 'ar',
  },
  transformRequest: [
    (data, headers) => {
      // إذا كانت البيانات FormData في React Native، نمنع Axios من تحويلها لـ JSON
      const isFormData =
        data instanceof FormData ||
        (data &&
          typeof data === 'object' &&
          ('_parts' in data ||
            typeof (data as any).append === 'function' ||
            typeof (data as any).getParts === 'function'));

      if (isFormData) {
        if (headers) {
          if (typeof (headers as any).delete === 'function') {
            (headers as any).delete('Content-Type');
            (headers as any).delete('content-type');
          } else {
            delete headers['Content-Type'];
            delete headers['content-type'];
          }
        }
        return data;
      }

      // المعالجة الافتراضية للكائنات العادية (JSON)
      if (
        data &&
        typeof data === 'object' &&
        !(data instanceof ArrayBuffer) &&
        !ArrayBuffer.isView(data)
      ) {
        if (headers && typeof (headers as any).setContentType === 'function') {
          (headers as any).setContentType('application/json');
        } else if (headers) {
          headers['Content-Type'] = 'application/json';
        }
        return JSON.stringify(data);
      }

      return data;
    },
  ],
});

api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  if (!siteAccessToken) {
    siteAccessToken = await getStoredItem('site_access_token');
  }

  if (siteAccessToken) {
    // Laravel SiteMobileAccessToken::extractFromRequest — Bearer أو X-Site-Access-Token
    config.headers.Authorization = `Bearer ${siteAccessToken}`;
    config.headers['X-Site-Access-Token'] = siteAccessToken;
  }

  logger.debug(`[API →] ${config.method?.toUpperCase()} ${config.url}`);

  // إذا كانت البيانات FormData، نحذف Content-Type لنسمح للمحرك بتوليد الـ boundary التلقائي الصحيح وتجنب رفض LiteSpeed
  const isFormData =
    config.data instanceof FormData ||
    (config.data &&
      typeof config.data === 'object' &&
      ('_parts' in config.data ||
        typeof (config.data as any).append === 'function' ||
        typeof (config.data as any).getParts === 'function'));

  if (isFormData && config.headers) {
    if (typeof (config.headers as any).delete === 'function') {
      (config.headers as any).delete('Content-Type');
      (config.headers as any).delete('content-type');
    } else {
      delete config.headers['Content-Type'];
      delete config.headers['content-type'];
    }
  }

  return config;
});

// متغير لمزامنة ومنع ازدواجية طلبات تجديد التوكن المتزامنة
let refreshPromise: Promise<string | null> | null = null;

async function executeTokenRefresh(): Promise<string | null> {
  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = (async () => {
    try {
      const refreshToken = await getStoredItem('site_refresh_token');
      if (!refreshToken) {
        return null;
      }

      logger.debug('[API 🔄] محاولة تجديد الجلسة تلقائياً عبر refresh_token...');
      const res = await axios.post(
        `${API_BASE_URL}/v1/auth/refresh`,
        { refresh_token: refreshToken },
        {
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          timeout: 15000,
        }
      );

      if (res.data?.success && res.data?.data?.access_token) {
        const newToken: string = res.data.data.access_token;
        const expiresInSeconds: number = res.data.data.expires_in ?? 30 * 24 * 3600;
        const newExpiresAt = Date.now() + expiresInSeconds * 1000;

        setSiteAccessToken(newToken);
        await setStoredItem('site_access_token', newToken);
        await setStoredItem('site_token_expires_at', newExpiresAt.toString());

        if (res.data.data.refresh_token) {
          await setStoredItem('site_refresh_token', res.data.data.refresh_token);
        }

        logger.debug('[API ✅] تم تجديد الجلسة بنجاح واستئناف الطلبات!');
        onTokenRefreshed?.(newToken, expiresInSeconds);
        return newToken;
      }
      return null;
    } catch (err: any) {
      logger.warn(
        '[API ⚠️] خطأ أثناء محاولة تجديد الجلسة من السيرفر:',
        err?.response?.data?.message || err?.message
      );
      // نحذف الجلسة فقط إذا أكد السيرفر رفض الرمز (401 أو 403 صريح)، وليس عند انقطاع الشبكة أو التايم آوت
      if (err?.response?.status === 401 || err?.response?.status === 403) {
        await removeStoredItem('site_access_token');
        await removeStoredItem('site_refresh_token');
        await removeStoredItem('site_token_expires_at');
        await removeStoredItem('authenticated_user_data');
        setSiteAccessToken(null);

        // تمييز سبب انتهاء الجلسة: طرد بسبب تسجيل دخول من جهاز آخر أم انتهاء عادي
        const errCode = err?.response?.data?.code as string | undefined;
        if (errCode === 'SESSION_DISPLACED' || errCode === 'TOKEN_REVOKED') {
          logger.warn('[API 🔒] الجلسة أُنهيت بسبب تسجيل دخول من جهاز آخر (SESSION_DISPLACED).');
          onSessionExpired?.('SESSION_DISPLACED');
        } else {
          onSessionExpired?.();
          onSitePasswordRequired?.();
        }
      }
      return null;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

api.interceptors.response.use(
  (response) => {
    if (
      typeof response.data === 'string' &&
      response.data.trimStart().startsWith('<')
    ) {
      const preview = response.data.slice(0, 200).replace(/\s+/g, ' ');
      logger.error(
        `[API ✗] ${response.config.url}\n` +
          `  ⚠ السيرفر أرجع HTML بدل JSON!\n` +
          `  ► غالباً صفحة كلمة المرور أو إعادة توجيه — تحقق من تسجيل الدخول ووجود رمز (Bearer + X-Site-Access-Token).\n` +
          `  ► تأكد أن API_BASE_URL صحيح.\n` +
          `  ► أول 200 حرف من الرد: ${preview}`
      );
      return null as unknown as typeof response.data;
    }
    logger.debug(`[API ✓] ${response.config.url} — status ${response.status}`);
    return response.data;
  },
  async (error) => {
    const config = error.config as InternalAxiosRequestConfig | undefined;

    // ─────────────────────────────────────────────────────────────
    // 1️⃣ معالجة الـ 401 و 403 (PROFILE_IDENTITY_REQUIRED) والتجديد التلقائي للتوكن (Transparent Auto-Refresh & Retry)
    // ─────────────────────────────────────────────────────────────
    const status = error.response?.status;
    const resCode = error.response?.data?.code;
    const isAuthChallenge =
      status === 401 ||
      (status === 403 && resCode === 'PROFILE_IDENTITY_REQUIRED');

    if (config && isAuthChallenge && !config._isTokenRetry) {
      const url = String(config.url || '');
      const isAuthPath =
        url.includes('/v1/auth/refresh') ||
        url.includes('/v1/auth/face-verify') ||
        url.includes('/v1/auth/whatsapp') ||
        url.includes('/site-password/verify');

      if (!isAuthPath) {
        config._isTokenRetry = true;
        const newToken = await executeTokenRefresh();
        if (newToken) {
          config.headers.Authorization = `Bearer ${newToken}`;
          config.headers['X-Site-Access-Token'] = newToken;
          return api(config);
        }
      }
    }

    // التحقق مما إذا كان الخطأ بسبب انقطاع الشبكة أو انتهاء المهلة (Timeout)
    const isNetworkOrTimeout =
      !error.response ||
      error.code === 'ECONNABORTED' ||
      error.message === 'Network Error';

    if (config && isNetworkOrTimeout && !config.skipRetry) {
      config.retryCount = config.retryCount ?? 0;
      const MAX_RETRIES = 3;

      if (config.retryCount < MAX_RETRIES) {
        config.retryCount += 1;
        const delay = config.retryCount * 1500; // متأخر تصاعدي: 1.5ث، 3ث، 4.5ث
        logger.warn(
          `[API ⏱] فشل الاتصال بـ ${config.url || 'API'}. جاري إعادة المحاولة (${config.retryCount}/${MAX_RETRIES}) بعد ${delay}ms...`
        );
        return new Promise((resolve) => {
          setTimeout(() => {
            resolve(api(config));
          }, delay);
        });
      }
    }

    if (error.config?.skipGlobalErrorHandler) {
      return Promise.reject(error);
    }

    if (isCancel(error)) {
      logger.warn('[API ⏱] Request cancelled (timeout):', error.config?.url);
    } else if (error.response) {
      const data = error.response.data;
      const code = typeof data === 'object' && data && 'code' in data ? (data as { code?: string }).code : undefined;
      if (error.response.status === 401 && code === 'SITE_PASSWORD_REQUIRED') {
        const sentToken =
          config?.headers?.Authorization || config?.headers?.['X-Site-Access-Token'];
        if (sentToken) {
          logger.warn('[API] SITE_PASSWORD_REQUIRED — تم رفض الرمز من السيرفر');
          siteAccessToken = null;
          onSitePasswordRequired?.();
        } else {
          const stored = await getStoredItem('site_access_token');
          if (stored) {
            setSiteAccessToken(stored);
            if (config) {
              config.headers.Authorization = `Bearer ${stored}`;
              config.headers['X-Site-Access-Token'] = stored;
              return api(config);
            }
          } else {
            siteAccessToken = null;
            onSitePasswordRequired?.();
          }
        }
      } else {
        logger.error(
          `[API ✗] ${error.config?.url}\n` +
            `  status: ${error.response.status}\n` +
            `  body:   ${JSON.stringify(error.response.data).slice(0, 300)}`
        );
      }
    } else if (error.request) {
      logger.error(
        `[API ✗] لا يوجد رد من السيرفر — تحقق من الاتصال بالإنترنت\n` +
          `  URL: ${error.config?.url}\n` +
          `  Reason: ${error.message || 'No message'} (${error.code || 'NO_CODE'})\n` +
          `  Status: ${error.request?.status ?? 0}`
      );
    } else {
      logger.error('[API ✗] خطأ غير متوقع:', error.message);
    }
    return null;
  }
);

export { api };

export async function getSiteAccessToken(): Promise<string | null> {
  if (siteAccessToken) return siteAccessToken;
  siteAccessToken = await getStoredItem('site_access_token');
  return siteAccessToken;
}

/** الـ interceptors تُرجع جسم الـ JSON مباشرة (أو `null`)، وليس `AxiosResponse`. */
export async function apiFetch(endpoint: string): Promise<any> {
  return (await api.get(endpoint)) as any;
}

/**
 * رفع الملفات والـ FormData عبر XMLHttpRequest — الطريقة الوحيدة في React Native
 * التي تدعم إرفاق ملفات بصيغة {uri, name, type} بدون أخطاء FormDataPart.
 * `fetch()` في RN 0.76+ (Hermes) يرفض هذا الصيغة بخطأ:
 * "Unsupported FormDataPart implementation"
 */
export function apiUpload(
  endpoint: string,
  formData: FormData,
  timeoutMs = 60000
): Promise<any> {
  return new Promise(async (resolve, reject) => {
    const token = await getSiteAccessToken();
    const url = endpoint.startsWith('http')
      ? endpoint
      : `${API_BASE_URL}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;

    logger.debug(`[API UPLOAD →] POST ${url}`);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.timeout = timeoutMs;
    xhr.responseType = 'text';

    // Headers
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('Accept-Language', 'ar');
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`);
      xhr.setRequestHeader('X-Site-Access-Token', token);
    }
    // لا نُعيّن Content-Type — XMLHttpRequest يضعه تلقائياً مع boundary صحيح

    xhr.onload = () => {
      let json: any = null;
      try {
        json = JSON.parse(xhr.responseText);
      } catch {
        json = null;
      }
      if (xhr.status >= 200 && xhr.status < 300) {
        logger.debug(`[API UPLOAD ✓] ${url} — status ${xhr.status}`);
        resolve(json);
      } else {
        logger.error(`[API UPLOAD ✗] ${url} — status: ${xhr.status}`, json);
        resolve(json); // نعيد الـ JSON بدل throw حتى يتعامل معه المستدعي
      }
    };

    xhr.onerror = () => {
      logger.error(`[API UPLOAD ✗] فشل الرفع إلى ${url}: Network Error`);
      reject(new Error('فشل الاتصال بالخادم'));
    };

    xhr.ontimeout = () => {
      logger.error(`[API UPLOAD ✗] فشل الرفع إلى ${url}: Timeout`);
      const err: any = new Error('استغرق الرفع وقتاً أطول');
      err.code = 'ECONNABORTED';
      reject(err);
    };

    xhr.send(formData);
  });
}


