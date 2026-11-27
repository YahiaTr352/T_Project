import { InvitationGradientButton } from '@/components/invitations/InvitationPrimitives';
import { getInvitationPalette } from '@/components/invitations/invitationTheme';
import { ImageViewerModal, type ViewerImage } from '@/components/ui/zoomable-image';
import type { ThemeColors } from '@/constants/theme';
import { creativePreviewImage, type CreativeTemplate } from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { composerStyles as styles } from './composerStyles';

interface CreativePickerModalProps {
  visible: boolean;
  onClose: () => void;
  colors: ThemeColors;
  cardBg: string;
  border: string;
  muted: string;
  invitationTypeName?: string;
  loading: boolean;
  creatives: CreativeTemplate[];
  tempCreativeId: number | null;
  onSelect: (id: number | null) => void;
  onConfirm: () => void;
}

export function CreativePickerModal({
  visible,
  onClose,
  invitationTypeName,
  loading,
  creatives,
  tempCreativeId,
  onSelect,
  onConfirm,
}: CreativePickerModalProps) {
  const isDark = useColorScheme() === 'dark';
  const palette = getInvitationPalette(isDark);
  const [preview, setPreview] = useState<ViewerImage | null>(null);
  const [failedImages, setFailedImages] = useState<string[]>([]);
  const closePreview = () => setPreview(null);

  useEffect(() => {
    if (!visible) setFailedImages([]);
  }, [visible]);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={preview ? closePreview : onClose}>
      {preview ? (
        <ImageViewerModal visible embedded images={[preview]} showDownload={false} onClose={closePreview} />
      ) : (
        <View style={[styles.previewOverlay, { backgroundColor: palette.overlay }]}>
          <View style={[styles.previewCard, { backgroundColor: palette.surface, borderColor: palette.border, maxHeight: '88%' }]}>
            <Text style={[styles.previewTitleText, { color: palette.text }]}>قوالب الصور</Text>
            {invitationTypeName ? (
              <Text style={[styles.sectionLabel, { color: palette.primary, textAlign: 'center', marginTop: 0 }]}>
                صور {invitationTypeName}
              </Text>
            ) : null}
            <ScrollView style={styles.previewBody} showsVerticalScrollIndicator={false}>
              {loading ? (
                <ActivityIndicator color={palette.primary} style={{ marginVertical: 20 }} />
              ) : (
                <View style={{ marginTop: 8 }}>
                  <View style={styles.ctGrid}>
                    {creatives.map((creative) => {
                      const selected = tempCreativeId === creative.id;
                      const name = creative.name || 'قالب صورة';
                      const image = creativePreviewImage(creative, failedImages);
                      return (
                        <View key={creative.id}>
                          <TouchableOpacity
                            accessibilityRole="button"
                            accessibilityLabel={name}
                            accessibilityState={{ selected }}
                            onPress={() => onSelect(selected ? null : creative.id)}
                            style={[
                              styles.bgThumb,
                              {
                                backgroundColor: palette.surfaceAlt,
                                borderColor: selected ? palette.primary : palette.border,
                                borderWidth: selected ? 2.5 : 1,
                              },
                            ]}>
                            {image ? (
                              <Image
                                source={image}
                                style={styles.bgThumbImg}
                                contentFit="contain"
                                onError={() => setFailedImages((previous) => [...previous, image.uri])}
                              />
                            ) : null}
                            {creative.name ? (
                              <View style={styles.bgNameBadge}>
                                <Text style={styles.bgNameBadgeText} numberOfLines={1}>{creative.name}</Text>
                              </View>
                            ) : null}
                            {selected ? (
                              <View style={[styles.bgCheck, { backgroundColor: palette.primary }]}>
                                <Ionicons name="checkmark" size={14} color="#FFFFFF" />
                              </View>
                            ) : null}
                          </TouchableOpacity>
                          {image ? (
                            <Pressable
                              accessibilityRole="button"
                              accessibilityLabel={`تكبير ${name}`}
                              hitSlop={4}
                              style={pickerStyles.expand}
                              onPress={() => setPreview({ ...image, title: name })}>
                              <Ionicons name="expand-outline" size={16} color="#fff" />
                            </Pressable>
                          ) : null}
                        </View>
                      );
                    })}
                  </View>
                  {creatives.length === 0 ? (
                    <Text style={[styles.emptyCreativeText, { color: palette.muted }]}>
                      لا توجد قوالب معتمدة لهذا النوع من الدعوات.
                    </Text>
                  ) : null}
                </View>
              )}
            </ScrollView>
            <View style={styles.modalFooterRow}>
              <Pressable onPress={onClose} style={[styles.modalCancelBtn, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}>
                <Text style={[styles.modalCancelText, { color: palette.textSecondary }]}>إلغاء</Text>
              </Pressable>
              <View style={styles.modalConfirmBtn}>
                <InvitationGradientButton
                  label={tempCreativeId !== null ? 'تأكيد الاختيار' : 'متابعة بدون قالب'}
                  disabled={loading}
                  onPress={onConfirm}
                  palette={palette}
                />
              </View>
            </View>
          </View>
        </View>
      )}
    </Modal>
  );
}

const pickerStyles = StyleSheet.create({
  expand: {
    position: 'absolute',
    top: 6,
    left: 6,
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(15, 36, 28, 0.78)',
  },
});
