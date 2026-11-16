import { ThemedText } from '@/components/themed-text';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import type { CreateGroupPayload } from '@/lib/api/walkingApi';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ChallengeInvitePickerModal, SelectedPerson } from './ChallengeInvitePickerModal';

type Props = {
  visible: boolean;
  onClose: () => void;
  onSubmit: (payload: CreateGroupPayload) => Promise<{ success: boolean; message?: string }>;
};

type DurationPreset = '7_days' | '14_days' | '30_days' | 'open' | 'custom';
type TargetMode = 'steps' | 'open';

function formatDateYMD(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function addDaysToDate(base: Date, days: number): Date {
  const res = new Date(base);
  res.setDate(res.getDate() + days);
  return res;
}

const DURATION_PRESETS: { key: DurationPreset; label: string; days: number }[] = [
  { key: '7_days', label: 'أسبوع (7 أيام)', days: 7 },
  { key: '14_days', label: 'أسبوعين (14 يوم)', days: 14 },
  { key: '30_days', label: 'شهر (30 يوم)', days: 30 },
  { key: 'open', label: 'مستمر (مفتوح ♾️)', days: 0 },
  { key: 'custom', label: 'تاريخ مخصص 📅', days: 0 },
];

const STEP_PRESETS = [35000, 50000, 70000, 100000];

export function CreateGroupModal({ visible, onClose, onSubmit }: Props) {
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [targetMode, setTargetMode] = useState<TargetMode>('steps');
  const [targetSteps, setTargetSteps] = useState('70000');
  const [durationPreset, setDurationPreset] = useState<DurationPreset>('7_days');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [keyboardVisible, setKeyboardVisible] = useState(false);
  const [showMemberPicker, setShowMemberPicker] = useState(false);

  // Default dates: Today to +7 days
  const todayStr = formatDateYMD(new Date());
  const nextWeekStr = formatDateYMD(addDaysToDate(new Date(), 7));

  const [startDate, setStartDate] = useState(todayStr);
  const [endDate, setEndDate] = useState<string | null>(nextWeekStr);

  // Selected family members state
  const [selectedMembers, setSelectedMembers] = useState<SelectedPerson[]>([]);

  // Keyboard visibility listener to handle dismiss accessory
  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSub = Keyboard.addListener(showEvent, () => setKeyboardVisible(true));
    const hideSub = Keyboard.addListener(hideEvent, () => setKeyboardVisible(false));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  const handleTogglePerson = (person: any) => {
    try {
      Haptics.selectionAsync();
    } catch { }

    const personId = person.id;
    const personName =
      person.name ||
      person.full_name ||
      [person.first_name, person.last_name].filter(Boolean).join(' ') ||
      person.first_name ||
      'عضو';
    const personAvatar = person.avatar || person.photo_url || null;

    const isAlreadySelected = selectedMembers.some((m) => m.id === personId);

    if (isAlreadySelected) {
      setSelectedMembers((prev) => prev.filter((m) => m.id !== personId));
    } else {
      setSelectedMembers((prev) => [
        ...prev,
        { id: personId, name: personName, avatar: personAvatar },
      ]);
    }
  };

  const handleRemoveSelected = (personId: number) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }
    setSelectedMembers((prev) => prev.filter((m) => m.id !== personId));
  };

  const handleDurationPresetChange = (preset: DurationPreset) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setDurationPreset(preset);
    const today = new Date();
    const start = formatDateYMD(today);
    setStartDate(start);

    if (preset === '7_days') {
      setEndDate(formatDateYMD(addDaysToDate(today, 7)));
    } else if (preset === '14_days') {
      setEndDate(formatDateYMD(addDaysToDate(today, 14)));
    } else if (preset === '30_days') {
      setEndDate(formatDateYMD(addDaysToDate(today, 30)));
    } else if (preset === 'open') {
      setEndDate(null);
    }
  };

  const resetForm = () => {
    setName('');
    setDescription('');
    setTargetMode('steps');
    setTargetSteps('70000');
    setDurationPreset('7_days');
    setStartDate(todayStr);
    setEndDate(nextWeekStr);
    setSelectedMembers([]);
    setShowMemberPicker(false);
  };

  const handleCreate = async () => {
    Keyboard.dismiss();
    if (!name.trim()) {
      CustomAlert.alert('تنبيه', 'يرجى إدخال اسم المجموعة أو التحدي');
      return;
    }

    let parsedSteps: number | null = null;
    if (targetMode === 'steps') {
      parsedSteps = parseInt(targetSteps, 10);
      if (isNaN(parsedSteps) || parsedSteps <= 0) {
        CustomAlert.alert('تنبيه', 'يرجى إدخال عدد خطوات صحيح للتحدي');
        return;
      }
    }

    setIsSubmitting(true);
    try {
      const payload: CreateGroupPayload = {
        name: name.trim(),
        description: description.trim() || null,
        target_steps: parsedSteps,
        start_date: startDate,
        end_date: durationPreset === 'open' ? null : endDate || null,
        is_public: false, // دائماً مجموعة خاصة للعائلة والأصدقاء
        member_person_ids: selectedMembers.map((m) => m.id),
      };

      const res = await onSubmit(payload);

      if (res.success) {
        resetForm();
        onClose();
        setTimeout(() => {
          CustomAlert.alert('تم إنشاء التحدي 🎉', res.message || 'تم إنشاء التحدي بنجاح وإضافة الأعضاء!');
        }, 350);
      } else {
        CustomAlert.alert('خطأ', res.message || 'تعذر إنشاء المجموعة');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.keyboardContainer}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 10 : 0}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {/* Modal Header */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={() => {
                  Keyboard.dismiss();
                  try {
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  } catch { }
                  onClose();
                }}
                style={styles.closeBtn}>
                <IconSymbol name="xmark" size={20} color={colors.icon} />
              </TouchableOpacity>

              <ThemedText style={styles.headerTitle}>إنشاء تحدي / مجموعة جديدة</ThemedText>
              <View style={{ width: 32 }} />
            </View>

            {/* Scrollable Form Body */}
            <ScrollView
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="interactive"
              automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
              style={{ flexShrink: 1 }}
              contentContainerStyle={[styles.formContent, { paddingBottom: 20 }]}>
              {/* Group Name */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>اسم التحدي أو المجموعة *</ThemedText>
                <TextInput
                  value={name}
                  onChangeText={setName}
                  placeholder="مثال: تحدي فرسان السريّع الأسبوعي"
                  placeholderTextColor={colors.icon}
                  style={[
                    styles.input,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                      borderColor: colors.border,
                      color: colors.text,
                    },
                  ]}
                />
              </View>

              {/* Description */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>وصف التحدي (اختياري)</ThemedText>
                <TextInput
                  value={description}
                  onChangeText={setDescription}
                  placeholder="وصف مختصر لأهداف وقواعد التحدي..."
                  placeholderTextColor={colors.icon}
                  multiline
                  numberOfLines={2}
                  style={[
                    styles.input,
                    styles.textArea,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                      borderColor: colors.border,
                      color: colors.text,
                    },
                  ]}
                />
              </View>

              {/* Target Mode */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>نوع هدف التحدي</ThemedText>
                <View style={[styles.segmentWrap, { backgroundColor: isDark ? '#27272A' : '#F3F4F6' }]}>
                  <TouchableOpacity
                    activeOpacity={0.8}
                    onPress={() => {
                      try {
                        Haptics.selectionAsync();
                      } catch { }
                      setTargetMode('steps');
                    }}
                    style={[
                      styles.segmentBtn,
                      targetMode === 'steps' && [
                        styles.segmentBtnActive,
                        { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                      ],
                    ]}>
                    <ThemedText
                      style={[
                        styles.segmentText,
                        { color: targetMode === 'steps' ? silaTheme.primary : colors.icon },
                        targetMode === 'steps' && styles.segmentTextActive,
                      ]}>
                      🎯 هدف بعدد الخطوات
                    </ThemedText>
                  </TouchableOpacity>

                  <TouchableOpacity
                    activeOpacity={0.8}
                    onPress={() => {
                      try {
                        Haptics.selectionAsync();
                      } catch { }
                      setTargetMode('open');
                    }}
                    style={[
                      styles.segmentBtn,
                      targetMode === 'open' && [
                        styles.segmentBtnActive,
                        { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                      ],
                    ]}>
                    <ThemedText
                      style={[
                        styles.segmentText,
                        { color: targetMode === 'open' ? silaTheme.primary : colors.icon },
                        targetMode === 'open' && styles.segmentTextActive,
                      ]}>
                      ⚡ صدارة مفتوحة (بدون سقف)
                    </ThemedText>
                  </TouchableOpacity>
                </View>
              </View>

              {/* Target Steps (only if steps mode) */}
              {targetMode === 'steps' && (
                <View style={styles.inputGroup}>
                  <ThemedText style={styles.label}>الهدف الكلي للتحدي (عدد الخطوات) *</ThemedText>
                  {/* Step Presets */}
                  <View style={styles.presetsRow}>
                    {STEP_PRESETS.map((st) => {
                      const isSelected = targetSteps === String(st);
                      return (
                        <TouchableOpacity
                          key={st}
                          activeOpacity={0.8}
                          onPress={() => {
                            try {
                              Haptics.selectionAsync();
                            } catch { }
                            setTargetSteps(String(st));
                          }}
                          style={[
                            styles.presetPill,
                            {
                              backgroundColor: isSelected
                                ? silaTheme.primary
                                : isDark
                                  ? '#27272A'
                                  : '#F3F4F6',
                              borderColor: isSelected ? silaTheme.primary : colors.border,
                            },
                          ]}>
                          <ThemedText
                            style={[
                              styles.presetPillText,
                              { color: isSelected ? '#FFFFFF' : colors.text },
                              isSelected && styles.presetPillTextActive,
                            ]}>
                            {(st / 1000).toLocaleString('ar-SA')} ألف
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>

                  <TextInput
                    value={targetSteps}
                    onChangeText={setTargetSteps}
                    keyboardType="numeric"
                    placeholder="مثال: 70000"
                    placeholderTextColor={colors.icon}
                    style={[
                      styles.input,
                      {
                        backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                        borderColor: colors.border,
                        color: colors.text,
                      },
                    ]}
                  />
                </View>
              )}

              {/* Duration / Timeline Section */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>مدة التحدي والجدول الزمني</ThemedText>

                {/* Duration Presets */}
                <View style={styles.presetsRow}>
                  {DURATION_PRESETS.map((item) => {
                    const isSelected = durationPreset === item.key;
                    return (
                      <TouchableOpacity
                        key={item.key}
                        activeOpacity={0.8}
                        onPress={() => handleDurationPresetChange(item.key)}
                        style={[
                          styles.presetPill,
                          {
                            backgroundColor: isSelected
                              ? silaTheme.primary
                              : isDark
                                ? '#27272A'
                                : '#F3F4F6',
                            borderColor: isSelected ? silaTheme.primary : colors.border,
                          },
                        ]}>
                        <ThemedText
                          style={[
                            styles.presetPillText,
                            { color: isSelected ? '#FFFFFF' : colors.text },
                            isSelected && styles.presetPillTextActive,
                          ]}>
                          {item.label}
                        </ThemedText>
                      </TouchableOpacity>
                    );
                  })}
                </View>

                {/* Duration Summary Strip */}
                <View
                  style={[
                    styles.durationSummaryStrip,
                    {
                      backgroundColor: isDark ? '#064E3B33' : '#ECFDF5',
                      borderColor: isDark ? '#065F46' : '#A7F3D0',
                    },
                  ]}>
                  <IconSymbol name="calendar" size={15} color={silaTheme.primary} />
                  <ThemedText style={[styles.durationSummaryText, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                    {durationPreset === 'open'
                      ? `يبدأ من ${startDate} (تحدي مفتوح ومستمر ♾️)`
                      : `الفترة: من ${startDate} إلى ${endDate || 'مستمر'}`}
                  </ThemedText>
                </View>

                {/* Custom Dates row */}
                {durationPreset === 'custom' && (
                  <View style={styles.datesRow}>
                    <View style={{ flex: 1 }}>
                      <DatePickerField
                        label="تاريخ البدء"
                        value={startDate}
                        onChange={setStartDate}
                        placeholder="اختر تاريخ البدء"
                        minimumDate={todayStr}
                        allowClear={false}
                      />
                    </View>

                    <View style={{ flex: 1 }}>
                      <DatePickerField
                        label="تاريخ الانتهاء"
                        value={endDate}
                        onChange={setEndDate}
                        placeholder="اختياري أو مستمر"
                        minimumDate={startDate || todayStr}
                        allowClear={true}
                      />
                    </View>
                  </View>
                )}
              </View>

              {/* ==================================================== */}
              {/* دعوة الأعضاء (بطاقة سهلة لكبار السن مع منتقي مخصص) */}
              {/* ==================================================== */}
              <View style={styles.memberSection}>
                <View style={styles.memberSectionHeader}>
                  <ThemedText style={styles.label}>
                    المشاركون من العائلة ({selectedMembers.length})
                  </ThemedText>
                  {selectedMembers.length > 0 && (
                    <TouchableOpacity onPress={() => setSelectedMembers([])}>
                      <ThemedText style={styles.clearSelectedText}>مسح التحديد</ThemedText>
                    </TouchableOpacity>
                  )}
                </View>

                {/* Selected Members Chips Horizontal */}
                {selectedMembers.length > 0 && (
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.chipsScroll}>
                    {selectedMembers.map((member) => (
                      <View
                        key={member.id}
                        style={[
                          styles.memberChip,
                          {
                            backgroundColor: isDark ? '#064E3B' : '#ECFDF5',
                            borderColor: silaTheme.primary,
                          },
                        ]}>
                        {member.avatar ? (
                          <Image source={{ uri: member.avatar }} style={styles.chipAvatar} />
                        ) : (
                          <View style={[styles.chipAvatarPlaceholder, { backgroundColor: silaTheme.primary }]}>
                            <ThemedText style={styles.chipInitialText}>
                              {member.name.slice(0, 1)}
                            </ThemedText>
                          </View>
                        )}
                        <ThemedText style={[styles.chipName, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                          {member.name}
                        </ThemedText>
                        <TouchableOpacity
                          onPress={() => handleRemoveSelected(member.id)}
                          style={styles.chipRemoveBtn}>
                          <IconSymbol name="xmark.circle.fill" size={16} color={isDark ? '#34D399' : '#059669'} />
                        </TouchableOpacity>
                      </View>
                    ))}
                  </ScrollView>
                )}

                {/* Big Friendly Button to Open Dedicated Family Selector */}
                <TouchableOpacity
                  activeOpacity={0.85}
                  onPress={() => {
                    Keyboard.dismiss();
                    try {
                      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                    } catch { }
                    setShowMemberPicker(true);
                  }}
                  style={[
                    styles.openPickerCard,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F0FDF4',
                      borderColor: selectedMembers.length > 0 ? silaTheme.primary : (isDark ? '#374151' : '#BBF7D0'),
                    },
                  ]}>
                  <View style={[styles.pickerIconCircle, { backgroundColor: silaTheme.primary }]}>
                    <IconSymbol name="person.crop.circle.badge.plus" size={20} color="#FFFFFF" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <ThemedText style={styles.openPickerCardTitle}>
                      {selectedMembers.length > 0
                        ? `تم تحديد (${selectedMembers.length}) من أفراد العائلة`
                        : 'اختيار ودعوة أفراد من العائلة للتحدي'}
                    </ThemedText>
                    <ThemedText style={[styles.openPickerCardSub, { color: colors.icon }]}>
                      {selectedMembers.length > 0
                        ? 'اضغط هنا لتعديل أو إضافة المزيد من الأفراد'
                        : 'اضغط لاختيار من تحب من قائمة العائلة للمنافسة معك'}
                    </ThemedText>
                  </View>
                  <IconSymbol name="chevron.left" size={18} color={silaTheme.primary} />
                </TouchableOpacity>
              </View>
            </ScrollView>

            {/* زر إخفاء الكيبورد السريع (يظهر تلقائياً عندما تكون لوحة المفاتيح نشطة لتسهيل الأمر لكبار السن) */}
            {keyboardVisible && (
              <TouchableOpacity
                activeOpacity={0.8}
                onPress={() => Keyboard.dismiss()}
                style={[styles.keyboardDismissBar, { backgroundColor: isDark ? '#27272A' : '#F3F4F6', borderTopColor: colors.border }]}>
                <IconSymbol name="keyboard.chevron.compact.down" size={16} color={silaTheme.primary} />
                <ThemedText style={[styles.keyboardDismissText, { color: silaTheme.primary }]}>
                  إخفاء لوحة المفاتيح ⌄
                </ThemedText>
              </TouchableOpacity>
            )}

            {/* ==================================================== */}
            {/* شريط الإجراءات السفلي الثابت (Pinned Fixed Footer) */}
            {/* يظل ظاهراً دائماً في أسفل الشاشة ولا يختفي نهائياً! */}
            {/* ==================================================== */}
            <View
              style={[
                styles.pinnedFooter,
                {
                  backgroundColor: colors.card,
                  borderTopColor: colors.border,
                  paddingBottom: Math.max(insets.bottom + 8, Platform.OS === 'ios' ? 34 : 22),
                },
              ]}>
              <TouchableOpacity
                activeOpacity={0.85}
                disabled={isSubmitting}
                onPress={handleCreate}
                style={[styles.submitBtn, { opacity: isSubmitting ? 0.7 : 1 }]}>
                {isSubmitting ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <ThemedText style={styles.submitBtnText}>
                    {selectedMembers.length > 0
                      ? `إطلاق التحدي مع (${selectedMembers.length}) أعضاء 🚀`
                      : 'إطلاق التحدي الآن 🚀'}
                  </ThemedText>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </KeyboardAvoidingView>

      {/* ==================================================== */}
      {/* مودال اختيار ودعوة أفراد العائلة (شجرة صلة + قائمة البحث) */}
      {/* ==================================================== */}
      {showMemberPicker && (
        <ChallengeInvitePickerModal
          visible={showMemberPicker}
          onClose={() => setShowMemberPicker(false)}
          selectedMembers={selectedMembers}
          onTogglePerson={handleTogglePerson}
          onRemovePerson={handleRemoveSelected}
          onClearAll={() => setSelectedMembers([])}
          onConfirm={() => setShowMemberPicker(false)}
          confirmLabel={
            selectedMembers.length > 0
              ? `✅ تأكيد واختيار (${selectedMembers.length}) أفراد`
              : '✅ متابعة بدون إضافة أعضاء'
          }
          title="اختيار أفراد العائلة للتحدي 👥"
        />
      )}
    </Modal>
  );
}

const styles = StyleSheet.create({
  keyboardContainer: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingTop: 18,
    maxHeight: Platform.OS === 'ios' ? '90%' : '92%',
  },
  pickerModalContent: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingTop: 18,
    maxHeight: Platform.OS === 'ios' ? '88%' : '90%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
    paddingHorizontal: 20,
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(150, 150, 150, 0.2)',
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(150, 150, 150, 0.1)',
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
  },
  formContent: {
    gap: 16,
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  inputGroup: {
    gap: 6,
  },
  label: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  input: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 10,

  },
  textArea: {
    minHeight: 60,
    textAlignVertical: 'top',
  },
  subLabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  segmentWrap: {
    flexDirection: 'row',
    borderRadius: 14,
    padding: 3,
    gap: 4,
  },
  segmentBtn: {
    flex: 1,
    paddingVertical: 9,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  segmentBtnActive: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.08,
        shadowRadius: 3,
      },
      android: { elevation: 2 },
    }),
  },
  segmentText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  segmentTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  presetsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 4,
  },
  presetPill: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 12,
    borderWidth: 1,
  },
  presetPillText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  presetPillTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  durationSummaryStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    borderWidth: 1,
  },
  durationSummaryText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  datesRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 4,
  },
  memberSection: {
    gap: 8,
    marginTop: 4,
  },
  memberSectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  clearSelectedText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    color: '#EF4444',
  },
  chipsScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 4,
  },
  memberChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingLeft: 8,
    paddingRight: 6,
    borderRadius: 20,
    borderWidth: 1,
    gap: 6,
  },
  chipAvatar: {
    width: 24,
    height: 24,
    borderRadius: 12,
  },
  chipAvatarPlaceholder: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipInitialText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  chipName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  chipRemoveBtn: {
    padding: 2,
  },
  openPickerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 16,
    borderWidth: 1.5,
    gap: 12,
  },
  pickerIconCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  openPickerCardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  openPickerCardSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    marginTop: 2,
  },
  keyboardDismissBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  keyboardDismissText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  pinnedFooter: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: Platform.OS === 'ios' ? 34 : 16,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  submitBtn: {
    backgroundColor: silaTheme.primary,
    paddingVertical: 14,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 52,
    ...Platform.select({
      ios: {
        shadowColor: silaTheme.primary,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
      },
      android: { elevation: 4 },
    }),
  },
  submitBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    color: '#FFFFFF',
    lineHeight: 22,
    includeFontPadding: false,
  },
  pickerHelpText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    paddingHorizontal: 20,
    marginBottom: 8,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 20,
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
    gap: 8,
    marginBottom: 8,
  },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,

    padding: 0,
  },
  pickerListScroll: {
    maxHeight: 320,
    paddingHorizontal: 20,
  },
  resultRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    marginBottom: 4,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(150, 150, 150, 0.15)',
  },
  resultLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  resultAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  resultAvatarPlaceholder: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  resultInitialText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  resultName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,

  },
  selectBadge: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  noResultBox: {
    padding: 24,
    alignItems: 'center',
  },
  noResultText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
});
