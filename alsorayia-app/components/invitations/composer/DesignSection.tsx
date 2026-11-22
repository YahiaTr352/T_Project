import type { ThemeColors } from '@/constants/theme';
import { ThemedText } from '@/components/themed-text';
import {
  type CreativeTemplate,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import {
  Text,
  TouchableOpacity,
  View,
  StyleSheet,
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
  attachedImage: { uri: string } | null;
  onOpenCreative: () => void;
  onSubmitDesign: () => void;
  onClearCreative: () => void;
  onPickAttachment: () => void;
  onRemoveAttachment: () => void;
}

export function DesignSection({
  invitationTypeName,
  colors,
  isDark,
  cardBg,
  border,
  muted,
  title,
  body,
  fontFamily,
  selectedCreative,
  attachedImage,
  onOpenCreative,
  onSubmitDesign,
  onClearCreative,
  onPickAttachment,
  onRemoveAttachment,
}: DesignSectionProps) {
  return (
    <>
      <ThemedText style={styles.sectionLabel}>الصورة والتصميم</ThemedText>

      {invitationTypeName ? <TouchableOpacity
        accessibilityRole="button"
        accessibilityLabel="قوالب الصور"
        activeOpacity={0.85}
        style={[designStyles.creativeEntry, { backgroundColor: colors.primary }]}
        onPress={onOpenCreative}>
        <Ionicons name="images-outline" size={24} color="#FFFFFF" />
        <View style={{ flex: 1 }}>
          <Text style={designStyles.entryTitle}>قوالب الصور</Text>
          <Text style={designStyles.entrySubtitle}>
            {selectedCreative
              ? `القالب المختار: ${selectedCreative.name || 'بطاقة مصممة'}`
              : `صور ${invitationTypeName}`}
          </Text>
        </View>
        <Ionicons name="chevron-back" size={18} color="#FFFFFF" />
      </TouchableOpacity> : null}
        {selectedCreative ? (
          <View style={[styles.creativeSelectedRow, { marginBottom: 12 }]}>
            <View style={[styles.miniBadgeOk, { backgroundColor: '#10B98118' }]}>
              <Ionicons name="checkmark-circle" size={14} color="#10B981" />
              <ThemedText style={[styles.miniBadgeOkText, { color: '#059669' }]}>
                {body.trim() ? 'سيتم دمج النص داخل التصميم' : 'سيتم إرسال الصورة وحدها'}
              </ThemedText>
            </View>
            <TouchableOpacity onPress={() => void onClearCreative()} style={styles.clearVisual}>
              <ThemedText style={[styles.clearVisualText, { color: muted }]}>
                إزالة القالب
              </ThemedText>
            </TouchableOpacity>
          </View>
        ) : null}

      <TouchableOpacity onPress={onSubmitDesign} accessibilityRole="button" style={[styles.clearVisual, { marginBottom: 12 }]}>
        <Ionicons name="color-palette-outline" size={18} color={colors.primary} />
        <ThemedText style={{ color: colors.primary }}>مساحة إبداع</ThemedText>
      </TouchableOpacity>
      <View style={[styles.card, { backgroundColor: cardBg, borderColor: attachedImage ? colors.primary : border, borderWidth: attachedImage ? 1.5 : 1 }]}>
        <View style={styles.creativeHeaderRow}>
          <View style={[styles.creativeIconWrap, { backgroundColor: '#3B82F618' }]}>
            <Ionicons name="chatbubble-ellipses-outline" size={20} color="#3B82F6" />
          </View>
          <View style={{ flex: 1 }}>
            <View style={styles.attachTitleRow}>
              <ThemedText style={[styles.creativeBtnTitle, { color: colors.text }]}>
                صورة مع الرسالة
              </ThemedText>
              <View style={[styles.attachStatusPill, { backgroundColor: attachedImage ? '#10B9811A' : (isDark ? '#334155' : '#F1F5F9') }]}>
                <View style={[styles.attachDot, { backgroundColor: attachedImage ? '#10B981' : muted }]} />
                <Text style={[styles.attachStatusText, { color: attachedImage ? '#059669' : muted }]}>
                  {attachedImage ? 'تم الإرفاق' : 'بدون مرفق'}
                </Text>
              </View>
            </View>
            <ThemedText style={[styles.hint, { color: muted, marginBottom: 0, textAlign: 'right' }]}>
              يمكنك إرسال الصورة وحدها، أو إضافة نص يظهر أسفلها في واتساب.
            </ThemedText>
          </View>
        </View>

        {!attachedImage ? (
          <TouchableOpacity
            activeOpacity={0.8}
            style={[styles.dropzone, { borderColor: selectedCreative ? '#F59E0B' : colors.primary, backgroundColor: isDark ? '#0F172A' : '#F8FAFC' }]}
            onPress={() => void onPickAttachment()}>
            <View style={[styles.dropzoneIcon, { backgroundColor: colors.primary + '14' }]}>
              <Ionicons name="image-outline" size={28} color={colors.primary} />
            </View>
            <Text style={[styles.dropzoneTitle, { color: colors.text }]}>اضغط لاختيار صورة من المعرض</Text>
            <Text style={[styles.dropzoneSub, { color: muted }]}>JPG أو PNG — ستظهر أسفلها رسالتك في نفس الفقاعة</Text>
            {selectedCreative ? (
              <View style={[styles.conflictBar, { backgroundColor: '#FEF3C7', borderColor: '#F59E0B' }]}>
                <Ionicons name="warning-outline" size={14} color="#D97706" />
                <Text style={styles.conflictText}>اختيار صورة سيلغي قالب الصورة المختار</Text>
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
                        <Text style={[styles.chatTitle, { fontFamily }]} numberOfLines={1}>{title.trim()}</Text>
                      ) : null}
                      {body.trim() ? (
                        <Text style={[styles.chatBody, { fontFamily }]} numberOfLines={3}>{body.trim().slice(0, 160)}</Text>
                      ) : null}
                    </>
                  ) : (
                    <Text style={styles.chatPlaceholder}>نص رسالتك سيظهر هنا تحت الصورة…</Text>
                  )}
                  <View style={styles.chatMetaRow}>
                    <Text style={styles.chatTime}>الآن</Text>
                    <Ionicons name="checkmark-done" size={15} color="#53BDEB" />
                  </View>
                </View>
              </View>
            </View>
            <View style={styles.attachCaptionRow}>
              <Ionicons name="checkmark-circle" size={14} color="#10B981" />
              <Text style={[styles.attachCaption, { color: muted }]}>هكذا ستصل: الصورة أولاً ثم النص تحتها في نفس الرسالة</Text>
            </View>
            <View style={styles.attachActions}>
              <TouchableOpacity
                style={[styles.attachSecondaryBtn, { borderColor: border }]}
                onPress={() => void onPickAttachment()}>
                <Ionicons name="refresh-outline" size={16} color={colors.primary} />
                <Text style={[styles.attachSecondaryText, { color: colors.primary }]}>تغيير الصورة</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.attachRemoveBtn}
                onPress={() => onRemoveAttachment()}>
                <Ionicons name="trash-outline" size={16} color="#DC2626" />
                <Text style={styles.attachRemoveText}>إزالة</Text>
              </TouchableOpacity>
            </View>
          </View>
        )}
      </View>
    </>
  );
}

const designStyles = StyleSheet.create({
  creativeEntry: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderRadius: 16,
    gap: 12,
    marginBottom: 12,
  },
  entryTitle: { color: '#FFFFFF', fontFamily: 'Tajawal-Bold', fontSize: 14 },
  entrySubtitle: { color: '#FFFFFF', fontFamily: 'Tajawal-Medium', fontSize: 12, marginTop: 2 },
});
