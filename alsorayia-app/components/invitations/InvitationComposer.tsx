import { CountryPickerModal } from '@/components/auth/CountryPickerModal';
import {
  CountryCodeItem,
  DEFAULT_COUNTRY,
  toWesternDigits,
} from '@/components/auth/countryData';
import { InvitationField } from '@/components/invitations/InvitationField';
import {
  InvitationGradientButton,
  InvitationSectionTitle,
} from '@/components/invitations/InvitationPrimitives';
import {
  cardShadowStyle,
  getInvitationPalette,
  getTemplateVisualMeta,
  getVarFieldIcon,
} from '@/components/invitations/invitationTheme';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { RecipientPickerModal } from '@/components/invitations/RecipientPickerModal';
import { PreparedArtworkEditor } from '@/components/invitations/composer/PreparedArtworkEditor';
import { ThemedText } from '@/components/themed-text';
import { CustomAlert } from '@/components/ui/AlertModal';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { IconSymbol } from '@/components/ui/icon-symbol';
import {
  type CreativeTemplate,
  extractInvitationError,
  extractTemplateVarKeys,
  fetchCreatives,
  type InvitationChannel,
  type InvitationPersonBrief,
  type InvitationRecipientType,
  type InvitationTemplate,
  isIdentityRequired,
  isProfileIncomplete,
  isUnderageBlocked,
  previewCreativeArtwork,
  previewInvitation,
  type SendInvitationInput,
  sendInvitation,
  sendInvitationWithMedia,
  templateVarLabel,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ChannelToggle } from './composer/ChannelToggle';
import { CreativePickerModal } from './composer/CreativePickerModal';
import { DesignSection } from './composer/DesignSection';
import { InvitationPreviewModal } from './composer/InvitationPreviewModal';
import { composerStyles as styles } from './composer/composerStyles';
import { invitationFontName } from './composer/typography';
import {
  type InvitationComposerProps,
  PUSH_BLUE,
  WHATSAPP_GREEN,
} from './composerHelpers';

const invitationTypography = { font_family: 'amiri', font_weight: 'bold' } as const;
const invitationTextFont = invitationFontName(invitationTypography.font_family, invitationTypography.font_weight);

export function InvitationComposer({
  templates,
  templatesLoading,
  groups,
  senderId,
  colors,
  isDark,
  onSent,
}: InvitationComposerProps) {
  const palette = getInvitationPalette(isDark);
  const muted = palette.muted;
  const cardBg = palette.surface;
  const border = palette.border;

  const [templateKey, setTemplateKey] = useState<string | null>(null);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [templateVars, setTemplateVars] = useState<Record<string, string>>({});
  const [channels, setChannels] = useState<InvitationChannel[]>(['whatsapp']);
  const [recipientType, setRecipientType] = useState<InvitationRecipientType>('persons');
  const [externalName, setExternalName] = useState('');
  const [externalPhone, setExternalPhone] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<CountryCodeItem>(DEFAULT_COUNTRY);
  const [countryPickerVisible, setCountryPickerVisible] = useState(false);
  const [chosenPersons, setChosenPersons] = useState<InvitationPersonBrief[]>([]);
  const [groupId, setGroupId] = useState<number | null>(null);
  const [excludeDeceased] = useState(true);
  const [pickerVisible, setPickerVisible] = useState(false);
  const [previewVisible, setPreviewVisible] = useState(false);
  const [previewText, setPreviewText] = useState('');
  const [previewing, setPreviewing] = useState(false);
  const [previewCard, setPreviewCard] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const [activeFormatMark, setActiveFormatMark] = useState<'*' | '_' | '~'>('*');
  // قوالب الصور المعتمدة وتصاميم العضو المرسلة للمراجعة.
  const [creatives, setCreatives] = useState<CreativeTemplate[]>([]);
  const [creativeLoading, setCreativeLoading] = useState(false);
  const [selectedCreativeId, setSelectedCreativeId] = useState<number | null>(null);
  const [artworkRevision, setArtworkRevision] = useState<string>();
  const [preparedEditorOpen, setPreparedEditorOpen] = useState(false);
  const [appliedArtworkImage, setAppliedArtworkImage] = useState<string>();
  const [tempCreativeId, setTempCreativeId] = useState<number | null>(null);

  // إرفاق صورة: صورة عادية تُرسل بجانب النص كما هي بدون دمج
  const [attachedImage, setAttachedImage] = useState<{ uri: string } | null>(null);
  const [creativeModalVisible, setCreativeModalVisible] = useState(false);
  const [bodySel, setBodySel] = useState<{ start: number; end: number } | null>(null);
  const [previewBg, setPreviewBg] = useState<{ id: number; name?: string | null; image_url?: string | null } | null>(null);
  const [previewCreative, setPreviewCreative] = useState<{ id: number; name?: string | null; image_url?: string | null } | null>(null);

  const selectedPersons = useMemo(() => chosenPersons.filter((person) => person.id !== senderId), [chosenPersons, senderId]);
  const recipientGroups = useMemo(() => groups.map((group) => {
    const persons = group.persons.filter((person) => person.id !== senderId);
    return { ...group, persons, persons_count: persons.length };
  }), [groups, senderId]);
  const selectedGroup = recipientGroups.find((group) => group.id === groupId);

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

  const resetArtwork = useCallback(() => {
    setPreparedEditorOpen(false);
    setAppliedArtworkImage(undefined);
    setArtworkRevision(undefined);
  }, []);

  const applyTemplate = (tpl: InvitationTemplate) => {
    const nextKey = templateKey === tpl.key ? null : tpl.key;
    setTemplateKey(nextKey);
    setSelectedCreativeId(null);
    setTempCreativeId(null);
    resetArtwork();
    if (nextKey) {
      setTitle(tpl.title);
      setBody(tpl.body);
      setTemplateVars({});
    }
  };

  const { visualTemplates, pillTemplates } = useMemo(() => {
    const visual: InvitationTemplate[] = [];
    const pills: InvitationTemplate[] = [];
    for (const tpl of templates) {
      const meta = getTemplateVisualMeta(tpl.key);
      if (meta.isVisualCard) visual.push(tpl);
      else pills.push(tpl);
    }
    visual.sort((a, b) => getTemplateVisualMeta(a.key).order - getTemplateVisualMeta(b.key).order);
    pills.sort((a, b) => getTemplateVisualMeta(a.key).order - getTemplateVisualMeta(b.key).order);
    return { visualTemplates: visual, pillTemplates: pills };
  }, [templates]);

  const selectedCreative = creatives.find((b) => b.id === selectedCreativeId) ?? null;
  const preparedCreative = selectedCreative?.editable_text_fields?.length && selectedCreative.image_url
    && (selectedCreative.design_mode === 'artwork' || selectedCreative.design_mode === 'web_artwork') ? selectedCreative : null;
  const preparedImage = preparedCreative?.preview_image_url || preparedCreative?.image_url;
  const varKeys = useMemo(() => Array.from(new Set([
    ...extractTemplateVarKeys(body),
    ...(selectedCreative?.editable_text_fields?.length ? [] : selectedCreative?.variable_keys ?? []),
  ])), [body, selectedCreative]);
  const bodyTokenList = useMemo(() => {
    const matches = body.match(/\{[a-zA-Z_][a-zA-Z0-9_]*\}/g) ?? [];
    const unique = Array.from(new Set(matches));
    return unique.length > 0 ? unique : ['{name}'];
  }, [body]);
  const matchingCreatives = creatives.filter((item) => templateKey && item.invitation_type === templateKey);
  const invitationTemplate = templates.find((item) => item.key === templateKey);
  const invitationTypeName = invitationTemplate?.name;

  const renderPreparedPreview = useCallback(async (values: Record<string, string>, signal: AbortSignal) => {
    const response = await previewCreativeArtwork({
      creative_id: selectedCreativeId ?? undefined,
      template_key: templateKey ?? undefined,
      template_vars: values,
      creative_edit_source: artworkRevision,
    }, signal);
    if (!response.success || !response.data?.artwork_image) {
      throw new Error(extractInvitationError(response));
    }

    return response.data.artwork_image;
  }, [artworkRevision, selectedCreativeId, templateKey]);

  const loadCreatives = useCallback(async () => {
    setCreativeLoading(true);
    try {
      const lib = await fetchCreatives();
      if (lib.success && Array.isArray((lib.data as any)?.templates)) {
        setCreatives((lib.data as any).templates);
      } else if (lib.success && Array.isArray((lib.data as any)?.backgrounds)) {
        setCreatives((lib.data as any).backgrounds);
      }
    } finally {
      setCreativeLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadCreatives();
  }, [loadCreatives]);

  const openCreativeModal = useCallback(() => {
    setTempCreativeId(selectedCreativeId);
    setCreativeModalVisible(true);
    void loadCreatives();
  }, [selectedCreativeId, loadCreatives]);

  const confirmCreativeSelection = () => {
    const choice = matchingCreatives.find((item) => item.id === tempCreativeId);
    const revision = choice?.artwork_revision ?? undefined;
    if (choice?.id !== selectedCreativeId || revision !== artworkRevision) {
      resetArtwork();
      setTemplateVars((vars) => Object.fromEntries(Object.entries(vars).filter(([key]) => !key.startsWith('creative_text_'))));
    }
    setSelectedCreativeId(choice?.id ?? null);
    setArtworkRevision(revision);
    if (choice) {
      setAttachedImage(null);
    }
    setCreativeModalVisible(false);
  };

  const clearCreativeSelection = useCallback(() => {
    setSelectedCreativeId(null);
    setTempCreativeId(null);
    resetArtwork();
    setTemplateVars((vars) => Object.fromEntries(Object.entries(vars).filter(([key]) => !key.startsWith('creative_text_'))));
  }, [resetArtwork]);

  const wrapBodySelection = (mark: '*' | '_' | '~') => {
    setActiveFormatMark(mark);
    const sel = bodySel ?? { start: body.length, end: body.length };
    const before = body.slice(0, sel.start);
    const mid = body.slice(sel.start, sel.end);
    const after = body.slice(sel.end);
    const next = mid.length > 0 ? `${before}${mark}${mid}${mark}${after}` : `${before}${mark}${mark}${after}`;
    setBody(next);
    const cursor = mid.length > 0 ? sel.end + mark.length * 2 : sel.start + mark.length;
    setBodySel({ start: cursor, end: cursor });
  };

  const insertToken = (token: string) => {
    if (body.includes(token)) return;
    const sel = bodySel ?? { start: body.length, end: body.length };
    const before = body.slice(0, sel.start);
    const after = body.slice(sel.end);
    const spacer = before.length > 0 && !before.endsWith(' ') && !before.endsWith('\n') ? ' ' : '';
    const next = `${before}${spacer}${token}${after}`;
    setBody(next);
    const cursor = before.length + spacer.length + token.length;
    setBodySel({ start: cursor, end: cursor });
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
    setAttachedImage({ uri: picked.assets[0].uri });
    clearCreativeSelection();
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
        creative_edit_source: artworkRevision,
        ...invitationTypography,
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
    if (!personId && !selectedCreative) {
      CustomAlert.alert('المعاينة', 'اختر مستلمًا واحدًا على الأقل لمعاينة النص.');
      return;
    }
    setPreviewing(true);
    const res = await previewInvitation({
      person_id: personId ?? undefined,
      body,
      template_key: templateKey ?? undefined,
      template_vars: templateVars,
      title: title.trim() || undefined,
      creative_id: selectedCreativeId ?? undefined,
      creative_edit_source: artworkRevision,
      ...invitationTypography,
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
    if (recipientType === 'group' && !selectedGroup) {
      CustomAlert.alert('الإرسال', 'اختر مجموعة للمستلمين.');
      return;
    }
    if (recipientType === 'group' && selectedGroup?.persons_count === 0) {
      CustomAlert.alert('الإرسال', 'لا يوجد مستلمون في المجموعة.');
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
      creative_edit_source: artworkRevision,
      ...invitationTypography,
      body,
      template_key: templateKey,
      template_vars: templateVars,
      exclude_deceased: recipientType === 'external' ? false : excludeDeceased,
    };
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
        <InvitationSectionTitle
          title="نوع الدعوة"
          subtitle="اختر القالب المناسب"
          palette={palette}
        />

        {templatesLoading ? (
          <ActivityIndicator color={palette.primary} style={{ marginVertical: 14 }} />
        ) : (
          <>
            {/* بطاقات القوالب المصورة الرئيسية */}
            {visualTemplates.length > 0 ? (
              <View style={styles.visualCardsRow}>
                {visualTemplates.map((tpl) => {
                  const active = templateKey === tpl.key;
                  const meta = getTemplateVisualMeta(tpl.key);
                  return (
                    <Pressable
                      key={tpl.key}
                      accessibilityRole="button"
                      accessibilityState={{ selected: active }}
                      onPress={() => applyTemplate(tpl)}
                      style={({ pressed }) => [
                        styles.visualCard,
                        cardShadowStyle(palette),
                        {
                          borderColor: active ? palette.primary : palette.border,
                          borderWidth: active ? 2 : 1,
                          opacity: pressed ? 0.88 : 1,
                        },
                      ]}>
                      {meta.image ? (
                        <Image source={meta.image} style={StyleSheet.absoluteFill} contentFit="cover" />
                      ) : null}
                      <LinearGradient
                        colors={palette.cardOverlayGradient}
                        style={styles.visualCardOverlay}>
                        <View style={styles.visualCardTopRow}>
                          <View
                            style={[
                              styles.visualCardIconPill,
                              {
                                backgroundColor: active ? palette.primary : 'rgba(255,255,255,0.9)',
                              },
                            ]}>
                            <Ionicons
                              name={meta.icon}
                              size={15}
                              color={active ? '#FFFFFF' : '#10241C'}
                            />
                          </View>
                          {active ? (
                            <View style={styles.visualCardCheck}>
                              <Ionicons name="checkmark" size={14} color={palette.primary} />
                            </View>
                          ) : null}
                        </View>
                        <Text style={styles.visualCardLabel} numberOfLines={2}>
                          {tpl.name}
                        </Text>
                      </LinearGradient>
                    </Pressable>
                  );
                })}
              </View>
            ) : null}

            {/* شرائح القوالب النصية الثانوية */}
            {pillTemplates.length > 0 ? (
              <View style={styles.chipsRow}>
                {pillTemplates.map((tpl) => {
                  const active = templateKey === tpl.key;
                  const meta = getTemplateVisualMeta(tpl.key);
                  return (
                    <Pressable
                      key={tpl.key}
                      accessibilityRole="button"
                      accessibilityState={{ selected: active }}
                      onPress={() => applyTemplate(tpl)}
                      style={[
                        styles.chip,
                        cardShadowStyle(palette),
                        {
                          backgroundColor: active ? palette.pillBg : palette.surface,
                          borderColor: active ? palette.primary : palette.border,
                        },
                      ]}>
                      <View style={[styles.chipDot, { backgroundColor: meta.dotColor }]} />
                      <ThemedText
                        style={[
                          styles.chipText,
                          { color: active ? palette.pillText : palette.textSecondary },
                        ]}>
                        {tpl.name}
                      </ThemedText>
                    </Pressable>
                  );
                })}
              </View>
            ) : null}
          </>
        )}

        {/* بطاقة تفاصيل الدعوة */}
        <View
          style={[
            styles.detailsCard,
            cardShadowStyle(palette),
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <View style={[styles.detailsTopAccent, { backgroundColor: palette.primaryDark }]} />
          <InvitationSectionTitle
            title="تفاصيل الدعوة"
            palette={palette}
            style={{ marginTop: 2, marginBottom: 10 }}
          />

          <InvitationField
            label="العنوان"
            fontFamily={invitationTextFont}
            value={title}
            onChangeText={setTitle}
            placeholder="دعوة لحضور مناسبة عائلية"
            mutedColor={muted}
            textColor={palette.text}
            cardBg={palette.surfaceAlt}
            borderColor={border}
          />

          <View style={styles.formatRow}>
            <ThemedText style={[styles.formatLabel, { color: muted }]}>نص الرسالة · تنسيق النص</ThemedText>
            <View style={[styles.formatToolbar, { backgroundColor: palette.toolbarBg }]}>
              {([
                { mark: '*', label: 'B', extra: { fontWeight: '700' as const } },
                { mark: '_', label: 'I', extra: { fontStyle: 'italic' as const } },
                { mark: '~', label: 'S', extra: { textDecorationLine: 'line-through' as const } },
              ] as const).map(({ mark, label, extra }) => {
                const isActive = activeFormatMark === mark;
                return (
                  <TouchableOpacity
                    key={mark}
                    style={[
                      styles.formatBtn,
                      isActive && { backgroundColor: palette.toolbarActive },
                    ]}
                    onPress={() => wrapBodySelection(mark)}>
                    <Text style={[styles.formatBtnText, extra]}>{label}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>

          <InvitationField
            label="نص الرسالة"
            hideLabel
            fontFamily={invitationTextFont}
            value={body}
            onChangeText={setBody}
            placeholder="اكتب نص الدعوة أو اختر قالباً من الأعلى..."
            multiline
            selection={bodySel}
            onSelectionChange={setBodySel}
            mutedColor={muted}
            textColor={palette.text}
            cardBg={palette.surfaceAlt}
            borderColor={border}
          />

          <View style={styles.tokensRow}>
            {bodyTokenList.map((token) => (
              <Pressable
                key={token}
                onPress={() => insertToken(token)}
                style={[
                  styles.tokenPill,
                  { backgroundColor: palette.pillBg, borderColor: palette.pillBorder },
                ]}>
                <Text style={[styles.tokenPillText, { color: palette.pillText }]}>{token}</Text>
              </Pressable>
            ))}
          </View>
        </View>

        {/* شبكة تفاصيل القالب */}
        {varKeys.length > 0 ? (
          <>
            <InvitationSectionTitle
              title="تفاصيل القالب"
              palette={palette}
              rightBadge={
                <View style={[styles.varBadgePill, { backgroundColor: palette.pillBg, borderColor: palette.pillBorder }]}>
                  <Ionicons name="sync-outline" size={13} color={palette.pillText} />
                  <Text style={[styles.varBadgeText, { color: palette.pillText }]}>
                    {`متغيرات: ${invitationTypeName ?? 'مخصص'}`}
                  </Text>
                </View>
              }
            />

            <View style={styles.varGrid}>
              {varKeys.map((key) => {
                if (key === 'date') {
                  return (
                    <View
                      key={key}
                      style={[
                        styles.varCard,
                        cardShadowStyle(palette),
                        { width: '100%', backgroundColor: palette.surface, borderColor: palette.border },
                      ]}>
                      <View style={{ flex: 1 }}>
                        <DatePickerField
                          label={templateVarLabel(key)}
                          value={templateVars[key] ?? ''}
                          onChange={(text) =>
                            setTemplateVars((prev) => ({ ...prev, [key]: text }))
                          }
                          placeholder="اختر التاريخ..."
                          containerStyle={{ marginBottom: 0 }}
                        />
                      </View>
                    </View>
                  );
                }
                return (
                  <View
                    key={key}
                    style={[
                      styles.varCard,
                      cardShadowStyle(palette),
                      { backgroundColor: palette.surface, borderColor: palette.border },
                    ]}>
                    <View style={[styles.varCardIcon, { backgroundColor: palette.surfaceSoft }]}>
                      <Ionicons name={getVarFieldIcon(key)} size={18} color={palette.primary} />
                    </View>
                    <View style={styles.varCardBody}>
                      <Text style={[styles.varCardLabel, { color: palette.muted }]}>
                        {templateVarLabel(key)}
                      </Text>
                      <TextInput
                        value={templateVars[key] ?? ''}
                        onChangeText={(text) =>
                          setTemplateVars((prev) => ({ ...prev, [key]: text }))
                        }
                        placeholder={templateVarLabel(key)}
                        placeholderTextColor={palette.muted}
                        style={[styles.varCardInput, { color: palette.text }]}
                      />
                    </View>
                  </View>
                );
              })}
            </View>
          </>
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
          fontFamily={invitationTextFont}
          selectedCreative={selectedCreative}
          artworkEdited={Boolean(preparedCreative && appliedArtworkImage)}
          artworkPreviewImage={appliedArtworkImage ?? preparedImage ?? undefined}
          onEditArtwork={preparedCreative ? () => setPreparedEditorOpen(true) : undefined}
          attachedImage={attachedImage}
          onOpenCreative={() => void openCreativeModal()}
          onClearCreative={() => void clearCreativeSelection()}
          onPickAttachment={() => void pickAttachment()}
          onRemoveAttachment={() => setAttachedImage(null)}
        />

        <InvitationSectionTitle title="قنوات الإرسال" palette={palette} />
        <View style={styles.row}>
          <ChannelToggle
            label="واتساب"
            kind="whatsapp"
            color={WHATSAPP_GREEN}
            active={channels.includes('whatsapp')}
            onPress={() => toggleChannel('whatsapp')}
            cardBg={cardBg}
            border={border}
            textColor={palette.text}
          />
          <ChannelToggle
            label="إشعارات"
            kind="push"
            color={PUSH_BLUE}
            active={channels.includes('push')}
            onPress={() => toggleChannel('push')}
            cardBg={cardBg}
            border={border}
            textColor={palette.text}
          />
        </View>

        <InvitationSectionTitle title="المستلمون" palette={palette} />
        <View style={styles.row}>
          <Pressable
            onPress={() => setRecipientType('persons')}
            style={[
              styles.modeBtn,
              cardShadowStyle(palette),
              {
                backgroundColor: recipientType === 'persons' ? palette.primary : cardBg,
                borderColor: recipientType === 'persons' ? palette.primary : border,
              },
            ]}>
            <IconSymbol
              name="person.fill"
              size={15}
              color={recipientType === 'persons' ? '#FFFFFF' : palette.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'persons' ? '#FFFFFF' : palette.text },
              ]}>
              أشخاص
            </ThemedText>
          </Pressable>
          <Pressable
            onPress={() => setRecipientType('group')}
            style={[
              styles.modeBtn,
              cardShadowStyle(palette),
              {
                backgroundColor: recipientType === 'group' ? palette.primary : cardBg,
                borderColor: recipientType === 'group' ? palette.primary : border,
              },
            ]}>
            <IconSymbol
              name="person.2.fill"
              size={15}
              color={recipientType === 'group' ? '#FFFFFF' : palette.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'group' ? '#FFFFFF' : palette.text },
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
              cardShadowStyle(palette),
              {
                backgroundColor: recipientType === 'external' ? palette.primary : cardBg,
                borderColor: recipientType === 'external' ? palette.primary : border,
              },
            ]}>
            <Ionicons
              name="globe-outline"
              size={15}
              color={recipientType === 'external' ? '#FFFFFF' : palette.primary}
            />
            <ThemedText
              style={[
                styles.modeText,
                { color: recipientType === 'external' ? '#FFFFFF' : palette.text },
              ]}>
              خارج العائلة
            </ThemedText>
          </Pressable>
        </View>

        {recipientType === 'persons' ? (
          <View style={[styles.card, cardShadowStyle(palette), { backgroundColor: cardBg, borderColor: border }]}>
            <Pressable
              onPress={() => setPickerVisible(true)}
              style={[styles.pickBtn, { borderColor: palette.borderStrong, backgroundColor: palette.surfaceSoft }]}>
              <IconSymbol name="magnifyingglass" size={18} color={palette.primary} />
              <ThemedText style={[styles.pickBtnText, { color: palette.primary }]}>
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
                      setChosenPersons((prev) => prev.filter((x) => x.id !== p.id))
                    }
                    style={[styles.personChip, { borderColor: palette.pillBorder, backgroundColor: palette.pillBg }]}>
                    <PersonAvatar uri={p.photo_url} size={22} mutedColor={muted} />
                    <ThemedText style={[styles.personChipText, { color: palette.pillText }]}>{p.first_name}</ThemedText>
                    <IconSymbol name="xmark.circle.fill" size={14} color={palette.pillText} />
                  </Pressable>
                ))}
              </View>
            )}
          </View>
        ) : recipientType === 'group' ? (
          <View style={styles.groupsList}>
            {recipientGroups.length === 0 ? (
              <ThemedText style={[styles.hint, { color: muted }]}>
                لا توجد مجموعات بعد — أنشئ مجموعة من تبويب المجموعات.
              </ThemedText>
            ) : (
              recipientGroups.map((g) => {
                const active = groupId === g.id;
                return (
                  <Pressable
                    key={g.id}
                    onPress={() => setGroupId(g.id)}
                    style={[
                      styles.groupRow,
                      cardShadowStyle(palette),
                      {
                        backgroundColor: active ? palette.surfaceSoft : cardBg,
                        borderColor: active ? palette.primary : border,
                        borderWidth: active ? 1.5 : 1,
                      },
                    ]}>
                    <View style={{ flex: 1 }}>
                      <ThemedText style={[styles.groupName, { color: palette.text }]}>{g.name}</ThemedText>
                      <ThemedText style={[styles.hint, { color: muted, marginBottom: 0, textAlign: 'right' }]}>
                        {g.persons_count} عضو
                      </ThemedText>
                    </View>
                    {active ? (
                      <IconSymbol name="checkmark.circle.fill" size={22} color={palette.primary} />
                    ) : null}
                  </Pressable>
                );
              })
            )}
          </View>
        ) : (
          <View style={[styles.card, cardShadowStyle(palette), { backgroundColor: cardBg, borderColor: border }]}>
            <View style={styles.externalHeader}>
              <View style={[styles.externalIconWrap, { backgroundColor: palette.surfaceSoft }]}>
                <Ionicons name="logo-whatsapp" size={20} color={WHATSAPP_GREEN} />
              </View>
              <View style={{ flex: 1 }}>
                <ThemedText style={[styles.cardTitle, { color: palette.text, marginBottom: 2, textAlign: 'right' }]}>
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
              textColor={palette.text}
              cardBg={palette.surfaceAlt}
              borderColor={border}
            />

            <View style={{ marginBottom: 12 }}>
              <ThemedText style={[styles.fieldLabel, { color: muted }]}>
                رقم الواتساب
              </ThemedText>
              <View
                style={[
                  styles.phoneInputRow,
                  { backgroundColor: palette.surfaceAlt, borderColor: border },
                ]}>
                <TouchableOpacity
                  style={styles.countryBadge}
                  onPress={() => setCountryPickerVisible(true)}
                  activeOpacity={0.7}>
                  <ThemedText style={styles.countryFlag}>{selectedCountry.flag}</ThemedText>
                  <ThemedText style={[styles.countryCode, { color: palette.text }]}>
                    {selectedCountry.dialCode}
                  </ThemedText>
                  <MaterialIcons name="arrow-drop-down" size={18} color={muted} />
                </TouchableOpacity>

                <View
                  style={[
                    styles.verticalDivider,
                    { backgroundColor: border },
                  ]}
                />

                <TextInput
                  style={[styles.phoneTextInput, { color: palette.text }]}
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

            <View style={[styles.noticeBadge, { backgroundColor: palette.pillBg }]}>
              <Ionicons name="checkmark-circle-outline" size={16} color={palette.primary} />
              <ThemedText style={[styles.noticeText, { color: palette.pillText }]}>
                سيتم استبدال {'{name}'} باسم الضيف عند إرسال الدعوة عبر واتساب.
              </ThemedText>
            </View>
          </View>
        )}

        <View style={styles.actions}>
          <Pressable
            onPress={() => void handlePreview()}
            disabled={previewing || sending}
            style={[
              styles.secondaryBtn,
              {
                borderColor: palette.primary,
                backgroundColor: palette.surface,
              },
            ]}>
            {previewing ? (
              <ActivityIndicator color={palette.primary} />
            ) : (
              <>
                <IconSymbol name="eye.fill" size={18} color={palette.primary} />
                <Text style={[styles.secondaryBtnText, { color: palette.primary }]}>معاينة</Text>
              </>
            )}
          </Pressable>
          <View style={styles.primaryBtn}>
            <InvitationGradientButton
              label="إرسال"
              icon="paper-plane"
              loading={sending}
              disabled={sending}
              onPress={handleSend}
              palette={palette}
            />
          </View>
        </View>
      </ScrollView>

      <RecipientPickerModal
        visible={pickerVisible}
        onClose={() => setPickerVisible(false)}
        onConfirm={setChosenPersons}
        initialSelected={selectedPersons}
        excludedPersonId={senderId}
        colors={colors}
        isDark={isDark}
      />

      <CreativePickerModal
        invitationTypeName={invitationTypeName}
        visible={creativeModalVisible}
        onClose={() => setCreativeModalVisible(false)}
        colors={colors}
        cardBg={cardBg}
        border={border}
        muted={muted}
        loading={creativeLoading}
        creatives={matchingCreatives}
        tempCreativeId={tempCreativeId}
        onSelect={setTempCreativeId}
        onConfirm={() => void confirmCreativeSelection()}
      />

      {preparedEditorOpen && preparedImage && preparedCreative?.editable_text_fields ? (
        <PreparedArtworkEditor
          imageUrl={preparedImage}
          fields={preparedCreative.editable_text_fields}
          values={templateVars}
          colors={colors}
          background={cardBg}
          border={border}
          renderPreview={renderPreparedPreview}
          onClose={() => setPreparedEditorOpen(false)}
          onApply={(values, image) => {
            setTemplateVars((current) => ({ ...current, ...values }));
            setAppliedArtworkImage(image);
            setPreparedEditorOpen(false);
          }}
        />
      ) : null}

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
        fontFamily={invitationTextFont}
      />
    </View>
  );
}
