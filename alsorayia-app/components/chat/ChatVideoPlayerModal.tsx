import React from 'react';
import {
  Modal,
  StyleSheet,
  View,
  Pressable,
  Dimensions,
  TouchableOpacity,
} from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { Ionicons } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { silaTheme } from '@/constants/theme';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

interface ChatVideoPlayerModalProps {
  visible: boolean;
  videoUrl: string | null;
  duration?: number | null;
  onClose: () => void;
  senderName?: string;
}

export function ChatVideoPlayerModal({
  visible,
  videoUrl,
  duration,
  onClose,
  senderName,
}: ChatVideoPlayerModalProps) {
  if (!visible || !videoUrl) return null;

  const formattedDuration = duration
    ? `${Math.floor(duration)} ثانية`
    : 'أقل من 30 ثانية';

  const handlePlay = async () => {
    try {
      await WebBrowser.openBrowserAsync(videoUrl);
    } catch (err) {
      console.error('[ChatVideoPlayerModal] Error opening video:', err);
    }
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <Pressable style={styles.backdrop} onPress={onClose} />

        <View style={styles.cardContainer}>
          <TouchableOpacity style={styles.closeBtn} onPress={onClose} hitSlop={12}>
            <Ionicons name="close" size={24} color="#FFFFFF" />
          </TouchableOpacity>

          <View style={styles.content}>
            <View style={styles.iconCircle}>
              <Ionicons name="videocam" size={44} color={silaTheme.primary} />
            </View>

            <ThemedText style={styles.title}>
              مقطع فيديو من {senderName || 'عضو في النقاش'}
            </ThemedText>

            <View style={styles.durationBadge}>
              <Ionicons name="time-outline" size={15} color="#059669" />
              <ThemedText style={styles.durationText}>
                المدة: {formattedDuration}
              </ThemedText>
            </View>

            <ThemedText style={styles.hint}>
              المقطع محدد بـ 30 ثانية كحد أقصى للمحافظة على سرعة التطبيق وبيانات الاتصال.
            </ThemedText>

            <TouchableOpacity
              style={styles.playButton}
              activeOpacity={0.88}
              onPress={handlePlay}
            >
              <Ionicons name="play" size={20} color="#FFFFFF" />
              <ThemedText style={styles.playButtonText}>مشاهدة الفيديو</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  backdrop: {
    ...StyleSheet.absoluteFill,
  },
  cardContainer: {
    width: Math.min(SCREEN_WIDTH - 40, 360),
    backgroundColor: '#1E293B',
    borderRadius: 24,
    padding: 24,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.4,
    shadowRadius: 20,
    elevation: 10,
  },
  closeBtn: {
    position: 'absolute',
    top: 14,
    left: 14,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(255, 255, 255, 0.12)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  content: {
    alignItems: 'center',
    width: '100%',
    marginTop: 10,
  },
  iconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(45, 106, 79, 0.18)',
    borderWidth: 1.5,
    borderColor: silaTheme.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: '#FFFFFF',
    textAlign: 'center',
    marginBottom: 8,
  },
  durationBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#064E3B',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 12,
    marginBottom: 14,
  },
  durationText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#A7F3D0',
  },
  hint: {
    fontSize: 12,
    color: '#94A3B8',
    textAlign: 'center',
    lineHeight: 18,
    marginBottom: 20,
  },
  playButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: silaTheme.primary,
    paddingVertical: 12,
    paddingHorizontal: 28,
    borderRadius: 14,
    width: '100%',
  },
  playButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#FFFFFF',
  },
});
