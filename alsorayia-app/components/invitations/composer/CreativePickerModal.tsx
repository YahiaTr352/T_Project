import type { ThemeColors } from '@/constants/theme';
import { ImageViewerModal, type ViewerImage } from '@/components/ui/zoomable-image';
import { creativePreviewImage, type CreativeTemplate } from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
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
  visible, onClose, colors, cardBg, border, muted, invitationTypeName, loading, creatives,
  tempCreativeId, onSelect, onConfirm,
}: CreativePickerModalProps) {
  const [preview, setPreview] = useState<ViewerImage | null>(null);
  const [failedImages, setFailedImages] = useState<string[]>([]);
  const closePreview = () => setPreview(null);

  useEffect(() => {
    if (!visible) setFailedImages([]);
  }, [visible]);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={preview ? closePreview : onClose}>
      {preview ? <ImageViewerModal visible embedded images={[preview]} showDownload={false} onClose={closePreview} /> : (
      <View style={styles.previewOverlay}>
        <View style={[styles.previewCard, { backgroundColor: cardBg, maxHeight: '88%' }]}>
          <Text style={[styles.previewTitleText, { color: colors.text }]}>قوالب الصور</Text>
          {invitationTypeName ? (
            <Text style={[styles.sectionLabel, { color: colors.text }]}>صور {invitationTypeName}</Text>
          ) : null}
          <ScrollView style={styles.previewBody} showsVerticalScrollIndicator={false}>
            {loading ? (
              <ActivityIndicator color={colors.primary} style={{ marginVertical: 20 }} />
            ) : (
              <View style={{ marginTop: 12 }}>
                <View style={styles.ctGrid}>
                  {creatives.map((creative) => {
                    const selected = tempCreativeId === creative.id;
                    const name = creative.name || 'قالب صورة';
                    const image = creativePreviewImage(creative, failedImages);
                    return (
                      <View key={creative.id}>
                        <TouchableOpacity accessibilityRole="button" accessibilityLabel={name} accessibilityState={{ selected }}
                          onPress={() => onSelect(selected ? null : creative.id)}
                          style={[styles.bgThumb, { borderColor: selected ? colors.primary : border, borderWidth: selected ? 3 : 1 }]}>
                          {image ? <Image source={image} style={styles.bgThumbImg} contentFit="contain"
                            onError={() => setFailedImages((previous) => [...previous, image.uri])} /> : null}
                          {creative.name ? (
                            <View style={styles.bgNameBadge}>
                              <Text style={styles.bgNameBadgeText} numberOfLines={1}>{creative.name}</Text>
                            </View>
                          ) : null}
                          {selected ? (
                            <View style={[styles.bgCheck, { backgroundColor: colors.primary }]}>
                              <Ionicons name="checkmark" size={14} color="#FFFFFF" />
                            </View>
                          ) : null}
                        </TouchableOpacity>
                        {image ? (
                          <Pressable accessibilityRole="button" accessibilityLabel={`تكبير ${name}`} hitSlop={4} style={pickerStyles.expand}
                            onPress={() => setPreview({ ...image, title: name })}>
                            <Ionicons name="expand-outline" size={18} color="#fff" />
                          </Pressable>
                        ) : null}
                      </View>
                    );
                  })}
                </View>
                {creatives.length === 0 ? (
                  <Text style={[styles.emptyCreativeText, { color: muted }]}>لا توجد قوالب معتمدة لهذا النوع من الدعوات.</Text>
                ) : null}
              </View>
            )}
          </ScrollView>
          <View style={styles.modalFooterRow}>
            <Pressable onPress={onClose} style={[styles.modalCancelBtn, { borderColor: border }]}>
              <Text style={[styles.modalCancelText, { color: muted }]}>إلغاء</Text>
            </Pressable>
            <Pressable onPress={onConfirm} disabled={loading}
              style={[styles.modalConfirmBtn, { backgroundColor: colors.primary, opacity: loading ? 0.6 : 1 }]}>
              <Text style={[styles.modalConfirmText, { color: '#FFFFFF' }]}>
                {tempCreativeId !== null ? 'تأكيد الاختيار' : 'متابعة بدون قالب'}
              </Text>
            </Pressable>
          </View>
        </View>
      </View>
      )}
    </Modal>
  );
}

const pickerStyles = StyleSheet.create({
  expand: { position: 'absolute', top: 5, left: 5, width: 29, height: 29, borderRadius: 15,
    alignItems: 'center', justifyContent: 'center', backgroundColor: '#111827CC' },
});
