import type { ThemeColors } from '@/constants/theme';
import {
  type CreativeTemplate,
  type MyCreativeTemplate,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { composerStyles as styles } from './composerStyles';

interface CreativePickerModalProps {
  visible: boolean;
  onClose: () => void;
  colors: ThemeColors;
  isDark: boolean;
  cardBg: string;
  border: string;
  muted: string;
  invitationTypeName?: string;
  loading: boolean;
  uploading: boolean;
  creatives: CreativeTemplate[];
  myCreatives: MyCreativeTemplate[];
  tempCreativeId: number | null;
  onSelect: (id: number | null) => void;
  onConfirm: () => void;
  onUpload: () => void;
  onDelete: (id: number) => void;
}

export function CreativePickerModal({
  visible,
  onClose,
  colors,
  isDark,
  cardBg,
  border,
  muted,
  invitationTypeName,
  loading,
  uploading,
  creatives,
  myCreatives,
  tempCreativeId,
  onSelect,
  onConfirm,
  onUpload,
  onDelete,
}: CreativePickerModalProps) {
  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}>
      <View style={styles.previewOverlay}>
        <View style={[styles.previewCard, { backgroundColor: cardBg, maxHeight: '88%' }]}>
          <Text style={[styles.previewTitleText, { color: colors.text }]}>مساحة إبداع</Text>
          {invitationTypeName ? (
            <Text style={[styles.sectionLabel, { color: colors.text }]}>
              صور {invitationTypeName} والتصاميم العامة
            </Text>
          ) : null}
          <Text style={[styles.modalSubText, { color: muted }]}>
            اختر صورة جاهزة لنوع الدعوة أو تصميمًا عامًا. يمكنك ترك النص فارغًا لإرسال الصورة وحدها.
          </Text>
          {loading ? (
            <ActivityIndicator color={colors.primary} style={{ marginVertical: 20 }} />
          ) : (
            <ScrollView style={styles.previewBody} showsVerticalScrollIndicator={false}>
              <View style={styles.ctGrid}>
                <TouchableOpacity
                  style={[styles.bgUploadTile, { borderColor: border }]}
                  onPress={() => void onUpload()}
                  disabled={uploading}>
                  {uploading ? (
                    <ActivityIndicator color={colors.primary} />
                  ) : (
                    <>
                      <Ionicons name="cloud-upload-outline" size={22} color={colors.primary} />
                      <Text style={[styles.bgUploadTextNative, { color: colors.primary }]}>
                        تصميم جديد
                      </Text>
                      <Text style={[styles.bgUploadHint, { color: muted }]}>بانتظار الاعتماد</Text>
                    </>
                  )}
                </TouchableOpacity>
                {creatives.map((bg) => {
                  const cactive = tempCreativeId === bg.id;
                  return (
                    <TouchableOpacity
                      key={bg.id}
                      accessibilityRole="button"
                      accessibilityLabel={bg.name || 'قالب صورة'}
                      accessibilityState={{ selected: cactive }}
                      onPress={() => onSelect(cactive ? null : bg.id)}
                      style={[
                        styles.bgThumb,
                        {
                          borderColor: cactive ? colors.primary : border,
                          borderWidth: cactive ? 3 : 1,
                        },
                      ]}>
                      {bg.image_url ? (
                        <Image source={{ uri: bg.image_url }} style={styles.bgThumbImg} contentFit="contain" />
                      ) : null}
                      {bg.name ? (
                        <View style={styles.bgNameBadge}>
                          <Text style={styles.bgNameBadgeText} numberOfLines={1}>{bg.name}</Text>
                        </View>
                      ) : null}
                      {cactive ? (
                        <View style={[styles.bgCheck, { backgroundColor: colors.primary }]}>
                          <Ionicons name="checkmark" size={14} color="#FFFFFF" />
                        </View>
                      ) : null}
                    </TouchableOpacity>
                  );
                })}
              </View>
              {creatives.length === 0 ? (
                <Text style={[styles.emptyCreativeText, { color: muted }]}>
                  لا توجد قوالب معتمدة بعد — ارفع تصميمك وسيظهر هنا بعد موافقة الإدارة.
                </Text>
              ) : null}
              {tempCreativeId != null ? (
                <View style={[styles.tempPreviewBox, { borderColor: colors.primary, backgroundColor: colors.primary + '0D' }]}>
                  <Ionicons name="information-circle-outline" size={16} color={colors.primary} />
                  <Text style={[styles.tempPreviewText, { color: colors.text }]}>
                    {creatives.find((item) => item.id === tempCreativeId)?.invitation_type
                      ? 'سيُرسل القالب كصورة. يمكنك إضافة نص بعد اختياره.'
                      : 'اختر تصميمًا وأضف النص المطلوب، أو اترك النص فارغًا لإرسال الصورة وحدها.'}
                  </Text>
                </View>
              ) : null}
              {myCreatives
                .filter((b) => b.status !== 'approved')
                .map((b) => (
                  <View key={`mine-${b.id}`} style={styles.myBgRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.myBgNameNative, { color: colors.text }]}>
                        {b.name || 'تصميمك'}
                      </Text>
                      <Text
                        style={[
                          styles.myBgStatusNative,
                          { color: b.status === 'rejected' ? '#DC2626' : '#D97706' },
                        ]}>
                        {b.status_label || (b.status === 'rejected' ? 'مرفوض' : 'قيد المراجعة')}
                      </Text>
                      {b.status === 'rejected' && (b as any).rejection_reason ? (
                        <Text style={[styles.myBgReasonNative, { color: muted }]}>
                          {(b as any).rejection_reason}
                        </Text>
                      ) : null}
                    </View>
                    <TouchableOpacity onPress={() => void onDelete(b.id)} hitSlop={8}>
                      <Ionicons name="trash-outline" size={18} color={muted} />
                    </TouchableOpacity>
                  </View>
                ))}
            </ScrollView>
          )}
          {/* أزرار التأكيد — Text صريح لضمان ظهور النص دائمًا */}
          <View style={styles.modalFooterRow}>
            <Pressable
              onPress={onClose}
              style={[styles.modalCancelBtn, { borderColor: border }]}>
              <Text style={[styles.modalCancelText, { color: muted }]}>إلغاء</Text>
            </Pressable>
            <Pressable
              onPress={() => void onConfirm()}
              style={[
                styles.modalConfirmBtn,
                {
                  backgroundColor: tempCreativeId != null ? colors.primary : (isDark ? '#334155' : '#E2E8F0'),
                  opacity: loading ? 0.6 : 1,
                },
              ]}
              disabled={loading}>
              <Text
                style={[
                  styles.modalConfirmText,
                  { color: tempCreativeId != null ? '#FFFFFF' : (isDark ? '#94A3B8' : '#64748B') },
                ]}>
                {tempCreativeId != null ? 'تأكيد الاختيار ✓' : 'متابعة بدون قالب'}
              </Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}
