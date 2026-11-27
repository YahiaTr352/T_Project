import { InvitationSectionTitle } from '@/components/invitations/InvitationPrimitives';
import { cardShadowStyle, getInvitationPalette } from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import {
  creativeArtworkSource,
  resolveCreativeArtworkUrl,
  type CreativeTemplate,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import type { ReactNode } from 'react';
import {
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { composerStyles as styles } from './composerStyles';

interface DesignSectionProps {
  invitationTypeName?: string;
  colors: ThemeColors;
  isDark: boolean;
  cardBg: string;
  border: string;
  muted: string;
  title: string;
  body: string;
  fontFamily: string;
  selectedCreative: CreativeTemplate | null;
  artworkEdited?: boolean;
  artworkPreviewImage?: string;
  onEditArtwork?: () => void;
  attachedImage: { uri: string } | null;
  onOpenCreative: () => void;
  onClearCreative: () => void;
  onPickAttachment: () => void;
  onRemoveAttachment: () => void;
  children?: ReactNode;
}

export function DesignSection({
  invitationTypeName,
  isDark,
  title,
  body,
  fontFamily,
  selectedCreative,
  artworkEdited,
  artworkPreviewImage,
  onEditArtwork,
  attachedImage,
  onOpenCreative,
  onClearCreative,
  onPickAttachment,
  onRemoveAttachment,
  children,
}: DesignSectionProps) {
  const palette = getInvitationPalette(isDark);

  return (
    <>
      <InvitationSectionTitle title="الصورة والتصميم" palette={palette} />

      {invitationTypeName ? (
        <DesignEntryButton
          title="قوالب الصور"
          description={selectedCreative ? `القالب المختار: ${selectedCreative.name || 'بطاقة مصممة'}` : `صور ${invitationTypeName}`}
          icon="images-outline"
          gradient={palette.primaryGradient}
          onPress={onOpenCreative}
        />
      ) : null}

      {selectedCreative ? (
        <View style={[styles.creativeSelectedRow, { marginBottom: 12 }]}>
          {onEditArtwork ? (
            <TouchableOpacity
              accessibilityRole="button"
              onPress={onEditArtwork}
              style={[styles.miniBadgeOk, { backgroundColor: palette.pillBg, borderWidth: 1, borderColor: palette.pillBorder, paddingVertical: 9 }]}>
              <Ionicons name="pencil-outline" size={15} color={palette.pillText} />
              <ThemedText style={[styles.miniBadgeOkText, { color: palette.pillText }]}>
                {artworkEdited ? 'مراجعة تعديل الكتابة' : 'تعديل كتابة القالب'}
              </ThemedText>
            </TouchableOpacity>
          ) : null}
          <TouchableOpacity onPress={() => void onClearCreative()} style={styles.clearVisual}>
            <ThemedText style={[styles.clearVisualText, { color: palette.muted }]}>
              إزالة القالب
            </ThemedText>
          </TouchableOpacity>
        </View>
      ) : null}

      {selectedCreative && artworkPreviewImage ? (
        <Image
          source={artworkPreviewImage.startsWith('data:')
            ? { uri: artworkPreviewImage }
            : creativeArtworkSource(resolveCreativeArtworkUrl(artworkPreviewImage))}
          accessibilityLabel="معاينة صورة الدعوة"
          style={designStyles.artworkPreview}
          contentFit="contain"
        />
      ) : null}
      {children}

      <View
        style={[
          styles.card,
          cardShadowStyle(palette),
          {
            backgroundColor: palette.surface,
            borderColor: attachedImage ? palette.primary : palette.border,
            borderWidth: attachedImage ? 1.5 : 1,
          },
        ]}>
        <View style={styles.creativeHeaderRow}>
          <View style={[styles.creativeIconWrap, { backgroundColor: palette.surfaceSoft }]}>
            <Ionicons name="chatbubble-ellipses-outline" size={20} color={palette.primary} />
          </View>
          <View style={{ flex: 1 }}>
            <View style={styles.attachTitleRow}>
              <ThemedText style={[styles.creativeBtnTitle, { color: palette.text }]}>
                صورة مع الرسالة
              </ThemedText>
              <View style={[styles.attachStatusPill, { backgroundColor: attachedImage ? palette.successBg : palette.surfaceAlt }]}>
                <View style={[styles.attachDot, { backgroundColor: attachedImage ? palette.primary : palette.muted }]} />
                <Text style={[styles.attachStatusText, { color: attachedImage ? palette.successText : palette.muted }]}>
                  {attachedImage ? 'تم الإرفاق' : 'بدون مرفق'}
                </Text>
              </View>
            </View>
            <ThemedText style={[styles.hint, { color: palette.muted, marginBottom: 0, textAlign: 'right' }]}>
              يمكنك إرسال الصورة وحدها، أو إضافة نص يظهر أسفلها في واتساب.
            </ThemedText>
          </View>
        </View>

        {!attachedImage ? (
          <TouchableOpacity
            activeOpacity={0.82}
            style={[
              styles.dropzone,
              {
                borderColor: selectedCreative ? palette.warningText : palette.borderStrong,
                backgroundColor: palette.surfaceAlt,
              },
            ]}
            onPress={() => void onPickAttachment()}>
            <View style={[styles.dropzoneIcon, { backgroundColor: palette.pillBg }]}>
              <Ionicons name="image-outline" size={26} color={palette.primary} />
            </View>
            <Text style={[styles.dropzoneTitle, { color: palette.text }]}>اضغط لاختيار صورة من المعرض</Text>
            <Text style={[styles.dropzoneSub, { color: palette.muted }]}>JPG أو PNG — ستظهر أسفلها رسالتك في نفس الفقاعة</Text>
            {selectedCreative ? (
              <View style={[styles.conflictBar, { backgroundColor: palette.warningBg, borderColor: palette.warningBorder }]}>
                <Ionicons name="warning-outline" size={14} color={palette.warningText} />
                <Text style={[styles.conflictText, { color: palette.warningText }]}>اختيار صورة سيلغي قالب الصورة المختار</Text>
              </View>
            ) : null}
          </TouchableOpacity>
        ) : (
          <View>
            {/* معاينة حية بشكل فقاعة واتساب */}
            <View style={[styles.chatPreviewWrap, { backgroundColor: isDark ? '#0B141A' : '#ECE5DD' }]}>
              <View style={[styles.chatBubble, { backgroundColor: isDark ? '#005C4B' : '#DCF8C6' }]}>
                <Image source={{ uri: attachedImage.uri }} style={styles.chatImage} contentFit="cover" />
                <View style={styles.chatTextWrap}>
                  {(title.trim() || body.trim()) ? (
                    <>
                      {title.trim() ? (
                        <Text style={[styles.chatTitle, { fontFamily, color: isDark ? '#F1F5F9' : '#111B21' }]} numberOfLines={1}>{title.trim()}</Text>
                      ) : null}
                      {body.trim() ? (
                        <Text style={[styles.chatBody, { fontFamily, color: isDark ? '#E2E8F0' : '#111B21' }]} numberOfLines={3}>{body.trim().slice(0, 160)}</Text>
                      ) : null}
                    </>
                  ) : (
                    <Text style={[styles.chatPlaceholder, { color: isDark ? '#CBD5E1' : '#667781' }]}>نص رسالتك سيظهر هنا تحت الصورة…</Text>
                  )}
                  <View style={styles.chatMetaRow}>
                    <Text style={[styles.chatTime, { color: isDark ? '#CBD5E1' : '#667781' }]}>الآن</Text>
                    <Ionicons name="checkmark-done" size={15} color="#53BDEB" />
                  </View>
                </View>
              </View>
            </View>
            <View style={styles.attachCaptionRow}>
              <Ionicons name="checkmark-circle" size={14} color={palette.primary} />
              <Text style={[styles.attachCaption, { color: palette.muted }]}>هكذا ستصل: الصورة أولاً ثم النص تحتها في نفس الرسالة</Text>
            </View>
            <View style={styles.attachActions}>
              <TouchableOpacity
                style={[styles.attachSecondaryBtn, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}
                onPress={() => void onPickAttachment()}>
                <Ionicons name="refresh-outline" size={16} color={palette.primary} />
                <Text style={[styles.attachSecondaryText, { color: palette.primary }]}>تغيير الصورة</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.attachRemoveBtn, { backgroundColor: palette.dangerBg }]}
                onPress={() => onRemoveAttachment()}>
                <Ionicons name="trash-outline" size={16} color={palette.dangerText} />
                <Text style={[styles.attachRemoveText, { color: palette.dangerText }]}>إزالة</Text>
              </TouchableOpacity>
            </View>
          </View>
        )}
      </View>
    </>
  );
}

function DesignEntryButton({ title, description, icon, gradient, onPress }: {
  title: string;
  description: string;
  icon: keyof typeof Ionicons.glyphMap;
  gradient: readonly [string, string];
  onPress: () => void;
}) {
  return (
    <TouchableOpacity
      accessibilityRole="button"
      accessibilityLabel={title}
      activeOpacity={0.85}
      style={designStyles.creativeEntryWrap}
      onPress={onPress}>
      <LinearGradient
        colors={gradient}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={designStyles.creativeEntry}>
        <View style={designStyles.entryIconCircle}>
          <Ionicons name={icon} size={20} color="#FFFFFF" />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={designStyles.entryTitle}>{title}</Text>
          <Text style={designStyles.entrySubtitle}>{description}</Text>
        </View>
        <Ionicons name="chevron-back" size={18} color="#FFFFFF" />
      </LinearGradient>
    </TouchableOpacity>
  );
}

const designStyles = StyleSheet.create({
  artworkPreview: { width: '100%', height: 210, borderRadius: 16, marginBottom: 12 },
  creativeEntryWrap: {
    borderRadius: 18,
    overflow: 'hidden',
    marginBottom: 12,
  },
  creativeEntry: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 13,
    gap: 12,
  },
  entryIconCircle: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  entryTitle: { color: '#FFFFFF', fontFamily: 'Tajawal-Bold', fontSize: 14.5 },
  entrySubtitle: { color: 'rgba(255,255,255,0.88)', fontFamily: 'Tajawal-Medium', fontSize: 12, marginTop: 2 },
});
