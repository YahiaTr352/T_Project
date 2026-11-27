import { CreativeUploadEditor, type PreparedCreativeUpload } from '@/components/invitations/composer/CreativeUploadEditor';
import {
  InvitationGradientButton,
  InvitationHeroBanner,
  InvitationSectionTitle,
} from '@/components/invitations/InvitationPrimitives';
import {
  cardShadowStyle,
  getInvitationPalette,
  INVITATION_IMAGES,
  type InvitationPalette,
} from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import { CustomAlert } from '@/components/ui/AlertModal';
import { ImageViewerModal, type ViewerImage } from '@/components/ui/zoomable-image';
import { Colors } from '@/constants/theme';
import type { useInvitationTemplates } from '@/hooks/use-invitation-templates';
import {
  consentToCreativePublication,
  creativePreviewImage,
  extractInvitationError,
  fetchMyCreatives,
  previewCreativeUpload,
  type CreativeUploadField,
  type MyCreativeTemplate,
  uploadCreative,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const consentText = 'أوافق على عرض تصميمي للأعضاء في قوالب الصور إذا اعتمدته الإدارة.';
const statusLabels: Record<string, string> = { pending: 'قيد المراجعة', approved: 'معتمد', rejected: 'مرفوض' };
// Android aligns automatic text to the start of the explicitly RTL paragraph.
const arabicTextAlign = Platform.OS === 'android' ? 'auto' : 'right';

function designStatusColors(status: string, palette: InvitationPalette) {
  switch (status) {
    case 'approved':
      return { bg: palette.successBg, text: palette.successText, border: palette.successBorder };
    case 'rejected':
      return { bg: palette.dangerBg, text: palette.dangerText, border: palette.dangerBorder };
    default:
      return { bg: palette.warningBg, text: palette.warningText, border: palette.warningBorder };
  }
}

export function CreativeSpace({ active, types }: { active: boolean; types: ReturnType<typeof useInvitationTemplates> }) {
  const insets = useSafeAreaInsets();
  const isDark = useColorScheme() === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];
  const palette = getInvitationPalette(isDark);
  const [type, setType] = useState('');
  const [name, setName] = useState('');
  const [artwork, setArtwork] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [prepared, setPrepared] = useState<PreparedCreativeUpload>();
  const [agreed, setAgreed] = useState(false);
  const [saving, setSaving] = useState(false);
  const [designs, setDesigns] = useState<MyCreativeTemplate[]>([]);
  const [failedImages, setFailedImages] = useState<string[]>([]);
  const [previewImage, setPreviewImage] = useState<ViewerImage | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [listError, setListError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ text: string; error: boolean } | null>(null);
  const [savingConsent, setSavingConsent] = useState(false);
  const buttonDisabled = !artwork || !type || !agreed || saving;

  const loadDesigns = useCallback(async () => {
    if (!active) return;
    setRefreshing(true);
    setListError(null);
    try {
      const result = await fetchMyCreatives();
      if (result.success) {
        setDesigns(result.data?.templates ?? []);
        setFailedImages([]);
      } else {
        setListError(extractInvitationError(result));
      }
    } finally {
      setRefreshing(false);
    }
  }, [active]);

  useFocusEffect(useCallback(() => { void loadDesigns(); }, [loadDesigns]));

  const chooseArtwork = async () => {
    try {
      if (Platform.OS !== 'web') {
        const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!permission.granted) {
          setFeedback({ text: 'اسمح بالوصول إلى الصور حتى تتمكن من اختيار تصميم.', error: true });
          return;
        }
      }
      const picked = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: false, quality: 0.9 });
      if (!picked.canceled && picked.assets[0]) {
        setArtwork(picked.assets[0]);
        setEditorOpen(false);
        setPrepared(undefined);
        setAgreed(false);
        setFeedback(null);
      }
    } catch {
      setFeedback({ text: 'تعذّر فتح معرض الصور. جرّب مرة أخرى.', error: true });
    }
  };

  const renderPreview = async (fields: CreativeUploadField[]) => {
    if (!artwork) throw new Error('اختر صورة التصميم أولًا.');
    const result = await previewCreativeUpload(artwork, fields);
    if (!result.success || !result.data?.design || !result.data.proof) throw new Error(extractInvitationError(result));
    return result.data;
  };

  const submit = async () => {
    if (buttonDisabled || !artwork || !agreed) return;
    setSaving(true);
    setFeedback(null);
    try {
      const result = await uploadCreative(artwork, {
        name,
        invitationType: type,
        publicationConsent: agreed,
        ...(prepared ? { prepared: { fields: prepared.fields, proof: prepared.proof } } : {}),
      });
      if (!result.success) {
        setFeedback({ text: extractInvitationError(result), error: true });
        return;
      }
      setArtwork(null);
      setPrepared(undefined);
      setType('');
      setName('');
      setAgreed(false);
      setFeedback({ text: 'تم إرسال التصميم إلى الإدارة للمراجعة.', error: false });
      await loadDesigns();
    } finally {
      setSaving(false);
    }
  };

  const saveConsent = async (id: number) => {
    if (savingConsent) return;
    setSavingConsent(true);
    try {
      const result = await consentToCreativePublication(id);
      if (!result.success) {
        CustomAlert.alert('تصاميمي', extractInvitationError(result));
        return;
      }
      await loadDesigns();
    } finally {
      setSavingConsent(false);
    }
  };

  const designsCountLabel = `${designs.length} ${designs.length >= 3 && designs.length <= 10 ? 'تصاميم' : 'تصميم'}`;

  return (
    <ScrollView
      testID="creative-space"
      style={styles.container}
      showsVerticalScrollIndicator={false}
      showsHorizontalScrollIndicator={false}
      keyboardShouldPersistTaps="handled"
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 28 }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={() => void loadDesigns()} tintColor={palette.primary} />
      }>
      <InvitationHeroBanner
        title="مساحة إبداع"
        subtitle="قدّم تصميمك ليُعتمد في قوالب العائلة"
        image={INVITATION_IMAGES.greeting}
        palette={palette}
        style={{ marginBottom: 4 }}
      />

      <View
        style={[
          styles.card,
          cardShadowStyle(palette),
          { backgroundColor: palette.surface, borderColor: palette.border },
        ]}>
        <InvitationSectionTitle title="قدّم تصميمك" palette={palette} style={{ marginTop: 0, marginBottom: 4 }} />
        <ThemedText style={[styles.subText, { color: palette.muted }]}>
          ارفع تصميمك كاملًا وأرسله للإدارة للمراجعة.
        </ThemedText>

        {types.loading ? <ActivityIndicator color={palette.primary} /> : null}
        {types.error ? (
          <Pressable accessibilityRole="button" disabled={saving} onPress={() => void types.reload()}>
            <ThemedText style={[styles.text, { color: palette.primary }]}>{types.error} · إعادة المحاولة</ThemedText>
          </Pressable>
        ) : null}

        <View style={styles.categories}>
          {types.templates.map((item) => {
            const selected = type === item.key;
            return (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                accessibilityState={{ selected }}
                disabled={saving}
                onPress={() => {
                  if (item.key === type) return;
                  setType(item.key);
                  setAgreed(false);
                }}
                style={[
                  styles.category,
                  {
                    borderColor: selected ? palette.primary : palette.border,
                    backgroundColor: selected ? palette.pillBg : palette.surfaceAlt,
                  },
                ]}>
                {selected ? <Ionicons name="checkmark" size={15} color={palette.primary} /> : null}
                <ThemedText
                  style={[
                    styles.chipText,
                    { color: selected ? palette.pillText : palette.textSecondary },
                  ]}>
                  {item.name}
                </ThemedText>
              </Pressable>
            );
          })}
        </View>

        <TextInput
          accessibilityLabel="اسم التصميم"
          editable={!saving}
          value={name}
          onChangeText={setName}
          maxLength={255}
          placeholder="مثال: بطاقة زفاف الياسمين (اختياري)"
          placeholderTextColor={palette.muted}
          style={[
            styles.input,
            {
              backgroundColor: palette.surfaceAlt,
              borderColor: palette.border,
              color: palette.text,
            },
          ]}
        />

        <Pressable
          accessibilityRole="button"
          accessibilityLabel={artwork ? 'تغيير صورة التصميم' : 'اختيار صورة التصميم'}
          disabled={saving}
          onPress={() => void chooseArtwork()}
          style={[
            styles.artwork,
            {
              backgroundColor: palette.surfaceSoft,
              borderColor: palette.borderStrong,
            },
          ]}>
          {artwork ? (
            <Image source={{ uri: prepared?.design ?? artwork.uri }} style={styles.preview} contentFit="contain" />
          ) : null}
          <View style={styles.uploadRow}>
            <LinearGradient colors={palette.primaryGradient} style={styles.uploadIconCircle}>
              <Ionicons name={artwork ? 'images-outline' : 'cloud-upload-outline'} size={24} color="#FFFFFF" />
            </LinearGradient>
            <View style={styles.uploadCopy}>
              <ThemedText style={[styles.label, { color: palette.text }]}>
                {artwork ? 'تغيير الصورة' : 'اختيار صورة التصميم'}
              </ThemedText>
              <ThemedText style={[styles.uploadHint, { color: palette.muted }]}>
                تحدد الإدارة النص القابل للتعديل قبل اعتماده
              </ThemedText>
            </View>
          </View>
        </Pressable>

        <View
          style={[
            styles.editorIntro,
            { borderColor: palette.border, backgroundColor: palette.surfaceAlt },
          ]}>
          <View style={styles.editorDescription}>
            <View style={[styles.editorIconWrap, { backgroundColor: palette.pillBg }]}>
              <Ionicons name="create-outline" size={19} color={palette.primary} />
            </View>
            <View style={styles.designInfo}>
              <ThemedText style={[styles.label, { color: palette.text }]}>متغيّرات التصميم</ThemedText>
              <ThemedText style={[styles.subText, { color: palette.muted }]}>
                أضف حقولًا مثل الاسم والتاريخ والمكان ليقدر العضو يغيّرها عند استخدام القالب.
              </ThemedText>
            </View>
          </View>
          <Pressable
            accessibilityRole="button"
            disabled={!artwork || saving}
            onPress={() => setEditorOpen(true)}
            style={[
              styles.editorButton,
              {
                backgroundColor: palette.surface,
                borderColor: palette.primary,
                opacity: artwork && !saving ? 1 : 0.55,
              },
            ]}>
            <ThemedText style={[styles.label, { color: palette.primary }]}>فتح محرّر المتغيّرات</ThemedText>
          </Pressable>
          {prepared ? (
            <ThemedText style={[styles.text, { color: palette.primary }]}>
              {`الحقول المحفوظة (${prepared.fields.length})`}
            </ThemedText>
          ) : null}
        </View>

        <Pressable
          accessibilityRole="checkbox"
          accessibilityLabel={consentText}
          accessibilityState={{ checked: agreed, disabled: saving }}
          disabled={saving}
          onPress={() => setAgreed(!agreed)}
          style={styles.consent}>
          <Ionicons
            name={agreed ? 'checkbox' : 'square-outline'}
            size={23}
            color={agreed ? palette.primary : palette.muted}
          />
          <ThemedText style={[styles.consentTextStyle, styles.consentLabel, { color: palette.textSecondary }]}>
            {consentText}
          </ThemedText>
        </Pressable>

        {feedback ? (
          <View
            style={[
              styles.feedbackBanner,
              {
                backgroundColor: feedback.error ? palette.dangerBg : palette.successBg,
                borderColor: feedback.error ? palette.dangerBorder : palette.successBorder,
              },
            ]}>
            <ThemedText
              accessibilityLiveRegion="polite"
              style={[styles.text, { color: feedback.error ? palette.dangerText : palette.successText }]}>
              {feedback.text}
            </ThemedText>
          </View>
        ) : null}

        <InvitationGradientButton
          label="إرسال للمراجعة"
          icon="paper-plane-outline"
          disabled={buttonDisabled}
          loading={saving}
          onPress={() => void submit()}
          palette={palette}
        />
      </View>

      <InvitationSectionTitle
        title="تصاميمي"
        palette={palette}
        subtitle={designs.length > 0 ? designsCountLabel : undefined}
        style={{ marginTop: 4, marginBottom: 0 }}
      />

      {listError ? <ThemedText style={[styles.text, { color: palette.dangerText }]}>{listError}</ThemedText> : null}

      {!refreshing && !listError && designs.length === 0 ? (
        <View
          style={[
            styles.emptyCard,
            cardShadowStyle(palette),
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <ThemedText style={[styles.text, { color: palette.muted, textAlign: 'center' }]}>
            لسا ما أرسلت أي تصميم.
          </ThemedText>
        </View>
      ) : null}

      {designs.map((design) => {
        const typeName = types.templates.find((item) => item.key === design.invitation_type)?.name;
        const designName = design.name || 'تصميمك';
        const image = creativePreviewImage(design, failedImages);
        const statusLabel = design.status_label || statusLabels[design.status] || design.status;
        const statusTheme = designStatusColors(design.status, palette);
        const isRejected = design.status === 'rejected' || Boolean(design.rejection_reason);

        return (
          <View
            key={design.id}
            style={[
              styles.designCard,
              cardShadowStyle(palette),
              {
                backgroundColor: isRejected ? palette.dangerBg : palette.surface,
                borderColor: isRejected ? palette.dangerBorder : palette.border,
              },
            ]}>
            <Pressable
              style={styles.designRow}
              disabled={!image}
              accessible={Boolean(image)}
              accessibilityRole={image ? 'button' : undefined}
              accessibilityLabel={image ? `معاينة ${designName}` : undefined}
              onPress={() => { if (image) setPreviewImage({ ...image, title: designName }); }}>
              {image ? (
                <View style={[styles.thumbFrame, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}>
                  <Image
                    source={image}
                    style={styles.thumbnail}
                    contentFit="cover"
                    onError={() => setFailedImages((previous) => [...previous, image.uri])}
                  />
                </View>
              ) : null}

              <View style={styles.designInfo}>
                <ThemedText style={[styles.label, { color: palette.text }]} numberOfLines={1}>
                  {designName}
                </ThemedText>
                {typeName ? (
                  <ThemedText style={[styles.subText, { color: palette.muted }]}>{typeName}</ThemedText>
                ) : null}
                {design.rejection_reason ? (
                  <ThemedText style={[styles.rejectionText, { color: palette.dangerText }]}>
                    {`السبب: ${design.rejection_reason}`}
                  </ThemedText>
                ) : null}
              </View>

              <View style={styles.designEnd}>
                <View style={[styles.statusBadge, { backgroundColor: statusTheme.bg, borderColor: statusTheme.border }]}>
                  <Text style={[styles.statusBadgeText, { color: statusTheme.text }]}>{statusLabel}</Text>
                </View>
                {image ? <Ionicons name="expand-outline" size={16} color={palette.muted} /> : null}
              </View>
            </Pressable>

            {design.awaits_publication_consent ? (
              <Pressable
                accessibilityRole="button"
                disabled={savingConsent}
                style={[styles.consentActionBtn, { backgroundColor: palette.pillBg, borderColor: palette.pillBorder }]}
                onPress={() => CustomAlert.alert('الموافقة على عرض التصميم', consentText, [
                  { text: 'إلغاء', style: 'cancel' },
                  { text: 'أوافق', onPress: () => void saveConsent(design.id) },
                ])}>
                <ThemedText style={[styles.label, { color: palette.pillText }]}>
                  الموافقة على عرض التصميم
                </ThemedText>
              </Pressable>
            ) : null}
          </View>
        );
      })}

      {previewImage ? (
        <ImageViewerModal visible images={[previewImage]} showDownload={false} onClose={() => setPreviewImage(null)} />
      ) : null}

      {editorOpen && artwork ? (
        <CreativeUploadEditor
          imageUri={artwork.uri}
          initial={prepared}
          colors={colors}
          renderPreview={renderPreview}
          onClose={() => setEditorOpen(false)}
          onApply={(draft) => {
            setPrepared(draft);
            setEditorOpen(false);
          }}
        />
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, direction: 'rtl' },
  content: { direction: 'rtl', paddingTop: 4, gap: 12, width: '100%', maxWidth: 680, alignSelf: 'center' },
  card: { direction: 'rtl', borderWidth: 1, borderRadius: 22, padding: 16, gap: 12 },
  label: { fontFamily: 'Tajawal-Bold', fontSize: 15, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  text: { fontFamily: 'Tajawal-Medium', fontSize: 14, lineHeight: 21, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  subText: { fontFamily: 'Tajawal-Regular', fontSize: 12.5, lineHeight: 19, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  categories: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  category: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    borderWidth: 1,
    borderRadius: 18,
    minHeight: 38,
    paddingHorizontal: 14,
  },
  chipText: { fontFamily: 'Tajawal-Bold', fontSize: 13 },
  input: {
    minHeight: 48,
    borderWidth: 1,
    paddingHorizontal: 14,
    borderRadius: 14,
    textAlign: 'right',
    writingDirection: 'rtl',
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
  artwork: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    padding: 16,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderRadius: 18,
  },
  uploadRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  uploadIconCircle: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  uploadCopy: {
    flex: 1,
  },
  uploadHint: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
    lineHeight: 17,
    textAlign: arabicTextAlign,
    writingDirection: 'rtl',
  },
  preview: { width: '100%', height: 210, borderRadius: 12 },
  editorIntro: { borderWidth: 1, borderRadius: 16, padding: 12, gap: 10 },
  editorDescription: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  editorIconWrap: { width: 38, height: 38, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  editorButton: { borderWidth: 1, borderRadius: 12, minHeight: 42, justifyContent: 'center', alignItems: 'center' },
  consent: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 44 },
  consentTextStyle: { fontFamily: 'Tajawal-Medium', fontSize: 13, lineHeight: 20, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  consentLabel: { flex: 1 },
  feedbackBanner: { borderWidth: 1, borderRadius: 12, padding: 10 },
  emptyCard: { borderWidth: 1, borderRadius: 18, padding: 22 },
  designCard: { direction: 'rtl', borderWidth: 1, borderRadius: 18, padding: 12, gap: 10 },
  designRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  thumbFrame: { width: 58, height: 58, borderRadius: 14, borderWidth: 1, overflow: 'hidden', flexShrink: 0 },
  thumbnail: { width: '100%', height: '100%' },
  designInfo: { flex: 1, minWidth: 0, gap: 2 },
  rejectionText: { fontFamily: 'Tajawal-Medium', fontSize: 12, lineHeight: 18, textAlign: arabicTextAlign, writingDirection: 'rtl', marginTop: 2 },
  designEnd: { alignItems: 'flex-end', gap: 6, flexShrink: 0 },
  statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 12, borderWidth: 1 },
  statusBadgeText: { fontFamily: 'Tajawal-Bold', fontSize: 11.5, lineHeight: 16 },
  consentActionBtn: { borderWidth: 1, borderRadius: 12, paddingVertical: 10, alignItems: 'center' },
});
