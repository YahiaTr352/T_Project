/**
 * واجهة Alsorayia لشجرة العائلة والأشخاص — لأي شاشة في التطبيق.
 * يعتمد على `alsorayiaClient` (العنوان الأساسي والمهلات والـ interceptors).
 */
import { API_BASE_URL, WEB_BASE_URL, apiFetch, api, apiUpload } from '@/lib/api/alsorayiaClient';

export { API_BASE_URL, WEB_BASE_URL };

export function searchPersonByImage(
  payload: { image_base64: string } | FormData
): Promise<any> {
  if (payload && typeof payload === 'object' && 'image_base64' in payload) {
    return api.post('/persons/search/image', payload, {
      timeout: 60000,
      skipRetry: true,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
  }
  return apiUpload('/persons/search/image', payload as FormData, 60000);
}

export function fetchFamilyTree(): Promise<any> {
  return apiFetch('/family-tree');
}

/** قائمة الأشخاص (بحث عام / فلترة محلية) */
export function fetchPersonsSearchList(): Promise<any> {
  return apiFetch('/persons/search');
}

export function fetchPersonChildren(personId: number | string): Promise<any> {
  return apiFetch(`/person/${personId}/children`);
}

export function fetchPersonById(personId: number | string): Promise<any> {
  return apiFetch(`/person/${personId}`);
}

export function fetchPersonChildrenDetails(personId: number | string): Promise<any> {
  return apiFetch(`/person/${personId}/children-details`);
}

/** `{ "count": number }` */
export function fetchPersonStoriesCount(personId: number | string): Promise<any> {
  return apiFetch(`/person/${personId}/stories/count`);
}

/** `{ "success": true, "count": number }` */
export function fetchPersonFriendshipsCount(personId: number | string): Promise<any> {
  return apiFetch(`/person/${personId}/friendships/count`);
}

/** حساب صلة القرابة والنسب والجد المشترك */
export function fetchKinship(personBId: number | string, personAId?: number | string): Promise<any> {
  const query = personAId ? `?person_a_id=${personAId}&person_b_id=${personBId}` : `?person_b_id=${personBId}`;
  return apiFetch(`/v1/kinship/calculate${query}`);
}

export interface PersonFriendItem {
  id: number;
  first_name: string;
  last_name?: string | null;
  full_name?: string | null;
  gender?: string;
  birth_date?: string | null;
  death_date?: string | null;
  photo_url?: string | null;
  description?: string | null;
  friendship_story?: string | null;
}

export interface PersonStoryItem {
  id: number;
  title: string;
  content: string;
  audio_url?: string | null;
  video_url?: string | null;
  created_at?: string;
  story_owner?: {
    id: number;
    name: string;
    photo_url?: string | null;
  } | null;
  narrators?: {
    id: number;
    name: string;
    photo_url?: string | null;
  }[];
}

export interface PersonImageItem {
  id: number;
  name: string;
  description?: string | null;
  media_type?: string;
  image_url?: string | null;
  thumbnail_url?: string | null;
  category?: {
    id: number;
    name: string;
  } | null;
  article?: {
    id: number;
    title: string;
  } | null;
  created_at?: string;
}

/** جلب صور شخص معين (الصور المرتبطة به في المعرض) */
export async function fetchPersonImages(
  personId: number | string,
  page = 1
): Promise<{ success: boolean; data: PersonImageItem[]; meta?: any }> {
  try {
    const res = (await api.get(`/person/${personId}/images?page=${page}`)) as any;
    if (res && res.success && Array.isArray(res.data)) {
      return { success: true, data: res.data, meta: res.meta };
    }
    return { success: false, data: [] };
  } catch {
    return { success: false, data: [] };
  }
}

/** جلب قصص شخص معين */
export async function fetchPersonStories(
  personId: number | string,
  page = 1
): Promise<{ success: boolean; stories: PersonStoryItem[]; meta?: any }> {
  try {
    const res = (await api.get(`/person/${personId}/stories?page=${page}`)) as any;
    if (res && res.success && Array.isArray(res.stories)) {
      return { success: true, stories: res.stories, meta: res.meta };
    }
    if (res && Array.isArray(res.data)) {
      return { success: true, stories: res.data, meta: res.meta };
    }
    return { success: false, stories: [] };
  } catch {
    return { success: false, stories: [] };
  }
}

/** جلب أصدقاء شخص معين */
export async function fetchPersonFriends(
  personId: number | string
): Promise<{ success: boolean; friends: PersonFriendItem[] }> {
  try {
    const res = (await api.get(`/person/${personId}/friends`)) as any;
    if (res && res.success && Array.isArray(res.friends)) {
      return { success: true, friends: res.friends };
    }
    return { success: false, friends: [] };
  } catch {
    return { success: false, friends: [] };
  }
}


