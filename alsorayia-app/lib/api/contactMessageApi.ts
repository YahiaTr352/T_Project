import { apiUpload } from './alsorayiaClient';

export type ContactMessageType = 'problem' | 'inquiry' | 'suggestion' | 'complaint' | 'other';
export type ContactMediaKind = 'none' | 'image' | 'video' | 'voice';

export interface SendContactMessagePayload {
  name?: string;
  phone?: string;
  type?: ContactMessageType;
  message?: string;
  mediaUri?: string | null;
  mediaKind?: ContactMediaKind;
  mediaName?: string;
  mediaMime?: string;
}

export interface ContactMessageApiResponse {
  success: boolean;
  message?: string;
  data?: {
    id: number;
    status: string;
    type: string;
    media_type: string;
    created_at: string;
  };
  errors?: Record<string, string[]>;
}

/**
 * إرسال رسالة أو بلاغ مشكلة أو مقترح لإدارة العائلة عبر API الخادم
 */
export async function sendContactMessageApi(
  payload: SendContactMessagePayload
): Promise<ContactMessageApiResponse> {
  try {
    const formData = new FormData();

    if (payload.name?.trim()) {
      formData.append('name', payload.name.trim());
    }
    if (payload.phone?.trim()) {
      formData.append('phone', payload.phone.trim());
    }
    if (payload.type) {
      formData.append('type', payload.type);
    }
    if (payload.message?.trim()) {
      formData.append('message', payload.message.trim());
    }
    formData.append('media_type', payload.mediaKind || 'none');
    formData.append('source', 'app');

    if (payload.mediaUri) {
      const uri = payload.mediaUri;
      let name = payload.mediaName;
      let mime = payload.mediaMime;

      if (!name) {
        if (payload.mediaKind === 'voice') name = 'voice_note.m4a';
        else if (payload.mediaKind === 'video') name = 'video.mp4';
        else name = 'image.jpg';
      }

      if (!mime) {
        if (payload.mediaKind === 'voice') mime = 'audio/m4a';
        else if (payload.mediaKind === 'video') mime = 'video/mp4';
        else mime = 'image/jpeg';
      }

      formData.append('media_file', {
        uri,
        name,
        type: mime,
      } as any);
    }

    const res = await apiUpload('/v1/contact-messages', formData);
    return res as ContactMessageApiResponse;
  } catch (error: any) {
    return {
      success: false,
      message: error?.message || 'تعذر الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت.',
    };
  }
}
