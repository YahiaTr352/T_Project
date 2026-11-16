import React, {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  Animated,
  Keyboard,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import {
  useAudioRecorder,
  RecordingPresets,
  requestRecordingPermissionsAsync,
  setAudioModeAsync,
} from 'expo-audio';
import * as Haptics from 'expo-haptics';
import { Ionicons } from '@expo/vector-icons';
import { silaTheme as T } from '@/constants/theme';

export interface VoiceRecorderBarHandle {
  startRecording: () => Promise<void>;
  stopAndCancel: () => Promise<void>;
  stopAndSend: () => Promise<void>;
  isRecording: boolean;
}

export interface VoiceRecorderBarProps {
  onSendAudio: (audioUri: string) => void;
  onRecordingChange?: (isRecording: boolean) => void;
  disabled?: boolean;
  isDark?: boolean;
  hideStandaloneButton?: boolean;
}

export const VoiceRecorderBar = forwardRef<VoiceRecorderBarHandle, VoiceRecorderBarProps>(
  (
    {
      onSendAudio,
      onRecordingChange,
      disabled = false,
      isDark = false,
      hideStandaloneButton = false,
    },
    ref
  ) => {
    const [isRecording, setIsRecording] = useState(false);
    const [recordSeconds, setRecordSeconds] = useState(0);

    const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const pulseAnim = useRef(new Animated.Value(1)).current;

    // Pulse animation while recording
    useEffect(() => {
      let anim: Animated.CompositeAnimation | null = null;
      if (isRecording) {
        anim = Animated.loop(
          Animated.sequence([
            Animated.timing(pulseAnim, {
              toValue: 1.25,
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

    // Clean up timer on unmount
    useEffect(() => {
      return () => {
        if (timerRef.current) clearInterval(timerRef.current);
      };
    }, []);

    const startRecording = useCallback(async () => {
      if (disabled) return;

      try {
        Keyboard.dismiss();
        const permission = await requestRecordingPermissionsAsync();
        if (!permission.granted) {
          CustomAlert.alert(
            'إذن الميكروفون مطلوب',
            'يرجى تفعيل صلاحية الميكروفون لتسجيل الرسائل الصوتية والتحدث مع المساعد.'
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
        onRecordingChange?.(true);
        setRecordSeconds(0);

        timerRef.current = setInterval(() => {
          setRecordSeconds((prev) => prev + 1);
        }, 1000);
      } catch {
        CustomAlert.alert('خطأ في التسجيل', 'تعذر بدء التسجيل الصوتي، يرجى المحاولة مرة أخرى.');
      }
    }, [disabled, onRecordingChange, recorder]);

    const stopAndCancel = useCallback(async () => {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);

      if (timerRef.current) clearInterval(timerRef.current);
      setIsRecording(false);
      onRecordingChange?.(false);
      setRecordSeconds(0);

      try {
        await recorder.stop();
      } catch {}
    }, [onRecordingChange, recorder]);

    const stopAndSend = useCallback(async () => {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

      if (timerRef.current) clearInterval(timerRef.current);
      setIsRecording(false);
      onRecordingChange?.(false);

      try {
        await recorder.stop();
        const uri = recorder.uri ?? recorder.getStatus()?.url;

        if (uri) {
          onSendAudio(uri);
        }
      } catch {
        CustomAlert.alert('خطأ', 'تعذر إرسال التسجيل الصوتي.');
      } finally {
        setRecordSeconds(0);
      }
    }, [onRecordingChange, onSendAudio, recorder]);

    useImperativeHandle(
      ref,
      () => ({
        startRecording,
        stopAndCancel,
        stopAndSend,
        isRecording,
      }),
      [startRecording, stopAndCancel, stopAndSend, isRecording]
    );

    const formatTimer = (secs: number) => {
      const mins = Math.floor(secs / 60);
      const s = secs % 60;
      return `${mins}:${s < 10 ? '0' : ''}${s}`;
    };

    // If not recording
    if (!isRecording) {
      if (hideStandaloneButton) {
        return null;
      }

      return (
        <TouchableOpacity
          style={[
            styles.micBtn,
            {
              backgroundColor: isDark ? '#334155' : '#e2e8f0',
            },
          ]}
          disabled={disabled}
          activeOpacity={0.7}
          onPress={startRecording}
        >
          <Ionicons
            name="mic"
            size={20}
            color={disabled ? (isDark ? '#64748b' : '#94a3b8') : T.primary}
          />
        </TouchableOpacity>
      );
    }

    // Active recording bar: Live pulsing red indicator, elapsed time, cancel and send buttons
    return (
      <View
        style={[
          styles.recordingBar,
          {
            backgroundColor: isDark ? '#1e293b' : '#ffffff',
            borderColor: isDark ? '#475569' : '#fecdd3',
          },
        ]}
      >
        {/* Pulse Dot + Timer */}
        <View style={styles.recordStatus}>
          <Animated.View
            style={[
              styles.pulseDot,
              {
                transform: [{ scale: pulseAnim }],
              },
            ]}
          />
          <Text style={[styles.timerText, { color: isDark ? '#f87171' : '#dc2626' }]}>
            {formatTimer(recordSeconds)}
          </Text>
          <Text style={[styles.statusLabel, { color: isDark ? '#94a3b8' : '#64748b' }]}>
            جاري التسجيل...
          </Text>
        </View>

        {/* Actions: Cancel & Send */}
        <View style={styles.actionsWrap}>
          <TouchableOpacity
            style={[styles.cancelBtn, { backgroundColor: isDark ? '#334155' : '#fee2e2' }]}
            activeOpacity={0.7}
            onPress={stopAndCancel}
          >
            <Ionicons name="trash-outline" size={17} color="#ef4444" />
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.sendVoiceBtn, { backgroundColor: '#25D366' }]}
            activeOpacity={0.8}
            onPress={stopAndSend}
          >
            <Ionicons
              name="send"
              size={16}
              color="#ffffff"
              style={{ transform: [{ scaleX: -1 }] }}
            />
          </TouchableOpacity>
        </View>
      </View>
    );
  }
);

VoiceRecorderBar.displayName = 'VoiceRecorderBar';

const styles = StyleSheet.create({
  micBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  recordingBar: {
    width: '100%',
    height: 48,
    borderRadius: 24,
    borderWidth: 1.5,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    shadowColor: '#ef4444',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  recordStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  pulseDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#ef4444',
  },
  timerText: {
    fontSize: 14,
    fontWeight: '800',
    fontVariant: ['tabular-nums'],
  },
  statusLabel: {
    fontSize: 12,
    fontWeight: '600',
  },
  actionsWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cancelBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendVoiceBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 3,
    elevation: 2,
  },
});
