import { AudioMessagePlayer } from '@/components/ai/AudioMessagePlayer';
import {
  VoiceRecorderBar,
  type VoiceRecorderBarHandle,
} from '@/components/ai/VoiceRecorderBar';
import { AnimatedBubbles } from '@/components/animated-bubbles';
import { ComingSoon } from '@/components/coming-soon';
import HeaderBar from '@/components/headerBar';
import { ThemedView } from '@/components/themed-view';
import { Colors, silaTheme as T } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { useColorScheme } from '@/hooks/use-color-scheme';

import {
  aiChatStream,
  aiGetTts,
  aiPhotoChat,
  aiSuggestions,
  aiVoiceChat,
  type AiCitation,
  type AiPersonMatch,
} from '@/lib/api/ai';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import {
  FontAwesome5,
  Ionicons,
  MaterialCommunityIcons,
} from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import { File as ExpoFile, Paths } from 'expo-file-system';
import * as FileSystemLegacy from 'expo-file-system/legacy';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';

import { appStorage } from '@/lib/storage/appStorage';
import * as Sharing from 'expo-sharing';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActionSheetIOS,
  ActivityIndicator,
  FlatList,
  Keyboard,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Share,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

/** اقلبه إلى `false` عندما تكون ميزة الذكاء الاصطناعي جاهزة للإطلاق */
const AI_COMING_SOON = true;

interface Message {
  id: string;
  sender: 'user' | 'ai';
  text: string;
  timestamp: string;
  isError?: boolean;
  isVoice?: boolean;
  audioUri?: string;
  messageId?: number;
  imageUri?: string;
  personMatch?: AiPersonMatch;
  citations?: AiCitation[];
}

const STORAGE_CHAT_MESSAGES = 'ai_chat_messages_cache_v2';
const STORAGE_CONVERSATION_ID = 'ai_chat_conversation_id_v2';

const FALLBACK_SUGGESTIONS = [
  'من هو أكبر فرد مسجّل في الشجرة؟',
  'احكِ لي عن قصة من قصص العائلة',
  'إيه صلة القرابة بين شخصين؟',
  'كم عدد أفراد العائلة المسجلين؟',
];

const WELCOME_TEXT =
  'أهلاً بك في منصة «ذكاء السريّع»\nأنا المساعد الذكي المخصص لخدمة أبناء عائلة السريّع. يمكنك الاستفسار عن الشجرة، القصص، القرابة، أو إرفاق صورة للتعرف على أي فرد من أفراد العائلة.';

function formatTime() {
  return new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
}

function makeWelcomeMessage(): Message {
  return {
    id: `welcome-${Date.now()}`,
    sender: 'ai',
    text: WELCOME_TEXT,
    timestamp: formatTime(),
  };
}

export default function AiScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const router = useRouter();
  const { user, lock } = useAuth();


  const personId: number | null = typeof user?.id === 'number' ? user.id : null;
  const [headerHeight, setHeaderHeight] = useState(68);

  const [input, setInput] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const [thinkingStatus, setThinkingStatus] = useState<string | null>(null);
  const activeStreamAbortRef = useRef<(() => void) | null>(null);
  const [isRecordingAudio, setIsRecordingAudio] = useState(false);
  const [selectedImage, setSelectedImage] = useState<string | null>(null);
  const [suggestions, setSuggestions] = useState<string[]>(FALLBACK_SUGGESTIONS);
  const [suggestionsLoading, setSuggestionsLoading] = useState(true);
  const [copiedMessageId, setCopiedMessageId] = useState<string | null>(null);
  const [sharingAudioId, setSharingAudioId] = useState<string | null>(null);

  const conversationIdRef = useRef<string | null>(null);
  const flatListRef = useRef<FlatList>(null);
  const voiceRecorderRef = useRef<VoiceRecorderBarHandle>(null);

  const [messages, setMessages] = useState<Message[]>([makeWelcomeMessage()]);

  // استرجاع سجل المحادثة المحفوظ عند فتح التطبيق
  useEffect(() => {
    let isMounted = true;
    async function loadCachedChat() {
      try {
        const [savedMsgs, savedConvId] = await Promise.all([
          appStorage.getItem(STORAGE_CHAT_MESSAGES),
          appStorage.getItem(STORAGE_CONVERSATION_ID),
        ]);

        if (isMounted && savedMsgs) {
          const parsed = JSON.parse(savedMsgs);
          if (Array.isArray(parsed) && parsed.length > 0) {
            setMessages(parsed);
            if (savedConvId) {
              conversationIdRef.current = savedConvId;
            }
          }
        }
      } catch (err) {
        console.warn('[AI Chat] فشل استرجاع سجل المحادثة:', err);
      }
    }
    void loadCachedChat();
    return () => {
      isMounted = false;
    };
  }, []);

  // الحفظ التلقائي الفوري لرسائل المحادثة
  useEffect(() => {
    if (messages.length === 1 && messages[0].id.startsWith('welcome')) {
      return;
    }
    const toSave = messages.filter((m) => !!m.text || m.isError || m.imageUri || m.audioUri).slice(-60);
    const json = JSON.stringify(toSave);
    void appStorage.setItem(STORAGE_CHAT_MESSAGES, json).catch(() => {});
    if (conversationIdRef.current) {
      void appStorage.setItem(STORAGE_CONVERSATION_ID, conversationIdRef.current).catch(() => {});
    }
  }, [messages]);

  const scrollToEnd = useCallback(() => {
    requestAnimationFrame(() => {
      flatListRef.current?.scrollToEnd({ animated: true });
    });
  }, []);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const showSub = Keyboard.addListener(showEvent, () => scrollToEnd());
    return () => showSub.remove();
  }, [scrollToEnd]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setSuggestionsLoading(true);
      const res = await aiSuggestions();
      if (cancelled) return;
      if (res.ok && res.suggestions.length > 0) {
        setSuggestions(res.suggestions);
      }
      setSuggestionsLoading(false);
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const openCitation = useCallback(
    (citation: AiCitation) => {
      const type = (citation.type || '').toLowerCase();
      if (type === 'person' && citation.id) {
        router.push(`/(app)/person/${citation.id}`);
        return;
      }
      if (type === 'article' && citation.id) {
        router.push(`/(app)/article/${citation.id}`);
        return;
      }
      const url =
        citation.url ||
        (type === 'story'
          ? `${WEB_BASE_URL}/stories/${citation.id}`
          : type === 'person'
          ? `${WEB_BASE_URL}/person/${citation.id}`
          : type === 'article'
          ? `${WEB_BASE_URL}/articles/${citation.id}`
          : null);
      if (url) {
        Linking.openURL(url).catch(() => {
          CustomAlert.alert('تعذر فتح الرابط', 'تحقق من الاتصال أو حاول لاحقاً.');
        });
      }
    },
    [router]
  );

  // إرسال سؤال نصي مع بث مباشر للرد وتأثير تفكير ذكي (Streaming & Thinking)
  const handleSendText = useCallback(
    (textToSend?: string) => {
      const query = (textToSend || input).trim();
      if (!query || isTyping) return;

      const userMsg: Message = {
        id: `user-${Date.now()}`,
        sender: 'user',
        text: query,
        timestamp: formatTime(),
      };

      const aiMsgId = `ai-${Date.now()}`;
      const tempAiMsg: Message = {
        id: aiMsgId,
        sender: 'ai',
        text: '',
        timestamp: formatTime(),
      };

      setMessages((prev) => [...prev, userMsg, tempAiMsg]);
      setInput('');
      setIsTyping(true);
      setThinkingStatus('جاري التفكير وتحليل السؤال...');
      scrollToEnd();

      console.log(`\n💬 [إرسال سؤال للمساعد - بث فوري]: "${query}"`);

      let accumulated = '';
      let receivedCitations: AiCitation[] = [];

      if (activeStreamAbortRef.current) {
        activeStreamAbortRef.current();
      }

      activeStreamAbortRef.current = aiChatStream(
        query,
        conversationIdRef.current,
        personId,
        {
          onThinking: (text: string) => {
            setThinkingStatus(text);
          },
          onDelta: (delta: string) => {
            setThinkingStatus(null);
            accumulated += delta;
            setMessages((prev) =>
              prev.map((m) => (m.id === aiMsgId ? { ...m, text: accumulated } : m))
            );
            scrollToEnd();
          },
          onCitations: (citations: AiCitation[]) => {
            receivedCitations = citations;
            setMessages((prev) =>
              prev.map((m) => (m.id === aiMsgId ? { ...m, citations } : m))
            );
            scrollToEnd();
          },
          onDone: (data: { conversationId: string; messageId?: number; answer: string }) => {
            if (data.conversationId) {
              conversationIdRef.current = data.conversationId;
            }
            setMessages((prev) =>
              prev.map((m) =>
                m.id === aiMsgId
                  ? {
                      ...m,
                      text: data.answer || accumulated,
                      messageId: data.messageId,
                      citations: receivedCitations.length ? receivedCitations : m.citations,
                    }
                  : m
              )
            );
            setIsTyping(false);
            setThinkingStatus(null);
            activeStreamAbortRef.current = null;
            scrollToEnd();
          },
          onError: (errMsg: string, requiresAuth?: boolean) => {
            if (requiresAuth) {
              CustomAlert.alert('انتهت الجلسة', errMsg, [
                { text: 'حسناً', onPress: () => void lock() },
              ]);
            }
            setMessages((prev) =>
              prev.map((m) =>
                m.id === aiMsgId
                  ? {
                      ...m,
                      text: errMsg,
                      isError: true,
                    }
                  : m
              )
            );
            setIsTyping(false);
            setThinkingStatus(null);
            activeStreamAbortRef.current = null;
            scrollToEnd();
          },
        }
      );
    },
    [input, isTyping, personId, lock, scrollToEnd]
  );

  // إرسال صورة للتعرف على الوجه مع سؤال اختياري
  const handleSendPhoto = useCallback(
    async (imageUri: string, userCaption?: string) => {
      if (isTyping) return;

      const caption = userCaption?.trim() || '';
      const userMsg: Message = {
        id: `user-img-${Date.now()}`,
        sender: 'user',
        text: caption || 'صورة للتعرف على الوجه في شجرة العائلة',
        timestamp: formatTime(),
        imageUri,
      };

      const aiMsgId = `ai-${Date.now()}`;
      const tempAiMsg: Message = {
        id: aiMsgId,
        sender: 'ai',
        text: '',
        timestamp: formatTime(),
      };

      setMessages((prev) => [...prev, userMsg, tempAiMsg]);
      setSelectedImage(null);
      setInput('');
      setIsTyping(true);
      setThinkingStatus('جاري فحص ملامح الوجه والبحث في شجرة العائلة...');
      scrollToEnd();

      console.log(`\n📷 [رفع صورة للتعرف البيومتري]: "${imageUri}"`);
      const res = await aiPhotoChat(imageUri, caption, conversationIdRef.current, personId);

      if (res.ok) {
        if (res.conversation_id) {
          conversationIdRef.current = res.conversation_id;
        }
        setMessages((prev) =>
          prev.map((m) =>
            m.id === aiMsgId
              ? {
                  ...m,
                  text: res.answer,
                  timestamp: formatTime(),
                  messageId: res.message_id,
                  citations: res.citations?.length ? res.citations : undefined,
                  personMatch: res.person_match ?? undefined,
                }
              : m
          )
        );
      } else {
        if (res.requiresAuth) {
          CustomAlert.alert('انتهت الجلسة', res.message, [
            { text: 'حسناً', onPress: () => void lock() },
          ]);
        }
        setMessages((prev) =>
          prev.map((m) =>
            m.id === aiMsgId
              ? {
                  ...m,
                  text: res.message,
                  timestamp: formatTime(),
                  isError: true,
                }
              : m
          )
        );
      }

      setIsTyping(false);
      setThinkingStatus(null);
      scrollToEnd();
    },
    [isTyping, personId, lock, scrollToEnd]
  );

  // الموجه العام للإرسال
  const handleSend = useCallback(() => {
    if (selectedImage) {
      void handleSendPhoto(selectedImage, input);
    } else {
      void handleSendText();
    }
  }, [selectedImage, input, handleSendPhoto, handleSendText]);

  // إرسال رسالة صوتية مسجلة
  const handleSendAudio = useCallback(
    async (audioUri: string) => {
      if (isTyping) return;

      const tempId = `user-voice-${Date.now()}`;
      const userMsg: Message = {
        id: tempId,
        sender: 'user',
        text: 'رسالة صوتية',
        timestamp: formatTime(),
        isVoice: true,
        audioUri,
      };

      const aiMsgId = `ai-${Date.now()}`;
      const tempAiMsg: Message = {
        id: aiMsgId,
        sender: 'ai',
        text: '',
        timestamp: formatTime(),
      };

      setMessages((prev) => [...prev, userMsg, tempAiMsg]);
      setIsTyping(true);
      setThinkingStatus('جاري تفريغ الصوت وتحليل السؤال...');
      scrollToEnd();

      console.log(`\n🎙️ [رفع رسالة صوتية للمساعد]: مسار الملف = ${audioUri}`);
      const res = await aiVoiceChat(audioUri, conversationIdRef.current, personId);

      if (res.ok) {
        if (res.conversation_id) {
          conversationIdRef.current = res.conversation_id;
        }

        setMessages((prev) =>
          prev.map((m) => {
            if (m.id === tempId) {
              return {
                ...m,
                text: res.transcription ? res.transcription : 'رسالة صوتية',
                audioUri,
              };
            }
            if (m.id === aiMsgId) {
              return {
                ...m,
                text: res.answer,
                timestamp: formatTime(),
                messageId: res.message_id,
                citations: res.citations?.length ? res.citations : undefined,
              };
            }
            return m;
          })
        );
      } else {
        if (res.requiresAuth) {
          CustomAlert.alert('انتهت الجلسة', res.message, [
            { text: 'حسناً', onPress: () => void lock() },
          ]);
        }
        setMessages((prev) =>
          prev.map((m) =>
            m.id === aiMsgId
              ? {
                  ...m,
                  text: res.message,
                  timestamp: formatTime(),
                  isError: true,
                }
              : m
          )
        );
      }

      setIsTyping(false);
      setThinkingStatus(null);
      scrollToEnd();
    },
    [isTyping, personId, lock, scrollToEnd]
  );

  // فتح منتقي المرفقات (كاميرا / معرض)
  const handlePickAttachment = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    const openCamera = async () => {
      const perm = await ImagePicker.requestCameraPermissionsAsync();
      if (!perm.granted) {
        CustomAlert.alert('إذن الكاميرا مطلوب', 'يرجى السماح للتطبيق بالوصول للكاميرا لالتقاط صورة.');
        return;
      }
      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        quality: 0.8,
      });
      if (!result.canceled && result.assets?.[0]?.uri) {
        setSelectedImage(result.assets[0].uri);
      }
    };

    const openGallery = async () => {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        CustomAlert.alert('إذن المعرض مطلوب', 'يرجى السماح للتطبيق بالوصول لمعرض الصور.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        quality: 0.8,
      });
      if (!result.canceled && result.assets?.[0]?.uri) {
        setSelectedImage(result.assets[0].uri);
      }
    };

    if (Platform.OS === 'ios') {
      ActionSheetIOS.showActionSheetWithOptions(
        {
          options: ['إلغاء', 'التقاط صورة بالكاميرا 📷', 'اختيار من معرض الصور 🖼️'],
          cancelButtonIndex: 0,
          title: 'إرفاق صورة للتعرف على الوجه',
          message: 'يتم فحص ملامح الوجه ومطابقتها مع شجرة عائلة السريّع عبر AWS Rekognition',
        },
        (btnIdx) => {
          if (btnIdx === 1) void openCamera();
          else if (btnIdx === 2) void openGallery();
        }
      );
    } else {
      CustomAlert.alert(
        'إرفاق صورة للتعرف على الوجه',
        'اختر وسيلة إرفاق الصورة للبحث البيومتري في شجرة العائلة:',
        [
          { text: 'إلغاء', style: 'cancel' },
          { text: '📷 الكاميرا', onPress: () => void openCamera() },
          { text: '🖼️ المعرض', onPress: () => void openGallery() },
        ]
      );
    }
  }, []);

  const handleCopyMessage = useCallback(async (msgId: string, text: string) => {
    try {
      await Clipboard.setStringAsync(text);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setCopiedMessageId(msgId);
      setTimeout(() => {
        setCopiedMessageId((prev) => (prev === msgId ? null : prev));
      }, 2500);
    } catch {
      CustomAlert.alert('خطأ', 'تعذر نسخ النص.');
    }
  }, []);

  // مشاركة المقطع الصوتي الحقيقي (حل جذري شامل)
  const handleShareAudio = useCallback(async (msg: Message) => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      setSharingAudioId(msg.id);

      // 1. إذا كان تسجيلاً صوتياً محلياً للمستخدم
      if (msg.isVoice && msg.audioUri) {
        const canShare = await Sharing.isAvailableAsync();
        if (canShare) {
          await Sharing.shareAsync(msg.audioUri, {
            mimeType: 'audio/m4a',
            dialogTitle: 'مشاركة الرسالة الصوتية',
          });
          return;
        }
      }

      // 2. إذا كان رد الذكاء الاصطناعي -> توليد أو جلب ملف الصوت
      let targetAudioUrl = msg.audioUri;
      if (!targetAudioUrl) {
        const ttsRes = await aiGetTts(msg.messageId, msg.text);
        if (ttsRes?.ok && ttsRes.audio_url) {
          targetAudioUrl = ttsRes.audio_url;
        }
      }

      if (!targetAudioUrl) {
        CustomAlert.alert('تنبيه', 'تعذر تجهيز الملف الصوتي للمشاركة حالياً.');
        return;
      }

      // التأكد من أن الرابط كامل وليس مساراً نسبياً
      const fullUrl = targetAudioUrl.startsWith('http')
        ? targetAudioUrl
        : `${WEB_BASE_URL.replace(/\/$/, '')}/${targetAudioUrl.replace(/^\//, '')}`;

      const canShare = await Sharing.isAvailableAsync();
      if (!canShare) {
        await Share.share({
          message: `${msg.text}\n\n🔊 استمع للمقطع الصوتي: ${fullUrl}\n\n— عبر مساعد ذكاء السريّع`,
          title: 'إجابة من ذكاء السريّع',
        });
        return;
      }

      // تنزيل الملف الصوتي باستخدام الـ File API الحديث في Expo مع Fallback آمن للـ Legacy
      let localFileUri: string;
      const audioFileName = `ai_audio_${msg.id || Date.now()}.mp3`;

      try {
        const targetFile = new ExpoFile(Paths.cache, audioFileName);
        const downloadedFile = await ExpoFile.downloadFileAsync(fullUrl, targetFile, { idempotent: true });
        localFileUri = downloadedFile.uri;
      } catch {
        // Fallback في حال عدم توفر الـ native module الحديث في البيئة الحالية
        const legacyTargetUri = `${FileSystemLegacy.cacheDirectory || ''}${audioFileName}`;
        const legacyRes = await FileSystemLegacy.downloadAsync(fullUrl, legacyTargetUri);
        localFileUri = legacyRes.uri;
      }

      await Sharing.shareAsync(localFileUri, {
        mimeType: 'audio/mpeg',
        dialogTitle: 'مشاركة المقطع الصوتي',
        UTI: 'public.mp3',
      });
    } catch (err) {
      console.warn('[Share Audio Error]:', err);
      // بديل أخير بمشاركة الرابط والنص في حال تعذر التنزيل
      try {
        const fallbackUrl = msg.audioUri?.startsWith('http')
          ? msg.audioUri
          : `${WEB_BASE_URL.replace(/\/$/, '')}/${(msg.audioUri || '').replace(/^\//, '')}`;
        await Share.share({
          message: `${msg.text}${fallbackUrl ? `\n\n🔊 استمع للمقطع الصوتي: ${fallbackUrl}` : ''}\n\n— عبر مساعد ذكاء السريّع`,
          title: 'إجابة من ذكاء السريّع',
        });
      } catch {
        CustomAlert.alert('تعذر المشاركة', 'حدث خطأ أثناء تحميل ومشاركة الملف الصوتي.');
      }
    } finally {
      setSharingAudioId(null);
    }
  }, []);

  const handleClearChat = useCallback(() => {
    CustomAlert.alert(
      'بدء محادثة جديدة',
      'هل ترغب في مسح سجل المحادثة الحالي وبدء سؤال جديد كلياً ومستقل دون تداخل مع الأسئلة السابقة؟',
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'محادثة جديدة',
          style: 'destructive',
          onPress: () => {
            void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
            conversationIdRef.current = null;
            setSelectedImage(null);
            setMessages([makeWelcomeMessage()]);
            void appStorage.removeItem(STORAGE_CHAT_MESSAGES).catch(() => {});
            void appStorage.removeItem(STORAGE_CONVERSATION_ID).catch(() => {});
          },
        },
      ]
    );
  }, []);

  const renderMessageItem = ({ item }: { item: Message }) => {
    const isUser = item.sender === 'user';

    return (
      <View style={[styles.msgRow, isUser ? styles.msgRowUser : styles.msgRowAi]}>
        {/* صورة مساعد الذكاء الاصطناعي */}
        {!isUser && (
          <View style={[styles.aiAvatar, { backgroundColor: T.primary + '20', borderColor: T.primary + '40' }]}>
            <MaterialCommunityIcons name="robot-happy-outline" size={20} color={T.primary} />
          </View>
        )}

        <View
          style={[
            styles.msgBubble,
            isUser
              ? [styles.userBubble, { backgroundColor: T.primary }]
              : [
                  styles.aiBubble,
                  {
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    borderColor: item.isError ? '#ef4444' : isDark ? '#334155' : '#e2e8f0',
                  },
                ],
          ]}
        >
          {item.isError && (
            <View style={styles.errorBadge}>
              <Ionicons name="alert-circle-outline" size={14} color="#ef4444" />
              <Text style={styles.errorBadgeText}>تعذر الرد</Text>
            </View>
          )}

          {item.isVoice && (
            <View style={styles.voiceTag}>
              <Ionicons name="mic" size={12} color="#ffffff" />
              <Text style={styles.voiceTagText}>رسالة صوتية</Text>
            </View>
          )}

          {/* صورة مرفقة بالرسالة */}
          {!!item.imageUri && (
            <View style={styles.msgImageContainer}>
              <Image
                source={{ uri: item.imageUri }}
                style={styles.msgImage}
                contentFit="cover"
                transition={200}
              />
            </View>
          )}

          {/* محتوى الرد: مؤشر تفكير أثناء البث إن كان النص فارغاً، أو النص الفعلي المتدفق */}
          {!isUser && !item.text && !item.isError && isTyping && (
            <View style={styles.thinkingBubbleContent}>
              <ActivityIndicator size="small" color={T.primary} />
              <Text style={[styles.thinkingBubbleText, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                {thinkingStatus || 'جاري التفكير والبحث في شجرة العائلة...'}
              </Text>
            </View>
          )}

          {(!isUser ? !!item.text : true) && (
            <Text
              style={[
                styles.msgText,
                { color: isUser ? '#ffffff' : isDark ? '#f8fafc' : '#1e293b' },
              ]}
            >
              {item.text}
            </Text>
          )}

          {/* بطاقة الشخص المطابق بيومترياً عبر AWS Rekognition */}
          {!isUser && !!item.personMatch && (
            <TouchableOpacity
              style={[
                styles.personMatchCard,
                {
                  backgroundColor: isDark ? '#0f172a' : '#f0fdf4',
                  borderColor: isDark ? '#1e3a8a' : '#bbf7d0',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => item.personMatch?.id && router.push(`/(app)/person/${item.personMatch.id}`)}
            >
              <View style={styles.personMatchHeader}>
                <View style={styles.matchBadge}>
                  <Ionicons name="sparkles" size={12} color="#10b981" />
                  <Text style={styles.matchBadgeText}>
                    تطابق الوجه {item.personMatch.similarity}%
                  </Text>
                </View>
                {!!item.personMatch.kinship && (
                  <View style={styles.kinshipBadge}>
                    <Text style={styles.kinshipBadgeText}>{item.personMatch.kinship}</Text>
                  </View>
                )}
              </View>

              <View style={styles.personMatchBody}>
                {item.personMatch.avatar ? (
                  <Image
                    source={{ uri: item.personMatch.avatar }}
                    style={styles.personMatchAvatar}
                    contentFit="cover"
                  />
                ) : (
                  <View style={[styles.personMatchAvatarFallback, { backgroundColor: T.primary + '20' }]}>
                    <Ionicons name="person" size={20} color={T.primary} />
                  </View>
                )}
                <View style={styles.personMatchInfo}>
                  <Text style={[styles.personMatchName, { color: isDark ? '#f8fafc' : '#0f172a' }]}>
                    {item.personMatch.full_name}
                  </Text>
                  {!!item.personMatch.branch && (
                    <Text style={[styles.personMatchBranch, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                      الفرع: {item.personMatch.branch}
                    </Text>
                  )}
                </View>
                <Ionicons name="chevron-back" size={18} color={T.primary} />
              </View>

              <View style={styles.personMatchFooter}>
                <Text style={styles.personMatchActionText}>عرض ملف الفرد في شجرة العائلة ←</Text>
              </View>
            </TouchableOpacity>
          )}

          {/* الاستماع للتسجيل الصوتي الخاص بالمستخدم */}
          {isUser && !!item.audioUri && (
            <AudioMessagePlayer
              audioUri={item.audioUri}
              isDark={isDark}
              isUserMessage={true}
            />
          )}

          {/* مشغل الصوت لردود الذكاء الاصطناعي (عند الطلب) */}
          {!isUser && !item.isError && !item.id.startsWith('welcome') && (
            <AudioMessagePlayer
              messageId={item.messageId}
              text={item.text}
              isDark={isDark}
            />
          )}

          {/* أزرار المشاركة والنسخ لرسائل الذكاء الاصطناعي */}
          {!isUser && !item.isError && !item.id.startsWith('welcome') && (
            <View style={styles.msgActionsRow}>
              <TouchableOpacity
                style={[
                  styles.msgActionBtn,
                  { backgroundColor: isDark ? '#0f172a' : '#f8fafc', borderColor: isDark ? '#334155' : '#e2e8f0' },
                ]}
                onPress={() => handleCopyMessage(item.id, item.text)}
                activeOpacity={0.7}
              >
                <Ionicons
                  name={copiedMessageId === item.id ? 'checkmark-circle' : 'copy-outline'}
                  size={14}
                  color={copiedMessageId === item.id ? '#10b981' : isDark ? '#94a3b8' : '#64748b'}
                />
                <Text
                  style={[
                    styles.msgActionText,
                    { color: copiedMessageId === item.id ? '#10b981' : isDark ? '#cbd5e1' : '#64748b' },
                  ]}
                >
                  {copiedMessageId === item.id ? 'تم النسخ' : 'نسخ'}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[
                  styles.msgActionBtn,
                  { backgroundColor: isDark ? '#0f172a' : '#f8fafc', borderColor: isDark ? '#334155' : '#e2e8f0' },
                ]}
                onPress={() => handleShareAudio(item)}
                disabled={sharingAudioId === item.id}
                activeOpacity={0.7}
              >
                {sharingAudioId === item.id ? (
                  <ActivityIndicator size="small" color={T.primary} />
                ) : (
                  <>
                    <Ionicons name="share-social-outline" size={14} color={isDark ? '#94a3b8' : '#64748b'} />
                    <Text style={[styles.msgActionText, { color: isDark ? '#cbd5e1' : '#64748b' }]}>
                      مشاركة المقطع الصوتي
                    </Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
          )}

          {/* المراجع والمصادر */}
          {!!item.citations?.length && (
            <View style={styles.citationsWrap}>
              <Text style={[styles.citationsLabel, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                مصادر موثقة من الشجرة
              </Text>
              {item.citations.map((c, idx) => (
                <TouchableOpacity
                  key={c.key || `${c.type}-${c.id}-${idx}`}
                  style={[
                    styles.citationChip,
                    {
                      backgroundColor: isDark ? '#0f172a' : '#f8fafc',
                      borderColor: isDark ? '#334155' : '#e2e8f0',
                    },
                  ]}
                  activeOpacity={0.7}
                  onPress={() => openCitation(c)}
                >
                  <Ionicons
                    name={
                      c.type === 'person'
                        ? 'person-outline'
                        : c.type === 'story'
                        ? 'book-outline'
                        : 'document-text-outline'
                    }
                    size={14}
                    color={T.primary}
                  />
                  <Text
                    style={[styles.citationTitle, { color: isDark ? '#e2e8f0' : '#1e293b' }]}
                    numberOfLines={2}
                  >
                    {c.title}
                  </Text>
                  <Ionicons name="chevron-back" size={14} color={isDark ? '#94a3b8' : '#94a3b8'} />
                </TouchableOpacity>
              ))}
            </View>
          )}

          <Text
            style={[
              styles.msgTime,
              { color: isUser ? 'rgba(255,255,255,0.7)' : isDark ? '#94a3b8' : '#94a3b8' },
            ]}
          >
            {item.timestamp}
          </Text>
        </View>

        {/* صورة أو أيقونة المستخدم الحقيقي المسجل */}
        {isUser && (
          <View style={styles.userAvatarContainer}>
            {user?.avatar ? (
              <Image
                source={{ uri: user.avatar }}
                style={styles.userAvatarImg}
                contentFit="cover"
                transition={200}
              />
            ) : (
              <View style={[styles.userAvatarFallback, { backgroundColor: T.primary }]}>
                <Text style={styles.userAvatarInitial}>
                  {user?.first_name ? user.first_name.charAt(0) : 'أ'}
                </Text>
              </View>
            )}
          </View>
        )}
      </View>
    );
  };

  const showSuggestions = messages.length <= 1 && !isTyping;
  const hasTextOrImage = input.trim().length > 0 || Boolean(selectedImage);

  /* ── Coming Soon gate — لا تحذف الكود أسفله، فقط يتم تجاوزه ── */
  if (AI_COMING_SOON) {
    return (
      <ThemedView style={styles.root}>
        <HeaderBar title="ذكاء السريّع" />
        <ComingSoon
          title="ذكاء السريّع"
          subtitle="نعمل على تجربة مساعد ذكي فريدة لعائلتكم"
          iconName="sparkles"
        />
      </ThemedView>
    );
  }

  return (
    <ThemedView style={styles.root}>
      <AnimatedBubbles bubbleCount={7} minSize={30} maxSize={100} />
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={colors.background} translucent={false} />

      <View
        style={{ borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border }}
        onLayout={(e) => {
          const h = e.nativeEvent.layout.height;
          if (h > 0) setHeaderHeight(Math.round(h));
        }}
      >
        <HeaderBar
          title="ذكاء السريّع"
          subtitle="اسأل شجرة العائلة"
          avatarCustomElement={
            <MaterialCommunityIcons name="robot-happy-outline" size={22} color={T.primary} />
          }
        >
          <View style={styles.headerActions}>
            <TouchableOpacity
              style={[
                styles.newChatBtn,
                { backgroundColor: T.primary + '15', borderColor: T.primary + '35' },
              ]}
              onPress={handleClearChat}
              activeOpacity={0.7}
            >
              <Ionicons name="sparkles" size={13} color={T.primary} />
              <Text style={[styles.newChatBtnText, { color: T.primary }]}>محادثة جديدة</Text>
            </TouchableOpacity>

            <View style={[styles.statusBadge, { backgroundColor: '#10b98115', borderColor: '#10b98140' }]}>
              <View style={styles.statusDot} />
              <Text style={styles.statusText}>متصل</Text>
            </View>
          </View>
        </HeaderBar>
      </View>

      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={headerHeight}
      >
        <FlatList
          ref={flatListRef}
          data={messages}
          keyExtractor={(item) => item.id}
          renderItem={renderMessageItem}
          contentContainerStyle={styles.chatListContent}
          style={styles.chatList}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          onContentSizeChange={scrollToEnd}
          ListHeaderComponent={
            showSuggestions ? (
              <View style={styles.welcomeHero}>
                <LinearGradient
                  colors={isDark ? ['#1e1b4b', '#0f172a'] : ['#f0fdf4', '#e0f2fe']}
                  style={[styles.heroCard, { borderColor: isDark ? '#312e81' : '#bbf7d0' }]}
                >
                  <View style={styles.heroHeader}>
                    <View style={styles.sparkleIconWrap}>
                      <FontAwesome5 name="robot" size={26} color={T.primary} />
                    </View>
                    <View style={styles.heroTextWrap}>
                      <Text style={[styles.heroTitle, { color: isDark ? '#f8fafc' : '#0f172a' }]}>
                        {user?.first_name ? `مرحباً بك يا ${user.first_name}` : 'مساعد السريّع الذكي'}
                      </Text>
                      <Text style={[styles.heroDesc, { color: isDark ? '#cbd5e1' : '#475569' }]}>
                        اطرح أسئلة أو ارفع صور أفراد العائلة للتعرف عليهم مباشرة من شجرة العائلة عبر AWS Rekognition.
                      </Text>
                    </View>
                  </View>

                  <Text style={[styles.quickPromptHeader, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                    أسئلة مقترحة للبدء السريع:
                  </Text>
                  {suggestionsLoading ? (
                    <ActivityIndicator size="small" color={T.primary} style={{ marginVertical: 8 }} />
                  ) : (
                    <View style={styles.suggestionsGrid}>
                      {suggestions.map((prompt, idx) => (
                        <TouchableOpacity
                          key={`${idx}-${prompt.slice(0, 24)}`}
                          style={[
                            styles.suggestionChip,
                            {
                              backgroundColor: isDark ? '#1e293b' : '#ffffff',
                              borderColor: isDark ? '#334155' : '#cbd5e1',
                            },
                          ]}
                          activeOpacity={0.7}
                          disabled={isTyping}
                          onPress={() => void handleSendText(prompt)}
                        >
                          <Text
                            style={[styles.suggestionText, { color: isDark ? '#e2e8f0' : '#1e293b' }]}
                            numberOfLines={2}
                          >
                            {prompt}
                          </Text>
                          <Ionicons
                            name="arrow-back"
                            size={14}
                            color={T.primary}
                            style={{ transform: [{ rotate: '180deg' }] }}
                          />
                        </TouchableOpacity>
                      ))}
                    </View>
                  )}
                </LinearGradient>
              </View>
            ) : null
          }
        />

        {/* شريط الإدخال المتطور بتجربة WhatsApp بالكامل */}
        <View
          style={[
            styles.inputBarContainer,
            {
              backgroundColor: colors.background,
              borderTopColor: colors.border,
            },
          ]}
        >
          {/* معاينة الصورة المختارة قبل الإرسال */}
          {!!selectedImage && !isRecordingAudio && (
            <View
              style={[
                styles.imagePreviewBar,
                {
                  backgroundColor: isDark ? '#1e293b' : '#f8fafc',
                  borderColor: isDark ? '#334155' : '#e2e8f0',
                },
              ]}
            >
              <Image source={{ uri: selectedImage }} style={styles.imagePreviewThumb} contentFit="cover" />
              <View style={styles.imagePreviewInfo}>
                <Text style={[styles.imagePreviewTitle, { color: isDark ? '#f8fafc' : '#0f172a' }]}>
                  صورة مرفقة للتعرف على الوجه
                </Text>
                <Text style={[styles.imagePreviewSub, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                  سيتم فحص ملامح الوجه في شجرة العائلة عبر AWS Rekognition
                </Text>
              </View>
              <TouchableOpacity
                style={styles.imagePreviewRemoveBtn}
                onPress={() => setSelectedImage(null)}
                activeOpacity={0.7}
              >
                <Ionicons name="close-circle" size={22} color="#ef4444" />
              </TouchableOpacity>
            </View>
          )}

          {/* Voice Recorder Bar (تظهر بالحجم الكامل أثناء التسجيل) */}
          <VoiceRecorderBar
            ref={voiceRecorderRef}
            onSendAudio={handleSendAudio}
            onRecordingChange={setIsRecordingAudio}
            disabled={isTyping}
            isDark={isDark}
            hideStandaloneButton={true}
          />

          {/* حقل الإدخال وزر الإجراء الموحد على نمط WhatsApp */}
          {!isRecordingAudio && (
            <View style={styles.inputRow}>
              {/* صندوق النص وزر المرفقات */}
              <View
                style={[
                  styles.inputWrapper,
                  {
                    backgroundColor: isDark ? '#1e293b' : '#f1f5f9',
                    borderColor: colors.border,
                  },
                ]}
              >
                {/* زر المشبك للمرفقات (كاميرا / معرض) */}
                <TouchableOpacity
                  style={styles.attachmentBtn}
                  onPress={handlePickAttachment}
                  disabled={isTyping}
                  activeOpacity={0.7}
                >
                  <Ionicons name="attach" size={22} color={isDark ? '#94a3b8' : '#64748b'} />
                </TouchableOpacity>

                <TextInput
                  style={[styles.textInput, { color: colors.text }]}
                  placeholder={selectedImage ? 'أضف سؤالاً حول الصورة (اختياري)...' : 'اسأل عن شجرة العائلة أو أرفق صورة...'}
                  placeholderTextColor={isDark ? '#64748b' : '#94a3b8'}
                  value={input}
                  onChangeText={setInput}
                  multiline
                  maxLength={2000}
                  editable={!isTyping}
                  textAlignVertical="center"
                  blurOnSubmit={false}
                  returnKeyType="default"
                />
              </View>

              {/* زر الإجراء الديناميكي الموحد (مايك عند الإفراغ / إرسال عند الكتابة أو إرفاق صورة) */}
              <TouchableOpacity
                style={[
                  styles.unifiedActionBtn,
                  {
                    backgroundColor: '#25D366', // لون واتساب الأخضر المميز
                  },
                ]}
                disabled={isTyping}
                onPress={() => {
                  if (hasTextOrImage) {
                    handleSend();
                  } else {
                    Keyboard.dismiss();
                    void voiceRecorderRef.current?.startRecording();
                  }
                }}
                activeOpacity={0.8}
              >
                {isTyping ? (
                  <ActivityIndicator size="small" color="#ffffff" />
                ) : hasTextOrImage ? (
                  <Ionicons name="send" size={18} color="#ffffff" style={{ transform: [{ scaleX: -1 }] }} />
                ) : (
                  <Ionicons name="mic" size={22} color="#ffffff" />
                )}
              </TouchableOpacity>
            </View>
          )}
        </View>
      </KeyboardAvoidingView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  keyboardView: { flex: 1 },
  chatList: { flex: 1 },

  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    borderWidth: 1,
    gap: 6,
  },
  statusDot: {
    width: 7,
    height: 7,
    borderRadius: 3.5,
    backgroundColor: '#10b981',
  },
  statusText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#10b981',
  },
  newChatBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 16,
    borderWidth: 1,
  },
  newChatBtnText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },

  chatListContent: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 24,
  },

  welcomeHero: {
    marginBottom: 20,
  },
  heroCard: {
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
  },
  heroHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 14,
  },
  sparkleIconWrap: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: T.primary + '20',
    justifyContent: 'center',
    alignItems: 'center',
  },
  heroTextWrap: {
    flex: 1,
  },
  heroTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 2,
  },
  heroDesc: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 18,
  },
  quickPromptHeader: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 10,
    marginTop: 4,
  },
  suggestionsGrid: {
    gap: 8,
  },
  suggestionChip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
  },
  suggestionText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    flex: 1,
  },

  msgRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    marginVertical: 6,
    gap: 8,
  },
  msgRowUser: {
    justifyContent: 'flex-end',
  },
  msgRowAi: {
    justifyContent: 'flex-start',
  },

  aiAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 4,
  },
  userAvatarContainer: {
    marginBottom: 4,
  },
  userAvatarImg: {
    width: 32,
    height: 32,
    borderRadius: 16,
  },
  userAvatarFallback: {
    width: 32,
    height: 32,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  userAvatarInitial: {
    color: '#ffffff',
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },

  msgBubble: {
    maxWidth: '82%',
    borderRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  userBubble: {
    borderBottomRightRadius: 4,
  },
  aiBubble: {
    borderBottomLeftRadius: 4,
    borderWidth: 1,
  },
  msgText: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 21,
  },
  msgTime: {
    fontSize: 9.5,
    fontFamily: 'Tajawal-Regular',
    alignSelf: 'flex-end',
    marginTop: 4,
  },

  msgImageContainer: {
    marginBottom: 8,
    borderRadius: 12,
    overflow: 'hidden',
  },
  msgImage: {
    width: 220,
    height: 160,
    borderRadius: 12,
  },

  // بطاقة التعرف على الوجه
  personMatchCard: {
    borderRadius: 14,
    padding: 12,
    marginTop: 10,
    borderWidth: 1,
    gap: 8,
  },
  personMatchHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 6,
  },
  matchBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#10b98120',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  matchBadgeText: {
    color: '#10b981',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  kinshipBadge: {
    backgroundColor: T.primary + '20',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  kinshipBadgeText: {
    color: T.primary,
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  personMatchBody: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  personMatchAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
  },
  personMatchAvatarFallback: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  personMatchInfo: {
    flex: 1,
  },
  personMatchName: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  personMatchBranch: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  personMatchFooter: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(148, 163, 184, 0.25)',
    paddingTop: 6,
    alignItems: 'flex-start',
  },
  personMatchActionText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Bold',
    color: T.primary,
  },

  citationsWrap: {
    marginTop: 10,
    gap: 6,
  },
  citationsLabel: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 2,
  },
  citationChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
  },
  citationTitle: {
    flex: 1,
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    lineHeight: 18,
  },

  errorBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ef444415',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    marginBottom: 8,
    alignSelf: 'flex-start',
  },
  errorBadgeText: {
    color: '#ef4444',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },

  typingWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    alignSelf: 'flex-start',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 16,
    marginLeft: 40,
    marginTop: 4,
  },
  typingText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },

  inputBarContainer: {
    paddingHorizontal: 10,
    paddingTop: 6,
    paddingBottom: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    minHeight: 58,
    justifyContent: 'center',
  },
  imagePreviewBar: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 8,
    borderRadius: 14,
    marginBottom: 8,
    borderWidth: 1,
    gap: 10,
  },
  imagePreviewThumb: {
    width: 44,
    height: 44,
    borderRadius: 8,
  },
  imagePreviewInfo: {
    flex: 1,
  },
  imagePreviewTitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  imagePreviewSub: {
    fontSize: 10,
    fontFamily: 'Tajawal-Regular',
    marginTop: 1,
  },
  imagePreviewRemoveBtn: {
    padding: 4,
  },

  inputRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 8,
  },
  inputWrapper: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-end',
    borderRadius: 24,
    borderWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: 8,
    paddingVertical: 4,
    minHeight: 46,
  },
  attachmentBtn: {
    width: 36,
    height: 38,
    justifyContent: 'center',
    alignItems: 'center',
  },
  textInput: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 14.5,
    lineHeight: 20,
    paddingVertical: Platform.OS === 'ios' ? 9 : 7,
    paddingHorizontal: 6,
    maxHeight: 120,
    writingDirection: 'rtl',
  },
  unifiedActionBtn: {
    width: 46,
    height: 46,
    borderRadius: 23,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#25D366',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 4,
  },

  voiceTag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(255,255,255,0.25)',
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 8,
    alignSelf: 'flex-start',
    marginBottom: 6,
  },
  voiceTagText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#ffffff',
  },
  msgActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(148, 163, 184, 0.2)',
  },
  msgActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 14,
    borderWidth: 1,
  },
  msgActionText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  thinkingBubbleContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 6,
    paddingHorizontal: 4,
  },
  thinkingBubbleText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
});
