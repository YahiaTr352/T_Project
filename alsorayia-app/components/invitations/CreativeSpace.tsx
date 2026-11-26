import { ThemedText } from '@/components/themed-text';
import { CreativeUploadEditor, type PreparedCreativeUpload } from '@/components/invitations/composer/CreativeUploadEditor';
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
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator, Platform, Pressable, RefreshControl, ScrollView, StyleSheet,
  TextInput, useColorScheme, View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const consentText = 'أوافق على عرض تصميمي للأعضاء في قوالب الصور إذا اعتمدته الإدارة.';
const statusLabels: Record<string, string> = { pending: 'قيد المراجعة', approved: 'معتمد', rejected: 'مرفوض' };
// Android aligns automatic text to the start of the explicitly RTL paragraph.
const arabicTextAlign = Platform.OS === 'android' ? 'auto' : 'right';

export function CreativeSpace({ active, types }: { active: boolean; types: ReturnType<typeof useInvitationTemplates> }) {
  const insets = useSafeAreaInsets();
  const colors = Colors[useColorScheme() ?? 'light'];
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
      }
      else setListError(extractInvitationError(result));
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
        name, invitationType: type, publicationConsent: agreed,
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

  const cardColors = { backgroundColor: colors.card, borderColor: colors.border };

  return (
    <ScrollView testID="creative-space" style={styles.container}
      showsVerticalScrollIndicator={false} showsHorizontalScrollIndicator={false}
      keyboardShouldPersistTaps="handled" contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadDesigns()} tintColor={colors.primary} />}>
      <View style={[styles.card, cardColors]}>
        <ThemedText style={styles.heading}>قدّم تصميمك</ThemedText>
        <ThemedText style={[styles.text, { color: colors.icon }]}>ارفع تصميمك كاملًا وأرسله للإدارة للمراجعة.</ThemedText>
        <ThemedText style={styles.label}>نوع الدعوة</ThemedText>
        {types.loading ? <ActivityIndicator color={colors.primary} /> : null}
        {types.error ? (
          <Pressable accessibilityRole="button" disabled={saving} onPress={() => void types.reload()}>
            <ThemedText style={[styles.text, { color: colors.primary }]}>{types.error} · إعادة المحاولة</ThemedText>
          </Pressable>
        ) : null}
        <View style={styles.categories}>
          {types.templates.map((item) => (
            <Pressable key={item.key} accessibilityRole="button" accessibilityState={{ selected: type === item.key }}
              disabled={saving} onPress={() => {
                if (item.key === type) return;
                setType(item.key);
                setAgreed(false);
              }}
              style={[styles.category, { borderColor: type === item.key ? colors.primary : colors.border, backgroundColor: type === item.key ? `${colors.primary}19` : colors.card }]}>
              {type === item.key ? <Ionicons name="checkmark" size={15} color={colors.primary} /> : null}
              <ThemedText style={styles.text}>{item.name}</ThemedText>
            </Pressable>
          ))}
        </View>
        <ThemedText style={styles.label}>اسم التصميم (اختياري)</ThemedText>
        <TextInput accessibilityLabel="اسم التصميم" editable={!saving} value={name} onChangeText={setName} maxLength={255}
          placeholder="مثال: بطاقة زفاف" placeholderTextColor={colors.icon}
          style={[styles.input, { borderColor: colors.border, color: colors.text }]} />

        <Pressable accessibilityRole="button" accessibilityLabel={artwork ? 'تغيير صورة التصميم' : 'اختيار صورة التصميم'}
          disabled={saving} onPress={() => void chooseArtwork()}
          style={[styles.artwork, { borderColor: colors.border }]}>
          {artwork ? <Image source={{ uri: prepared?.design ?? artwork.uri }} style={styles.preview} contentFit="contain" /> : null}
          <Ionicons name={artwork ? 'images-outline' : 'cloud-upload-outline'} size={24} color={colors.primary} />
          <ThemedText style={[styles.label, { color: colors.primary }]}>{artwork ? 'تغيير الصورة' : 'اختيار صورة التصميم'}</ThemedText>
        </Pressable>

        <View style={[styles.editorIntro, { borderColor: colors.border, backgroundColor: `${colors.primary}12` }]}>
          <View style={styles.editorDescription}>
            <Ionicons name="create-outline" size={21} color={colors.primary} />
            <View style={styles.designInfo}>
              <ThemedText style={styles.label}>متغيّرات التصميم</ThemedText>
              <ThemedText style={[styles.text, { color: colors.icon }]}>أضف حقولًا مثل الاسم والتاريخ والمكان ليقدر العضو يغيّرها عند استخدام القالب.</ThemedText>
            </View>
          </View>
          <Pressable accessibilityRole="button" disabled={!artwork || saving} onPress={() => setEditorOpen(true)}
            style={[styles.editorButton, { backgroundColor: colors.card, borderColor: colors.primary, opacity: artwork && !saving ? 1 : .55 }]}>
            <ThemedText style={[styles.label, { color: colors.primary }]}>فتح محرّر المتغيّرات</ThemedText>
          </Pressable>
          {prepared ? <ThemedText style={[styles.text, { color: colors.primary }]}>{`الحقول المحفوظة (${prepared.fields.length})`}</ThemedText> : null}
        </View>

        <Pressable accessibilityRole="checkbox" accessibilityLabel={consentText} accessibilityState={{ checked: agreed, disabled: saving }}
          disabled={saving} onPress={() => setAgreed(!agreed)} style={styles.consent}>
          <Ionicons name={agreed ? 'checkbox' : 'square-outline'} size={24} color={agreed ? colors.primary : colors.icon} />
          <ThemedText style={[styles.text, styles.consentLabel]}>{consentText}</ThemedText>
        </Pressable>
        {feedback ? <ThemedText accessibilityLiveRegion="polite" style={[styles.text, { color: feedback.error ? '#DC5656' : colors.primary }]}>{feedback.text}</ThemedText> : null}
        <Pressable accessibilityRole="button" accessibilityState={{ disabled: buttonDisabled, busy: saving }}
          disabled={buttonDisabled} onPress={() => void submit()} style={[styles.submit, { backgroundColor: colors.primary, opacity: buttonDisabled ? 0.55 : 1 }]}>
          {saving ? <ActivityIndicator color="#FFF" /> : <Ionicons name="paper-plane-outline" size={18} color="#FFF" />}
          <ThemedText style={styles.submitText}>إرسال للمراجعة</ThemedText>
        </Pressable>
      </View>

      <ThemedText style={styles.heading}>تصاميمي</ThemedText>
      {listError ? <ThemedText style={[styles.text, { color: '#DC5656' }]}>{listError}</ThemedText> : null}
      {!refreshing && !listError && designs.length === 0 ? <ThemedText style={[styles.text, { color: colors.icon }]}>لسا ما أرسلت أي تصميم.</ThemedText> : null}
      {designs.map((design) => {
        const typeName = types.templates.find((item) => item.key === design.invitation_type)?.name;
        const name = design.name || 'تصميمك';
        const image = creativePreviewImage(design, failedImages);
        return (
          <View key={design.id} style={[styles.card, cardColors]}>
            <Pressable style={styles.designRow} disabled={!image} accessible={Boolean(image)}
              accessibilityRole={image ? 'button' : undefined} accessibilityLabel={image ? `معاينة ${name}` : undefined}
              onPress={() => { if (image) setPreviewImage({ ...image, title: name }); }}>
              {image ? <Image source={image} style={styles.thumbnail} contentFit="contain"
                onError={() => setFailedImages((previous) => [...previous, image.uri])} /> : null}
              <View style={styles.designInfo}>
                <ThemedText style={styles.label}>{name}</ThemedText>
                <ThemedText style={[styles.text, { color: colors.icon }]}>{[typeName, design.status_label || statusLabels[design.status]].filter(Boolean).join(' · ')}</ThemedText>
              </View>
              {image ? <Ionicons name="expand-outline" size={20} color={colors.primary} /> : null}
            </Pressable>
            {design.rejection_reason ? <ThemedText style={[styles.text, { color: '#DC5656' }]}>{design.rejection_reason}</ThemedText> : null}
            {design.awaits_publication_consent ? (
              <Pressable accessibilityRole="button" disabled={savingConsent}
                onPress={() => CustomAlert.alert('الموافقة على عرض التصميم', consentText, [
                  { text: 'إلغاء', style: 'cancel' },
                  { text: 'أوافق', onPress: () => void saveConsent(design.id) },
                ])}>
                <ThemedText style={[styles.label, { color: colors.primary }]}>الموافقة على عرض التصميم</ThemedText>
              </Pressable>
            ) : null}
          </View>
        );
      })}
      {previewImage ? (
        <ImageViewerModal visible images={[previewImage]} showDownload={false} onClose={() => setPreviewImage(null)} />
      ) : null}
      {editorOpen && artwork ? (
        <CreativeUploadEditor imageUri={artwork.uri} initial={prepared} colors={colors} renderPreview={renderPreview}
          onClose={() => setEditorOpen(false)} onApply={(draft) => { setPrepared(draft); setEditorOpen(false); }} />
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, direction: 'rtl' },
  content: { direction: 'rtl', paddingTop: 10, gap: 14, width: '100%', maxWidth: 680, alignSelf: 'center' },
  card: { direction: 'rtl', borderWidth: 1, borderRadius: 20, padding: 16, gap: 10 },
  heading: { fontFamily: 'Tajawal-Bold', fontSize: 20, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  label: { fontFamily: 'Tajawal-Bold', fontSize: 15, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  text: { fontFamily: 'Tajawal-Medium', fontSize: 14, lineHeight: 21, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  categories: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  category: { flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1, borderRadius: 12, minHeight: 42, paddingHorizontal: 12 },
  input: { minHeight: 48, borderWidth: 1, paddingHorizontal: 12, borderRadius: 12, textAlign: 'right', writingDirection: 'rtl', fontFamily: 'Tajawal-Medium' },
  artwork: { alignItems: 'center', gap: 8, padding: 14, borderWidth: 1, borderStyle: 'dashed', borderRadius: 14 },
  preview: { width: '100%', height: 220, borderRadius: 10 },
  editorIntro: { borderWidth: 1, borderRadius: 14, padding: 12, gap: 8 },
  editorDescription: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  editorButton: { borderWidth: 1, borderRadius: 12, minHeight: 44, justifyContent: 'center', alignItems: 'center' },
  consent: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 48 },
  consentLabel: { flex: 1 },
  submit: { flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 8, minHeight: 48, borderRadius: 14 },
  submitText: { color: '#FFF', fontFamily: 'Tajawal-Bold', fontSize: 16, writingDirection: 'rtl' },
  designRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  designInfo: { flex: 1, minWidth: 0 },
  thumbnail: { width: 74, height: 64, borderRadius: 10 },
});
