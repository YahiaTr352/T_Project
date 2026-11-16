import React from 'react';
import { Modal, StyleSheet, View, Pressable, Dimensions } from 'react-native';
import { Image } from 'expo-image';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import * as WebBrowser from 'expo-web-browser';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

interface MediaLightboxProps {
  visible: boolean;
  mediaUrl: string | null;
  isVideo?: boolean;
  onClose: () => void;
  title?: string;
}

export default function MediaLightbox({ visible, mediaUrl, isVideo = false, onClose, title }: MediaLightboxProps) {
  if (!visible || !mediaUrl) return null;

  const handlePlayVideo = async () => {
    if (isVideo && mediaUrl) {
      try {
        await WebBrowser.openBrowserAsync(mediaUrl);
      } catch (err) {
        console.error('Error opening video in WebBrowser:', err);
      }
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

        <View style={styles.contentContainer}>
          <Pressable style={styles.closeBtn} onPress={onClose} hitSlop={12}>
            <IconSymbol name="xmark" size={20} color="#fff" />
          </Pressable>

          <View style={styles.mediaContainer}>
            {isVideo ? (
              <View style={styles.videoPlaceholder}>
                <IconSymbol name="play.circle.fill" size={80} color="#10b981" />
                <ThemedText style={styles.videoTitle}>
                  {title || 'فيديو توضيحي'}
                </ThemedText>
                <Pressable style={styles.playBtn} onPress={handlePlayVideo}>
                  <ThemedText style={styles.playBtnText}>تشغيل الفيديو</ThemedText>
                  <IconSymbol name="play.fill" size={16} color="#fff" />
                </Pressable>
              </View>
            ) : (
              <Image
                source={{ uri: mediaUrl }}
                style={styles.image}
                contentFit="contain"
              />
            )}
          </View>

          {title && !isVideo && (
            <View style={styles.titleContainer}>
              <ThemedText style={styles.title}>{title}</ThemedText>
            </View>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.9)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  backdrop: {
    ...StyleSheet.absoluteFill,
  },
  contentContainer: {
    width: SCREEN_WIDTH * 0.92,
    height: SCREEN_HEIGHT * 0.8,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  closeBtn: {
    position: 'absolute',
    top: -10,
    right: 10,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.15)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  mediaContainer: {
    width: '100%',
    height: '90%',
    borderRadius: 20,
    overflow: 'hidden',
    justifyContent: 'center',
    alignItems: 'center',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  videoPlaceholder: {
    padding: 30,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.05)',
    alignItems: 'center',
    justifyContent: 'center',
    width: '100%',
    height: 300,
    gap: 15,
  },
  videoTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    color: '#fff',
    textAlign: 'center',
  },
  playBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#10b981',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 14,
  },
  playBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#fff',
  },
  titleContainer: {
    position: 'absolute',
    bottom: -30,
    paddingHorizontal: 20,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    color: '#fff',
    textAlign: 'center',
  },
});
