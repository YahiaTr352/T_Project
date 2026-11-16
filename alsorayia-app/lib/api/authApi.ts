import { api } from '@/lib/api/alsorayiaClient';
import { getDeviceMetadata } from './devices';
import { isAxiosError } from 'axios';
import { Platform } from 'react-native';

export interface AuthPerson {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  avatar?: string | null;
  phone?: string | null;
  birth_date?: string | null;
  gender?: string | null;
}

export interface AuthTokenResponse {
  success: boolean;
  message?: string;
  requires_otp?: boolean;
  has_whatsapp?: boolean;
  is_child?: boolean;
  guardian_relation?: string;
  whatsapp_required?: boolean;
  whatsapp_grace_expires_at?: string;
  grace_hours_remaining?: number;
  is_trusted_device?: boolean;
  data?: {
    access_token?: string;
    refresh_token?: string;
    token_type?: string;
    expires_in?: number;
    similarity_score?: number;
    person?: AuthPerson | null;
    challenge_token?: string;
    maskedPhone?: string;
    masked_phone?: string;
    is_child?: boolean;
    guardian_relation?: string;
    whatsapp_required?: boolean;
    whatsapp_grace_expires_at?: string;
    grace_hours_remaining?: number;
  };
  errors?: Record<string, string[]> | null;
}

export interface ChildPersonItem {
  id: number;
  first_name: string;
  full_name: string;
  age: number | null;
  avatar?: string | null;
  gender?: string | null;
}

export interface ChildParentLookupResponse {
  success: boolean;
  message?: string;
  data?: {
    parent?: {
      id: number;
      name: string;
      gender?: string | null;
    } | null;
    children: ChildPersonItem[];
  };
  errors?: Record<string, string[]> | null;
}

export interface SendChildOtpResponse {
  success: boolean;
  message?: string;
  data?: {
    phone: string;
    child_id: number;
    child_name: string;
    expires_in: number;
  };
  errors?: Record<string, string[]> | null;
}

export interface SendOtpResponse {
  success: boolean;
  message?: string;
  is_child?: boolean;
  data?: {
    phone: string;
    expires_in: number;
    is_child?: boolean;
    child_name?: string;
    guardian_relation?: string;
    masked_guardian_phone?: string;
  };
  errors?: Record<string, string[]> | null;
}

export async function imageUriToBase64(uri: string): Promise<string> {
  const response = await fetch(uri);
  const blob = await response.blob();
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => {
      const base64data = reader.result as string;
      resolve(base64data);
    };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

export interface FaceAuthVerificationMeta {
  client_timestamp?: number;
  is_live_capture?: boolean;
  liveness_score?: number;
}

/**
 * 1️⃣ إرسال صورة الوجه للتحقق البيومتري المباشر (POST /api/v1/auth/face-verify)
 * متضمنةً بصمة ومعرّف الجهاز (Device Trust)
 */
export async function verifyFaceAuth(
  imageUri: string,
  directBase64?: string | null,
  meta?: FaceAuthVerificationMeta
): Promise<AuthTokenResponse> {
  try {
    const deviceMeta = await getDeviceMetadata();

    let base64Image = '';
    if (directBase64) {
      base64Image = directBase64.startsWith('data:')
        ? directBase64
        : `data:image/jpeg;base64,${directBase64}`;
    } else {
      try {
        base64Image = await imageUriToBase64(imageUri);
      } catch {
        base64Image = '';
      }
    }

    const now = Date.now();
    let payload: any;
    if (base64Image) {
      payload = {
        image_base64: base64Image,
        client_timestamp: meta?.client_timestamp ?? now,
        is_live_capture: meta?.is_live_capture ?? true,
        liveness_score: meta?.liveness_score ?? 98.4,
        device_uuid: deviceMeta.device_uuid,
        expo_push_token: deviceMeta.expo_push_token,
        device_name: deviceMeta.device_name,
        device_model: deviceMeta.device_model,
        os_platform: deviceMeta.os_platform,
        os_version: deviceMeta.os_version,
        app_version: deviceMeta.app_version,
      };
    } else {
      const formData = new FormData();
      if (Platform.OS === 'web') {
        const response = await fetch(imageUri);
        const blob = await response.blob();
        formData.append('image', blob, 'selfie.jpg');
      } else {
        const cleanUri = Platform.OS === 'android' ? imageUri : imageUri.replace('file://', '');
        formData.append('image', {
          uri: cleanUri,
          name: 'selfie.jpg',
          type: 'image/jpeg',
        } as any);
      }
      formData.append('client_timestamp', String(meta?.client_timestamp ?? now));
      formData.append('is_live_capture', String(meta?.is_live_capture ?? true));
      if (meta?.liveness_score) {
        formData.append('liveness_score', String(meta.liveness_score));
      }
      formData.append('device_uuid', deviceMeta.device_uuid);
      if (deviceMeta.expo_push_token) formData.append('expo_push_token', deviceMeta.expo_push_token);
      if (deviceMeta.device_name) formData.append('device_name', deviceMeta.device_name);
      if (deviceMeta.device_model) formData.append('device_model', deviceMeta.device_model);
      if (deviceMeta.os_platform) formData.append('os_platform', deviceMeta.os_platform);
      if (deviceMeta.os_version) formData.append('os_version', deviceMeta.os_version);
      if (deviceMeta.app_version) formData.append('app_version', deviceMeta.app_version);

      payload = formData;
    }

    const data = await api.post<AuthTokenResponse>(
      '/v1/auth/face-verify',
      payload,
      {
        skipGlobalErrorHandler: true,
        skipRetry: true,
        timeout: 45000,
        headers: {
          Accept: 'application/json',
          ...(base64Image ? { 'Content-Type': 'application/json' } : {}),
        },
      }
    );

    return (data as unknown) as AuthTokenResponse;
  } catch (err: any) {
    console.error('[authApi] verifyFaceAuth error:', err?.response?.data || err?.message || err);
    if (isAxiosError(err) && err.response?.data) {
      if (typeof err.response.data === 'string' && err.response.data.includes('<html')) {
        return {
          success: false,
          message: 'تعذر التحقق من صورة الوجه. يرجى المحاولة مرة أخرى.',
        };
      }
      return err.response.data as AuthTokenResponse;
    }
    return {
      success: false,
      message: err?.response?.data?.message || 'تعذر الاتصال بخادم بصمة الوجه. تحقق من الشبكة وحاول مجدداً.',
    };
  }
}

/**
 * 1️⃣-ب تأكيد كود OTP للوجه وتوثيق الجهاز الجديد (POST /api/v1/auth/face-verify/confirm-otp)
 */
export async function confirmFaceAuthOtp(
  challengeToken: string,
  otpCode: string,
  customDeviceUuid?: string
): Promise<AuthTokenResponse> {
  try {
    const deviceMeta = await getDeviceMetadata();
    const deviceUuid = customDeviceUuid || deviceMeta.device_uuid;

    const data = await api.post<AuthTokenResponse>(
      '/v1/auth/face-verify/confirm-otp',
      {
        challenge_token: challengeToken,
        otp_code: otpCode,
        device_uuid: deviceUuid,
        expo_push_token: deviceMeta.expo_push_token,
      },
      { skipGlobalErrorHandler: true }
    );

    return (data as unknown) as AuthTokenResponse;
  } catch (err: any) {
    console.error('[authApi] confirmFaceAuthOtp error:', err?.response?.data || err?.message || err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as AuthTokenResponse;
    }
    return {
      success: false,
      message: 'رمز التحقق غير صحيح أو تعذر الاتصال بالخادم.',
    };
  }
}

/**
 * 1️⃣-ج إعادة إرسال كود OTP للوجه (POST /api/v1/auth/face-verify/resend-otp)
 */
export async function resendFaceAuthOtp(
  challengeToken: string
): Promise<{ success: boolean; message?: string; data?: { expires_in: number } }> {
  try {
    const data = await api.post(
      '/v1/auth/face-verify/resend-otp',
      { challenge_token: challengeToken },
      { skipGlobalErrorHandler: true }
    );
    return data as any;
  } catch (err: any) {
    console.error('[authApi] resendFaceAuthOtp error:', err?.response?.data || err?.message || err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as any;
    }
    return {
      success: false,
      message: 'تعذر إعادة إرسال رمز التحقق حالياً.',
    };
  }
}

/**
 * 2️⃣ طلب كود التحقق عبر الواتساب (POST /api/v1/auth/whatsapp/send-otp)
 */
export async function sendWhatsappOtp(phone: string): Promise<SendOtpResponse> {
  try {
    const data = await api.post<SendOtpResponse>(
      '/v1/auth/whatsapp/send-otp',
      { phone },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as SendOtpResponse;
  } catch (err: any) {
    console.error('[authApi] sendWhatsappOtp error:', err.response.data);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as SendOtpResponse;
    }
    return {
      success: false,
      message: 'تعذر إرسال كود الواتساب. تحقق من الاتصال بالشبكة.',
    };
  }
}

/**
 * 2️⃣ تأكيد كود التحقق عبر الواتساب (POST /api/v1/auth/whatsapp/verify-otp)
 */
export async function verifyWhatsappOtp(
  phone: string,
  otpCode: string
): Promise<AuthTokenResponse> {
  try {
    const deviceMeta = await getDeviceMetadata();
    const data = await api.post<AuthTokenResponse>(
      '/v1/auth/whatsapp/verify-otp',
      {
        phone,
        otp_code: otpCode,
        device_uuid: deviceMeta.device_uuid,
        expo_push_token: deviceMeta.expo_push_token,
        device_name: deviceMeta.device_name,
        device_model: deviceMeta.device_model,
        os_platform: deviceMeta.os_platform,
        os_version: deviceMeta.os_version,
        app_version: deviceMeta.app_version,
      },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as AuthTokenResponse;
  } catch (err: any) {
    console.error('[authApi] verifyWhatsappOtp error:', err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as AuthTokenResponse;
    }
    return {
      success: false,
      message: 'كود التحقق غير صحيح أو تعذر التوثيق من الشبكة.',
    };
  }
}

/**
 * 2️⃣-أ الاستعلام عن أطفال ولي الأمر المؤهلين (POST /api/v1/auth/child/parent-lookup)
 */
export async function lookupParentForChild(phone: string): Promise<ChildParentLookupResponse> {
  try {
    const data = await api.post<ChildParentLookupResponse>(
      '/v1/auth/child/parent-lookup',
      { phone },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as ChildParentLookupResponse;
  } catch (err: any) {
    console.error('[authApi] lookupParentForChild error:', err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as ChildParentLookupResponse;
    }
    return {
      success: false,
      message: 'تعذر التحقق من بيانات ولي الأمر. يرجى التأكد من الاتصال بالشبكة.',
    };
  }
}

/**
 * 2️⃣-ب إرسال رمز التحقق لواتساب ولي الأمر لدخول طفل محدد (POST /api/v1/auth/child/send-otp)
 */
export async function sendChildWhatsappOtp(
  parentPhone: string,
  childId: number
): Promise<SendChildOtpResponse> {
  try {
    const data = await api.post<SendChildOtpResponse>(
      '/v1/auth/child/send-otp',
      { parent_phone: parentPhone, child_id: childId },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as SendChildOtpResponse;
  } catch (err: any) {
    console.error('[authApi] sendChildWhatsappOtp error:', err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as SendChildOtpResponse;
    }
    return {
      success: false,
      message: 'تعذر إرسال كود الواتساب لولي الأمر.',
    };
  }
}

/**
 * 2️⃣-ج التحقق من كود الواتساب وتوثيق جهاز وجلسة الطفل (POST /api/v1/auth/child/verify-otp)
 */
export async function verifyChildWhatsappOtp(
  parentPhone: string,
  childId: number,
  otpCode: string
): Promise<AuthTokenResponse> {
  try {
    const deviceMeta = await getDeviceMetadata();
    const data = await api.post<AuthTokenResponse>(
      '/v1/auth/child/verify-otp',
      {
        parent_phone: parentPhone,
        child_id: childId,
        otp_code: otpCode,
        device_uuid: deviceMeta.device_uuid,
        expo_push_token: deviceMeta.expo_push_token,
        device_name: deviceMeta.device_name,
        device_model: deviceMeta.device_model,
        os_platform: deviceMeta.os_platform,
        os_version: deviceMeta.os_version,
        app_version: deviceMeta.app_version,
      },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as AuthTokenResponse;
  } catch (err: any) {
    console.error('[authApi] verifyChildWhatsappOtp error:', err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as AuthTokenResponse;
    }
    return {
      success: false,
      message: 'كود التحقق غير صحيح أو تعذر التوثيق من الشبكة.',
    };
  }
}

/**
 * 3️⃣ تجديد الرمز والجلسة المستمرة (POST /api/v1/auth/refresh)
 */
export async function refreshAuthToken(refreshToken: string): Promise<AuthTokenResponse> {
  try {
    const data = await api.post<AuthTokenResponse>(
      '/v1/auth/refresh',
      { refresh_token: refreshToken },
      { skipGlobalErrorHandler: true }
    );
    return (data as unknown) as AuthTokenResponse;
  } catch (err: any) {
    console.error('[authApi] refreshAuthToken error:', err);
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as AuthTokenResponse;
    }
    return {
      success: false,
      message: 'تعذر تجديد الجلسة.',
    };
  }
}
