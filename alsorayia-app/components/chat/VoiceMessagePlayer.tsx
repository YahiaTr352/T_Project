import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  GestureResponderEvent,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import {
  createAudioPlayer,
  setAudioModeAsync,
  type AudioPlayer,
  type AudioStatus,
} from 'expo-audio';
import { audioPlaybackManager } from '@/lib/audio/audioPlaybackManager';
import { silaTheme } from '@/constants/theme';

interface VoiceMessagePlayerProps {
  id: string | number;
  audioUrl: string;
  duration?: number;
  waveform?: number[];
  isMe?: boolean;
  isDark?: boolean;
}

const TOTAL_WAVEFORM_BARS = 30;
const SPEEDS = [1.0, 1.5, 2.0] as const;

export const VoiceMessagePlayer = React.memo(function VoiceMessagePlayer({
  id,
  audioUrl,
  duration: initialDuration,
  waveform: rawWaveform,
  isMe = false,
  isDark = false,
}: VoiceMessagePlayerProps) {
  const playerId = `voice_${id}_${audioUrl}`;
  const playerRef = useRef<AudioPlayer | null>(null);
  const listenerSubRef = useRef<{ remove: () => void } | null>(null);
  const isPlayingRef = useRef(false);
  const speedIndexRef = useRef<number>(0);

  const [isPlaying, setIsPlaying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(initialDuration || 0);
  const [speedIndex, setSpeedIndex] = useState<number>(0);
  const [waveformWidth, setWaveformWidth] = useState(140);

  // إيقاف وتدمير المشغل بالكامل لتحرير موارد النظام ومنع استمرار الصوت بعد الحذف
  const destroyPlayer = useCallback(() => {
    if (listenerSubRef.current) {
      try {
        listenerSubRef.current.remove();
      } catch {}
      listenerSubRef.current = null;
    }

    const player = playerRef.current;
    playerRef.current = null;
    isPlayingRef.current = false;

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

  // تنسيق الوقت بالثواني والدقائق (مثال: 0:05)
  const formatTime = (secs: number) => {
    if (!Number.isFinite(secs) || secs < 0) return '0:00';
    const totalSecs = Math.floor(secs);
    const m = Math.floor(totalSecs / 60);
    const s = totalSecs % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  // توليد أطوال الأعمدة من عينات الصوت الحقيقية (Waveform)
  const normalizedBars = useMemo(() => {
    if (Array.isArray(rawWaveform) && rawWaveform.length > 0) {
      const step = rawWaveform.length / TOTAL_WAVEFORM_BARS;
      const bars: number[] = [];
      for (let i = 0; i < TOTAL_WAVEFORM_BARS; i++) {
        const sampleIdx = Math.min(Math.floor(i * step), rawWaveform.length - 1);
        const val = Number(rawWaveform[sampleIdx] ?? 0.3);
        const safeVal = Number.isFinite(val) ? val : 0.3;
        // تقييد الارتفاع بين 4 بكسل و26 بكسل
        const clamped = Math.max(0.12, Math.min(1, Math.abs(safeVal)));
        bars.push(Math.round(4 + clamped * 22));
      }
      return bars;
    }

    // بديل جمالي ثابت مبني على معرّف الرسالة (Hash) لضمان عدم الاهتزاز العشوائي
    const str = `${id}_${audioUrl}`;
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = (hash << 5) - hash + str.charCodeAt(i);
      hash |= 0;
    }
    const bars: number[] = [];
    for (let i = 0; i < TOTAL_WAVEFORM_BARS; i++) {
      const pseudo = Math.abs(Math.sin((hash + i * 17) * 0.25));
      bars.push(Math.round(5 + pseudo * 21));
    }
    return bars;
  }, [rawWaveform, id, audioUrl]);

  // الاشتراك في مدير الصوت الحصري وتنظيف المشغل عند إزالة/حذف الرسالة فقط
  useEffect(() => {
    const unsubscribe = audioPlaybackManager.subscribe((activeId) => {
      if (activeId !== playerId && (isPlayingRef.current || playerRef.current?.playing)) {
        if (playerRef.current) {
          try {
            playerRef.current.pause();
          } catch {}
        }
        isPlayingRef.current = false;
        setIsPlaying(false);
      }
    });

    return () => {
      unsubscribe();
      destroyPlayer();
      audioPlaybackManager.releasePlayback(playerId);
    };
  }, [playerId, destroyPlayer]);

  // تجهيز المشغل وربط المستمعات
  const getOrCreatePlayer = useCallback((): AudioPlayer => {
    if (playerRef.current) {
      return playerRef.current;
    }

    const player = createAudioPlayer({ uri: audioUrl }, { updateInterval: 100 });
    try {
      player.loop = false;
      player.shouldCorrectPitch = true;
    } catch {}

    const sub = player.addListener('playbackStatusUpdate', (status: AudioStatus) => {
      if (status.isLoaded) {
        setIsLoading(false);
        isPlayingRef.current = status.playing;
        setIsPlaying(status.playing);
        setCurrentTime(status.currentTime);
        if (status.duration && status.duration > 0) {
          setDuration(status.duration);
        }
        // انتهاء المقطع الصوتي - إيقاف تام بدون إعادة تشغيل (No auto-loop)
        if (
          status.didJustFinish ||
          (status.duration > 0 && status.currentTime >= status.duration - 0.05 && !status.playing)
        ) {
          try {
            player.pause();
          } catch {}
          isPlayingRef.current = false;
          setIsPlaying(false);
          setCurrentTime(0);
          try {
            void player.seekTo(0);
          } catch {}
          audioPlaybackManager.releasePlayback(playerId);
        }
      } else if (status.isBuffering) {
        setIsLoading(true);
      }
    });

    listenerSubRef.current = sub;
    playerRef.current = player;
    return player;
  }, [audioUrl, playerId]);

  // تشغيل / إيقاف مؤقت فوري
  const togglePlayPause = useCallback(async () => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}

    const existingPlayer = playerRef.current;
    const currentlyPlaying = isPlayingRef.current || Boolean(existingPlayer?.playing);

    if (currentlyPlaying && existingPlayer) {
      try {
        existingPlayer.pause();
      } catch {}
      isPlayingRef.current = false;
      setIsPlaying(false);
      audioPlaybackManager.releasePlayback(playerId);
      return;
    }

    const player = getOrCreatePlayer();

    // إيقاف أي رسالة صوتية أخرى شغالة فوراً وبشكل لحظي متزامن بدون تأخير
    audioPlaybackManager.claimPlayback(playerId, player, () => {
      try {
        player.pause();
      } catch {}
      isPlayingRef.current = false;
      setIsPlaying(false);
    });

    try {
      // وضع الصوت الحصري (doNotMix) على iOS و Android لضمان عدم تداخل أي صوتين معاً
      await setAudioModeAsync({
        allowsRecording: false,
        playsInSilentMode: true,
        interruptionMode: 'doNotMix',
      });
    } catch {}

    // إذا كان المقطع عند النهاية، أعد البدء من البداية
    const effectiveDuration = player.duration > 0 ? player.duration : duration;
    const effectiveCurrentTime = player.currentTime > 0 ? player.currentTime : currentTime;
    if (effectiveDuration > 0 && effectiveCurrentTime >= effectiveDuration - 0.15) {
      try {
        await player.seekTo(0);
      } catch {}
      setCurrentTime(0);
    }

    const currentSpeed = SPEEDS[speedIndexRef.current];
    try {
      player.loop = false;
      player.shouldCorrectPitch = true;
      player.setPlaybackRate(currentSpeed, 'high');
    } catch {}

    player.play();
    isPlayingRef.current = true;
    setIsPlaying(true);
  }, [getOrCreatePlayer, duration, currentTime, playerId]);

  // تبديل سرعة التشغيل (1x, 1.5x, 2x)
  const handleToggleSpeed = useCallback(() => {
    try {
      void Haptics.selectionAsync();
    } catch {}

    const nextIndex = (speedIndexRef.current + 1) % SPEEDS.length;
    speedIndexRef.current = nextIndex;
    setSpeedIndex(nextIndex);
    const newSpeed = SPEEDS[nextIndex];

    if (playerRef.current) {
      try {
        playerRef.current.shouldCorrectPitch = true;
        playerRef.current.setPlaybackRate(newSpeed, 'high');
      } catch (err) {
        console.warn('[VoiceMessagePlayer] Error setting playback rate:', err);
      }
    }
  }, []);

  // النقر على شريط التموج للتخطي (Seek)
  const handleWaveformPress = useCallback(
    (e: GestureResponderEvent) => {
      if (waveformWidth <= 0 || duration <= 0) return;
      const touchX = e.nativeEvent.locationX;
      const progressRatio = Math.max(0, Math.min(1, touchX / waveformWidth));
      const targetSec = progressRatio * duration;

      try {
        void Haptics.selectionAsync();
      } catch {}

      const player = getOrCreatePlayer();
      void player.seekTo(targetSec);
      setCurrentTime(targetSec);
    },
    [waveformWidth, duration, getOrCreatePlayer]
  );

  // نسبة التقدم من 0 إلى 1
  const progressRatio = duration > 0 ? Math.min(1, Math.max(0, currentTime / duration)) : 0;
  const activeBarCount = Math.floor(progressRatio * TOTAL_WAVEFORM_BARS);

  // أنماط الألوان حسب هوية المرسل (أنا أم الآخرين)
  const playBtnBg = isMe ? '#FFFFFF' : silaTheme.primary;
  const playBtnIconColor = isMe ? silaTheme.primary : '#FFFFFF';
  const barActiveColor = isMe ? '#FFFFFF' : silaTheme.primary;
  const barInactiveColor = isMe
    ? 'rgba(255, 255, 255, 0.35)'
    : isDark
      ? '#3F564C'
      : '#CBD5E1';
  const timerTextColor = isMe
    ? 'rgba(255, 255, 255, 0.85)'
    : isDark
      ? '#94A3B8'
      : '#64748B';
  const speedBtnBg = isMe
    ? 'rgba(255, 255, 255, 0.2)'
    : isDark
      ? '#2D3F36'
      : '#E2E8F0';
  const speedBtnTextColor = isMe ? '#FFFFFF' : isDark ? '#E2E8F0' : '#334155';

  return (
    <View style={styles.container}>
      {/* زر التشغيل / الإيقاف المؤقت الدائري بنمط واتساب */}
      <TouchableOpacity
        onPress={togglePlayPause}
        activeOpacity={0.8}
        hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
        style={[styles.playBtn, { backgroundColor: playBtnBg }]}
        accessibilityRole="button"
        accessibilityLabel={isPlaying ? 'إيقاف مؤقت' : 'تشغيل التسجيل الصوتي'}>
        {isLoading ? (
          <ActivityIndicator size="small" color={playBtnIconColor} />
        ) : (
          <Ionicons
            name={isPlaying ? 'pause' : 'play'}
            size={18}
            color={playBtnIconColor}
            style={isPlaying ? undefined : { marginLeft: 2 }}
          />
        )}
      </TouchableOpacity>

      {/* منطقة التموجات الصوتية والوقت */}
      <View style={styles.bodyWrap}>
        {/* شريط أعمدة التموجات الصوتية التفاعلية */}
        <TouchableOpacity
          activeOpacity={1}
          onPress={handleWaveformPress}
          onLayout={(e) => {
            const w = e.nativeEvent.layout.width;
            if (w > 0) setWaveformWidth(w);
          }}
          style={styles.waveformContainer}>
          {normalizedBars.map((height, idx) => {
            const isActive = idx <= activeBarCount;
            return (
              <View
                key={idx}
                style={[
                  styles.waveformBar,
                  {
                    height,
                    backgroundColor: isActive ? barActiveColor : barInactiveColor,
                  },
                ]}
              />
            );
          })}
        </TouchableOpacity>

        {/* سطر بيانات الوقت وسرعة التشغيل */}
        <View style={styles.infoRow}>
          <Text style={[styles.timeText, { color: timerTextColor }]}>
            {isPlaying || currentTime > 0
              ? `${formatTime(currentTime)} / ${formatTime(duration)}`
              : formatTime(duration)}
          </Text>

          {/* زر تبديل السرعة 1x, 1.5x, 2x */}
          <TouchableOpacity
            onPress={handleToggleSpeed}
            activeOpacity={0.7}
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            style={[styles.speedBtn, { backgroundColor: speedBtnBg }]}
            accessibilityRole="button"
            accessibilityLabel={`سرعة الصوت ${SPEEDS[speedIndex]}x`}>
            <Text style={[styles.speedText, { color: speedBtnTextColor }]}>
              {`${SPEEDS[speedIndex]}x`}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* شارة أيقونة الصوت */}
      <View
        style={[
          styles.micBadge,
          {
            backgroundColor: isMe
              ? 'rgba(255, 255, 255, 0.2)'
              : isDark
                ? '#24352C'
                : '#E7F5EC',
          },
        ]}>
        <Ionicons
          name="mic"
          size={14}
          color={isMe ? '#FFFFFF' : silaTheme.primary}
        />
      </View>
    </View>
  );
});

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingHorizontal: 2,
    minWidth: 230,
    maxWidth: 290,
  },
  playBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.12,
    shadowRadius: 2,
    elevation: 2,
  },
  bodyWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  waveformContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    height: 28,
    paddingVertical: 2,
  },
  waveformBar: {
    width: 3,
    borderRadius: 2,
    marginHorizontal: 0.5,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 4,
  },
  timeText: {
    fontSize: 11,
    fontWeight: '500',
    fontVariant: ['tabular-nums'],
  },
  speedBtn: {
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: 10,
    marginLeft: 6,
  },
  speedText: {
    fontSize: 10,
    fontWeight: '700',
    fontVariant: ['tabular-nums'],
  },
  micBadge: {
    width: 26,
    height: 26,
    borderRadius: 13,
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 8,
  },
});
