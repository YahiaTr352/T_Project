import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import {
  useAudioPlayer,
  useAudioPlayerStatus,
  setAudioModeAsync,
} from 'expo-audio';
import * as Haptics from 'expo-haptics';
import { Ionicons } from '@expo/vector-icons';
import { aiGetTts } from '@/lib/api/ai';
import { silaTheme as T } from '@/constants/theme';

interface AudioMessagePlayerProps {
  messageId?: number;
  text?: string;
  audioUri?: string;
  isDark?: boolean;
  isUserMessage?: boolean;
}

export function AudioMessagePlayer({
  messageId,
  text,
  audioUri,
  isDark = false,
  isUserMessage = false,
}: AudioMessagePlayerProps) {
  const [loading, setLoading] = useState(false);
  const [hasStarted, setHasStarted] = useState(false);
  const [audioUrl, setAudioUrl] = useState<string | null>(audioUri ?? null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const player = useAudioPlayer(null, { updateInterval: 250 });
  const status = useAudioPlayerStatus(player);

  const [waveAnims] = useState(() => [
    new Animated.Value(0.3),
    new Animated.Value(0.7),
    new Animated.Value(1),
    new Animated.Value(0.5),
    new Animated.Value(0.8),
  ]);

  // Reset to start when playback finishes
  useEffect(() => {
    if (status.didJustFinish) {
      void player.seekTo(0);
    }
  }, [status.didJustFinish, player]);

  // Equalizer wave animation loop
  useEffect(() => {
    let animLoop: Animated.CompositeAnimation | null = null;
    if (status.playing) {
      const createBarAnim = (anim: Animated.Value, delay: number) => {
        return Animated.sequence([
          Animated.delay(delay),
          Animated.loop(
            Animated.sequence([
              Animated.timing(anim, {
                toValue: 1,
                duration: 250,
                useNativeDriver: false,
              }),
              Animated.timing(anim, {
                toValue: 0.2,
                duration: 250,
                useNativeDriver: false,
              }),
            ])
          ),
        ]);
      };

      animLoop = Animated.parallel(
        waveAnims.map((anim, idx) => createBarAnim(anim, idx * 80))
      );
      animLoop.start();
    } else {
      waveAnims.forEach((anim) => {
        anim.stopAnimation();
        Animated.timing(anim, {
          toValue: 0.35,
          duration: 200,
          useNativeDriver: false,
        }).start();
      });
    }

    return () => {
      if (animLoop) animLoop.stop();
    };
  }, [status.playing, waveAnims]);

  const handlePlayToggle = useCallback(async () => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setErrorMessage(null);

      // 1. If currently playing -> pause
      if (status.playing) {
        player.pause();
        return;
      }

      // 2. If already loaded & paused -> resume
      if (hasStarted && (status.isLoaded || player.isLoaded)) {
        player.play();
        return;
      }

      // 3. If direct local audioUri is provided (e.g. user's own recorded voice message)
      if (audioUri) {
        await setAudioModeAsync({
          playsInSilentMode: true,
          interruptionMode: 'duckOthers',
        });
        player.replace({ uri: audioUri });
        player.play();
        setHasStarted(true);
        return;
      }

      // 4. On-demand AI voice synthesis (TTS) - 100% token savings until requested
      setLoading(true);

      const res = await aiGetTts(messageId, text);
      if (!res.ok) {
        setErrorMessage(res.message || 'تعذر توليد الصوت');
        setLoading(false);
        return;
      }

      if (!res.audio_url) {
        setErrorMessage('تعذر الحصول على ملف الصوت');
        setLoading(false);
        return;
      }

      await setAudioModeAsync({
        playsInSilentMode: true,
        interruptionMode: 'duckOthers',
      });

      setAudioUrl(res.audio_url);
      player.replace({ uri: res.audio_url });
      player.play();
      setHasStarted(true);
    } catch (e) {
      setErrorMessage('حدث خطأ أثناء تشغيل الصوت');
    } finally {
      setLoading(false);
    }
  }, [audioUri, hasStarted, messageId, player, status.isLoaded, status.playing, text]);

  const formatTime = (seconds: number) => {
    const totalSecs = Math.floor(seconds || 0);
    const mins = Math.floor(totalSecs / 60);
    const secs = totalSecs % 60;
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  };

  const positionSec = status.currentTime ?? 0;
  const durationSec = status.duration ?? 0;
  const isPlaying = status.playing ?? false;
  const progressPct = durationSec > 0 ? Math.min(100, (positionSec / durationSec) * 100) : 0;

  // Render initial idle state: sleek, inviting on-demand pill button
  if (!hasStarted && !loading) {
    return (
      <View style={styles.container}>
        <TouchableOpacity
          style={[
            styles.idlePill,
            isUserMessage
              ? {
                  backgroundColor: 'rgba(255, 255, 255, 0.22)',
                  borderColor: 'rgba(255, 255, 255, 0.45)',
                }
              : {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.95)' : '#f0fdf4',
                  borderColor: isDark ? 'rgba(51, 65, 85, 0.8)' : '#bbf7d0',
                },
          ]}
          activeOpacity={0.8}
          onPress={handlePlayToggle}
        >
          <View
            style={[
              styles.speakerIconWrap,
              {
                backgroundColor: isUserMessage
                  ? 'rgba(255, 255, 255, 0.3)'
                  : T.primary + '20',
              },
            ]}
          >
            <Ionicons
              name={isUserMessage ? 'play' : 'volume-medium-outline'}
              size={15}
              color={isUserMessage ? '#ffffff' : T.primary}
            />
          </View>
          <Text
            style={[
              styles.idleText,
              {
                color: isUserMessage
                  ? '#ffffff'
                  : isDark
                  ? '#f1f5f9'
                  : '#065f46',
              },
            ]}
          >
            {isUserMessage ? 'تشغيل التسجيل الصوتي' : 'استمع للإجابة'}
          </Text>
          <View
            style={[
              styles.voiceBadge,
              isUserMessage && { backgroundColor: 'rgba(255, 255, 255, 0.25)' },
            ]}
          >
            <Text
              style={[
                styles.voiceBadgeText,
                isUserMessage && { color: '#ffffff' },
              ]}
            >
              {isUserMessage ? 'صوتي' : 'صوت ذكي'}
            </Text>
          </View>
        </TouchableOpacity>
        {errorMessage && <Text style={styles.errorText}>{errorMessage}</Text>}
      </View>
    );
  }

  // Render loading state (fetching TTS from server on demand)
  if (loading) {
    return (
      <View style={styles.container}>
        <View
          style={[
            styles.loadingPill,
            isUserMessage
              ? {
                  backgroundColor: 'rgba(255, 255, 255, 0.25)',
                  borderColor: 'rgba(255, 255, 255, 0.4)',
                }
              : {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.95)' : '#ecfdf5',
                  borderColor: isDark ? '#334155' : '#a7f3d0',
                },
          ]}
        >
          <ActivityIndicator size="small" color={isUserMessage ? '#ffffff' : T.primary} />
          <Text
            style={[
              styles.loadingText,
              { color: isUserMessage ? '#ffffff' : isDark ? '#cbd5e1' : '#065f46' },
            ]}
          >
            جاري إعداد الصوت الطبيعي...
          </Text>
        </View>
      </View>
    );
  }

  // Render active player (playing / paused with wave animation and progress bar)
  return (
    <View style={styles.container}>
      <View
        style={[
          styles.playerCard,
          isUserMessage
            ? {
                backgroundColor: 'rgba(0, 0, 0, 0.18)',
                borderColor: 'rgba(255, 255, 255, 0.3)',
              }
            : {
                backgroundColor: isDark ? '#0f172a' : '#f8fafc',
                borderColor: isDark ? '#334155' : '#e2e8f0',
              },
        ]}
      >
        <View style={styles.playerHeader}>
          {/* Play/Pause Button */}
          <TouchableOpacity
            style={[
              styles.playBtn,
              { backgroundColor: isUserMessage ? '#ffffff' : T.primary },
            ]}
            activeOpacity={0.8}
            onPress={handlePlayToggle}
          >
            <Ionicons
              name={isPlaying ? 'pause' : 'play'}
              size={15}
              color={isUserMessage ? T.primary : '#ffffff'}
              style={!isPlaying ? { marginLeft: 2 } : undefined}
            />
          </TouchableOpacity>

          {/* Equalizer Waveform Bars */}
          <View style={styles.equalizerWrap}>
            {waveAnims.map((anim, idx) => (
              <Animated.View
                key={idx}
                style={[
                  styles.eqBar,
                  {
                    backgroundColor: isUserMessage
                      ? '#ffffff'
                      : isPlaying
                      ? T.primary
                      : isDark
                      ? '#64748b'
                      : '#94a3b8',
                    height: anim.interpolate({
                      inputRange: [0, 1],
                      outputRange: [4, 18],
                    }),
                  },
                ]}
              />
            ))}
          </View>

          {/* Time & Duration */}
          <View style={styles.timeWrap}>
            <Text
              style={[
                styles.timeText,
                {
                  color: isUserMessage
                    ? 'rgba(255, 255, 255, 0.95)'
                    : isDark
                    ? '#cbd5e1'
                    : '#475569',
                },
              ]}
            >
              {formatTime(positionSec)}
              {durationSec > 0 ? ` / ${formatTime(durationSec)}` : ''}
            </Text>
          </View>
        </View>

        {/* Progress Line */}
        <View
          style={[
            styles.progressTrack,
            {
              backgroundColor: isUserMessage
                ? 'rgba(255, 255, 255, 0.25)'
                : isDark
                ? '#1e293b'
                : '#e2e8f0',
            },
          ]}
        >
          <View
            style={[
              styles.progressBar,
              {
                width: `${progressPct}%`,
                backgroundColor: isUserMessage ? '#ffffff' : T.primary,
              },
            ]}
          />
        </View>
      </View>

      {errorMessage && (
        <Text style={[styles.errorText, isUserMessage && { color: '#fef08a' }]}>
          {errorMessage}
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginTop: 8,
    width: '100%',
  },
  idlePill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 22,
    borderWidth: 1,
    gap: 8,
    alignSelf: 'flex-start',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 1,
  },
  speakerIconWrap: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  idleText: {
    fontSize: 12,
    fontWeight: '700',
  },
  voiceBadge: {
    backgroundColor: '#10b98120',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 10,
    marginLeft: 4,
  },
  voiceBadgeText: {
    fontSize: 9,
    fontWeight: '800',
    color: '#10b981',
  },
  loadingPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 22,
    borderWidth: 1,
    gap: 9,
    alignSelf: 'flex-start',
  },
  loadingText: {
    fontSize: 11,
    fontWeight: '700',
  },
  playerCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 9,
    gap: 7,
  },
  playerHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  playBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 3,
    elevation: 2,
  },
  equalizerWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    height: 20,
    paddingHorizontal: 4,
  },
  eqBar: {
    width: 3.5,
    borderRadius: 2,
  },
  timeWrap: {
    marginLeft: 'auto',
  },
  timeText: {
    fontSize: 11,
    fontWeight: '700',
    fontVariant: ['tabular-nums'],
  },
  progressTrack: {
    height: 4,
    borderRadius: 2,
    overflow: 'hidden',
    width: '100%',
  },
  progressBar: {
    height: '100%',
    borderRadius: 2,
  },
  errorText: {
    fontSize: 10,
    color: '#ef4444',
    marginTop: 4,
    fontWeight: '600',
  },
});
