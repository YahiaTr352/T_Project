import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Colors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import {
  sendContactMessageApi,
  type ContactMediaKind,
  type ContactMessageType,
} from '@/lib/api/contactMessageApi';
import { Ionicons } from '@expo/vector-icons';
import {
  RecordingPresets,
  requestRecordingPermissionsAsync,
  setAudioModeAsync,
  useAudioPlayer,
  useAudioPlayerStatus,
  useAudioRecorder,
} from 'expo-audio';
import * as Haptics from 'expo-haptics';
import { Image as RNImage } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Keyboard,
  KeyboardAvoidingView,
  Linking,
  Platform,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';

const FAMILY_WHATSAPP_NUMBER = '966539895800';

interface AttachedMedia {
  uri: string;
  kind: ContactMediaKind;
  name: string;
  mime?: string;
  durationSeconds?: number;
}

const MESSAGE_TYPES: {
  key: ContactMessageType;
  label: string;
  desc: string;
  icon: keyof typeof Ionicons.glyphMap;
  color: string;
}[] = [
  {
    key: 'problem',
    label: 'مشكلة أو عطل تقني',
    desc: 'واجهت خللاً بالتطبيق أو صعوبة بالدخول',
    icon: 'warning-outline',
    color: '#EF4444',
  },
  {
    key: 'inquiry',
    label: 'استفسار عام',
    desc: 'سؤال حول الأنشطة، الشجرة أو الفعاليات',
    icon: 'help-circle-outline',
    color: '#3B82F6',
  },
  {
    key: 'suggestion',
    label: 'اقتراح وتطوير',
    desc: 'فكرة مميزة ترغب في إضافتها للمنصة',
    icon: 'bulb-outline',
    color: '#10B981',
  },
  {
    key: 'complaint',
    label: 'شكوى أو ملاحظة',
    desc: 'ملاحظة خاصة تود إيصالها لمجلس الإدارة',
    icon: 'chatbox-ellipses-outline',
    color: '#F59E0B',
  },
];

export default function ContactScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const { user, hasIdentity } = useAuth();

  // High contrast color tokens
  const titleColor = isDark ? '#FFFFFF' : '#111827';
  const subtextColor = isDark ? '#E2E8F0' : '#334155';
  const placeholderColor = isDark ? '#94A3B8' : '#64748B';
  const cardBg = isDark ? '#1E293B' : '#FFFFFF';
  const cardBorder = isDark ? '#334155' : '#E2E8F0';
  const inputBg = isDark ? '#0F172A' : '#FFFFFF';
  const inputBorder = isDark ? '#475569' : '#CBD5E1';

  // Form State
  const [selectedType] = useState<ContactMessageType>('suggestion');
  const [guestName, setGuestName] = useState('');
  const [guestPhone, setGuestPhone] = useState('');
  const [messageText, setMessageText] = useState('');
  const [attachedMedia, setAttachedMedia] = useState<AttachedMedia | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Keyboard Tracking & ScrollView Ref for flexible scrolling & elderly accessibility
  const [keyboardHeight, setKeyboardHeight] = useState(0);
  const scrollViewRef = useRef<ScrollView>(null);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSub = Keyboard.addListener(showEvent, (e) => {
      setKeyboardHeight(e.endCoordinates.height);
    });
    const hideSub = Keyboard.addListener(hideEvent, () => {
      setKeyboardHeight(0);
    });

    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  // Audio Recording State
  const [isRecording, setIsRecording] = useState(false);
  const [recordSeconds, setRecordSeconds] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [pulseAnim] = useState(() => new Animated.Value(1));

  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const previewPlayer = useAudioPlayer(attachedMedia?.kind === 'voice' ? attachedMedia.uri : null);
  const previewStatus = useAudioPlayerStatus(previewPlayer);

  // Recording pulse animation
  useEffect(() => {
    let anim: Animated.CompositeAnimation | null = null;
    if (isRecording) {
      anim = Animated.loop(
        Animated.sequence([
          Animated.timing(pulseAnim, {
            toValue: 1.3,
            duration: 600,
            useNativeDriver: true,
          }),
          Animated.timing(pulseAnim, {
            toValue: 1,
            duration: 600,
            useNativeDriver: true,
          }),
        ])
      );
      anim.start();
    } else {
      pulseAnim.setValue(1);
    }
    return () => {
      if (anim) anim.stop();
    };
  }, [isRecording, pulseAnim]);

  // Clean timer on unmount
  useEffect(() => {
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, []);

  const formatTimer = (secs: number) => {
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  };

  // Start voice recording
  const handleStartRecording = useCallback(async () => {
    try {
      Keyboard.dismiss();
      const perm = await requestRecordingPermissionsAsync();
      if (!perm.granted) {
        CustomAlert.alert(
          'إذن الميكروفون مطلوب',
          'يرجى تفعيل صلاحية الميكروفون لتسجيل رسالتك الصوتية وإرسالها للإدارة.'
        );
        return;
      }

      await setAudioModeAsync({
        allowsRecording: true,
        playsInSilentMode: true,
      });

      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);

      await recorder.prepareToRecordAsync();
      recorder.record();

      setIsRecording(true);
      setRecordSeconds(0);

      timerRef.current = setInterval(() => {
        setRecordSeconds((prev) => prev + 1);
      }, 1000);
    } catch {
      CustomAlert.alert('خطأ في التسجيل', 'تعذر بدء التسجيل الصوتي، يرجى المحاولة مرة أخرى.');
    }
  }, [recorder]);

  // Cancel voice recording
  const handleCancelRecording = useCallback(async () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    if (timerRef.current) clearInterval(timerRef.current);
    setIsRecording(false);
    setRecordSeconds(0);
    try {
      await recorder.stop();
    } catch {
      // Ignored
    }
  }, [recorder]);

  // Finish voice recording
  const handleFinishRecording = useCallback(async () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    if (timerRef.current) clearInterval(timerRef.current);
    const duration = recordSeconds;
    setIsRecording(false);
    setRecordSeconds(0);

    try {
      await recorder.stop();
      const uri = recorder.uri;
      if (uri) {
        setAttachedMedia({
          uri,
          kind: 'voice',
          name: `voice_note_${Date.now()}.m4a`,
          mime: 'audio/m4a',
          durationSeconds: duration,
        });
      }
    } catch {
      CustomAlert.alert('خطأ', 'تعذر حفظ التسجيل الصوتي.');
    }
  }, [recordSeconds, recorder]);

  // Pick Image
  const handlePickImage = useCallback(async () => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

      const pickFromCamera = async () => {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن الكاميرا مطلوب', 'يرجى السماح بالوصول للكاميرا لالتقاط صورة توضيحية.');
          return;
        }
        const res = await ImagePicker.launchCameraAsync({
          mediaTypes: ['images'],
          quality: 0.8,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          setAttachedMedia({
            uri: res.assets[0].uri,
            kind: 'image',
            name: res.assets[0].fileName || `photo_${Date.now()}.jpg`,
            mime: res.assets[0].mimeType || 'image/jpeg',
          });
        }
      };

      const pickFromGallery = async () => {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن المعرض مطلوب', 'يرجى السماح بالوصول إلى المعرض لاختيار صورة.');
          return;
        }
        const res = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['images'],
          quality: 0.8,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          setAttachedMedia({
            uri: res.assets[0].uri,
            kind: 'image',
            name: res.assets[0].fileName || `photo_${Date.now()}.jpg`,
            mime: res.assets[0].mimeType || 'image/jpeg',
          });
        }
      };

      CustomAlert.alert('إرفاق صورة توضيحية', 'اختر مصدر الصورة:', [
        { text: 'إلغاء', style: 'cancel' },
        { text: '📷 الكاميرا', onPress: () => void pickFromCamera() },
        { text: '🖼️ الاستوديو / المعرض', onPress: () => void pickFromGallery() },
      ]);
    } catch {
      CustomAlert.alert('تنبيه', 'تعذر اختيار الصورة.');
    }
  }, []);

  // Pick Video
  const handlePickVideo = useCallback(async () => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

      const pickVideoCamera = async () => {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن الكاميرا مطلوب', 'يرجى السماح بالوصول للكاميرا لتصوير فيديو.');
          return;
        }
        const res = await ImagePicker.launchCameraAsync({
          mediaTypes: ['videos'],
          videoMaxDuration: 60,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          setAttachedMedia({
            uri: res.assets[0].uri,
            kind: 'video',
            name: res.assets[0].fileName || `video_${Date.now()}.mp4`,
            mime: res.assets[0].mimeType || 'video/mp4',
          });
        }
      };

      const pickVideoGallery = async () => {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن المعرض مطلوب', 'يرجى السماح بالوصول إلى المعرض لاختيار فيديو.');
          return;
        }
        const res = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['videos'],
          videoMaxDuration: 60,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          setAttachedMedia({
            uri: res.assets[0].uri,
            kind: 'video',
            name: res.assets[0].fileName || `video_${Date.now()}.mp4`,
            mime: res.assets[0].mimeType || 'video/mp4',
          });
        }
      };

      CustomAlert.alert('إرفاق مقطع فيديو', 'اختر مصدر الفيديو (بحد أقصى 60 ثانية):', [
        { text: 'إلغاء', style: 'cancel' },
        { text: '🎥 تصوير فيديو', onPress: () => void pickVideoCamera() },
        { text: '📁 من الاستوديو', onPress: () => void pickVideoGallery() },
      ]);
    } catch {
      CustomAlert.alert('تنبيه', 'تعذر اختيار الفيديو.');
    }
  }, []);

  const handleRemoveMedia = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setAttachedMedia(null);
  }, []);

  const senderName = hasIdentity && user?.full_name ? user.full_name : guestName.trim();
  const senderPhone = hasIdentity && user?.phone ? user.phone : guestPhone.trim();

  const validateForm = (isWhatsApp: boolean): boolean => {
    if (!hasIdentity) {
      if (!guestName.trim()) {
        CustomAlert.alert('الاسم مطلوب', 'يرجى إدخال اسمك الكريم لمتابعة الطلب.');
        return false;
      }
      if (!guestPhone.trim() || guestPhone.trim().length < 8) {
        CustomAlert.alert('رقم الجوال مطلوب', 'يرجى إدخال رقم جوال صحيح للتواصل معك.');
        return false;
      }
    }

    if (!isWhatsApp && !messageText.trim() && !attachedMedia) {
      CustomAlert.alert('الرسالة فارغة', 'يرجى كتابة تفاصيل رسالتك أو إرفاق صورة أو تسجيل صوتي.');
      return false;
    }

    return true;
  };

  // 1) Send via WhatsApp
  const handleSendViaWhatsApp = useCallback(async () => {
    Keyboard.dismiss();
    if (!validateForm(true)) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);

    const lines = [
      `السلام عليكم ورحمة الله وبركاته،`,
      `*تواصل عبر تطبيق عائلة السريّع*`,
      `─────────────────`,
      `*الموضوع:* مقترح / فكرة لتطوير المنصة`,
      `*الاسم:* ${senderName || 'غير محدد'}`,
      `*الجوال:* ${senderPhone || 'غير محدد'}`,
      hasIdentity ? `*حالة التوثيق:* عضو موثق بالبصمة ✅` : `*حالة التوثيق:* ضيف (رمز سريع) 👤`,
    ];

    if (messageText.trim()) {
      lines.push(`─────────────────`);
      lines.push(`*نص الاقتراح / الملاحظة:*`);
      lines.push(messageText.trim());
    }

    if (attachedMedia) {
      lines.push(`─────────────────`);
      lines.push(
        `*مرفق بالتطبيق:* ${
          attachedMedia.kind === 'voice'
            ? 'تسجيل صوتي'
            : attachedMedia.kind === 'video'
            ? 'مقطع فيديو'
            : 'صورة توضيحية'
        }`
      );
      lines.push(`(يرجى إرسال الملف المرفق مباشرة في هذه المحادثة)`);
    }

    lines.push(`─────────────────`);
    lines.push(`تم التوليد من منصة عائلة السريّع.`);

    const encoded = encodeURIComponent(lines.join('\n'));
    const url = `https://wa.me/${FAMILY_WHATSAPP_NUMBER}?text=${encoded}`;

    try {
      const supported = await Linking.canOpenURL(url);
      if (supported) {
        await Linking.openURL(url);
      } else {
        await Linking.openURL(
          `https://api.whatsapp.com/send?phone=${FAMILY_WHATSAPP_NUMBER}&text=${encoded}`
        );
      }
    } catch {
      CustomAlert.alert('خطأ', 'تعذر فتح تطبيق واتساب. يرجى التأكد من تثبيته على جهازك.');
    }
  }, [hasIdentity, senderName, senderPhone, messageText, attachedMedia, selectedType]);

  // 2) Send directly to Server API
  const handleSubmitToServer = useCallback(async () => {
    Keyboard.dismiss();
    if (!validateForm(false)) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);
    setIsSubmitting(true);

    try {
      const res = await sendContactMessageApi({
        name: senderName,
        phone: senderPhone,
        type: selectedType,
        message: messageText.trim() || undefined,
        mediaUri: attachedMedia?.uri,
        mediaKind: attachedMedia?.kind,
        mediaName: attachedMedia?.name,
        mediaMime: attachedMedia?.mime,
      });

      if (res?.success) {
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        CustomAlert.alert(
          'تم الاستلام بنجاح 🎉',
          res?.message ||
            'تم تسجيل مقترحك بنجاح لدى إدارة العائلة، وسيتم مراجعته والاهتمام به في أقرب فرصة بإذن الله.',
          [
            {
              text: 'حسناً',
              onPress: () => {
                router.back();
              },
            },
          ]
        );
        setMessageText('');
        setAttachedMedia(null);
      } else {
        const errorMsg = res?.message || 'حدث خطأ أثناء إرسال الرسالة، يرجى المحاولة مرة أخرى.';
        CustomAlert.alert('تعذر الإرسال', errorMsg);
      }
    } catch (err: any) {
      CustomAlert.alert('خطأ في الاتصال', err?.message || 'تعذر إرسال الرسالة، يرجى التحقق من اتصالك بالإنترنت.');
    } finally {
      setIsSubmitting(false);
    }
  }, [hasIdentity, senderName, senderPhone, messageText, attachedMedia, selectedType, router]);

  return (
    <ThemedView style={[styles.container, { backgroundColor: colors.background }]}>
      {/* Top Header */}
      <View style={{ paddingTop: Platform.OS === 'ios' ? 44 : 12, paddingHorizontal: 16 }}>
        <HeaderBar
          title="صندوق الاقتراحات والملاحظات"
          subtitle="تواصل مباشر مع إدارة ومجلس العائلة"
          showBack
          onBackPress={() => router.back()}
        />
      </View>

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 88 : 0}
      >
        <ScrollView
          ref={scrollViewRef}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={[
            styles.scrollContent,
            {
              paddingBottom: keyboardHeight > 0 ? keyboardHeight + 90 : 60,
            },
          ]}
          keyboardShouldPersistTaps="always"
        >
          {/* Identity Info Banner */}
          {hasIdentity && user ? (
            <View
              style={[
                styles.identityCard,
                {
                  backgroundColor: cardBg,
                  borderColor: isDark ? '#38BDF850' : '#86EFAC',
                },
              ]}
            >
              <View
                style={[
                  styles.identityAvatar,
                  { backgroundColor: isDark ? '#0284C725' : '#DCFCE7' },
                ]}
              >
                <Ionicons
                  name="shield-checkmark"
                  size={20}
                  color={isDark ? '#38BDF8' : '#16A34A'}
                />
              </View>
              <View style={styles.identityTextWrap}>
                <ThemedText style={[styles.identityName, { color: titleColor }]}>
                  أهلاً بك، {user.full_name}
                </ThemedText>
                <ThemedText style={[styles.identityDesc, { color: subtextColor }]}>
                  سيتم توثيق مشاركتك واقتراحك برقمك المسجل ({user.phone || 'مسجل'}) لدى إدارة العائلة.
                </ThemedText>
              </View>
            </View>
          ) : (
            <View
              style={[
                styles.guestCard,
                {
                  backgroundColor: cardBg,
                  borderColor: isDark ? '#F59E0B60' : '#FCD34D',
                },
              ]}
            >
              <View style={styles.guestNoticeHeader}>
                <Ionicons name="information-circle" size={20} color="#F59E0B" />
                <ThemedText style={[styles.guestNoticeTitle, { color: isDark ? '#FBBF24' : '#B45309' }]}>
                  الدخول كزائر
                </ThemedText>
              </View>
              <ThemedText style={[styles.guestNoticeDesc, { color: subtextColor }]}>
                يرجى إدخال اسمك ورقم جوالك لنتمكن من متابعة رسالتك والتواصل معك بسهولة.
              </ThemedText>

              <View style={styles.guestInputsRow}>
                <View style={styles.guestInputCol}>
                  <ThemedText style={[styles.fieldLabel, { color: titleColor }]}>
                    الاسم الكامل <ThemedText style={{ color: '#EF4444' }}>*</ThemedText>
                  </ThemedText>
                  <TextInput
                    value={guestName}
                    onChangeText={setGuestName}
                    placeholder="مثال: صالح السريّع"
                    placeholderTextColor={placeholderColor}
                    style={[
                      styles.textInput,
                      {
                        backgroundColor: inputBg,
                        borderColor: inputBorder,
                        color: titleColor,
                      },
                    ]}
                    textAlign="right"
                  />
                </View>

                <View style={styles.guestInputCol}>
                  <ThemedText style={[styles.fieldLabel, { color: titleColor }]}>
                    رقم الجوال <ThemedText style={{ color: '#EF4444' }}>*</ThemedText>
                  </ThemedText>
                  <TextInput
                    value={guestPhone}
                    onChangeText={setGuestPhone}
                    placeholder="05xxxxxxxx"
                    placeholderTextColor={placeholderColor}
                    keyboardType="phone-pad"
                    style={[
                      styles.textInput,
                      {
                        backgroundColor: inputBg,
                        borderColor: inputBorder,
                        color: titleColor,
                      },
                    ]}
                    textAlign="right"
                  />
                </View>
              </View>
            </View>
          )}

          {/* Section: Message Body */}
          <View style={styles.sectionWrap}>
            <View style={styles.sectionHeaderRow}>
              <ThemedText style={[styles.sectionTitle, { color: titleColor }]}>
                نص الاقتراح أو الملاحظة:
              </ThemedText>
              {keyboardHeight > 0 ? (
                <TouchableOpacity
                  onPress={() => Keyboard.dismiss()}
                  style={styles.inlineDismissBtn}
                  activeOpacity={0.7}
                >
                  <Ionicons name="chevron-down" size={14} color="#0284C7" />
                  <ThemedText style={styles.inlineDismissText}>إغلاق الكيبورد</ThemedText>
                </TouchableOpacity>
              ) : (
                <ThemedText style={[styles.charCounter, { color: subtextColor }]}>
                  {messageText.length} حرفاً
                </ThemedText>
              )}
            </View>

            <TextInput
              value={messageText}
              onChangeText={setMessageText}
              placeholder="اكتب هنا فكرتك، اقتراحك، أو ملاحظاتك وتوجيهاتك لمجلس الإدارة..."
              placeholderTextColor={placeholderColor}
              multiline
              numberOfLines={5}
              style={[
                styles.textArea,
                {
                  backgroundColor: inputBg,
                  borderColor: inputBorder,
                  color: titleColor,
                },
              ]}
              textAlign="right"
              textAlignVertical="top"
            />
          </View>

          {/* Section: Attachments */}
          <View style={styles.sectionWrap}>
            <ThemedText style={[styles.sectionTitle, { color: titleColor }]}>
              إرفاق وسائط توضيحية (اختياري):
            </ThemedText>

            {/* Attached Media Preview */}
            {attachedMedia && (
              <View
                style={[
                  styles.attachedPreviewCard,
                  {
                    backgroundColor: cardBg,
                    borderColor: cardBorder,
                  },
                ]}
              >
                {attachedMedia.kind === 'image' && (
                  <RNImage source={{ uri: attachedMedia.uri }} style={styles.mediaThumbImage} />
                )}

                {attachedMedia.kind === 'video' && (
                  <View style={[styles.mediaThumbIcon, { backgroundColor: '#EF444420' }]}>
                    <Ionicons name="videocam" size={26} color="#EF4444" />
                  </View>
                )}

                {attachedMedia.kind === 'voice' && (
                  <View style={[styles.mediaThumbIcon, { backgroundColor: '#F59E0B20' }]}>
                    <Ionicons name="mic" size={26} color="#F59E0B" />
                  </View>
                )}

                <View style={styles.attachedMeta}>
                  <ThemedText style={[styles.attachedTitle, { color: titleColor }]} numberOfLines={1}>
                    {attachedMedia.kind === 'voice'
                      ? `تسجيل صوتي (${formatTimer(attachedMedia.durationSeconds || 0)})`
                      : attachedMedia.kind === 'video'
                      ? 'مقطع فيديو توضيحي'
                      : 'صورة توضيحية مرفقة'}
                  </ThemedText>
                  <ThemedText style={[styles.attachedSubtitle, { color: subtextColor }]}>
                    جاهز للإرسال مع الاقتراح
                  </ThemedText>
                </View>

                {/* Voice Play/Pause */}
                {attachedMedia.kind === 'voice' && (
                  <TouchableOpacity
                    onPress={() => {
                      if (previewStatus.playing) {
                        previewPlayer.pause();
                      } else {
                        previewPlayer.play();
                      }
                    }}
                    style={[styles.voicePlayBtn, { backgroundColor: colors.primary }]}
                  >
                    <Ionicons
                      name={previewStatus.playing ? 'pause' : 'play'}
                      size={18}
                      color="#FFFFFF"
                      style={{ marginLeft: previewStatus.playing ? 0 : 2 }}
                    />
                  </TouchableOpacity>
                )}

                <TouchableOpacity
                  onPress={handleRemoveMedia}
                  style={styles.removeMediaBtn}
                  hitSlop={8}
                >
                  <Ionicons name="close-circle" size={24} color="#EF4444" />
                </TouchableOpacity>
              </View>
            )}

            {/* Active Voice Recording Bar */}
            {isRecording && (
              <View
                style={[
                  styles.recordingBar,
                  {
                    backgroundColor: isDark ? '#3E1F1F' : '#FEF2F2',
                    borderColor: '#EF444470',
                  },
                ]}
              >
                <Animated.View
                  style={[styles.recordingPulseDot, { transform: [{ scale: pulseAnim }] }]}
                />
                <ThemedText style={styles.recordingTimer}>
                  جارٍ التسجيل: {formatTimer(recordSeconds)}
                </ThemedText>

                <View style={styles.recordingControls}>
                  <TouchableOpacity
                    onPress={() => void handleCancelRecording()}
                    style={styles.recordingCancelBtn}
                  >
                    <ThemedText style={styles.recordingCancelText}>إلغاء</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity
                    onPress={() => void handleFinishRecording()}
                    style={styles.recordingSaveBtn}
                  >
                    <Ionicons name="checkmark" size={16} color="#FFFFFF" />
                    <ThemedText style={styles.recordingSaveText}>حفظ</ThemedText>
                  </TouchableOpacity>
                </View>
              </View>
            )}

            {/* Media Action Buttons */}
            {!isRecording && !attachedMedia && (
              <View style={styles.mediaButtonsGrid}>
                <TouchableOpacity
                  onPress={() => void handleStartRecording()}
                  style={[
                    styles.mediaBtn,
                    {
                      backgroundColor: cardBg,
                      borderColor: cardBorder,
                    },
                  ]}
                  activeOpacity={0.8}
                >
                  <View style={[styles.mediaBtnIconBox, { backgroundColor: '#F59E0B20' }]}>
                    <Ionicons name="mic-outline" size={22} color="#F59E0B" />
                  </View>
                  <ThemedText style={[styles.mediaBtnLabel, { color: titleColor }]}>
                    تسجيل صوتي
                  </ThemedText>
                  <ThemedText style={[styles.mediaBtnSub, { color: subtextColor }]}>
                    اشرح فكرتك بصوتك
                  </ThemedText>
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={() => void handlePickImage()}
                  style={[
                    styles.mediaBtn,
                    {
                      backgroundColor: cardBg,
                      borderColor: cardBorder,
                    },
                  ]}
                  activeOpacity={0.8}
                >
                  <View style={[styles.mediaBtnIconBox, { backgroundColor: '#10B98120' }]}>
                    <Ionicons name="image-outline" size={22} color="#10B981" />
                  </View>
                  <ThemedText style={[styles.mediaBtnLabel, { color: titleColor }]}>
                    لقطة شاشة / صورة
                  </ThemedText>
                  <ThemedText style={[styles.mediaBtnSub, { color: subtextColor }]}>
                    إرفاق صورة توضيحية
                  </ThemedText>
                </TouchableOpacity>

                <TouchableOpacity
                  onPress={() => void handlePickVideo()}
                  style={[
                    styles.mediaBtn,
                    {
                      backgroundColor: cardBg,
                      borderColor: cardBorder,
                    },
                  ]}
                  activeOpacity={0.8}
                >
                  <View style={[styles.mediaBtnIconBox, { backgroundColor: '#EF444420' }]}>
                    <Ionicons name="videocam-outline" size={22} color="#EF4444" />
                  </View>
                  <ThemedText style={[styles.mediaBtnLabel, { color: titleColor }]}>
                    مقطع فيديو
                  </ThemedText>
                  <ThemedText style={[styles.mediaBtnSub, { color: subtextColor }]}>
                    فيديو حتى 60 ثانية
                  </ThemedText>
                </TouchableOpacity>
              </View>
            )}
          </View>

          {/* Action CTAs */}
          <View style={styles.submitSection}>
            {/* Direct Server API Action */}
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={() => void handleSubmitToServer()}
              disabled={isSubmitting}
              style={[
                styles.primarySubmitBtn,
                { backgroundColor: colors.primary, opacity: isSubmitting ? 0.7 : 1 },
              ]}
            >
              <LinearGradient
                colors={[colors.primary, '#047857']}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 0 }}
                style={styles.gradientBtnBg}
              >
                {isSubmitting ? (
                  <View style={styles.btnRow}>
                    <ActivityIndicator color="#FFFFFF" size="small" />
                    <ThemedText style={styles.primaryBtnText}>جارٍ الإرسال والتسجيل...</ThemedText>
                  </View>
                ) : (
                  <View style={styles.btnRow}>
                    <Ionicons name="paper-plane-outline" size={20} color="#FFFFFF" />
                    <ThemedText style={styles.primaryBtnText}>
                      إرسال الاقتراح رسمياً لإدارة العائلة
                    </ThemedText>
                  </View>
                )}
              </LinearGradient>
            </TouchableOpacity>

            {/* WhatsApp Alternative */}
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={() => void handleSendViaWhatsApp()}
              disabled={isSubmitting}
              style={[
                styles.whatsappSubmitBtn,
                {
                  borderColor: '#25D366',
                  backgroundColor: isDark ? '#25D36618' : '#25D36610',
                },
              ]}
            >
              <Ionicons name="logo-whatsapp" size={20} color={isDark ? '#4ADE80' : '#15803D'} />
              <ThemedText
                style={[
                  styles.whatsappBtnText,
                  { color: isDark ? '#4ADE80' : '#15803D' },
                ]}
              >
                أو إرسال المحتوى مباشرة عبر واتساب
              </ThemedText>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Floating Keyboard Helper Toolbar for Elderly / Quick Navigation */}
      {keyboardHeight > 0 && (
        <View
          style={[
            styles.floatingKeyboardPill,
            {
              backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
              borderColor: isDark ? '#334155' : '#CBD5E1',
              bottom: Platform.OS === 'ios' ? keyboardHeight + 10 : 15,
            },
          ]}
        >
          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => {
              scrollViewRef.current?.scrollToEnd({ animated: true });
            }}
            style={styles.pillActionBtn}
          >
            <Ionicons name="arrow-down-circle" size={16} color="#0284C7" />
            <ThemedText style={styles.pillActionTextPrimary}>
              النزول لزر الإرسال والواتساب ↓
            </ThemedText>
          </TouchableOpacity>

          <View style={[styles.pillDivider, { backgroundColor: isDark ? '#334155' : '#E2E8F0' }]} />

          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => Keyboard.dismiss()}
            style={styles.pillActionBtn}
          >
            <Ionicons name="checkmark-circle" size={16} color={isDark ? '#E2E8F0' : '#475569'} />
            <ThemedText style={[styles.pillActionTextSec, { color: isDark ? '#E2E8F0' : '#475569' }]}>
              إخفاء الكيبورد
            </ThemedText>
          </TouchableOpacity>
        </View>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollContent: {
    padding: 18,
    paddingBottom: 50,
  },

  inlineDismissBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#0284C715',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  inlineDismissText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
    color: '#0284C7',
  },
  floatingKeyboardPill: {
    position: 'absolute',
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 25,
    borderWidth: 1.5,
    paddingHorizontal: 12,
    paddingVertical: 8,
    gap: 10,
    ...Platform.select({
      ios: {
        shadowColor: '#000000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
      },
      android: {
        elevation: 6,
      },
    }),
  },
  pillActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  pillActionTextPrimary: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    color: '#0284C7',
  },
  pillActionTextSec: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  pillDivider: {
    width: 1,
    height: 14,
  },

  // Identity
  identityCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 16,
    borderWidth: 1.5,
    marginBottom: 20,
    gap: 12,
  },
  identityAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  identityTextWrap: {
    flex: 1,
  },
  identityName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    textAlign: 'left',
  },
  identityDesc: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    marginTop: 2,
    textAlign: 'left',
  },

  // Guest Card
  guestCard: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1.5,
    marginBottom: 20,
  },
  guestNoticeHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  guestNoticeTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  guestNoticeDesc: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
    marginBottom: 14,
    lineHeight: 18,
  },
  guestInputsRow: {
    flexDirection: 'row',
    gap: 12,
  },
  guestInputCol: {
    flex: 1,
  },

  // Sections
  sectionWrap: {
    marginBottom: 22,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    marginBottom: 10,
    textAlign: 'left',
  },
  charCounter: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },

  // Category Selector
  typesGrid: {
    gap: 10,
  },
  typeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1.5,
    gap: 12,
  },
  typeIconBox: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  typeTextWrap: {
    flex: 1,
  },
  typeTitle: {
    fontSize: 13.5,
    textAlign: 'left',
  },
  typeDesc: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    marginTop: 2,
    textAlign: 'left',
  },

  // Inputs
  fieldLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginBottom: 6,
    textAlign: 'left',
  },
  textInput: {
    height: 48,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
    fontFamily: 'Tajawal-Medium',
    fontSize: 13.5,
  },
  textArea: {
    minHeight: 110,
    borderWidth: 1.5,
    borderRadius: 14,
    padding: 14,
    fontFamily: 'Tajawal-Medium',
    fontSize: 13.5,
    lineHeight: 22,
  },

  // Media Grid
  mediaButtonsGrid: {
    flexDirection: 'row',
    gap: 10,
  },
  mediaBtn: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 8,
    borderRadius: 14,
    borderWidth: 1.5,
  },
  mediaBtnIconBox: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  mediaBtnLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
    textAlign: 'center',
    marginBottom: 2,
  },
  mediaBtnSub: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 9.5,
    textAlign: 'center',
  },

  // Attached Media Preview Card
  attachedPreviewCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1.5,
    gap: 12,
  },
  mediaThumbImage: {
    width: 48,
    height: 48,
    borderRadius: 10,
  },
  mediaThumbIcon: {
    width: 48,
    height: 48,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  attachedMeta: {
    flex: 1,
  },
  attachedTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    textAlign: 'left',
  },
  attachedSubtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    marginTop: 2,
    textAlign: 'left',
  },
  voicePlayBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  removeMediaBtn: {
    padding: 4,
  },

  // Recording Bar
  recordingBar: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    gap: 10,
  },
  recordingPulseDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#EF4444',
  },
  recordingTimer: {
    flex: 1,
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    color: '#EF4444',
    textAlign: 'left',
  },
  recordingControls: {
    flexDirection: 'row',
    gap: 8,
  },
  recordingCancelBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
  },
  recordingCancelText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    color: '#EF4444',
  },
  recordingSaveBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: '#059669',
  },
  recordingSaveText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    color: '#FFFFFF',
  },

  // Submit CTAs
  submitSection: {
    marginTop: 10,
    gap: 12,
  },
  primarySubmitBtn: {
    borderRadius: 16,
    overflow: 'hidden',
  },
  gradientBtnBg: {
    paddingVertical: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  whatsappSubmitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 16,
    borderWidth: 1.5,
    gap: 8,
  },
  whatsappBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
});
