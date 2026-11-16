import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
  Modal,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import * as WebBrowser from 'expo-web-browser';

import { silaTheme } from '@/constants/theme';
import { getVideoThumbnail } from '@/lib/utils/videoThumbnails';

export interface PendingMediaAttachment {
  uri: string;
  type: 'image' | 'video';
  duration?: number;
  thumbnailUri?: string;
}

interface MediaCaptionModalProps {
  visible: boolean;
  media: PendingMediaAttachment | null;
  initialCaption?: string;
  sending?: boolean;
  onClose: () => void;
  onSend: (media: PendingMediaAttachment, caption: string) => void;
}

export function MediaCaptionModal({
  visible,
  media,
  initialCaption = '',
  sending = false,
  onClose,
  onSend,
}: MediaCaptionModalProps) {
  const insets = useSafeAreaInsets();
  const [caption, setCaption] = useState(initialCaption);
  const [videoThumbnail, setVideoThumbnail] = useState<string | null>(null);

  // تحديث نص الشرح والصورة المصغرة عند فتح المودال
  useEffect(() => {
    if (visible && media) {
      setCaption(initialCaption || '');
      if (media.type === 'video') {
        if (media.thumbnailUri) {
          setVideoThumbnail(media.thumbnailUri);
        } else {
          void getVideoThumbnail(media.uri).then((thumb) => {
            if (thumb) setVideoThumbnail(thumb);
          });
        }
      } else {
        setVideoThumbnail(null);
      }
    }
  }, [visible, media, initialCaption]);

  if (!media) return null;

  const handleSend = () => {
    if (sending) return;
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    Keyboard.dismiss();
    onSend(media, caption.trim());
  };

  const handleClose = () => {
    if (sending) return;
    try {
      void Haptics.selectionAsync();
    } catch {}
    Keyboard.dismiss();
    onClose();
  };

  const handlePlayVideoPreview = async () => {
    if (media.type !== 'video' || !media.uri) return;
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      await WebBrowser.openBrowserAsync(media.uri);
    } catch (err) {
      console.warn('[MediaCaptionModal] Error playing video preview:', err);
    }
  };

  const formattedDuration =
    media.duration && media.duration > 0
      ? `${Math.floor(media.duration / 60)}:${Math.floor(media.duration % 60)
          .toString()
          .padStart(2, '0')}`
      : null;

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent={false}
      statusBarTranslucent
      onRequestClose={handleClose}>
      <StatusBar barStyle="light-content" backgroundColor="#0B141B" />
      <View style={styles.container}>
        {/* الشريط العلوي مع زر الإغلاق والتفاصيل */}
        <View style={[styles.header, { paddingTop: Math.max(insets.top, 16) }]}>
          <TouchableOpacity
            onPress={handleClose}
            style={styles.closeBtn}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
            accessibilityRole="button"
            accessibilityLabel="إغلاق المعاينة">
            <Ionicons name="close" size={24} color="#FFFFFF" />
          </TouchableOpacity>

          <View style={styles.headerInfo}>
            <Text style={styles.headerTitle}>
              {media.type === 'video' ? 'معاينة مقطع الفيديو' : 'معاينة الصورة'}
            </Text>
            {formattedDuration && (
              <View style={styles.durationBadge}>
                <Ionicons name="videocam" size={12} color="#FFFFFF" />
                <Text style={styles.durationText}>{formattedDuration}</Text>
              </View>
            )}
          </View>

          <View style={styles.headerSpacer} />
        </View>

        {/* مساحة عرض الوسائط (صورة أو فيديو) */}
        <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
          <View style={styles.previewArea}>
            {media.type === 'video' ? (
              <TouchableOpacity
                activeOpacity={0.9}
                onPress={handlePlayVideoPreview}
                style={styles.videoContainer}>
                <Image
                  source={{ uri: videoThumbnail || media.uri }}
                  style={styles.mediaImage}
                  contentFit="contain"
                  transition={180}
                />
                <View style={styles.playOverlay}>
                  <View style={styles.playButtonCircle}>
                    <Ionicons name="play" size={32} color="#FFFFFF" style={{ marginLeft: 3 }} />
                  </View>
                  <Text style={styles.videoHintText}>انقر لمعاينة الفيديو</Text>
                </View>
              </TouchableOpacity>
            ) : (
              <Image
                source={{ uri: media.uri }}
                style={styles.mediaImage}
                contentFit="contain"
                transition={200}
              />
            )}
          </View>
        </TouchableWithoutFeedback>

        {/* الشريط السفلي لإضافة الشرح والإرسال بنمط واتساب */}
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}>
          <View
            style={[
              styles.captionBarContainer,
              { paddingBottom: Math.max(insets.bottom, 14) },
            ]}>
            <View style={styles.captionInputWrapper}>
              <TextInput
                value={caption}
                onChangeText={setCaption}
                placeholder="إضافة شرح..."
                placeholderTextColor="rgba(255, 255, 255, 0.55)"
                multiline
                maxLength={2000}
                editable={!sending}
                textAlign="right"
                textAlignVertical="center"
                style={styles.captionInput}
              />
            </View>

            {/* زر الإرسال الأخضر الدائري المميز */}
            <TouchableOpacity
              onPress={handleSend}
              disabled={sending}
              activeOpacity={0.8}
              style={[
                styles.sendBtn,
                { backgroundColor: sending ? '#059669' : silaTheme.primary },
              ]}
              accessibilityRole="button"
              accessibilityLabel="إرسال الوسائط">
              {sending ? (
                <ActivityIndicator size="small" color="#FFFFFF" />
              ) : (
                <Ionicons
                  name="send"
                  size={19}
                  color="#FFFFFF"
                  style={{ transform: [{ scaleX: -1 }] }}
                />
              )}
            </TouchableOpacity>
          </View>
        </KeyboardAvoidingView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0B141B',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingBottom: 12,
    zIndex: 10,
    backgroundColor: 'rgba(11, 20, 27, 0.85)',
  },
  closeBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(255, 255, 255, 0.12)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerInfo: {
    alignItems: 'center',
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  headerTitle: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  durationBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(255, 255, 255, 0.18)',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  durationText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  headerSpacer: {
    width: 38,
  },
  previewArea: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#0B141B',
  },
  mediaImage: {
    width: '100%',
    height: '100%',
  },
  videoContainer: {
    width: '100%',
    height: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },
  playOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0, 0, 0, 0.35)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  playButtonCircle: {
    width: 68,
    height: 68,
    borderRadius: 34,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    borderWidth: 2,
    borderColor: 'rgba(255, 255, 255, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  videoHintText: {
    color: 'rgba(255, 255, 255, 0.85)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  captionBarContainer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    paddingHorizontal: 12,
    paddingTop: 10,
    backgroundColor: 'rgba(11, 20, 27, 0.95)',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255, 255, 255, 0.12)',
    gap: 10,
  },
  captionInputWrapper: {
    flex: 1,
    minHeight: 44,
    maxHeight: 110,
    backgroundColor: '#1F2C34',
    borderRadius: 22,
    paddingHorizontal: 16,
    paddingVertical: Platform.OS === 'ios' ? 10 : 6,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.08)',
    justifyContent: 'center',
  },
  captionInput: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Regular',
    fontSize: 14.5,
    lineHeight: 20,
    textAlign: 'right',
  },
  sendBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3,
    elevation: 3,
  },
});
