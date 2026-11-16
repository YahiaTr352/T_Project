import { api, apiUpload } from '@/lib/api/alsorayiaClient';

// ==========================================
// 1. الأنواع ونماذج البيانات (Types & DTOs)
// ==========================================

export interface ChatSender {
  id: number;
  name: string;
  first_name?: string;
  avatar_url: string | null;
  gender?: string | null;
}

export interface ChatMessageMetadata {
  duration?: number;
  waveform?: number[];
  width?: number;
  height?: number;
  file_size?: number;
  [key: string]: any;
}

export interface ChatReplyPreview {
  id: number;
  preview: string;
  sender: string | null;
}

export interface ChatMessageItem {
  id: number;
  conversation_id: number;
  type: 'text' | 'image' | 'video' | 'audio' | 'system';
  message: string | null;
  attachment_url: string | null;
  metadata?: ChatMessageMetadata | null;
  is_me: boolean;
  is_system?: boolean;
  is_edited?: boolean;
  edited_at?: string | null;
  reply_to?: ChatReplyPreview | null;
  reactions?: Record<string, number> | null;
  sender: ChatSender;
  created_at: string;
  created_at_human?: string;
  time_formatted?: string;
}

export interface ChatPinnedMessage {
  id: number;
  message: string | null;
  type: string;
  sender: string | null;
}

export interface ChatRoomDetails {
  id: number;
  title: string | null;
  type: string;
  is_active: boolean;
  unread_count: number;
  is_muted: boolean;
  can_send: boolean;
  last_message_at: string | null;
  last_message_preview: string | null;
  messages_count: number;
  is_locked?: boolean;
  lock_reason?: string | null;
  slow_mode_seconds?: number;
  allow_attachments?: boolean;
  pinned_message_id?: number | null;
  pinned_message?: ChatPinnedMessage | null;
  send_blocked_reason?: string | null;
  send_blocked_message?: string | null;
  retry_after?: number | null;
}

export type WalkingGroupChatRoom = ChatRoomDetails;
export type QuestionChatRoom = ChatRoomDetails;

export interface ChatModerationMeta {
  is_locked: boolean;
  lock_reason: string | null;
  slow_mode_seconds: number;
  allow_attachments: boolean;
  pinned_message_id: number | null;
  can_send: boolean;
  send_blocked_reason: string | null;
  send_blocked_message: string | null;
  retry_after: number | null;
}

export interface ChatMessagesResult extends Array<ChatMessageItem> {
  deleted_message_ids?: number[];
  edited_messages?: ChatMessageItem[];
  moderation?: ChatModerationMeta;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  errors?: Record<string, string[]> | null;
}

export interface SendMessagePayload {
  message?: string;
  attachment_uri?: string;
  type?: 'text' | 'image' | 'video' | 'audio' | 'system';
  duration?: number;
  metadata?: ChatMessageMetadata;
  reply_to_message_id?: number | null;
}

export interface ChatSendResult {
  success: boolean;
  data?: ChatMessageItem;
  message?: string;
  /** سبب المنع من الخادم (banned / conversation_locked / slow_mode / participant_restricted) */
  reason?: string | null;
  retry_after?: number | null;
  status?: number | null;
}

// أسباب الإبلاغ المتوافقة مع config/chat.php → report_reasons
export const CHAT_REPORT_REASONS = [
  { key: 'spam', label: 'رسائل مزعجة أو تكرار' },
  { key: 'abuse', label: 'إساءة أو إهانة' },
  { key: 'inappropriate', label: 'محتوى غير لائق' },
  { key: 'personal_info', label: 'نشر بيانات شخصية' },
  { key: 'impersonation', label: 'انتحال شخصية' },
  { key: 'other', label: 'سبب آخر' },
] as const;

export type ChatReportReason = (typeof CHAT_REPORT_REASONS)[number]['key'];

// ==========================================
// Helpers للوسائط والمرفقات
// ==========================================

function getMediaMimeAndType(uri: string, explicitType?: 'text' | 'image' | 'video' | 'audio' | 'system'): {
  mimeType: string;
  msgType: 'image' | 'video' | 'audio';
  filename: string;
} {
  const filename = uri.split('/').pop() || `media_${Date.now()}`;
  const match = /\.([a-zA-Z0-9]+)$/.exec(filename);
  const ext = match ? match[1].toLowerCase() : '';

  const isAudioExt = ['m4a', 'aac', 'mp3', 'wav', 'ogg', 'oga', 'caf', 'weba', 'opus'].includes(ext);
  const isAudio = explicitType === 'audio' || (isAudioExt && explicitType !== 'video');

  if (isAudio) {
    let mimeType = 'audio/m4a';
    if (ext === 'mp3') mimeType = 'audio/mpeg';
    else if (ext === 'wav') mimeType = 'audio/wav';
    else if (ext === 'aac') mimeType = 'audio/aac';
    else if (ext === 'ogg' || ext === 'oga') mimeType = 'audio/ogg';
    return { mimeType, msgType: 'audio', filename: filename.endsWith(`.${ext}`) ? filename : `${filename}.m4a` };
  }

  const isVideoExt = ['mp4', 'mov', 'qt', 'webm', '3gp', 'm4v'].includes(ext);
  const isVideo = explicitType === 'video' || isVideoExt;

  if (isVideo) {
    const mimeType = ext === 'mov' || ext === 'qt' ? 'video/quicktime' : 'video/mp4';
    return { mimeType, msgType: 'video', filename: filename.endsWith(`.${ext}`) ? filename : `${filename}.mp4` };
  }

  const mimeType = ext === 'png' ? 'image/png' : (ext === 'webp' ? 'image/webp' : 'image/jpeg');
  return { mimeType, msgType: 'image', filename: filename.endsWith(`.${ext}`) ? filename : `${filename}.jpg` };
}

// ==========================================
// 2. دوال شات تحديات المشي (Walking Group Chat)
// ==========================================

/**
 * جلب تفاصيل غرفة دردشة تحدي المشي
 */
export async function fetchWalkingGroupChat(groupId: number): Promise<WalkingGroupChatRoom | null> {
  try {
    const raw = await api.get(`/v1/walking/groups/${groupId}/chat`, {
      skipGlobalErrorHandler: true,
    });
    const res = raw as unknown as ApiResponse<WalkingGroupChatRoom>;
    if (res && res.success) {
      return res.data;
    }
    return null;
  } catch (error) {
    console.error('[chatApi] fetchWalkingGroupChat error:', error);
    return null;
  }
}

/**
 * جلب رسائل دردشة تحدي المشي بدعم Cursor Pagination
 */
export async function fetchWalkingGroupMessages(
  groupId: number,
  params?: { before_id?: number; after_id?: number; limit?: number }
): Promise<ChatMessagesResult> {
  try {
    const raw = await api.get(
      `/v1/walking/groups/${groupId}/chat/messages`,
      {
        params,
        skipGlobalErrorHandler: true,
      }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem[]>;
    if (res && res.success) {
      const items = (res.data || []) as ChatMessagesResult;
      const meta = (res as any)?.meta;
      if (meta?.deleted_message_ids) {
        items.deleted_message_ids = meta.deleted_message_ids;
      }
      if (meta?.edited_messages) {
        items.edited_messages = meta.edited_messages;
      }
      if (meta?.moderation) {
        items.moderation = meta.moderation;
      }
      return items;
    }
    return [];
  } catch (error) {
    console.error('[chatApi] fetchWalkingGroupMessages error:', error);
    return [];
  }
}

/**
 * تعديل رسالة في تحدي المشي (متاح فقط لصاحب الرسالة خلال 60 دقيقة)
 */
export async function updateWalkingGroupMessage(
  groupId: number,
  messageId: number,
  messageText: string
): Promise<{ success: boolean; data?: ChatMessageItem; message?: string }> {
  try {
    const raw = await api.put(
      `/v1/walking/groups/${groupId}/chat/messages/${messageId}`,
      { message: messageText },
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem>;
    if (res && res.success && res.data) {
      return { success: true, data: res.data, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر تعديل الرسالة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء تعديل الرسالة';
    return { success: false, message: msg };
  }
}

/**
 * إرسال رسالة جديدة لتحدي المشي (نص أو صورة أو فيديو حتى 30 ثانية)
 */
export async function sendWalkingGroupMessage(
  groupId: number,
  payload: SendMessagePayload
): Promise<ChatSendResult> {
  try {
    if (payload.attachment_uri) {
      const { mimeType, msgType, filename } = getMediaMimeAndType(payload.attachment_uri, payload.type);
      const formData = new FormData();
      if (payload.message) {
        formData.append('message', payload.message);
      }
      formData.append('type', msgType);
      if (payload.duration) {
        formData.append('duration', String(payload.duration));
      }
      if (payload.reply_to_message_id) {
        formData.append('reply_to_message_id', String(payload.reply_to_message_id));
      }
      if (payload.metadata?.waveform && Array.isArray(payload.metadata.waveform)) {
        payload.metadata.waveform.forEach((val, idx) => {
          formData.append(`metadata[waveform][${idx}]`, String(val));
        });
      }

      // @ts-ignore React Native FormData format
      formData.append('attachment', {
        uri: payload.attachment_uri,
        name: filename,
        type: mimeType,
      });

      const res = (await apiUpload(
        `/v1/walking/groups/${groupId}/chat/messages`,
        formData,
        60000
      )) as ApiResponse<ChatMessageItem>;

      if (res && res.success) {
        return { success: true, data: res.data };
      }

      return { success: false, message: res?.message || 'تعذر إرسال المرفق' };
    }

    const raw = await api.post(
      `/v1/walking/groups/${groupId}/chat/messages`,
      payload,
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem>;
    if (res && res.success) {
      return { success: true, data: res.data };
    }
    return { success: false, message: res?.message || 'تعذر إرسال الرسالة' };
  } catch (error: any) {
    const data = error?.response?.data;
    const errorMsg = data?.message || error?.message || 'حدث خطأ أثناء إرسال الرسالة';
    return {
      success: false,
      message: errorMsg,
      reason: data?.errors?.reason ?? null,
      retry_after: data?.errors?.retry_after ?? null,
      status: error?.response?.status ?? null,
    };
  }
}

/**
 * تحديد قراءة الرسائل لتحدي المشي
 */
export async function markWalkingGroupChatAsRead(
  groupId: number,
  messageId: number
): Promise<boolean> {
  try {
    const raw = await api.post(
      `/v1/walking/groups/${groupId}/chat/read`,
      { message_id: messageId },
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<null>;
    return !!res?.success;
  } catch {
    return false;
  }
}

/**
 * تبديل كتم إشعارات محادثة المشي
 */
export async function toggleWalkingGroupChatMute(groupId: number): Promise<boolean | null> {
  try {
    const raw = await api.post(
      `/v1/walking/groups/${groupId}/chat/mute`,
      {},
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<{ is_muted: boolean }>;
    if (res?.success && res.data) {
      return res.data.is_muted;
    }
    return null;
  } catch {
    return null;
  }
}

/**
 * حذف رسالة من تحدي المشي
 * mode: 'me' (حذف لدي فقط) | 'everyone' (حذف لدى الجميع خلال ساعة من الإرسال)
 */
export async function deleteWalkingGroupMessage(
  groupId: number,
  messageId: number,
  mode: 'me' | 'everyone' = 'me'
): Promise<{ success: boolean; message?: string }> {
  try {
    const raw = await api.delete(`/v1/walking/groups/${groupId}/chat/messages/${messageId}`, {
      data: { mode },
      skipGlobalErrorHandler: true,
    });
    const res = raw as unknown as ApiResponse<{ message_id: number; mode: string }>;
    if (res && res.success) {
      return { success: true, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر حذف الرسالة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء حذف الرسالة';
    return { success: false, message: msg };
  }
}

/**
 * مسح سجل محادثة تحدي المشي بالكامل من شاشة المستخدم ("مسح الشات من عندي")
 */
export async function clearWalkingGroupChat(
  groupId: number
): Promise<{ success: boolean; message?: string }> {
  try {
    const raw = await api.post(
      `/v1/walking/groups/${groupId}/chat/clear`,
      {},
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<null>;
    if (res && res.success) {
      return { success: true, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر مسح المحادثة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء مسح المحادثة';
    return { success: false, message: msg };
  }
}

// ==========================================
// 3. دوال شات أسئلة واستطلاعات وتحديات المسابقة
// (Quiz Question & Survey Live Discussion API)
// ==========================================

/**
 * جلب تفاصيل غرفة محادثة السؤال/الاستطلاع
 */
export async function fetchQuestionChat(questionId: number): Promise<QuestionChatRoom | null> {
  try {
    const raw = await api.get(`/v1/quiz-competitions/questions/${questionId}/chat`, {
      skipGlobalErrorHandler: true,
    });
    const res = raw as unknown as ApiResponse<QuestionChatRoom>;
    if (res && res.success) {
      return res.data;
    }
    return null;
  } catch (error) {
    console.error('[chatApi] fetchQuestionChat error:', error);
    return null;
  }
}

/**
 * جلب رسائل دردشة السؤال/الاستطلاع بنظام Cursor Pagination
 */
export async function fetchQuestionMessages(
  questionId: number,
  params?: { before_id?: number; after_id?: number; limit?: number }
): Promise<ChatMessagesResult> {
  try {
    const raw = await api.get(
      `/v1/quiz-competitions/questions/${questionId}/chat/messages`,
      {
        params,
        skipGlobalErrorHandler: true,
      }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem[]>;
    if (res && res.success) {
      const items = (res.data || []) as ChatMessagesResult;
      const meta = (res as any)?.meta;
      if (meta?.deleted_message_ids) {
        items.deleted_message_ids = meta.deleted_message_ids;
      }
      if (meta?.edited_messages) {
        items.edited_messages = meta.edited_messages;
      }
      if (meta?.moderation) {
        items.moderation = meta.moderation;
      }
      return items;
    }
    return [];
  } catch (error) {
    console.error('[chatApi] fetchQuestionMessages error:', error);
    return [];
  }
}

/**
 * إرسال رسالة لنقاش السؤال/الاستطلاع (نص، صورة، أو فيديو <= 30 ثانية)
 */
export async function sendQuestionMessage(
  questionId: number,
  payload: SendMessagePayload
): Promise<ChatSendResult> {
  try {
    if (payload.attachment_uri) {
      const { mimeType, msgType, filename } = getMediaMimeAndType(payload.attachment_uri, payload.type);
      const formData = new FormData();
      if (payload.message) {
        formData.append('message', payload.message);
      }
      formData.append('type', msgType);
      if (payload.duration) {
        formData.append('duration', String(payload.duration));
      }
      if (payload.reply_to_message_id) {
        formData.append('reply_to_message_id', String(payload.reply_to_message_id));
      }
      if (payload.metadata?.waveform && Array.isArray(payload.metadata.waveform)) {
        payload.metadata.waveform.forEach((val, idx) => {
          formData.append(`metadata[waveform][${idx}]`, String(val));
        });
      }

      // @ts-ignore React Native FormData format
      formData.append('attachment', {
        uri: payload.attachment_uri,
        name: filename,
        type: mimeType,
      });

      const res = (await apiUpload(
        `/v1/quiz-competitions/questions/${questionId}/chat/messages`,
        formData,
        60000
      )) as ApiResponse<ChatMessageItem>;

      const isUploadSuccess = Boolean(
        res && (res.success === true || (res as any).status === 'success' || (res as any).data?.id)
      );

      if (isUploadSuccess) {
        return { success: true, data: res.data };
      }

      return { success: false, message: res?.message || 'تعذر إرسال المرفق' };
    }

    const raw = await api.post(
      `/v1/quiz-competitions/questions/${questionId}/chat/messages`,
      payload,
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem>;
    const isPostSuccess = Boolean(
      res && (res.success === true || (res as any).status === 'success' || (res as any).data?.id)
    );

    if (isPostSuccess) {
      return { success: true, data: res.data };
    }
    return { success: false, message: res?.message || 'تعذر إرسال الرسالة' };
  } catch (error: any) {
    const data = error?.response?.data;
    const errorMsg = data?.message || error?.message || 'حدث خطأ أثناء إرسال الرسالة';
    return {
      success: false,
      message: errorMsg,
      reason: data?.errors?.reason ?? null,
      retry_after: data?.errors?.retry_after ?? null,
      status: error?.response?.status ?? null,
    };
  }
}

/**
 * تعليم رسائل السؤال كمقروءة
 */
export async function markQuestionChatAsRead(
  questionId: number,
  messageId: number
): Promise<boolean> {
  try {
    const raw = await api.post(
      `/v1/quiz-competitions/questions/${questionId}/chat/read`,
      { message_id: messageId },
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<null>;
    return !!res?.success;
  } catch {
    return false;
  }
}

/**
 * تبديل كتم إشعارات محادثة السؤال
 */
export async function toggleQuestionChatMute(questionId: number): Promise<boolean | null> {
  try {
    const raw = await api.post(
      `/v1/quiz-competitions/questions/${questionId}/chat/mute`,
      {},
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<{ is_muted: boolean }>;
    if (res?.success && res.data) {
      return res.data.is_muted;
    }
    return null;
  } catch {
    return null;
  }
}

/**
 * حذف رسالة من نقاش السؤال/المسابقة
 * mode: 'me' (حذف لدي فقط) | 'everyone' (حذف لدى الجميع خلال ساعة من الإرسال)
 */
export async function deleteQuestionMessage(
  questionId: number,
  messageId: number,
  mode: 'me' | 'everyone' = 'me'
): Promise<{ success: boolean; message?: string }> {
  try {
    const raw = await api.delete(`/v1/quiz-competitions/questions/${questionId}/chat/messages/${messageId}`, {
      data: { mode },
      skipGlobalErrorHandler: true,
    });
    const res = raw as unknown as ApiResponse<{ message_id: number; mode: string }>;
    if (res && res.success) {
      return { success: true, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر حذف الرسالة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء حذف الرسالة';
    return { success: false, message: msg };
  }
}

/**
 * مسح سجل محادثة السؤال/المسابقة بالكامل من شاشة المستخدم ("مسح الشات من عندي")
 */
export async function clearQuestionChat(
  questionId: number
): Promise<{ success: boolean; message?: string }> {
  try {
    const raw = await api.post(
      `/v1/quiz-competitions/questions/${questionId}/chat/clear`,
      {},
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<null>;
    if (res && res.success) {
      return { success: true, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر مسح المحادثة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء مسح المحادثة';
    return { success: false, message: msg };
  }
}

/**
 * تعديل رسالة في نقاش السؤال/المسابقة (متاح فقط لصاحب الرسالة خلال 60 دقيقة)
 */
export async function updateQuestionMessage(
  questionId: number,
  messageId: number,
  messageText: string
): Promise<{ success: boolean; data?: ChatMessageItem; message?: string }> {
  try {
    const raw = await api.put(
      `/v1/quiz-competitions/questions/${questionId}/chat/messages/${messageId}`,
      { message: messageText },
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<ChatMessageItem>;
    if (res && res.success && res.data) {
      return { success: true, data: res.data, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر تعديل الرسالة' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء تعديل الرسالة';
    return { success: false, message: msg };
  }
}

// ==========================================
// 4. الإشراف من جهة المستخدم (بلاغات + حالة المحادثة)
// ==========================================

export interface ChatConversationStatus {
  conversation: ChatRoomDetails;
  my_state: {
    can_send: boolean;
    blocked_reason: string | null;
    blocked_message: string | null;
    is_muted: boolean;
    role: string;
    is_moderator: boolean;
    warnings_count: number;
    restricted: boolean;
    restriction_reason: string | null;
  };
}

/**
 * إبلاغ عن رسالة لمخالفتها القواعد (يزيد عدّاد البلاغات ويظهر في لوحة الإدارة)
 */
export async function reportChatMessage(
  messageId: number,
  reason: ChatReportReason,
  details?: string
): Promise<{ success: boolean; message?: string }> {
  try {
    const raw = await api.post(
      `/v1/chat/messages/${messageId}/report`,
      { reason, details: details?.trim() ? details.trim() : undefined },
      { skipGlobalErrorHandler: true }
    );
    const res = raw as unknown as ApiResponse<{ report_id: number; status: string }>;
    if (res?.success) {
      return { success: true, message: res.message };
    }
    return { success: false, message: res?.message || 'تعذر إرسال البلاغ' };
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'حدث خطأ أثناء إرسال البلاغ';
    return { success: false, message: msg };
  }
}

/**
 * حالة الإشراف للمحادثة الحالية (قفل / حظر / وضع بطيء / كتم / دور المستخدم)
 */
export async function fetchChatConversationStatus(
  conversationId: number
): Promise<ChatConversationStatus | null> {
  try {
    const raw = await api.get(`/v1/chat/conversations/${conversationId}/status`, {
      skipGlobalErrorHandler: true,
    });
    const res = raw as unknown as ApiResponse<ChatConversationStatus>;
    if (res?.success && res.data) {
      return res.data;
    }
    return null;
  } catch (error) {
    console.error('[chatApi] fetchChatConversationStatus error:', error);
    return null;
  }
}
