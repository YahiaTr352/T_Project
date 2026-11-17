import { CountryPickerModal } from '@/components/auth/CountryPickerModal';
import {
  CountryCodeItem,
  DEFAULT_COUNTRY,
  toWesternDigits,
} from '@/components/auth/countryData';
import { InvitationField } from '@/components/invitations/InvitationField';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { RecipientPickerModal } from '@/components/invitations/RecipientPickerModal';
import { ThemedText } from '@/components/themed-text';
import { CustomAlert } from '@/components/ui/AlertModal';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { IconSymbol } from '@/components/ui/icon-symbol';
import {
  type InvitationChannel,
  type InvitationPersonBrief,
  type InvitationRecipientType,
  type SendInvitationInput,
  type InvitationTemplate,
  type CreativeTemplate,
  type MyCreativeTemplate,
  deleteMyCreative,
  extractInvitationError,
  extractTemplateVarKeys,
  fetchCreatives,
  fetchMyCreatives,
  isProfileIncomplete,
  isIdentityRequired,
  isUnderageBlocked,
  previewInvitation,
  sendInvitation,
  templateVarLabel,
  sendInvitationWithMedia,
  uploadCreative,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as ImagePicker from 'expo-image-picker';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View
} from 'react-native';


import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  PUSH_BLUE,
  WHATSAPP_GREEN,
  type InvitationComposerProps,
} from './composerHelpers';
import { composerStyles as styles } from './composer/composerStyles';
import { ChannelToggle } from './composer/ChannelToggle';
import { CreativePickerModal } from './composer/CreativePickerModal';
import { InvitationPreviewModal } from './composer/InvitationPreviewModal';
import { DesignSection } from './composer/DesignSection';
import { TypographyControls } from './composer/TypographyControls';
import { invitationFontName, type InvitationFontFamily, type InvitationFontWeight } from './composer/typography';

export function InvitationComposer({
  templates,
  templatesLoading,
  groups,
  colors,
  isDark,
  onSent,
}: InvitationComposerProps) {
  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;

  const [templateKey, setTemplateKey] = useState<string | null>(null);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [fontFamily, setFontFamily] = useState<InvitationFontFamily>('tajawal');
  const [fontWeight, setFontWeight] = useState<InvitationFontWeight>('regular');
  const selectedFontName = invitationFontName(fontFamily, fontWeight);
  const [templateVars, setTemplateVars] = useState<Record<string, string>>({});
  const [channels, setChannels] = useState<InvitationChannel[]>(['whatsapp']);
  const [recipientType, setRecipientType] = useState<InvitationRecipientType>('persons');
  const [externalName, setExternalName] = useState('');
  const [externalPhone, setExternalPhone] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<CountryCodeItem>(DEFAULT_COUNTRY);
  const [countryPickerVisible, setCountryPickerVisible] = useState(false);
  const [selectedPersons, setSelectedPersons] = useState<InvitationPersonBrief[]>([]);
  const [groupId, setGroupId] = useState<number | null>(null);
  const [excludeDeceased] = useState(true);
  const [pickerVisible, setPickerVisible] = useState(false);
  const [previewVisible, setPreviewVisible] = useState(false);
  const [previewText, setPreviewText] = useState('');
  const [previewing, setPreviewing] = useState(false);
  const [previewCard, setPreviewCard] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  // ── مساحة الإبداع: قالب يُدمج فيه العنوان+النص داخل الصورة (card_image_url) ──
  const [creatives, setCreatives] = useState<CreativeTemplate[]>([]);
  const [myCreatives, setMyCreatives] = useState<MyCreativeTemplate[]>([]);
  const [creativeLoading, setCreativeLoading] = useState(false);
  const [creativeUploading, setCreativeUploading] = useState(false);
  const [selectedCreativeId, setSelectedCreativeId] = useState<number | null>(null);
  // اختيار مؤقت داخل المودال لا يُعتمد إلا بزر التأكيد
  const [tempCreativeId, setTempCreativeId] = useState<number | null>(null);

  // ── إرفاق صورة: صورة عادية تُرسل بجانب النص كما هي بدون دمج ──
  const [attachedImage, setAttachedImage] = useState<{ uri: string } | null>(null);
  const [creativeModalVisible, setCreativeModalVisible] = useState(false);
  const [bodySel, setBodySel] = useState<{ start: number; end: number } | null>(null);
  const [previewBg, setPreviewBg] = useState<{ id: number; name?: string | null; image_url?: string | null } | null>(null);
  const [previewCreative, setPreviewCreative] = useState<{ id: number; name?: string | null; image_url?: string | null } | null>(null);

  const varKeys = useMemo(() => extractTemplateVarKeys(body), [body]);
  const selectedGroup = groups.find((g) => g.id === groupId) ?? null;

  const insets = useSafeAreaInsets();
  const [isKeyboardOpen, setIsKeyboardOpen] = useState(false);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const onShow = () => setIsKeyboardOpen(true);
    const onHide = () => setIsKeyboardOpen(false);

    const showSub = Keyboard.addListener(showEvent, onShow);
    const hideSub = Keyboard.addListener(hideEvent, onHide);

    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);
  const applyTemplate = (tpl: InvitationTemplate) => {
    const nextKey = templateKey === tpl.key ? null : tpl.key;
    setTemplateKey(nextKey);
    setSelectedCreativeId(null);
    setTempCreativeId(null);
    if (nextKey) {
      setTitle(tpl.title);
      setBody(tpl.body);
      setTemplateVars({});
    }
  };

  const selectedCreative = creatives.find((b) => b.id === selectedCreativeId) ?? null;
  const visibleCreatives = creatives.filter((item) => !item.invitation_type || item.invitation_type === templateKey);
  const invitationTypeName = templates.find((item) => item.key === templateKey)?.name;

  const loadCreatives = useCallback(async () => {
    setCreativeLoading(true);
    try {
      const [lib, mine] = await Promise.all([fetchCreatives(), fetchMyCreatives()]);
      if (lib.success && Array.isArray((lib.data as any)?.templates)) {
        setCreatives((lib.data as any).templates);
      } else if (lib.success && Array.isArray((lib.data as any)?.backgrounds)) {
        // توافق خلفي لو رجع الباك اند backgrounds بدل templates
        setCreatives((lib.data as any).backgrounds);
      }
      if (mine.success && Array.isArray((mine.data as any)?.templates)) {
        setMyCreatives((mine.data as any).templates);
      } else if (mine.success && Array.isArray((mine.data as any)?.backgrounds)) {
        setMyCreatives((mine.data as any).backgrounds);
      }
    } finally {
      setCreativeLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadCreatives();
  }, [loadCreatives]);

  // فتح المودال ينسخ الاختيار الحالي إلى المؤقت حتى لا يُعتمد إلا بالتأكيد
  const openCreativeModal = useCallback(() => {
    setTempCreativeId(selectedCreativeId);
    setCreativeModalVisible(true);
  }, [selectedCreativeId]);

  const confirmCreativeSelection = useCallback(() => {
    setSelectedCreativeId(tempCreativeId);
    if (tempCreativeId !== selectedCreativeId && creatives.find((item) => item.id === tempCreativeId)?.invitation_type) {
      setBody('');
      setTemplateVars({});
    }
    // فصل ذكي: اختيار قالب إبداعي يلغي الصورة المرفقة العادية والعكس
    if (tempCreativeId != null) setAttachedImage(null);
    setCreativeModalVisible(false);
  }, [tempCreativeId, selectedCreativeId, creatives]);

  const clearCreativeSelection = useCallback(() => {
    setSelectedCreativeId(null);
    setTempCreativeId(null);
  }, []);

  const wrapBodySelection = (mark: string) => {
    const sel = bodySel ?? { start: body.length, end: body.length };
    const before = body.slice(0, sel.start);
    const mid = body.slice(sel.start, sel.end);
    const after = body.slice(sel.end);
    const next = mid.length > 0 ? `${before}${mark}${mid}${mark}${after}` : `${before}${mark}${mark}${after}`;
    setBody(next);
    const cursor = mid.length > 0 ? sel.end + mark.length * 2 : sel.start + mark.length;
    setBodySel({ start: cursor, end: cursor });
  };

  const handleCreativeUpload = async () => {
    if (creativeUploading) return;
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول الى معرض الصور لرفع تصميم.');
      return;
    }
    const picked = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.8,
    });
    if (picked.canceled || !picked.assets?.[0]?.uri) return;
    setCreativeUploading(true);
    try {
      const res = await uploadCreative(picked.assets[0].uri, undefined, templateKey);
      if (!res.success) {
        CustomAlert.alert('رفع تصميم', extractInvitationError(res));
        return;
      }
      await loadCreatives();
      CustomAlert.alert(
        'رفع تصميم',
        (res as any).message || 'تم استلام تصميمك وهو قيد مراجعة الإدارة. بعد الاعتماد يظهر للجميع كقالب جاهز.'
      );
    } finally {
      setCreativeUploading(false);
    }
  };

  const pickAttachment = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول الى معرض الصور لاختيار صورة.');
      return;
    }
    const picked = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.8,
    });
    if (picked.canceled || !picked.assets?.[0]?.uri) return;
    // فصل ذكي: إرفاق صورة عادية يلغي قالب مساحة الإبداع لأن النص لن يُدمج داخل الصورة
    setAttachedImage({ uri: picked.assets[0].uri });
    setSelectedCreativeId(null);
    setTempCreativeId(null);
  };

  const handleCreativeDelete = async (id: number) => {
    const res = await deleteMyCreative(id);
    if (res.success) {
      if (selectedCreativeId === id) setSelectedCreativeId(null);
      if (tempCreativeId === id) setTempCreativeId(null);
      void loadCreatives();
    } else {
      CustomAlert.alert('حذف التصميم', extractInvitationError(res));
    }
  };



  const toggleChannel = (channel: InvitationChannel) => {
    if (recipientType === 'external') {
      if (channel === 'push') {
        CustomAlert.alert(
          'تنبيه القنوات',
          'إشعارات التطبيق مخصصة لأفراد العائلة المسجلين في التطبيق فقط. دعوات الضيوف من خارج العائلة تُرسل عبر الواتساب.'
        );
        return;
      }
      setChannels(['whatsapp']);
      return;
    }
    setChannels((prev) => {
      if (prev.includes(channel)) {
        const next = prev.filter((c) => c !== channel);
        return next.length === 0 ? prev : next;
      }
      return [...prev, channel];
    });
  };

  const samplePersonId = (): number | null => {
    if (recipientType === 'persons') return selectedPersons[0]?.id ?? null;
    return selectedGroup?.persons[0]?.id ?? null;
  };

  const handlePreview = async () => {
    Keyboard.dismiss();
    if (recipientType === 'external') {
      setPreviewing(true);
      const res = await previewInvitation({
        external_name: externalName.trim() || 'ضيفنا العزيز',
        external_phone: externalPhone.trim(),
        external_country_code: selectedCountry.dialCode,
        body,
        template_key: templateKey ?? undefined,
        template_vars: templateVars,
        title: title.trim() || undefined,
        creative_id: selectedCreativeId ?? undefined,
        font_family: fontFamily,
        font_weight: fontWeight,
      });
      setPreviewing(false);
      if (!res.success || typeof res.data?.preview !== 'string') {
        CustomAlert.alert('المعاينة', extractInvitationError(res));
        return;
      }
      setPreviewText(res.data.preview);
      setPreviewBg((res.data?.background as any) ?? null);
      setPreviewCreative((res.data?.creative as any) ?? selectedCreative ?? null);
      setPreviewCard(res.data?.card_image_url ?? null);
      setPreviewVisible(true);
      return;
    }

    const personId = samplePersonId();
    if (!personId) {
      CustomAlert.alert('المعاينة', 'اختر مستلمًا واحدًا على الأقل لمعاينة النص.');
      return;
    }
    setPreviewing(true);
    const res = await previewInvitation({
      person_id: personId,
      body,
      template_key: templateKey ?? undefined,
      template_vars: templateVars,
      title: title.trim() || undefined,
      creative_id: selectedCreativeId ?? undefined,
      font_family: fontFamily,
      font_weight: fontWeight,
    });
    setPreviewing(false);
    if (!res.success || typeof res.data?.preview !== 'string') {
      CustomAlert.alert('المعاينة', extractInvitationError(res));
      return;
    }
    setPreviewText(res.data.preview);
    setPreviewBg((res.data?.background as any) ?? null);
    setPreviewCreative((res.data?.creative as any) ?? selectedCreative ?? null);
    setPreviewCard(res.data?.card_image_url ?? null);
    setPreviewVisible(true);
  };

  const handleSend = () => {
    Keyboard.dismiss();
    if (recipientType === 'external') {
      if (!externalPhone.trim()) {
        CustomAlert.alert('الإرسال', 'يرجى إدخال رقم الواتساب للضيف.');
        return;
      }
      if (!body.trim() && !attachedImage && !selectedCreativeId) {
        CustomAlert.alert('الإرسال', 'أدخل نص الرسالة أو اختر صورة.');
        return;
      }

      const guestDisplayName = externalName.trim() || 'الضيف الكريم';
      CustomAlert.alert(
        'تأكيد الإرسال',
        'سيتم إرسال الدعوة عبر واتساب إلى ' + guestDisplayName + ' (' + selectedCountry.dialCode + ' ' + externalPhone.trim() + '). هل تريد المتابعة؟',
        [
          { text: 'إلغاء', style: 'cancel' },
          {
            text: 'إرسال',
            onPress: () => void doSend(),
          },
        ]
      );
      return;
    }

    if (channels.length === 0) {
      CustomAlert.alert('الإرسال', 'اختر قناة إرسال واحدة على الأقل.');
      return;
    }
    if (recipientType === 'persons' && selectedPersons.length === 0) {
      CustomAlert.alert('الإرسال', 'اختر شخصًا واحدًا على الأقل.');
      return;
    }
    if (recipientType === 'group' && !groupId) {
      CustomAlert.alert('الإرسال', 'اختر مجموعة للمستلمين.');
      return;
    }
    if (!body.trim() && !attachedImage && !selectedCreativeId) {
      CustomAlert.alert('الإرسال', 'أدخل نص الرسالة أو اختر صورة.');
      return;
    }

    const count =
      recipientType === 'persons'
        ? selectedPersons.length
        : selectedGroup?.persons_count ?? 0;

    CustomAlert.alert(
      'تأكيد الإرسال',
      `سيتم الإرسال إلى ${count} عبر ${channels
        .map((c) => (c === 'whatsapp' ? 'واتساب' : 'الإشعارات'))
        .join(' و ')}. هل تريد المتابعة؟`,
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'إرسال',
          onPress: () => void doSend(),
        },
      ]
    );
  };

  const doSend = async () => {
    setSending(true);
    const basePayload: SendInvitationInput = {
      channels: recipientType === 'external' ? ['whatsapp'] : channels,
      recipient_type: recipientType,
      person_ids: recipientType === 'persons' ? selectedPersons.map((p) => p.id) : undefined,
      group_id: recipientType === 'group' ? groupId : undefined,
      external_name: recipientType === 'external' ? (externalName.trim() || undefined) : undefined,
      external_phone: recipientType === 'external' ? externalPhone.trim() : undefined,
      external_country_code: recipientType === 'external' ? selectedCountry.dialCode : undefined,
      title: title.trim() || null,
      creative_id: selectedCreativeId,
      font_family: fontFamily,
      font_weight: fontWeight,
      body,
      template_key: templateKey,
      template_vars: templateVars,
      exclude_deceased: recipientType === 'external' ? false : excludeDeceased,
    };
    // فصل ذكي: قالب إبداعي = دمج النص داخل الصورة عبر creative_id، مرفق عادي = صورة بجانب النص
    const res =
      attachedImage && !selectedCreativeId
        ? await sendInvitationWithMedia(basePayload, attachedImage.uri)
        : await sendInvitation(basePayload);
    setSending(false);
    if (!res.success || !res.data?.notification) {
      if (isIdentityRequired(res as any)) {
        CustomAlert.alert(
          'توثيق الهوية مطلوب',
          (res as any).message ?? 'إرسال الدعوات يتطلب تسجيل الدخول ببصمة الوجه أو واتساب. الدخول برمز الموقع فقط لا يكفي.'
        );
        return;
      }
      if (isProfileIncomplete(res as any)) {
        CustomAlert.alert(
          'أكمل بياناتك الأساسية',
          (res as any).message ??
            'لا يمكن الإرسال لأن الاسم أو تاريخ الميلاد ناقص. أكمل بياناتك الأساسية من حسابي الشخصي ثم حاول مجدداً.'
        );
        return;
      }
      if (isUnderageBlocked(res as any)) {
        CustomAlert.alert(
          'غير متاح لعمرك',
          (res as any).message ?? 'إرسال الدعوات متاح فقط لمن بلغ 20 سنة فأكثر.'
        );
        return;
      }
      CustomAlert.alert('الإرسال', extractInvitationError(res));
      return;
    }
    CustomAlert.alert(
      'حالة الدعوة',
      res.message ?? 'تم حفظ الدعوة في السجل.'
    );
    onSent?.();
  };

  return (
    <View style={styles.composerContainer}>
      <ScrollView
        style={styles.formScroll}
        contentContainerStyle={[
          styles.formScrollContent,
          {
            paddingBottom: isKeyboardOpen ? 220 : Math.max(48, insets.bottom + 24),
          },
        ]}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}>
        <ThemedText style={styles.sectionLabel}>القالب</ThemedText>
        {templatesLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginVertical: 12 }} />
        ) : (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipsRow}
            keyboardShouldPersistTaps="handled">
            {templates.map((tpl) => {
              const active = templateKey === tpl.key;
              return (
                <Pressable
                  key={tpl.key}
                  onPress={() => applyTemplate(tpl)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? colors.primary : cardBg,
                      borderColor: active ? colors.primary : border,
                    },
                  ]}>
                  <ThemedText
                    style={[styles.chipText, { color: active ? '#FFFFFF' : colors.text }]}>
                    {tpl.name}
                  </ThemedText>
                </Pressable>
              );
            })}
          </ScrollView>
        )}

        <TypographyControls
          colors={colors}
          fontFamily={fontFamily}
          fontWeight={fontWeight}
          onFamilyChange={setFontFamily}
          onWeightChange={setFontWeight}
          hasDesign={selectedCreativeId != null}
        />
        <InvitationField
          label="العنوان"
          fontFamily={selectedFontName}
          value={title}
          onChangeText={setTitle}
          placeholder="عنوان الدعوة"
          mutedColor={muted}
          textColor={colors.text}
          cardBg={cardBg}
          borderColor={border}
        />
        <View style={styles.formatRow}>
          <ThemedText style={[styles.formatLabel, { color: muted }]}>تنسيق النص</ThemedText>
          <View style={styles.formatBtns}>
            <TouchableOpacity
              style={[styles.formatBtn, { borderColor: border }]}
              onPress={() => wrapBodySelection('*')}>
              <ThemedText style={[styles.formatBtnText, { color: colors.text }]}>B</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.formatBtn, { borderColor: border }]}
              onPress={() => wrapBodySelection('_')}>
              <ThemedText style={[styles.formatBtnText, { color: colors.text, fontStyle: 'italic' }]}>
                I
              </ThemedText>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.formatBtn, { borderColor: border }]}
              onPress={() => wrapBodySelection('~')}>
              <ThemedText
                style={[styles.formatBtnText, { color: colors.text, textDecorationLine: 'line-through' }]}>
                S
              </ThemedText>
            </TouchableOpacity>
          </View>
        </View>
        <InvitationField
          label="نص الرسالة"
          fontFamily={selectedFontName}
          value={body}
          onChangeText={setBody}
          placeholder="النص اختياري عند اختيار صورة"
          multiline
          selection={bodySel}
          onSelectionChange={setBodySel}
          mutedColor={muted}
          textColor={colors.text}
          cardBg={cardBg}
          borderColor={border}
        />

        {varKeys.length > 0 ? (
          <View style={[styles.card, { backgroundColor: cardBg, borderColor: border }]}>
            <ThemedText style={[styles.cardTitle, { color: colors.text }]}>
              تفاصيل القالب
            </ThemedText>
            {varKeys.map((key) => {
              if (key === 'date') {
                return (
                  <DatePickerField
                    key={key}
                    label={templateVarLabel(key)}
                    value={templateVars[key] ?? ''}
                    onChange={(text) =>
                      setTemplateVars((prev) => ({ ...prev, [key]: text }))
                    }
                    placeholder="اختر التاريخ..."
                    containerStyle={{ marginBottom: 12 }}
                  />
                );
              }
              return (
                <InvitationField
                  key={key}
                  label={templateVarLabel(key)}
                  value={templateVars[key] ?? ''}
                  onChangeText={(text) =>
                    setTemplateVars((prev) => ({ ...prev, [key]: text }))
                  }
                  placeholder={templateVarLabel(key)}
                  mutedColor={muted}
                  textColor={colors.text}
                  cardBg={isDark ? '#0F172A' : '#F8FAFC'}
                  borderColor={border}
                />
              );
            })}
          </View>
        ) : null}

        <DesignSection
          invitationTypeName={invitationTypeName}
          colors={colors}
          isDark={isDark}
          cardBg={cardBg}
          border={border}
          muted={muted}
          title={title}
          body={body}
          selectedCreative={selectedCreative}
          fontFamily={selectedFontName}
          attachedImage={attachedImage}
          onOpenCreative={() => void openCreativeModal()}
          onClearCreative={() => void clearCreativeSelection()}
          onPickAttachment={() => void pickAttachment()}
          onRemoveAttachment={() => setAttachedImage(null)}
        />

        <ThemedText style={styles.sectionLabel}>قنوات الإرسال</ThemedText>
        <View style={styles.row}>
          <ChannelToggle
            label="واتساب"
            kind="whatsapp"
            color={WHATSAPP_GREEN}
            active={channels.includes('whatsapp')}
            onPress={() => toggleChannel('whatsapp')}
            cardBg={cardBg}
            border={border}
            textColor={colors.text}
          />
          <ChannelToggle
            label="إشعارات"
            kind="push"
            color={PUSH_BLUE}
            active={channels.includes('push')}
            onPress={() => toggleChannel('push')}
            cardBg={cardBg}
            border={border}
            textColor={colors.text}
          />
        </View>

        <ThemedText style={styles.sectionLabel}>المستلمون</ThemedText>
        <View style={styles.row}>
          <Pressable
            onPress={() => setRecipientType('persons')}
            style={[
              styles.modeBtn,
              {
                backgroundColor: recipientType === 'persons' ? colors.primary : cardBg,
                borderColor: recipientType === 'persons' ? colors.primary : border,
              },
            ]}>
            <IconSymbol
              name="person.fill"
              size={15}
              color={recipientType === 'persons' ? '#FFFFFF' : colors.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'persons' ? '#FFFFFF' : colors.text },
              ]}>
              أشخاص
            </ThemedText>
          </Pressable>
          <Pressable
            onPress={() => setRecipientType('group')}
            style={[
              styles.modeBtn,
              {
                backgroundColor: recipientType === 'group' ? colors.primary : cardBg,
                borderColor: recipientType === 'group' ? colors.primary : border,
              },
            ]}>
            <IconSymbol
              name="person.2.fill"
              size={15}
              color={recipientType === 'group' ? '#FFFFFF' : colors.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'group' ? '#FFFFFF' : colors.text },
              ]}>
              مجموعة
            </ThemedText>
          </Pressable>
          <Pressable
            onPress={() => {
              setRecipientType('external');
              if (!channels.includes('whatsapp')) {
                setChannels(['whatsapp']);
              }
            }}
            style={[
              styles.modeBtn,
              {
                backgroundColor: recipientType === 'external' ? colors.primary : cardBg,
                borderColor: recipientType === 'external' ? colors.primary : border,
              },
            ]}>
            <Ionicons
              name="globe-outline"
              size={15}
              color={recipientType === 'external' ? '#FFFFFF' : colors.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'external' ? '#FFFFFF' : colors.text },
              ]}>
              خارج العائلة
            </ThemedText>
          </Pressable>
        </View>

        {recipientType === 'persons' ? (
          <View style={[styles.card, { backgroundColor: cardBg, borderColor: border }]}>
            <Pressable
              onPress={() => setPickerVisible(true)}
              style={[styles.pickBtn, { borderColor: border }]}>
              <IconSymbol name="magnifyingglass" size={18} color={colors.primary} />
              <ThemedText style={[styles.pickBtnText, { color: colors.primary }]}>
                اختيار من الشجرة
              </ThemedText>
            </Pressable>
            {selectedPersons.length === 0 ? (
              <ThemedText style={[styles.hint, { color: muted }]}>
                لم يُختر أحد بعد
              </ThemedText>
            ) : (
              <View style={styles.chipsWrap}>
                {selectedPersons.map((p) => (
                  <Pressable
                    key={p.id}
                    onPress={() =>
                      setSelectedPersons((prev) => prev.filter((x) => x.id !== p.id))
                    }
                    style={[styles.personChip, { borderColor: border }]}>
                    <PersonAvatar uri={p.photo_url} size={22} mutedColor={muted} />
                    <ThemedText style={styles.personChipText}>{p.first_name}</ThemedText>
                    <IconSymbol name="xmark.circle.fill" size={14} color={muted} />
                  </Pressable>
                ))}
              </View>
            )}
          </View>
        ) : recipientType === 'group' ? (
          <View style={styles.groupsList}>
            {groups.length === 0 ? (
              <ThemedText style={[styles.hint, { color: muted }]}>
                لا توجد مجموعات بعد — أنشئ مجموعة من تبويب المجموعات.
              </ThemedText>
            ) : (
              groups.map((g) => {
                const active = groupId === g.id;
                return (
                  <Pressable
                    key={g.id}
                    onPress={() => setGroupId(g.id)}
                    style={[
                      styles.groupRow,
                      {
                        backgroundColor: active ? colors.primary + '18' : cardBg,
                        borderColor: active ? colors.primary : border,
                      },
                    ]}>
                    <View style={{ flex: 1 }}>
                      <ThemedText style={styles.groupName}>{g.name}</ThemedText>
                      <ThemedText style={[styles.hint, { color: muted, marginBottom: 0 }]}>
                        {g.persons_count} عضو
                      </ThemedText>
                    </View>
                    {active ? (
                      <IconSymbol name="checkmark.circle.fill" size={22} color={colors.primary} />
                    ) : null}
                  </Pressable>
                );
              })
            )}
          </View>
        ) : (
          <View style={[styles.card, { backgroundColor: cardBg, borderColor: border }]}>
            <View style={styles.externalHeader}>
              <View style={[styles.externalIconWrap, { backgroundColor: WHATSAPP_GREEN + '1A' }]}>
                <Ionicons name="logo-whatsapp" size={20} color={WHATSAPP_GREEN} />
              </View>
              <View style={{ flex: 1 }}>
                <ThemedText style={[styles.cardTitle, { color: colors.text, marginBottom: 2 }]}>
                  دعوة ضيف من خارج العائلة
                </ThemedText>
                <ThemedText style={[styles.hint, { textAlign: 'right', color: muted, marginBottom: 0 }]}>
                  أدخل اسم الضيف ورقم الواتساب مع مفتاح الدولة المناسب
                </ThemedText>
              </View>
            </View>

            <InvitationField
              label="اسم الضيف / المدعو"
              value={externalName}
              onChangeText={setExternalName}
              placeholder="مثال: د. محمد العلي أو أبو فهد"
              mutedColor={muted}
              textColor={colors.text}
              cardBg={isDark ? '#0F172A' : '#F8FAFC'}
              borderColor={border}
            />

            <View style={{ marginBottom: 12 }}>
              <ThemedText style={[styles.fieldLabel, { color: muted }]}>
                رقم الواتساب
              </ThemedText>
              <View
                style={[
                  styles.phoneInputRow,
                  { backgroundColor: isDark ? '#0F172A' : '#F8FAFC', borderColor: border },
                ]}>
                <TouchableOpacity
                  style={styles.countryBadge}
                  onPress={() => setCountryPickerVisible(true)}
                  activeOpacity={0.7}>
                  <ThemedText style={styles.countryFlag}>{selectedCountry.flag}</ThemedText>
                  <ThemedText style={[styles.countryCode, { color: colors.text }]}>
                    {selectedCountry.dialCode}
                  </ThemedText>
                  <MaterialIcons name="arrow-drop-down" size={18} color={muted} />
                </TouchableOpacity>

                <View
                  style={[
                    styles.verticalDivider,
                    { backgroundColor: isDark ? '#334155' : '#E2E8F0' },
                  ]}
                />

                <TextInput
                  style={[styles.phoneTextInput, { color: colors.text }]}
                  placeholder={selectedCountry.placeholder}
                  placeholderTextColor={muted}
                  keyboardType="phone-pad"
                  value={externalPhone}
                  onChangeText={(text) => setExternalPhone(toWesternDigits(text))}
                  maxLength={16}
                  returnKeyType="done"
                  onSubmitEditing={Keyboard.dismiss}
                />
              </View>
            </View>

            <View style={[styles.noticeBadge, { backgroundColor: WHATSAPP_GREEN + '12' }]}>
              <Ionicons name="checkmark-circle-outline" size={16} color={WHATSAPP_GREEN} />
              <ThemedText style={[styles.noticeText, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                سيتم استبدال {'{name}'} باسم الضيف عند إرسال الدعوة عبر واتساب.
              </ThemedText>
            </View>
          </View>
        )}

        {/* {recipientType !== 'external' ? (
          <View style={[styles.switchRow, { backgroundColor: cardBg, borderColor: border }]}>
            <ThemedText style={styles.switchLabel}>استبعاد المتوفين</ThemedText>
            <Switch
              value={excludeDeceased}
              onValueChange={setExcludeDeceased}
              trackColor={{ false: border, true: colors.primary + '80' }}
              thumbColor={excludeDeceased ? colors.primary : muted}
            />
          </View>
        ) : null} */}

        <View style={styles.actions}>
          <Pressable
            onPress={() => void handlePreview()}
            disabled={previewing || sending}
            style={[styles.secondaryBtn, { borderColor: colors.primary }]}>
            {previewing ? (
              <ActivityIndicator color={colors.primary} />
            ) : (
              <>
                <IconSymbol name="eye.fill" size={18} color={colors.primary} />
                <Text style={[styles.secondaryBtnText, { color: colors.primary }]}>معاينة</Text>
              </>
            )}
          </Pressable>
          <Pressable
            onPress={handleSend}
            disabled={sending}
            style={[styles.primaryBtn, { backgroundColor: colors.primary, opacity: sending ? 0.7 : 1 }]}>
            {sending ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <>
                <IconSymbol name="paperplane.fill" size={18} color="#FFFFFF" />
                <Text style={styles.primaryBtnTextNative}>إرسال</Text>
              </>
            )}
          </Pressable>
        </View>
      </ScrollView>

      <RecipientPickerModal


        visible={pickerVisible}
        onClose={() => setPickerVisible(false)}
        onConfirm={setSelectedPersons}
        initialSelected={selectedPersons}
        colors={colors}
        isDark={isDark}
      />

      <CreativePickerModal
        invitationTypeName={invitationTypeName}
        visible={creativeModalVisible}
        onClose={() => setCreativeModalVisible(false)}
        colors={colors}
        isDark={isDark}
        cardBg={cardBg}
        border={border}
        muted={muted}
        loading={creativeLoading}
        uploading={creativeUploading}
        creatives={visibleCreatives}
        myCreatives={myCreatives}
        tempCreativeId={tempCreativeId}
        onSelect={setTempCreativeId}
        onConfirm={() => void confirmCreativeSelection()}
        onUpload={() => void handleCreativeUpload()}
        onDelete={handleCreativeDelete}
      />

      <CountryPickerModal
        visible={countryPickerVisible}
        selectedCountry={selectedCountry}
        onSelect={(c) => {
          setSelectedCountry(c);
          setCountryPickerVisible(false);
        }}
        onClose={() => setCountryPickerVisible(false)}
      />

      <InvitationPreviewModal
        visible={previewVisible}
        onClose={() => setPreviewVisible(false)}
        colors={colors}
        muted={muted}
        cardBg={cardBg}
        previewCard={previewCard}
        previewCreative={previewCreative}
        previewBg={previewBg}
        attachedImage={attachedImage}
        previewText={previewText}
        fontFamily={selectedFontName}
      />
    </View>
  );
}
