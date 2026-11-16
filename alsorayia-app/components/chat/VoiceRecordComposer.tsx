import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Keyboard,
  PanResponder,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import * as FileSystemLegacy from 'expo-file-system/legacy';
import {
  createAudioPlayer,
  RecordingPresets,
  requestRecordingPermissionsAsync,
  setAudioModeAsync,
  useAudioRecorder,
  type AudioPlayer,
  type AudioStatus,
  type RecordingOptions,
} from 'expo-audio';
import { audioPlaybackManager } from '@/lib/audio/audioPlaybackManager';
import { silaTheme } from '@/constants/theme';
import { CustomAlert } from '@/components/ui/AlertModal';

export interface VoiceRecordComposerProps {
  inputText: string;
  onChangeText: (text: string) => void;
  onSendText: () => void;
  onSendAudio: (audioData: {
    uri: string;
    duration: number;
    waveform: number[];
  }) => Promise<void>;
  placeholder?: string;
  isDark?: boolean;
  disabled?: boolean;
  sending?: boolean;
  onPickImage?: () => void;
  allowAttachments?: boolean;
  editingMessage?: boolean;
  hasAttachment?: boolean;
}

const MAX_RECORDING_SECONDS = 300; // 5 دقائق كحد أقصى
const MIN_HOLD_SEND_MS = 700; // أقل مدة لإرسال التسجيل عند الإفلات المباشر
const QUICK_TAP_LOCK_MS = 400; // الضغطة السريعة تثبت التسجيل تلقائياً (Hands-Free)
const WAVEFORM_HISTORY_LENGTH = 24; // عدد أعمدة التموج الحية

export const VoiceRecordComposer = React.memo(function VoiceRecordComposer({
  inputText,
  onChangeText,
  onSendText,
  onSendAudio,
  placeholder = 'اكتب رسالة...',
  isDark = false,
  disabled = false,
  sending = false,
  onPickImage,
  allowAttachments = true,
  editingMessage = false,
  hasAttachment = false,
}: VoiceRecordComposerProps) {
  // حالات التسجيل
  const [isRecording, setIsRecording] = useState(false);
  const [isLocked, setIsLocked] = useState(false);
  const [isCancelled, setIsCancelled] = useState(false);
  const [recordedDurationSec, setRecordedDurationSec] = useState(0);
  const [liveWaveform, setLiveWaveform] = useState<number[]>(
    () => new Array(WAVEFORM_HISTORY_LENGTH).fill(0.18)
  );

  // حالة معاينة الصوت قبل الإرسال (في وضع التثبيت بعد الإيقاف المؤقت)
  const [previewUri, setPreviewUri] = useState<string | null>(null);
  const [isPreviewPlaying, setIsPreviewPlaying] = useState(false);
  const [previewCurrentTime, setPreviewCurrentTime] = useState(0);
  const [previewDuration, setPreviewDuration] = useState(0);
  const previewPlayerRef = useRef<AudioPlayer | null>(null);

  // الرسوم المتحركة
  const pulseAnim = useRef(new Animated.Value(1)).current;
  const micScaleAnim = useRef(new Animated.Value(1)).current;

  // مراجع متزامنة لمنع تعارض الحالات (Race Conditions) أثناء الإيماءات السريعة على iOS/Android
  const recordingStartTimeRef = useRef<number>(0);
  const pressStartTimeRef = useRef<number>(0);
  const recordingTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const meterPollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const isRecordingRef = useRef(false);
  const isPreparingRef = useRef(false);
  const isTouchingRef = useRef(false);
  const isLockedRef = useRef(false);
  const isCancelledRef = useRef(false);
  const recordedSamplesRef = useRef<number[]>([]);

  // إعدادات التسجيل بجودة عالية مع تفعيل قياس مستوى الصوت (metering)
  const recordingOptions: RecordingOptions = useMemo(
    () => ({
      ...RecordingPresets.HIGH_QUALITY,
      isMeteringEnabled: true,
    }),
    []
  );

  const recorder = useAudioRecorder(recordingOptions);

  // تشغيل نبض النقطة الحمراء أثناء التسجيل
  useEffect(() => {
    let animLoop: Animated.CompositeAnimation | null = null;
    if (isRecording) {
      animLoop = Animated.loop(
        Animated.sequence([
          Animated.timing(pulseAnim, {
            toValue: 0.25,
            duration: 500,
            useNativeDriver: true,
          }),
          Animated.timing(pulseAnim, {
            toValue: 1,
            duration: 500,
            useNativeDriver: true,
          }),
        ])
      );
      animLoop.start();
    } else {
      pulseAnim.setValue(1);
    }

    return () => {
      animLoop?.stop();
    };
  }, [isRecording, pulseAnim]);

  // إيقاف وتدمير مشغل المعاينة بالكامل لتحرير الموارد ومنع استمرار الصوت بعد الحذف
  const destroyPreviewPlayer = useCallback(() => {
    const player = previewPlayerRef.current;
    previewPlayerRef.current = null;
    audioPlaybackManager.releasePlayback('voice_preview_composer');
    if (!player) return;
    try {
      player.pause();
    } catch {}
    try {
      player.removeAllListeners('playbackStatusUpdate');
    } catch {}
    try {
      player.remove();
    } catch {}
    try {
      player.release();
    } catch {}
  }, []);

  // تنظيف المؤقتات والمشغل المؤقت عند الخروج من الشاشة
  useEffect(() => {
    return () => {
      destroyPreviewPlayer();
      if (recordingTimerRef.current) clearInterval(recordingTimerRef.current);
      if (meterPollTimerRef.current) clearInterval(meterPollTimerRef.current);
    };
  }, [destroyPreviewPlayer]);

  // تنسيق الوقت بالدقائق والثواني
  const formatTime = (secs: number) => {
    const totalSecs = Math.floor(Math.max(0, secs));
    const m = Math.floor(totalSecs / 60);
    const s = totalSecs % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  // تحويل قيمة الديسيبل الحقيقية من الميكروفون إلى نسبة ارتفاع بين 0.15 و 1.0
  const normalizeDecibels = (db?: number): number => {
    if (db === undefined || Number.isNaN(db) || !Number.isFinite(db)) {
      // في حال عدم توفر القراءة الفورية في أول إطار، نولد ذبذبة طفيفة طبيعية
      return 0.2 + Math.random() * 0.15;
    }
    // قصر النطاق بين -55dB (هدوء) إلى 0dB (صوت مرتفع)
    const clamped = Math.max(-55, Math.min(0, db));
    const normalized = (clamped + 55) / 55;
    return Math.max(0.14, Math.min(1.0, normalized));
  };

  // ضغط مصفوفة عينات الصوت لتناسب الـ metadata
  const downsampleWaveform = useCallback((samples: number[], targetCount: number): number[] => {
    if (samples.length === 0) {
      return new Array(targetCount).fill(0.3);
    }
    if (samples.length <= targetCount) {
      return samples.map((s) => Number(s.toFixed(2)));
    }
    const step = samples.length / targetCount;
    const result: number[] = [];
    for (let i = 0; i < targetCount; i++) {
      const idx = Math.min(Math.floor(i * step), samples.length - 1);
      result.push(Number((samples[idx] ?? 0.3).toFixed(2)));
    }
    return result;
  }, []);

  // حذف الملف المؤقت بأمان عبر واجهة legacy المتوافقة مع Expo SDK 54+
  const deleteTempAudio = useCallback(async (uri: string | null | undefined) => {
    if (!uri) return;
    try {
      await FileSystemLegacy.deleteAsync(uri, { idempotent: true });
    } catch {}
  }, []);

  // إعادة ضبط وضع الصوت في iOS للتشغيل الطبيعي عبر السبيكر
  const restorePlaybackAudioMode = useCallback(async () => {
    try {
      await setAudioModeAsync({
        allowsRecording: false,
        playsInSilentMode: true,
      });
    } catch {}
  }, []);

  // إيقاف المؤقتات الداخلية
  const clearRecordingTimers = useCallback(() => {
    if (recordingTimerRef.current) {
      clearInterval(recordingTimerRef.current);
      recordingTimerRef.current = null;
    }
    if (meterPollTimerRef.current) {
      clearInterval(meterPollTimerRef.current);
      meterPollTimerRef.current = null;
    }
  }, []);

  // إيقاف التسجيل والدخول في وضع المعاينة (Pause / Preview)
  const stopRecordingAndPreview = useCallback(async () => {
    if (!isRecordingRef.current) return;

    clearRecordingTimers();
    isRecordingRef.current = false;

    const durationMs = Date.now() - recordingStartTimeRef.current;
    const finalSec = Math.max(1, Math.round(durationMs / 1000));

    try {
      await recorder.stop();
      const audioUri = recorder.uri ?? recorder.getStatus()?.url;
      await restorePlaybackAudioMode();

      setIsRecording(false);
      Animated.spring(micScaleAnim, {
        toValue: 1,
        useNativeDriver: true,
      }).start();

      if (!audioUri) {
        setIsLocked(false);
        isLockedRef.current = false;
        return;
      }

      setPreviewUri(audioUri);
      setPreviewDuration(finalSec);
      setPreviewCurrentTime(0);

      // تجهيز مشغل المعاينة
      const player = createAudioPlayer({ uri: audioUri }, { updateInterval: 100 });
      player.addListener('playbackStatusUpdate', (status: AudioStatus) => {
        if (status.isLoaded) {
          setIsPreviewPlaying(status.playing);
          setPreviewCurrentTime(status.currentTime);
          if (status.duration && status.duration > 0) {
            setPreviewDuration(status.duration);
          }
          if (
            status.didJustFinish ||
            (status.duration > 0 && status.currentTime >= status.duration - 0.05 && !status.playing)
          ) {
            try {
              player.pause();
            } catch {}
            setIsPreviewPlaying(false);
            setPreviewCurrentTime(0);
            try {
              void player.seekTo(0);
            } catch {}
          }
        }
      });
      previewPlayerRef.current = player;
    } catch (err) {
      console.error('[VoiceRecordComposer] stopRecordingAndPreview error:', err);
      setIsRecording(false);
      setIsLocked(false);
      isLockedRef.current = false;
    }
  }, [clearRecordingTimers, micScaleAnim, recorder, restorePlaybackAudioMode]);

  // إيقاف التسجيل وإرساله مباشرة
  const stopAndSendRecording = useCallback(async () => {
    if (!isRecordingRef.current) return;

    clearRecordingTimers();
    isRecordingRef.current = false;
    isLockedRef.current = false;

    const durationMs = Date.now() - recordingStartTimeRef.current;
    const finalSec = Math.max(1, Math.round(durationMs / 1000));

    setIsRecording(false);
    setIsLocked(false);
    Animated.spring(micScaleAnim, {
      toValue: 1,
      useNativeDriver: true,
    }).start();

    try {
      await recorder.stop();
      const audioUri = recorder.uri ?? recorder.getStatus()?.url;
      await restorePlaybackAudioMode();

      if (durationMs < MIN_HOLD_SEND_MS) {
        await deleteTempAudio(audioUri);
        return;
      }

      if (!audioUri) {
        CustomAlert.alert('خطأ', 'تعذر حفظ التسجيل الصوتي، يرجى المحاولة مرة أخرى.');
        return;
      }

      const finalWaveform = downsampleWaveform(recordedSamplesRef.current, 35);

      try {
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } catch {}

      await onSendAudio({
        uri: audioUri,
        duration: finalSec,
        waveform: finalWaveform,
      });
    } catch (err) {
      console.error('[VoiceRecordComposer] stopAndSendRecording error:', err);
      CustomAlert.alert('خطأ', 'تعذر إرسال الرسالة الصوتية.');
    }
  }, [
    clearRecordingTimers,
    deleteTempAudio,
    downsampleWaveform,
    micScaleAnim,
    onSendAudio,
    recorder,
    restorePlaybackAudioMode,
  ]);

  // إلغاء التسجيل وحذف الملف المؤقت
  const cancelRecording = useCallback(async () => {
    clearRecordingTimers();
    destroyPreviewPlayer();
    const wasRecording = isRecordingRef.current;

    isRecordingRef.current = false;
    isPreparingRef.current = false;
    isLockedRef.current = false;
    isCancelledRef.current = false;

    setIsRecording(false);
    setIsLocked(false);
    setIsCancelled(false);
    setRecordedDurationSec(0);
    setPreviewUri(null);
    setIsPreviewPlaying(false);
    Animated.spring(micScaleAnim, {
      toValue: 1,
      useNativeDriver: true,
    }).start();

    try {
      if (wasRecording) {
        await recorder.stop();
      }
      const recUri = recorder.uri ?? recorder.getStatus()?.url;
      await deleteTempAudio(recUri);
      if (previewUri) {
        await deleteTempAudio(previewUri);
      }
      await restorePlaybackAudioMode();
    } catch {}

    try {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    } catch {}
  }, [clearRecordingTimers, deleteTempAudio, destroyPreviewPlayer, micScaleAnim, previewUri, recorder, restorePlaybackAudioMode]);

  // بدء التسجيل الصوتي (متوافق 100% مع iOS AVAudioSession و Android)
  const startRecording = useCallback(async () => {
    if (disabled || sending || isRecordingRef.current || isPreparingRef.current) return;

    isPreparingRef.current = true;

    try {
      audioPlaybackManager.stopAll();
      destroyPreviewPlayer();
      Keyboard.dismiss();

      // 1. طلب صلاحية الميكروفون
      const perm = await requestRecordingPermissionsAsync();
      if (!perm.granted) {
        isPreparingRef.current = false;
        isTouchingRef.current = false;
        CustomAlert.alert(
          'إذن الميكروفون مطلوب',
          'يرجى تفعيل صلاحية الميكروفون من إعدادات الجهاز لتتمكن من تسجيل وإرسال الرسائل الصوتية.'
        );
        return;
      }

      // 2. تفعيل وضع التسجيل في نظام الصوت (ضروري جداً لأجهزة Apple iOS وإلا يرمي RecordingDisabledException)
      await setAudioModeAsync({
        allowsRecording: true,
        playsInSilentMode: true,
      });

      setPreviewUri(null);
      setIsPreviewPlaying(false);

      // 3. تجهيز المسجل بملف جديد وإعدادات metering مفعلة
      await recorder.prepareToRecordAsync(recordingOptions);
      recorder.record();

      try {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);
      } catch {}

      recordingStartTimeRef.current = Date.now();
      isRecordingRef.current = true;
      isPreparingRef.current = false;
      isCancelledRef.current = false;
      recordedSamplesRef.current = [];

      // إذا رفع المستخدم إصبعه سريعاً أثناء التجهيز (ضغطة عادية سريعة)، نثبت التسجيل تلقائياً (Hands-Free)
      const shouldAutoLock = !isTouchingRef.current || isLockedRef.current;
      isLockedRef.current = shouldAutoLock;

      setIsRecording(true);
      setIsLocked(shouldAutoLock);
      setIsCancelled(false);
      setRecordedDurationSec(0);
      setLiveWaveform(new Array(WAVEFORM_HISTORY_LENGTH).fill(0.18));

      Animated.spring(micScaleAnim, {
        toValue: shouldAutoLock ? 1 : 1.25,
        useNativeDriver: true,
      }).start();

      // مؤقت العداد الزمني (كل ثانية)
      clearRecordingTimers();
      recordingTimerRef.current = setInterval(() => {
        const elapsedSec = Math.floor((Date.now() - recordingStartTimeRef.current) / 1000);
        setRecordedDurationSec(elapsedSec);

        if (elapsedSec >= MAX_RECORDING_SECONDS) {
          void stopRecordingAndPreview();
        }
      }, 1000);

      // استطلاع فوري لقوة الصوت (metering) كل 100ms لرسم التموجات الحية
      meterPollTimerRef.current = setInterval(() => {
        try {
          const status = recorder.getStatus();
          if (status && status.isRecording) {
            const normVal = normalizeDecibels(status.metering);
            recordedSamplesRef.current.push(normVal);
            setLiveWaveform((prev) => [...prev.slice(1), normVal]);
          }
        } catch {}
      }, 100);
    } catch (err) {
      console.error('[VoiceRecordComposer] Error starting recording:', err);
      isPreparingRef.current = false;
      isRecordingRef.current = false;
      setIsRecording(false);
      setIsLocked(false);
      CustomAlert.alert('خطأ في التسجيل', 'تعذر بدء التسجيل الصوتي، يرجى المحاولة مرة أخرى.');
    }
  }, [
    clearRecordingTimers,
    destroyPreviewPlayer,
    disabled,
    micScaleAnim,
    recorder,
    recordingOptions,
    sending,
    stopRecordingAndPreview,
  ]);

  // إرسال تسجيل المعاينة بعد الاستماع له
  const sendPreviewAudio = useCallback(async () => {
    if (!previewUri) return;

    destroyPreviewPlayer();

    const finalWaveform = downsampleWaveform(recordedSamplesRef.current, 35);
    const uriToSend = previewUri;
    const durationToSend = Math.max(1, Math.round(previewDuration));

    setPreviewUri(null);
    setIsLocked(false);
    isLockedRef.current = false;

    try {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {}

    await onSendAudio({
      uri: uriToSend,
      duration: durationToSend,
      waveform: finalWaveform,
    });
  }, [destroyPreviewPlayer, downsampleWaveform, onSendAudio, previewDuration, previewUri]);

  // تشغيل / إيقاف المعاينة
  const togglePreviewPlayback = useCallback(async () => {
    const player = previewPlayerRef.current;
    if (!player) return;
    try {
      void Haptics.selectionAsync();
    } catch {}

    if (isPreviewPlaying || player.playing) {
      try {
        player.pause();
      } catch {}
      setIsPreviewPlaying(false);
      audioPlaybackManager.releasePlayback('voice_preview_composer');
    } else {
      audioPlaybackManager.claimPlayback('voice_preview_composer', player, () => {
        try {
          player.pause();
        } catch {}
        setIsPreviewPlaying(false);
      });
      await restorePlaybackAudioMode();
      player.play();
      setIsPreviewPlaying(true);
    }
  }, [isPreviewPlaying, restorePlaybackAudioMode]);

  // معالج إيماءات الميكروفون (يدعم الضغطة السريعة للتثبيت + الضغط المطول + السحب للأعلى للتثبيت + السحب للإلغاء)
  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => !disabled && !sending,
        onMoveShouldSetPanResponder: () => !disabled && !sending,
        onPanResponderTerminationRequest: () => false,
        onPanResponderGrant: () => {
          isTouchingRef.current = true;
          pressStartTimeRef.current = Date.now();
          void startRecording();
        },
        onPanResponderMove: (_, gestureState) => {
          if (!isRecordingRef.current || isLockedRef.current) return;

          // السحب للأعلى لتثبيت التسجيل (dy < -40)
          if (gestureState.dy < -40) {
            isLockedRef.current = true;
            setIsLocked(true);
            Animated.spring(micScaleAnim, {
              toValue: 1,
              useNativeDriver: true,
            }).start();
            try {
              void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            } catch {}
            return;
          }

          // السحب أفقياً لإلغاء التسجيل (|dx| > 55)
          if (Math.abs(gestureState.dx) > 55) {
            if (!isCancelledRef.current) {
              isCancelledRef.current = true;
              setIsCancelled(true);
              try {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
              } catch {}
            }
          } else if (isCancelledRef.current) {
            isCancelledRef.current = false;
            setIsCancelled(false);
          }
        },
        onPanResponderRelease: () => {
          isTouchingRef.current = false;
          const pressDuration = Date.now() - pressStartTimeRef.current;

          // إذا كان لا يزال يجهز جلسة الصوت أو كانت ضغطة سريعة (< 400ms)، نثبت التسجيل (Hands-Free)
          if (isPreparingRef.current || (pressDuration < QUICK_TAP_LOCK_MS && !isCancelledRef.current)) {
            isLockedRef.current = true;
            setIsLocked(true);
            Animated.spring(micScaleAnim, {
              toValue: 1,
              useNativeDriver: true,
            }).start();
            return;
          }

          // إذا كان مثبتاً بالفعل، يظل التسجيل مستمراً
          if (isLockedRef.current) return;

          // إذا سحب المستخدم للإلغاء
          if (isCancelledRef.current) {
            void cancelRecording();
            return;
          }

          // إفلات بعد ضغط مطول: إرسال الرسالة الصوتية فوراً
          void stopAndSendRecording();
        },
        onPanResponderTerminate: () => {
          isTouchingRef.current = false;
          // إذا قاطعت النظام الإيماءة أثناء التسجيل، نثبته بدلاً من إضاعة تسجيل المستخدم
          if (isRecordingRef.current || isPreparingRef.current) {
            isLockedRef.current = true;
            setIsLocked(true);
            Animated.spring(micScaleAnim, {
              toValue: 1,
              useNativeDriver: true,
            }).start();
          }
        },
      }),
    [cancelRecording, disabled, micScaleAnim, sending, startRecording, stopAndSendRecording]
  );

  const hasText = inputText.trim().length > 0;

  // ألوان شريط الإدخال
  const barBg = isDark ? '#1F2923' : '#F8FAFA';
  const textClr = isDark ? '#F3F4F6' : '#0F172A';
  const borderClr = isDark ? '#2F4038' : silaTheme.border;

  // ─────────────────────────────────────────────────────────────
  // 1. عرض وضع المعاينة (Audio Preview Mode بعد الإيقاف المؤقت)
  // ─────────────────────────────────────────────────────────────
  if (previewUri) {
    return (
      <View style={[styles.previewBar, { backgroundColor: barBg, borderColor: borderClr }]}>
        {/* زر إرسال المعاينة النهائي */}
        <TouchableOpacity
          onPress={sendPreviewAudio}
          disabled={sending}
          style={[styles.sendCircleBtn, { backgroundColor: silaTheme.primary }]}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel="إرسال الرسالة الصوتية">
          {sending ? (
            <ActivityIndicator size="small" color="#FFFFFF" />
          ) : (
            <Ionicons
              name="send"
              size={18}
              color="#FFFFFF"
              style={{ transform: [{ scaleX: -1 }] }}
            />
          )}
        </TouchableOpacity>

        {/* شريط تموج المعاينة والوقت */}
        <View style={styles.previewWaveWrap}>
          <View style={styles.previewBarsRow}>
            {downsampleWaveform(recordedSamplesRef.current, 24).map((norm, idx) => (
              <View
                key={idx}
                style={[
                  styles.previewBarItem,
                  {
                    height: Math.round(5 + norm * 18),
                    backgroundColor: isDark ? '#4ADE80' : silaTheme.primary,
                  },
                ]}
              />
            ))}
          </View>
          <Text style={[styles.previewTimeText, { color: isDark ? '#9CA3AF' : '#64748B' }]}>
            {formatTime(previewCurrentTime)} / {formatTime(previewDuration)}
          </Text>
        </View>

        {/* زر الاستماع للمعاينة (تشغيل/إيقاف) */}
        <TouchableOpacity
          onPress={togglePreviewPlayback}
          style={[styles.previewPlayBtn, { backgroundColor: silaTheme.primary }]}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel="استماع للتسجيل">
          <Ionicons
            name={isPreviewPlaying ? 'pause' : 'play'}
            size={18}
            color="#FFFFFF"
            style={isPreviewPlaying ? undefined : { marginLeft: 2 }}
          />
        </TouchableOpacity>

        {/* زر حذف المعاينة (سلة المهملات) */}
        <TouchableOpacity
          onPress={cancelRecording}
          style={styles.trashBtn}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel="حذف التسجيل">
          <Ionicons name="trash-outline" size={20} color="#EF4444" />
        </TouchableOpacity>
      </View>
    );
  }

  // ─────────────────────────────────────────────────────────────
  // 2. العرض الموحد (يحافظ على بقاء زر الميكروفون في الشجرة أثناء اللمس لمنع انقطاع الإيماءة)
  // ─────────────────────────────────────────────────────────────
  return (
    <View style={styles.inputRow}>
      {/* مؤشر التثبيت العائم فوق زر الميكروفون أثناء الضغط المستمر */}
      {isRecording && !isLocked && (
        <View
          style={[
            styles.floatingLockHint,
            {
              backgroundColor: isDark ? '#24352C' : '#FFFFFF',
              borderColor: isDark ? '#344E41' : '#CBE5D3',
            },
          ]}>
          <Ionicons name="lock-closed" size={14} color={silaTheme.primary} />
          <Ionicons name="chevron-up" size={13} color={isDark ? '#9CA3AF' : '#64748B'} />
        </View>
      )}

      {/* الزر الأيسر/الأيمن: إما أزرار الإرسال في وضع التثبيت، أو زر الإرسال النصي، أو زر الميكروفون */}
      {isRecording && isLocked ? (
        <View style={styles.lockedActionsRow}>
          {/* زر إرسال الصوت المباشر */}
          <TouchableOpacity
            onPress={stopAndSendRecording}
            disabled={sending}
            style={[styles.sendCircleBtn, { backgroundColor: silaTheme.primary }]}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="إرسال الرسالة الصوتية">
            {sending ? (
              <ActivityIndicator size="small" color="#FFFFFF" />
            ) : (
              <Ionicons
                name="send"
                size={18}
                color="#FFFFFF"
                style={{ transform: [{ scaleX: -1 }] }}
              />
            )}
          </TouchableOpacity>

          {/* زر إيقاف مؤقت للاستماع والمعاينة */}
          <TouchableOpacity
            onPress={stopRecordingAndPreview}
            style={[
              styles.pauseBtn,
              {
                backgroundColor: isDark ? '#2D3F36' : '#E2E8F0',
              },
            ]}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel="إيقاف مؤقت ومعاينة الصوت">
            <Ionicons name="pause" size={18} color={isDark ? '#F3F4F6' : '#1E293B'} />
          </TouchableOpacity>
        </View>
      ) : hasText || hasAttachment || editingMessage ? (
        <TouchableOpacity
          activeOpacity={0.8}
          onPress={onSendText}
          disabled={disabled || sending}
          style={[
            styles.sendCircleBtn,
            {
              backgroundColor: disabled
                ? isDark
                  ? '#26332C'
                  : '#E5E7EB'
                : silaTheme.primary,
            },
          ]}
          accessibilityRole="button"
          accessibilityLabel={editingMessage ? 'حفظ التعديل' : 'إرسال الرسالة'}>
          {sending ? (
            <ActivityIndicator size="small" color="#FFFFFF" />
          ) : (
            <Ionicons
              name={editingMessage ? 'checkmark' : 'send'}
              size={18}
              color={disabled ? (isDark ? '#4B5563' : '#9CA3AF') : '#FFFFFF'}
              style={editingMessage ? undefined : { transform: [{ scaleX: -1 }] }}
            />
          )}
        </TouchableOpacity>
      ) : (
        /* زر الميكروفون التفاعلي: يبقى مركباً في الشجرة طوال فترة اللمس لضمان استمرارية PanResponder على iOS */
        <Animated.View
          {...panResponder.panHandlers}
          style={[
            styles.micBtnWrapper,
            { transform: [{ scale: micScaleAnim }] },
          ]}>
          <View
            style={[
              styles.sendCircleBtn,
              {
                backgroundColor: isRecording
                  ? isCancelled
                    ? '#EF4444'
                    : silaTheme.primary
                  : isDark
                    ? '#22382D'
                    : '#DCF2DD',
              },
            ]}>
            <Ionicons
              name={isRecording ? 'mic' : 'mic'}
              size={20}
              color={isRecording ? '#FFFFFF' : silaTheme.primary}
            />
          </View>
        </Animated.View>
      )}

      {/* المنطقة الوسطى: إما لوحة التسجيل الحية أو حقل الإدخال النصي */}
      {isRecording ? (
        <View
          style={[
            styles.recordingDashboard,
            {
              backgroundColor: isDark ? '#1C2721' : '#EDF7EE',
              borderColor: isCancelled
                ? '#EF4444'
                : isDark
                  ? '#2D4438'
                  : '#C7E8CE',
            },
          ]}>
          {/* إرشاد السحب للإلغاء أثناء الضغط المستمر */}
          {!isLocked && (
            <Text
              numberOfLines={1}
              style={[
                styles.slideCancelText,
                { color: isCancelled ? '#EF4444' : isDark ? '#9CA3AF' : '#64748B' },
              ]}>
              {isCancelled ? 'أفلت للإلغاء' : 'اسحب للإلغاء'}
            </Text>
          )}

          {/* التموجات الصوتية الحقيقية المتفاعلة مع صوت المتحدث */}
          <View style={styles.liveWaveformWrap}>
            {liveWaveform.map((vol, idx) => (
              <View
                key={idx}
                style={[
                  styles.liveWaveBar,
                  {
                    height: Math.round(4 + vol * 22),
                    backgroundColor: isCancelled
                      ? '#EF4444'
                      : isDark
                        ? '#4ADE80'
                        : silaTheme.primary,
                  },
                ]}
              />
            ))}
          </View>

          {/* مؤقت التسجيل مع النقطة الحمراء النابضة */}
          <View style={styles.timerWrap}>
            <Text style={[styles.timerText, { color: isDark ? '#F87171' : '#DC2626' }]}>
              {formatTime(recordedDurationSec)}
            </Text>
            <Animated.View style={[styles.redDot, { opacity: pulseAnim }]} />
          </View>

          {/* زر الحذف الفوري (سلة المهملات) */}
          <TouchableOpacity
            onPress={cancelRecording}
            style={styles.trashBtn}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel="إلغاء التسجيل">
            <Ionicons
              name={isCancelled ? 'trash' : 'trash-outline'}
              size={20}
              color="#EF4444"
            />
          </TouchableOpacity>
        </View>
      ) : (
        <>
          {/* حقل الكتابة النصي متعدد الأسطر بمحاذاة عربية صحيحة */}
          <TextInput
            value={inputText}
            onChangeText={onChangeText}
            placeholder={placeholder}
            placeholderTextColor={isDark ? '#6B7280' : '#9CA3AF'}
            multiline
            maxLength={2000}
            editable={!disabled}
            textAlignVertical="center"
            style={[
              styles.textInput,
              {
                backgroundColor: barBg,
                color: textClr,
                borderColor: borderClr,
              },
            ]}
          />

          {/* زر إرفاق صورة من الكاميرا / المعرض */}
          {allowAttachments && !editingMessage && onPickImage && (
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={onPickImage}
              disabled={disabled}
              style={[
                styles.attachBtn,
                {
                  backgroundColor: isDark ? '#24312B' : '#E8F5EC',
                  borderColor: isDark ? '#2F4038' : '#CDE7D5',
                },
              ]}
              accessibilityRole="button"
              accessibilityLabel="إرفاق صورة">
              <Ionicons name="camera-outline" size={20} color={silaTheme.primary} />
            </TouchableOpacity>
          )}
        </>
      )}
    </View>
  );
});

const styles = StyleSheet.create({
  inputRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 8,
    position: 'relative',
  },
  sendCircleBtn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.15,
    shadowRadius: 2,
  },
  micBtnWrapper: {
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  floatingLockHint: {
    position: 'absolute',
    top: -54,
    left: 3,
    width: 36,
    height: 44,
    borderRadius: 18,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    zIndex: 20,
  },
  textInput: {
    flex: 1,
    minHeight: 42,
    maxHeight: 110,
    borderRadius: 21,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingTop: Platform.OS === 'ios' ? 11 : 9,
    paddingBottom: Platform.OS === 'ios' ? 11 : 9,
    fontSize: 15,
    textAlign: 'right',
  },
  attachBtn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  // لوحة التسجيل الحية المدمجة بجانب زر الميكروفون
  recordingDashboard: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    paddingHorizontal: 10,
  },
  trashBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  timerWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 6,
    gap: 5,
  },
  redDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#EF4444',
  },
  timerText: {
    fontSize: 13,
    fontWeight: '700',
    fontVariant: ['tabular-nums'],
  },
  liveWaveformWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    height: 26,
    marginHorizontal: 6,
    overflow: 'hidden',
  },
  liveWaveBar: {
    width: 2.5,
    borderRadius: 1.5,
    marginHorizontal: 1,
  },
  slideCancelText: {
    fontSize: 11,
    fontWeight: '600',
    marginRight: 4,
  },
  lockedActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  pauseBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    justifyContent: 'center',
    alignItems: 'center',
  },
  // شريط المعاينة
  previewBar: {
    flexDirection: 'row',
    alignItems: 'center',
    height: 50,
    borderRadius: 25,
    borderWidth: 1,
    paddingHorizontal: 8,
    width: '100%',
    gap: 8,
  },
  previewPlayBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  previewWaveWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  previewBarsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    height: 22,
  },
  previewBarItem: {
    width: 2.5,
    borderRadius: 1.25,
  },
  previewTimeText: {
    fontSize: 10,
    fontWeight: '600',
    fontVariant: ['tabular-nums'],
    marginTop: 2,
    textAlign: 'center',
  },
});
