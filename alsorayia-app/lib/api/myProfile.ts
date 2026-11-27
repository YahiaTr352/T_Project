import { api, apiUpload } from '@/lib/api/alsorayiaClient';
import { imageUriToBase64 } from '@/lib/api/authApi';
import { isAxiosError } from 'axios';
import { Platform } from 'react-native';

export type ContactType =
  | 'phone'
  | 'whatsapp'
  | 'email'
  | 'facebook'
  | 'instagram'
  | 'twitter'
  | 'linkedin'
  | 'telegram'
  | 'other';

export type AuthMethod = 'face' | 'whatsapp';

export type PrivacyVisibility = 'public' | 'family' | 'private';

export interface ProfilePrivacySettings {
  contacts?: PrivacyVisibility;
  photo?: PrivacyVisibility;
  birth_date?: PrivacyVisibility;
  location?: PrivacyVisibility;
  biography?: PrivacyVisibility;
  occupation?: PrivacyVisibility;
}

export interface ContactVisibilityItem {
  id: number;
  visibility: PrivacyVisibility;
}

export interface UpdatePrivacyInput {
  contacts?: PrivacyVisibility;
  photo?: PrivacyVisibility;
  birth_date?: PrivacyVisibility;
  location?: PrivacyVisibility;
  biography?: PrivacyVisibility;
  occupation?: PrivacyVisibility;
  contact_visibilities?: ContactVisibilityItem[];
}

export interface MyPersonBrief {
  id: number;
  full_name: string;
  gender?: string;
  avatar?: string | null;
  birth_date?: string | null;
  status_text?: string | null;
  is_divorced?: boolean;
  marriage_id?: number;
}

export interface MyPerson {
  id: number;
  first_name: string;
  last_name?: string | null;
  full_name: string;
  gender: string;
  birth_date?: string | null;
  birth_place?: string | null;
  death_date?: string | null;
  death_place?: string | null;
  cemetery?: string | null;
  cemetery_location?: string | null;
  grave_number?: string | null;
  biography?: string | null;
  occupation?: string | null;
  avatar?: string | null;
  phone?: string | null;
  has_whatsapp?: boolean;
  whatsapp_number?: string | null;
  location_id?: number | null;
  privacy_settings?: ProfilePrivacySettings;
}

export interface MyContact {
  id: number;
  type: ContactType;
  value: string;
  visibility?: PrivacyVisibility;
  label?: string | null;
  sort_order?: number;
}

export interface MyMarriage {
  id: number;
  spouse?: MyPersonBrief | null;
  married_at?: string | null;
  divorced_at?: string | null;
  is_divorced: boolean;
  status?: string;
}

export interface MyPersonLocation {
  id: number;
  location_id: number;
  location_name?: string;
  label?: string | null;
  url?: string | null;
  is_primary?: boolean;
  sort_order?: number;
}

export interface LocationCatalogItem {
  id: number;
  name: string;
}

export interface MyProfilePayload {
  person: MyPerson;
  privacy_settings?: ProfilePrivacySettings;
  parents: {
    father?: MyPersonBrief | null;
    mother?: MyPersonBrief | null;
  };
  contacts: MyContact[];
  children: MyPersonBrief[];
  marriages: MyMarriage[];
  locations: MyPersonLocation[];
  auth_method?: AuthMethod | null;
  spouse_candidates: MyPersonBrief[];
  wife_candidates: MyPersonBrief[];
  husband_candidates?: MyPersonBrief[];
  father_candidates?: MyPersonBrief[];
  locations_catalog: LocationCatalogItem[];
}

export interface MyProfileApiResponse<T = MyProfilePayload> {
  success: boolean;
  message?: string;
  code?: string;
  data?: T;
  errors?: Record<string, string[]> | null;
}

export interface UpdateProfileInput {
  first_name?: string;
  last_name?: string | null;
  birth_date?: string | null;
  birth_place?: string | null;
  death_date?: string | null;
  death_place?: string | null;
  cemetery?: string | null;
  cemetery_location?: string | null;
  grave_number?: string | null;
  biography?: string | null;
  occupation?: string | null;
  location_id?: number | null;
  photoUri?: string | null;
  photoBase64?: string | null;
}

export interface StoreContactInput {
  type: ContactType;
  value: string;
  label?: string | null;
}

export interface StoreChildInput {
  first_name: string;
  last_name?: string | null;
  gender: 'male' | 'female';
  birth_date?: string | null;
  birth_place?: string | null;
  mother_id?: number;
  parent_id?: number;
  photoUri?: string | null;
  photoBase64?: string | null;
}

export interface StoreMarriageInput {
  spouse_type?: 'existing' | 'outside_family' | 'inside_family';
  husband_id?: number | null;
  wife_id?: number | null;
  first_name?: string | null;
  last_name?: string | null;
  parent_id?: number | null;
  birth_date?: string | null;
  photoUri?: string | null;
  photoBase64?: string | null;
  married_at?: string | null;
  divorced_at?: string | null;
  is_divorced?: boolean | null;
}

export interface UpdateMarriageInput {
  married_at?: string | null;
  divorced_at?: string | null;
  is_divorced?: boolean;
}

export interface StoreLocationInput {
  location_id: number;
  label?: string | null;
  url?: string | null;
  is_primary?: boolean;
}

const skipHandler = { skipGlobalErrorHandler: true } as const;

function asApiResponse<T>(data: unknown, fallbackMessage: string): MyProfileApiResponse<T> {
  return (data ?? { success: false, message: fallbackMessage }) as MyProfileApiResponse<T>;
}

export async function resolvePhotoDataUri(
  photoUri?: string | null,
  photoBase64?: string | null
): Promise<string | null> {
  if (photoBase64) {
    return photoBase64.startsWith('data:') ? photoBase64 : `data:image/jpeg;base64,${photoBase64}`;
  }
  if (!photoUri) return null;
  if (photoUri.startsWith('data:')) return photoUri;

  try {
    const base64 = await imageUriToBase64(photoUri);
    return base64.startsWith('data:') ? base64 : `data:image/jpeg;base64,${base64}`;
  } catch (err) {
    console.warn('[resolvePhotoDataUri] Failed to convert photoUri to base64:', err);
    return null;
  }
}

async function appendPhoto(formData: FormData, photoUri: string) {
  if (Platform.OS === 'web') {
    const response = await fetch(photoUri);
    const blob = await response.blob();
    formData.append('photo', blob, 'photo.jpg');
  } else {
    const cleanUri = Platform.OS === 'android' ? photoUri : photoUri.replace('file://', '');
    const filename = photoUri.split('/').pop() || 'photo.jpg';
    const match = /\.(\w+)$/.exec(filename);
    const ext = match?.[1]?.toLowerCase() || 'jpg';
    const type = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';

    formData.append('photo', {
      uri: cleanUri,
      name: filename.includes('.') ? filename : `photo.${ext}`,
      type,
    } as any);
  }
}

export function extractApiError(res: MyProfileApiResponse<unknown> | null): string {
  if (!res) return 'تعذّر الاتصال بالخادم.';
  if (res.message) return res.message;
  if (res.errors) {
    const firstKey = Object.keys(res.errors)[0];
    if (firstKey && res.errors[firstKey]?.[0]) return res.errors[firstKey][0];
  }
  return 'حدث خطأ غير متوقع.';
}

export async function fetchMyProfile(): Promise<MyProfileApiResponse<MyProfilePayload>> {
  try {
    const data = await api.get('/v1/me', skipHandler as any);
    return asApiResponse<MyProfilePayload>(data, 'تعذّر جلب الملف الشخصي.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<MyProfilePayload>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function updateMyProfile(
  input: UpdateProfileInput
): Promise<MyProfileApiResponse<{ person: MyPerson }>> {
  const { photoUri, photoBase64, ...rest } = input;

  // المسار الأول والأمثل: إرسال الصورة كـ Base64 Data URI ضمن كائن JSON قياسي
  // هذا يتجاوز مشاكل React Native OkHttp/LiteSpeed ويسري بسرعة فائقة
  if (photoBase64 || photoUri) {
    const dataUri = await resolvePhotoDataUri(photoUri, photoBase64);

    if (dataUri) {
      try {
        const payload = {
          ...rest,
          photo: dataUri,
        };
        const data = await api.put('/v1/me', payload, {
          ...skipHandler,
          skipRetry: true,
          timeout: 45000,
        } as any);
        return asApiResponse<{ person: MyPerson }>(data, 'تعذّر تحديث البيانات.');
      } catch (err: any) {
        if (isAxiosError(err) && err.response?.data) {
          return err.response.data as MyProfileApiResponse<{ person: MyPerson }>;
        }
        console.warn('[updateMyProfile] Base64 JSON failed, trying apiUpload fallback...', err?.message);
      }
    }

    // خط دفاع ثانٍ: استخدام apiUpload عبر Fetch الأصلي لتجاوز قيود Axios XHR
    if (photoUri) {
      try {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        for (const [key, value] of Object.entries(rest)) {
          if (value !== undefined && value !== null && value !== '') {
            formData.append(key, String(value));
          }
        }
        await appendPhoto(formData, photoUri);

        const data = await apiUpload('/v1/me', formData, 60000);
        return asApiResponse<{ person: MyPerson }>(data, 'تعذّر تحديث البيانات.');
      } catch (err: any) {
        return {
          success: false,
          message:
            err?.message === 'Network Error' || err?.name === 'AbortError'
              ? 'انتهت مهلة رفع الصورة أو حدث انقطاع في الشبكة. تحقق من سرعة الاتصال وحاول مجدداً.'
              : 'تعذّر الاتصال بالخادم.',
        };
      }
    }
  }

  // تحديث عادي بدون صورة (JSON PUT)
  try {
    const data = await api.put('/v1/me', rest, skipHandler as any);
    return asApiResponse<{ person: MyPerson }>(data, 'تعذّر تحديث البيانات.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ person: MyPerson }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function storeMyContact(
  input: StoreContactInput
): Promise<MyProfileApiResponse<{ contact: MyContact }>> {
  try {
    const data = await api.post('/v1/me/contacts', input, skipHandler as any);
    return asApiResponse<{ contact: MyContact }>(data, 'تعذّر إضافة حساب التواصل.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ contact: MyContact }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function deleteMyContact(contactId: number): Promise<MyProfileApiResponse<null>> {
  try {
    const data = await api.delete(`/v1/me/contacts/${contactId}`, skipHandler as any);
    return asApiResponse<null>(data, 'تعذّر حذف حساب التواصل.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<null>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function storeMyChild(
  input: StoreChildInput
): Promise<MyProfileApiResponse<{ child: MyPersonBrief }>> {
  const { photoUri, photoBase64, ...rest } = input;

  if (photoBase64 || photoUri) {
    const dataUri = await resolvePhotoDataUri(photoUri, photoBase64);

    if (dataUri) {
      try {
        const payload = {
          ...rest,
          photo: dataUri,
        };
        const data = await api.post('/v1/me/children', payload, {
          ...skipHandler,
          skipRetry: true,
          timeout: 45000,
        } as any);
        return asApiResponse<{ child: MyPersonBrief }>(data, 'تعذّر إضافة الابن/الابنة.');
      } catch (err: any) {
        if (isAxiosError(err) && err.response?.data) {
          return err.response.data as MyProfileApiResponse<{ child: MyPersonBrief }>;
        }
        console.warn('[storeMyChild] Base64 JSON failed, trying apiUpload fallback...', err?.message);
      }
    }

    if (photoUri) {
      try {
        const formData = new FormData();
        for (const [k, v] of Object.entries(rest)) {
          if (v !== undefined && v !== null && v !== '') {
            formData.append(k, String(v));
          }
        }
        await appendPhoto(formData, photoUri);
        const data = await apiUpload('/v1/me/children', formData, 60000);
        return asApiResponse<{ child: MyPersonBrief }>(data, 'تعذّر إضافة الابن/الابنة.');
      } catch {
        return { success: false, message: 'تعذّر الاتصال بالخادم.' };
      }
    }
  }

  try {
    const data = await api.post('/v1/me/children', rest, skipHandler as any);
    return asApiResponse<{ child: MyPersonBrief }>(data, 'تعذّر إضافة الابن/الابنة.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ child: MyPersonBrief }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function storeMyMarriage(
  input: StoreMarriageInput
): Promise<MyProfileApiResponse<{ marriage: MyMarriage }>> {
  const { photoUri, photoBase64, ...rest } = input;

  if (photoBase64 || photoUri) {
    const dataUri = await resolvePhotoDataUri(photoUri, photoBase64);

    if (dataUri) {
      try {
        const payload = {
          ...rest,
          photo: dataUri,
        };
        const data = await api.post('/v1/me/marriages', payload, {
          ...skipHandler,
          skipRetry: true,
          timeout: 45000,
        } as any);
        return asApiResponse<{ marriage: MyMarriage }>(data, 'تعذّر إضافة سجل الزواج.');
      } catch (err: any) {
        if (isAxiosError(err) && err.response?.data) {
          return err.response.data as MyProfileApiResponse<{ marriage: MyMarriage }>;
        }
        console.warn('[storeMyMarriage] Base64 JSON failed, trying apiUpload fallback...', err?.message);
      }
    }

    if (photoUri) {
      try {
        const formData = new FormData();
        for (const [k, v] of Object.entries(rest)) {
          if (v !== undefined && v !== null && v !== '') {
            formData.append(k, String(v));
          }
        }
        await appendPhoto(formData, photoUri);
        const data = await apiUpload('/v1/me/marriages', formData, 60000);
        return asApiResponse<{ marriage: MyMarriage }>(data, 'تعذّر إضافة سجل الزواج.');
      } catch {
        return { success: false, message: 'تعذّر الاتصال بالخادم.' };
      }
    }
  }

  try {
    const data = await api.post('/v1/me/marriages', rest, skipHandler as any);
    return asApiResponse<{ marriage: MyMarriage }>(data, 'تعذّر إضافة سجل الزواج.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ marriage: MyMarriage }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function updateMyMarriage(
  marriageId: number,
  input: UpdateMarriageInput
): Promise<MyProfileApiResponse<{ marriage: MyMarriage }>> {
  try {
    const data = await api.put(
      `/v1/me/marriages/${marriageId}`,
      input,
      skipHandler as any
    );
    return asApiResponse<{ marriage: MyMarriage }>(data, 'تعذّر تحديث سجل الزواج.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ marriage: MyMarriage }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function storeMyLocation(
  input: StoreLocationInput
): Promise<MyProfileApiResponse<{ location: MyPersonLocation }>> {
  try {
    const data = await api.post('/v1/me/locations', input, skipHandler as any);
    return asApiResponse<{ location: MyPersonLocation }>(data, 'تعذّر إضافة الموقع.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{ location: MyPersonLocation }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}

export async function deleteMyLocation(locationId: number): Promise<MyProfileApiResponse<null>> {
  try {
    const data = await api.delete(`/v1/me/locations/${locationId}`, skipHandler as any);
    return asApiResponse<null>(data, 'تعذّر حذف الموقع.');
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<null>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}


export async function updateMyPrivacy(
  input: UpdatePrivacyInput
): Promise<MyProfileApiResponse<{ privacy_settings: ProfilePrivacySettings; contacts?: MyContact[] }>> {
  try {
    const data = await api.patch('/v1/me/privacy', input, skipHandler as any);
    return asApiResponse<{ privacy_settings: ProfilePrivacySettings; contacts?: MyContact[] }>(
      data,
      'تعذّر تحديث إعدادات الخصوصية.'
    );
  } catch (err: any) {
    if (isAxiosError(err) && err.response?.data) {
      return err.response.data as MyProfileApiResponse<{
        privacy_settings: ProfilePrivacySettings;
        contacts?: MyContact[];
      }>;
    }
    return { success: false, message: 'تعذّر الاتصال بالخادم.' };
  }
}
