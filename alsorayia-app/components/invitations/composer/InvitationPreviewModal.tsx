import { InvitationGradientButton } from '@/components/invitations/InvitationPrimitives';
import { getInvitationPalette } from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import { creativeArtworkSource, resolveCreativeArtworkUrl } from '@/lib/api/invitations';
import { Image } from 'expo-image';
import { useState } from 'react';
import {
  Modal,
  ScrollView,
  Text,
  View,
  useColorScheme,
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

type PreviewArtworkSource = { uri: string; kind: 'generated' | 'template' | 'background' | 'attachment' };

function PreviewArtwork({
  sources,
  muted,
  successColor,
  hasMessage,
}: {
  sources: PreviewArtworkSource[];
  muted: string;
  successColor: string;
  hasMessage: boolean;
}) {
  const [failedSources, setFailedSources] = useState<string[]>([]);
  const [displayWidth, setDisplayWidth] = useState(0);
  const [dimensions, setDimensions] = useState<{ uri: string; ratio: number } | null>(null);
  const source = sources.find((item) => !failedSources.includes(item.uri));

  if (!source) {
    return sources.length > 0 ? (
      <Text style={[styles.cardMergedHint, { color: muted }]} accessibilityLiveRegion="polite">
        تعذّر تحميل صورة المعاينة.
      </Text>
    ) : null;
  }

  const isGenerated = source.kind === 'generated';
  const generatedHeight = displayWidth && dimensions?.uri === source.uri
    ? Math.min(300, displayWidth / dimensions.ratio)
    : 220;
  const hint = isGenerated
    ? 'هذه معاينة لصورة الدعوة المرفقة مع الرسالة.'
    : source.kind === 'template' && failedSources.length > 0
      ? 'تعذّر تحميل الصورة الناتجة. هذه صورة القالب الأصلي.'
      : source.kind === 'attachment' ? (hasMessage ? 'ستُرسل الصورة مع النص' : 'ستُرسل الصورة وحدها') : null;

  return (
    <>
      <Image
        key={source.uri}
        accessibilityLabel="صورة معاينة الدعوة"
        source={creativeArtworkSource(source.uri)}
        style={isGenerated ? [styles.previewBg, { height: generatedHeight }] : styles.previewBg}
        onLayout={isGenerated ? (event) => setDisplayWidth(event.nativeEvent.layout.width) : undefined}
        onLoad={isGenerated ? ({ source: loaded }) => {
          if (loaded.width > 0 && loaded.height > 0) {
            setDimensions({ uri: source.uri, ratio: loaded.width / loaded.height });
          }
        } : undefined}
        contentFit={source.kind === 'background' || source.kind === 'attachment' ? 'cover' : 'contain'}
        onError={() => setFailedSources((previous) => [...previous, source.uri])}
      />
      {hint ? <Text style={[styles.cardMergedHint, { color: isGenerated ? successColor : muted }]}>{hint}</Text> : null}
    </>
  );
}

export function InvitationPreviewModal({
  visible,
  onClose,
  previewCard,
  previewCreative,
  previewBg,
  attachedImage,
  previewText,
  fontFamily,
}: InvitationPreviewModalProps) {
  const isDark = useColorScheme() === 'dark';
  const palette = getInvitationPalette(isDark);

  const sources: PreviewArtworkSource[] = [];
  if (previewCard) sources.push({ uri: resolveCreativeArtworkUrl(previewCard), kind: 'generated' });
  if (previewCreative?.image_url) sources.push({ uri: resolveCreativeArtworkUrl(previewCreative.image_url), kind: 'template' });
  if (previewBg?.image_url) sources.push({ uri: resolveCreativeArtworkUrl(previewBg.image_url), kind: 'background' });
  if (attachedImage) sources.push({ uri: attachedImage.uri, kind: 'attachment' });

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}>
      <View style={[styles.previewOverlay, { backgroundColor: palette.overlay }]}>
        <View style={[styles.previewCard, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          {visible ? (
            <PreviewArtwork
              sources={sources}
              muted={palette.muted}
              successColor={palette.successText}
              hasMessage={Boolean(previewText.trim())}
            />
          ) : null}
          <Text style={[styles.previewTitleText, { color: palette.text }]}>معاينة الرسالة</Text>
          <ScrollView style={styles.previewBody}>
            <ThemedText style={[styles.previewText, { color: palette.text, fontFamily, textAlign: 'right' }]}>
              {previewText}
            </ThemedText>
          </ScrollView>
          <InvitationGradientButton
            label="إغلاق"
            onPress={onClose}
            palette={palette}
          />
        </View>
      </View>
    </Modal>
  );
}
