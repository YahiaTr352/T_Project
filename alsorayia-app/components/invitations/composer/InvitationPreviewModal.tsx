import type { ThemeColors } from '@/constants/theme';
import { ThemedText } from '@/components/themed-text';
import { Image } from 'expo-image';
import {
  Modal,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { composerStyles as styles } from './composerStyles';

export interface PreviewImageSource {
  id: number;
  name?: string | null;
  image_url?: string | null;
}

interface InvitationPreviewModalProps {
  visible: boolean;
  onClose: () => void;
  colors: ThemeColors;
  muted: string;
  cardBg: string;
  previewCard: string | null;
  previewCreative: PreviewImageSource | null;
  previewBg: PreviewImageSource | null;
  attachedImage: { uri: string } | null;
  previewText: string;
  fontFamily: string;
}

export function InvitationPreviewModal({
  visible,
  onClose,
  colors,
  muted,
  cardBg,
  previewCard,
  previewCreative,
  previewBg,
  attachedImage,
  previewText,
  fontFamily,
}: InvitationPreviewModalProps) {
  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}>
      <View style={styles.previewOverlay}>
        <View style={[styles.previewCard, { backgroundColor: cardBg }]}>
          {previewCard ? (
            <Image
              source={{ uri: previewCard }}
              style={[styles.previewBg, { height: 300 }]}
              contentFit="contain"
            />
          ) : (previewCreative as any)?.image_url ? (
            <Image
              source={{ uri: (previewCreative as any).image_url }}
              style={styles.previewBg}
              contentFit="cover"
            />
          ) : previewBg?.image_url ? (
            <Image
              source={{ uri: previewBg.image_url }}
              style={styles.previewBg}
              contentFit="cover"
            />
          ) : attachedImage ? (
            <Image
              source={{ uri: attachedImage.uri }}
              style={styles.previewBg}
              contentFit="cover"
            />
          ) : null}
          {previewCard ? (
            <Text style={[styles.cardMergedHint, { color: '#059669' }]}>
              ✓ النص مدمج داخل التصميم — هكذا ستصل لكل مستلم باسمه
            </Text>
          ) : attachedImage ? (
            <Text style={[styles.cardMergedHint, { color: muted }]}>
              الصورة المرفقة ستُرسل بجانب النص كما هي
            </Text>
          ) : null}
          <Text style={[styles.previewTitleText, { color: colors.text }]}>معاينة الرسالة</Text>
          <ScrollView style={styles.previewBody}>
            <ThemedText style={[styles.previewText, { color: colors.text, fontFamily }]}>
              {previewText}
            </ThemedText>
          </ScrollView>
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={onClose}
            style={[styles.previewCloseBtn, { backgroundColor: colors.primary }]}>
            <Text style={styles.previewCloseText}>إغلاق</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}
