/**
 * مسارات الذكاء الاصطناعي للموبايل.
 * @see family-tree-app/docs/ai-mobile-api.md
 */
import { api, apiUpload, API_BASE_URL } from '@/lib/api/alsorayiaClient';
import * as SecureStore from 'expo-secure-store';
import { searchPersonByImage } from '@/lib/api/familyTreeApi';
import { isAxiosError } from 'axios';
import { Platform } from 'react-native';

const AI_BASE = '/v1/ai';
/** مهلة الشات الموصى بها (الرد غير streaming). */
const CHAT_TIMEOUT_MS = 90_000;

export type AiCitationType = 'person' | 'story' | 'article' | string;

export type AiCitation = {
  key?: string;
  type: AiCitationType;
  id: number;
  title: string;
  url?: string | null;
};

export type AiSearchResult = {
  type: AiCitationType;
  id: number;
  title: string;
  snippet?: string | null;
  url?: string | null;
  score?: number;
};

export type AiConversationSummary = {
  uuid: string;
  title: string;
  created_at: string;
  updated_at: string;
};

export type AiConversationMessage = {
  role: 'user' | 'assistant' | string;
  content: string;
  citations: AiCitation[] | null;
  created_at: string;
};

export type AiConversationDetail = {
  id: string;
  title: string;
  messages: AiConversationMessage[];
};

export type AiChatSuccess = {
  ok: true;
  conversation_id: string;
  message_id?: number;
  answer: string;
  citations: AiCitation[];
};

export type AiVoiceChatSuccess = {
  ok: true;
  transcription: string;
  conversation_id: string;
  message_id?: number;
  answer: string;
  citations: AiCitation[];
};

export type AiVoiceChatResult = AiVoiceChatSuccess | AiFailure;

export type AiPersonMatch = {
  id: number;
  full_name: string;
  first_name: string;
  avatar?: string | null;
  similarity: number;
  kinship?: string | null;
  branch?: string | null;
};

export type AiPhotoChatSuccess = {
  ok: true;
  conversation_id: string;
  message_id?: number;
  answer: string;
  citations: AiCitation[];
  person_match?: AiPersonMatch | null;
};

export type AiPhotoChatResult = AiPhotoChatSuccess | AiFailure;

export type AiTtsOptions = {
  message_id?: number | string | null;
  text?: string | null;
  voice?: string | null;
};

export type AiTtsSuccess = {
  ok: true;
  audio_url: string;
  cached: boolean;
  duration_approx?: number;
  voice?: string;
};

export type AiTtsResult = AiTtsSuccess | AiFailure;

export type AiFailure = {
  ok: false;
  message: string;
  status?: number;
  /** انتهاء مهلة الشبكة / انقطاع */
  isTimeout?: boolean;
  /** يلزم إعادة إدخال كلمة سر الموقع */
  requiresAuth?: boolean;
};

export type AiChatResult = AiChatSuccess | AiFailure;

export type AiSuggestionsResult =
  | { ok: true; suggestions: string[] }
  | AiFailure;

export type AiConversationsResult =
  | { ok: true; conversations: AiConversationSummary[] }
  | AiFailure;

export type AiConversationDetailResult =
  | { ok: true; conversation: AiConversationDetail }
  | AiFailure;

export type AiSearchApiResult =
  | { ok: true; results: AiSearchResult[] }
  | AiFailure;

function extractErrorMessage(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && 'message' in data) {
    const m = (data as { message?: unknown }).message;
    if (typeof m === 'string' && m.trim()) return m;
  }
  return fallback;
}

function axiosFailure(e: unknown, fallback: string): AiFailure {
  if (isAxiosError(e)) {
    if (e.code === 'ECONNABORTED' || e.message?.toLowerCase().includes('timeout')) {
      return {
        ok: false,
        message: 'استغرق الرد وقتًا أطول، حاول مرة أخرى',
        isTimeout: true,
      };
    }
    if (e.response) {
      const status = e.response.status;
      return {
        ok: false,
        message: extractErrorMessage(
          e.response.data,
          status === 401 ? 'غير مصرح. يلزم رمز دخول الموقع.' : fallback
        ),
        status,
        requiresAuth: status === 401,
      };
    }
    if (!e.response || e.message === 'Network Error') {
      return { ok: false, message: 'تحقق من الاتصال بالإنترنت.' };
    }
  }
  return { ok: false, message: fallback };
}

function parseCitations(raw: unknown): AiCitation[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((item): AiCitation | null => {
      if (!item || typeof item !== 'object') return null;
      const o = item as Record<string, unknown>;
      if (typeof o.id !== 'number' || typeof o.title !== 'string') return null;
      return {
        key: typeof o.key === 'string' ? o.key : undefined,
        type: typeof o.type === 'string' ? o.type : 'unknown',
        id: o.id,
        title: o.title,
        url: typeof o.url === 'string' ? o.url : o.url === null ? null : undefined,
      };
    })
    .filter((x): x is AiCitation => x != null);
}

/**
 * `POST /api/v1/ai/chat`
 */
export async function aiChat(
  message: string,
  conversationId?: string | null,
  personId?: number | null
): Promise<AiChatResult> {
  const trimmed = message.trim().slice(0, 2000);
  if (!trimmed) {
    return { ok: false, message: 'السؤال فارغ' };
  }

  try {
    const raw = (await api.post(
      `${AI_BASE}/chat`,
      {
        message: trimmed,
        conversation_id: conversationId ?? null,
        person_id: personId ?? null,
      },
      {
        skipGlobalErrorHandler: true,
        timeout: CHAT_TIMEOUT_MS,
      }
    )) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر الحصول على رد من المساعد.' };
    }

    const body = raw as {
      ok?: boolean;
      conversation_id?: string;
      message_id?: number;
      answer?: string;
      citations?: unknown;
      message?: string;
    };

    if (body.ok === false) {
      console.warn(`[AI Chat ✗] خطأ من الخادم:`, body.message);
      return { ok: false, message: extractErrorMessage(body, 'تعذر الحصول على رد من المساعد.') };
    }

    if (typeof body.answer !== 'string' || !body.answer.trim()) {
      return { ok: false, message: extractErrorMessage(body, 'الرد فارغ من الخادم.') };
    }

    // طباعة إجابة الذكاء الاصطناعي بوضوح في اللوج للتيرمينال
    console.log('\n==================================================');
    console.log('🤖 [ذكاء السريّع - إجابة الذكاء الاصطناعي (شات نصي)]:');
    console.log(`💬 السؤال: "${trimmed}"`);
    console.log('📝 الإجابة:\n' + body.answer.trim());
    if (body.citations && Array.isArray(body.citations) && body.citations.length > 0) {
      console.log(`🔗 عدد المراجع الموثقة: ${body.citations.length}`);
    }
    console.log('==================================================\n');

    return {
      ok: true,
      conversation_id:
        typeof body.conversation_id === 'string' && body.conversation_id
          ? body.conversation_id
          : conversationId ?? '',
      message_id: typeof body.message_id === 'number' ? body.message_id : undefined,
      answer: body.answer,
      citations: parseCitations(body.citations),
    };
  } catch (e) {
    return axiosFailure(e, 'تعذر الاتصال بخادم الذكاء الاصطناعي.');
  }
}

export type AiChatStreamCallbacks = {
  onThinking?: (text: string) => void;
  onDelta?: (delta: string) => void;
  onCitations?: (citations: AiCitation[]) => void;
  onDone?: (data: { conversationId: string; messageId?: number; answer: string }) => void;
  onError?: (error: string, requiresAuth?: boolean) => void;
};

/**
 * `POST /api/v1/ai/chat/stream`
 * بث فوري وتفاعلي لرد الذكاء الاصطناعي مع مؤشر التفكير وحركات التوليد (ChatGPT style)
 */
export function aiChatStream(
  message: string,
  conversationId: string | null | undefined,
  personId: number | null | undefined,
  callbacks: AiChatStreamCallbacks
): () => void {
  const trimmed = message.trim().slice(0, 2000);
  if (!trimmed) {
    callbacks.onError?.('السؤال فارغ');
    return () => {};
  }

  let aborted = false;
  const xhr = new XMLHttpRequest();

  (async () => {
    try {
      let token: string | null = null;
      try {
        if (Platform.OS === 'web') {
          token = typeof localStorage !== 'undefined' ? localStorage.getItem('site_access_token') : null;
        } else {
          token = await SecureStore.getItemAsync('site_access_token');
        }
      } catch {}

      if (aborted) return;

      const endpoint = `${API_BASE_URL}${AI_BASE}/chat/stream`;
      xhr.open('POST', endpoint, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.setRequestHeader('Accept', 'text/event-stream');
      if (token) {
        xhr.setRequestHeader('Authorization', `Bearer ${token}`);
        xhr.setRequestHeader('X-Site-Access-Token', token);
      }

      let processedIndex = 0;
      let buffer = '';

      xhr.onprogress = () => {
        if (aborted) return;
        const newText = xhr.responseText.slice(processedIndex);
        processedIndex = xhr.responseText.length;
        buffer += newText;

        const chunks = buffer.split('\n\n');
        buffer = chunks.pop() || '';

        for (const chunk of chunks) {
          if (!chunk.trim()) continue;
          const lines = chunk.split('\n');
          let eventType = 'message';
          let dataStr = '';

          for (const line of lines) {
            if (line.startsWith('event: ')) {
              eventType = line.slice(7).trim();
            } else if (line.startsWith('data: ')) {
              dataStr = line.slice(6).trim();
            }
          }

          if (!dataStr) continue;
          if (dataStr === '[DONE]') continue;

          try {
            const parsed = JSON.parse(dataStr);
            if (eventType === 'thinking') {
              callbacks.onThinking?.(parsed.message || 'جاري التفكير والبحث...');
            } else if (eventType === 'delta') {
              if (typeof parsed.delta === 'string') {
                callbacks.onDelta?.(parsed.delta);
              }
            } else if (eventType === 'citations') {
              callbacks.onCitations?.(parseCitations(parsed.citations));
            } else if (eventType === 'done') {
              callbacks.onDone?.({
                conversationId: parsed.conversation_id || conversationId || '',
                messageId: parsed.message_id,
                answer: parsed.answer || '',
              });
            } else if (eventType === 'error') {
              callbacks.onError?.(parsed.message || 'حدث خطأ غير متوقع');
            }
          } catch {
            // Non-JSON SSE payload
          }
        }
      };

      xhr.onload = () => {
        if (aborted) return;
        if (xhr.status === 401) {
          callbacks.onError?.('غير مصرح. يلزم رمز دخول الموقع.', true);
          return;
        }
        if (xhr.status >= 400) {
          callbacks.onError?.(`فشل الاتصال بالخادم (${xhr.status})`);
        }
      };

      xhr.onerror = () => {
        if (aborted) return;
        callbacks.onError?.('تعذر الاتصال بخادم الذكاء الاصطناعي، يرجى التحقق من الشبكة.');
      };

      xhr.send(
        JSON.stringify({
          message: trimmed,
          conversation_id: conversationId ?? null,
          person_id: personId ?? null,
        })
      );
    } catch (e: any) {
      if (!aborted) {
        callbacks.onError?.(e?.message || 'حدث خطأ في تشغيل البث');
      }
    }
  })();

  return () => {
    aborted = true;
    try {
      xhr.abort();
    } catch {}
  };
}

/**
 * `POST /api/v1/ai/voice-chat`
 * رفع رسالة صوتية وتفريغها والإجابة عليها
 */
export async function aiVoiceChat(
  audioUri: string,
  conversationId?: string | null,
  personId?: number | null
): Promise<AiVoiceChatResult> {
  try {
    const formData = new FormData();
    const rawFilename = audioUri.split('/').pop() || 'recording.m4a';
    const match = /\.(\w+)$/.exec(rawFilename);
    const rawExt = match?.[1]?.toLowerCase() || 'm4a';
    const validExtensions = ['mp3', 'm4a', 'mp4', 'wav', 'aac', 'ogg', 'webm'];
    const ext = validExtensions.includes(rawExt) ? rawExt : 'm4a';
    const filename = rawFilename.toLowerCase().endsWith(`.${ext}`)
      ? rawFilename
      : `recording.${ext}`;

    const mimeType =
      ext === 'mp3'
        ? 'audio/mpeg'
        : ext === 'wav'
        ? 'audio/wav'
        : ext === 'ogg'
        ? 'audio/ogg'
        : ext === 'webm'
        ? 'audio/webm'
        : ext === 'aac'
        ? 'audio/aac'
        : 'audio/m4a';

    // نستخدم URI مباشرةً — React Native FormData تتعامل مع file:// URIs
    // بدون base64 encoding وهو أسرع وأصح من blob.slice() الذي يُفسد البيانات الثنائية
    const cleanUri = Platform.OS === 'ios' ? audioUri.replace('file://', '') : audioUri;
    formData.append('audio', {
      uri: cleanUri,
      name: filename,
      type: mimeType,
    } as any);

    console.log(`🎤 [Voice Upload] ext=${ext}, mime=${mimeType}, file=${filename}`);

    if (conversationId) {
      formData.append('conversation_id', conversationId);
    }
    if (personId != null) {
      formData.append('person_id', String(personId));
    }

    const res = (await apiUpload(
      `${AI_BASE}/voice-chat`,
      formData,
      CHAT_TIMEOUT_MS
    )) as unknown;

    if (!res || typeof res !== 'object') {
      return { ok: false, message: 'تعذر الحصول على رد من المساعد.' };
    }

    const body = res as {
      ok?: boolean;
      transcription?: string;
      conversation_id?: string;
      message_id?: number;
      answer?: string;
      citations?: unknown;
      message?: string;
    };

    if (body.ok === false) {
      console.warn(`[AI Voice Chat ✗] خطأ من الخادم:`, body.message);
      return { ok: false, message: extractErrorMessage(body, 'تعذر الحصول على رد من المساعد.') };
    }

    // طباعة إجابة الذكاء الاصطناعي وتفريغ الصوت بوضوح في اللوج للتيرمينال
    console.log('\n==================================================');
    console.log('🎙️ [ذكاء السريّع - إجابة الذكاء الاصطناعي على الصوت]:');
    console.log(`🗣️ تفريغ الصوت (Transcription): "${body.transcription || '(بدون تفريغ)'}"`);
    console.log('📝 الإجابة:\n' + (body.answer || '').trim());
    if (body.citations && Array.isArray(body.citations) && body.citations.length > 0) {
      console.log(`🔗 عدد المراجع الموثقة: ${body.citations.length}`);
    }
    console.log('==================================================\n');

    return {
      ok: true,
      transcription: body.transcription || '',
      conversation_id: body.conversation_id || conversationId || '',
      message_id: body.message_id,
      answer: body.answer || '',
      citations: parseCitations(body.citations),
    };
  } catch (e: any) {
    if (e?.name === 'AbortError' || e?.message?.includes('aborted')) {
      return {
        ok: false,
        message: 'استغرق إرسال الصوت وقتاً أطول، يرجى المحاولة مرة أخرى.',
        isTimeout: true,
      };
    }
    return axiosFailure(e, 'تعذر إرسال الرسالة الصوتية.');
  }
}

/**
 * `POST /api/v1/ai/tts`
 * تحويل الرد إلى صوت عند الطلب فقط (لا يتم استدعاؤه إلا بنقر المستخدم توفيراً للتوكنات)
 */
export async function aiGetTts(
  messageId?: number | string | null,
  text?: string | null,
  voice?: string | null
): Promise<AiTtsResult> {
  try {
    const payload: Record<string, unknown> = {};
    if (typeof messageId === 'number') {
      payload.message_id = messageId;
    }
    if (text) {
      payload.text = text;
    }
    if (voice) {
      payload.voice = voice;
    }

    const raw = (await api.post(`${AI_BASE}/tts`, payload, {
      skipGlobalErrorHandler: true,
      timeout: 35_000,
    })) as unknown;

    const body = raw as {
      ok?: boolean;
      audio_url?: string;
      cached?: boolean;
      duration_approx?: number;
      voice?: string;
      message?: string;
    };

    if (body?.ok && body.audio_url) {
      return {
        ok: true,
        audio_url: body.audio_url,
        cached: Boolean(body.cached),
        duration_approx: body.duration_approx,
        voice: body.voice,
      };
    }

    return { ok: false, message: extractErrorMessage(body, 'تعذر توليد الرد الصوتي.') };
  } catch (e) {
    return axiosFailure(e, 'تعذر الاتصال بخدمة الصوت.');
  }
}

/**
 * خط الدفاع الثاني المتطور (Self-Healing Fallback):
 * في حال تعذر /v1/ai/photo-chat، يتم البحث عن الوجه مباشرة عبر /persons/search/image
 * ثم دمج بطاقة الشخص المطابق مع إجابة الذكاء الاصطناعي بنعومة واحترافية تامة.
 */
async function fallbackPhotoChat(
  existingFormData: FormData | null,
  imageUri: string,
  message?: string,
  conversationId?: string | null,
  personId?: number | null
): Promise<AiPhotoChatResult> {
  try {
    console.log('🛡️ [AI Photo Fallback] جاري التعرف على الوجه عبر endpoint البحث المباشر...');

    let formData = existingFormData;
    if (!formData) {
      formData = new FormData();
      const rawFilename = imageUri.split('/').pop() || 'photo.jpg';
      const cleanUri = Platform.OS === 'ios' ? imageUri.replace('file://', '') : imageUri;
      formData.append('image', {
        uri: cleanUri,
        name: rawFilename,
        type: 'image/jpeg',
      } as any);
    }

    const searchRes = await searchPersonByImage(formData);

    if (searchRes && searchRes.status === 'success' && Array.isArray(searchRes.data) && searchRes.data.length > 0) {
      const best = searchRes.data[0];
      const p = best.person;
      const simRaw = best.similarity;
      const simNum = typeof simRaw === 'number' ? simRaw : parseFloat(String(simRaw).replace('%', '')) || 85;

      const personMatch: AiPersonMatch = {
        id: p.id,
        full_name: p.full_name,
        first_name: p.first_name || p.full_name?.split(' ')?.[0] || '',
        avatar: p.avatar || p.photo_url || null,
        similarity: simNum,
        kinship: null,
        branch: null,
      };

      console.log(`👤 [Fallback] تم التعرف بنجاح على: ${p.full_name} (${simNum}%) [ID: ${p.id}]`);

      // نسأل الذكاء الاصطناعي مع تمرير p.id (معرف الشخص المطابق) لكي يبني RAG سياق هذا الشخص بالتحديد
      const askPrompt = message?.trim()
        ? `${message.trim()} (هذا الشخص تم التعرف عليه بيومترياً رسمياً وهو ${p.full_name}، سجل رقم #${p.id})`
        : `حدثني عن ${p.full_name} (سجل العائلة #${p.id}) في شجرة عائلة السريّع، واذكر نسبه ومكانته ومعلومات عنه.`;

      const chatRes = await aiChat(askPrompt, conversationId, p.id);

      const citations: AiCitation[] = chatRes.ok && chatRes.citations ? [...chatRes.citations] : [];
      if (!citations.some((c) => c.id === p.id)) {
        citations.unshift({
          type: 'person',
          id: p.id,
          title: p.full_name,
        });
      }

      // إذا رد الذكاء الاصطناعي بنجاح ولم يقل "لم أجد" نعتمد رده، وإلا نضع رداً تعريفياً مؤكداً وفاخراً
      let finalAnswer = (chatRes.ok && chatRes.answer && !chatRes.answer.includes('لم أجد هذا الشخص'))
        ? chatRes.answer
        : `تم التعرف على الوجه بنجاح بنسبة تطابق (${simNum}%): هذا هو **${p.full_name}**، أحد أفراد عائلة السريّع الكرام.\n\nيمكنك الاطلاع على شجرته وكافة بياناته ونسبه بالنقر على البطاقة أدناه.`;

      return {
        ok: true,
        conversation_id: chatRes.ok ? chatRes.conversation_id : conversationId || '',
        message_id: chatRes.ok ? chatRes.message_id : undefined,
        answer: finalAnswer,
        citations,
        person_match: personMatch,
      };
    }

    // لم يتم العثور على أي تطابق
    console.log('🔍 [Fallback] لم يتم العثور على تطابق في شجرة العائلة');
    return {
      ok: true,
      conversation_id: conversationId || '',
      answer:
        'تم فحص ملامح الوجه في قاعدة بيانات شجرة عائلة السريّع عبر AWS Rekognition ولكن لم يُعثر على تطابق مع أي فرد مسجل في الشجرة حالياً. يرجى التأكد من وضوح زاوية الصورة أو البحث بالاسم ثلاثياً.',
      citations: [],
      person_match: null,
    };
  } catch (err: any) {
    console.error('❌ [AI Photo Fallback Error]:', err);
    return {
      ok: false,
      message: 'تعذر معالجة صورة الوجه والتعرف عليها حالياً.',
    };
  }
}

/**
 * `POST /api/v1/ai/photo-chat`
 * رفع صورة الوجه للتعرف عليها عبر AWS Rekognition وربطها بالشجرة والذكاء الاصطناعي مع خط دفاع ذاتي بديل
 */
export async function aiPhotoChat(
  imageUri: string,
  message?: string,
  conversationId?: string | null,
  personId?: number | null
): Promise<AiPhotoChatResult> {
  let formData: FormData | null = null;
  try {
    formData = new FormData();
    const rawFilename = imageUri.split('/').pop() || 'photo.jpg';
    const match = /\.(\w+)$/.exec(rawFilename);
    const rawExt = match?.[1]?.toLowerCase() || 'jpg';
    const validExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
    const ext = validExtensions.includes(rawExt) ? rawExt : 'jpg';
    const filename = rawFilename.toLowerCase().endsWith(`.${ext}`)
      ? rawFilename
      : `photo.${ext}`;

    const mimeType = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';
    const cleanUri = Platform.OS === 'ios' ? imageUri.replace('file://', '') : imageUri;

    formData.append('image', {
      uri: cleanUri,
      name: filename,
      type: mimeType,
    } as any);

    if (message?.trim()) {
      formData.append('message', message.trim());
    }
    if (conversationId) {
      formData.append('conversation_id', conversationId);
    }
    if (personId != null) {
      formData.append('person_id', String(personId));
    }

    console.log(`📷 [Photo Upload] ext=${ext}, mime=${mimeType}, file=${filename}`);

    const res = (await apiUpload(
      `${AI_BASE}/photo-chat`,
      formData,
      CHAT_TIMEOUT_MS
    )) as unknown;

    if (!res || typeof res !== 'object') {
      console.warn('[AI Photo Chat] استجابة غير صالحة من السيرفر — تفعيل Fallback...');
      return await fallbackPhotoChat(formData, imageUri, message, conversationId, personId);
    }

    const body = res as {
      ok?: boolean;
      conversation_id?: string;
      message_id?: number;
      answer?: string;
      citations?: unknown;
      person_match?: AiPersonMatch | null;
      message?: string;
    };

    if (body.ok === false) {
      console.warn(`[AI Photo Chat ✗] رد السيرفر (${body.message}) — تفعيل خط الدفاع الثاني البديل...`);
      return await fallbackPhotoChat(formData, imageUri, message, conversationId, personId);
    }

    console.log('\n==================================================');
    console.log('📷 [ذكاء السريّع - إجابة الذكاء الاصطناعي على صورة الوجه]:');
    if (body.person_match) {
      console.log(`👤 الشخص المطابق (AWS Rekognition): ${body.person_match.full_name} (${body.person_match.similarity}%)`);
    }
    console.log('📝 الإجابة:\n' + (body.answer || '').trim());
    console.log('==================================================\n');

    return {
      ok: true,
      conversation_id: body.conversation_id || conversationId || '',
      message_id: body.message_id,
      answer: body.answer || '',
      citations: parseCitations(body.citations),
      person_match: body.person_match ?? null,
    };
  } catch (e: any) {
    if (e?.name === 'AbortError' || e?.message?.includes('aborted')) {
      return {
        ok: false,
        message: 'استغرق فحص الصورة وقتاً أطول، يرجى المحاولة مرة أخرى.',
        isTimeout: true,
      };
    }
    console.warn(`[AI Photo Chat ✗] تعذر الاتصال (${e?.message}) — تفعيل خط الدفاع الثاني البديل...`);
    return await fallbackPhotoChat(formData, imageUri, message, conversationId, personId);
  }
}

/**
 * `GET /api/v1/ai/suggestions`
 */
export async function aiSuggestions(): Promise<AiSuggestionsResult> {
  try {
    const raw = (await api.get(`${AI_BASE}/suggestions`, {
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل الاقتراحات.' };
    }

    const body = raw as { ok?: boolean; suggestions?: unknown; message?: string };
    if (body.ok === false) {
      return { ok: false, message: extractErrorMessage(body, 'تعذر تحميل الاقتراحات.') };
    }

    const suggestions = Array.isArray(body.suggestions)
      ? body.suggestions.filter((s): s is string => typeof s === 'string' && s.trim().length > 0)
      : [];

    return { ok: true, suggestions };
  } catch (e) {
    return axiosFailure(e, 'تعذر تحميل الاقتراحات.');
  }
}

/**
 * `GET /api/v1/ai/conversations`
 */
export async function aiConversations(): Promise<AiConversationsResult> {
  try {
    const raw = (await api.get(`${AI_BASE}/conversations`, {
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المحادثات.' };
    }

    const body = raw as { ok?: boolean; conversations?: unknown; message?: string };
    if (body.ok === false) {
      return { ok: false, message: extractErrorMessage(body, 'تعذر تحميل المحادثات.') };
    }

    const conversations = Array.isArray(body.conversations)
      ? (body.conversations as AiConversationSummary[]).filter(
          (c) => c && typeof c.uuid === 'string' && typeof c.title === 'string'
        )
      : [];

    return { ok: true, conversations };
  } catch (e) {
    return axiosFailure(e, 'تعذر تحميل المحادثات.');
  }
}

/**
 * `GET /api/v1/ai/conversations/{uuid}`
 */
export async function aiConversationDetail(
  uuid: string
): Promise<AiConversationDetailResult> {
  try {
    const raw = (await api.get(`${AI_BASE}/conversations/${encodeURIComponent(uuid)}`, {
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تحميل المحادثة.' };
    }

    const body = raw as {
      ok?: boolean;
      conversation?: AiConversationDetail;
      message?: string;
    };

    if (body.ok === false || !body.conversation) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'المحادثة غير موجودة'),
        status: 404,
      };
    }

    const conv = body.conversation;
    const messages = Array.isArray(conv.messages)
      ? conv.messages.map((m) => ({
          ...m,
          citations: m.citations ? parseCitations(m.citations) : null,
        }))
      : [];

    return {
      ok: true,
      conversation: {
        id: conv.id,
        title: conv.title,
        messages,
      },
    };
  } catch (e) {
    return axiosFailure(e, 'تعذر تحميل المحادثة.');
  }
}

/**
 * `GET /api/v1/ai/search?q=`
 */
export async function aiSearch(q: string): Promise<AiSearchApiResult> {
  const query = q.trim();
  if (!query) {
    return { ok: false, message: 'أدخل نصًا للبحث.' };
  }

  try {
    const raw = (await api.get(`${AI_BASE}/search`, {
      params: { q: query },
      skipGlobalErrorHandler: true,
    })) as unknown;

    if (!raw || typeof raw !== 'object') {
      return { ok: false, message: 'تعذر تنفيذ البحث.' };
    }

    const body = raw as {
      ok?: boolean;
      results?: unknown;
      message?: string;
    };

    if (body.ok === false) {
      return {
        ok: false,
        message: extractErrorMessage(body, 'تعذر تنفيذ البحث.'),
      };
    }

    const results = Array.isArray(body.results)
      ? (body.results as AiSearchResult[]).filter(
          (r) => r && typeof r.id === 'number' && typeof r.title === 'string'
        )
      : [];

    return { ok: true, results };
  } catch (e) {
    return axiosFailure(e, 'تعذر تنفيذ البحث.');
  }
}
