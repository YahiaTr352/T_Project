import { ThemedText } from '@/components/themed-text';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { formatArabicDateDisplay } from '@/components/ui/DatePickerModal';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import {
  type UpdateGroupPayload,
  type WalkingGroupDetail,
  type WalkingGroupItem,
  type WalkingGroupMember,
  updateWalkingGroup,
  deleteWalkingGroup,
  addGroupMembers,
  fetchWalkingGroupDetails,
} from '@/lib/api/walkingApi';
import { cleanDateYMD, formatSteps } from '@/lib/walking/formatters';
import * as Haptics from 'expo-haptics';
import { useEffect, useMemo, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import {
  ChallengeInvitePickerModal,
  SelectedPerson,
} from './ChallengeInvitePickerModal';
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


type Props = {
  visible: boolean;
  group: WalkingGroupDetail | WalkingGroupItem | null;
  onClose: () => void;
  onSuccess: (updatedGroup: WalkingGroupItem) => void;
  onDelete?: (groupId: number) => void;
};

type DurationType = '7_days' | '14_days' | '30_days' | 'open' | 'custom';
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

/**
 * تحليل آمن وموثوق للتاريخ يتجنب أخطاء WebKit/iOS على السلاسل ذات المسافات
 */
function parseSafeDate(val?: string | null): Date {
  if (!val) return new Date();
  const clean = cleanDateYMD(val);
  const parts = clean.split('-');
  if (parts.length === 3) {
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const date = new Date(y, m, d);
    if (!isNaN(date.getTime())) return date;
  }
  const fallback = new Date(val);
  return isNaN(fallback.getTime()) ? new Date() : fallback;
}

function calculateDaysBetween(startStr?: string | null, endStr?: string | null): number | null {
  if (!startStr || !endStr) return null;
  const start = parseSafeDate(startStr);
  const end = parseSafeDate(endStr);
  const diffTime = end.getTime() - start.getTime();
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
  return diffDays >= 0 ? diffDays : null;
}

const STEP_PRESETS = [35000, 50000, 70000, 100000];

const DURATION_PRESETS: { key: DurationType; label: string }[] = [
  { key: '7_days', label: 'أسبوع (7 أيام)' },
  { key: '14_days', label: 'أسبوعين (14 يوم)' },
  { key: '30_days', label: 'شهر (30 يوم)' },
  { key: 'open', label: 'مستمر' },
  { key: 'custom', label: 'تاريخ مخصص' },
];

export function EditGroupModal({ visible, group, onClose, onSuccess, onDelete }: Props) {
  const [isDeleting, setIsDeleting] = useState(false);
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [targetMode, setTargetMode] = useState<TargetMode>('steps');
  const [targetSteps, setTargetSteps] = useState('70000');

  const [durationType, setDurationType] = useState<DurationType>('7_days');
  const [startDate, setStartDate] = useState(formatDateYMD(new Date()));
  const [endDate, setEndDate] = useState<string | null>(null);

  const [isSubmitting, setIsSubmitting] = useState(false);

  // حالة إدارة الأعضاء
  const [existingMembers, setExistingMembers] = useState<WalkingGroupMember[]>([]);
  const [loadingMembers, setLoadingMembers] = useState(false);
  const [newSelectedMembers, setNewSelectedMembers] = useState<SelectedPerson[]>([]);
  const [showMemberPicker, setShowMemberPicker] = useState(false);

  // Sync state when group prop changes or modal becomes visible
  useEffect(() => {
    if (group && visible) {
      setName(group.name || '');
      setDescription(group.description || '');
      setNewSelectedMembers([]);

      // جلب جميع أعضاء التحدي بشكل دقيق من الـ API (endpoint التفاصيل الكاملة)
      // لأن كروت المجموعات من قائمة الفهرس (index) تحتوي فقط على عضوية المستخدم الحالي
      setLoadingMembers(true);
      fetchWalkingGroupDetails(group.id)
        .then((detail) => {
          if (detail && Array.isArray(detail.members) && detail.members.length > 0) {
            setExistingMembers(detail.members);
          } else if ((group as any).members && Array.isArray((group as any).members)) {
            setExistingMembers((group as any).members);
          }
        })
        .catch((err) => {
          console.warn('[EditGroupModal] fetchWalkingGroupDetails error:', err);
          if ((group as any).members && Array.isArray((group as any).members)) {
            setExistingMembers((group as any).members);
          }
        })
        .finally(() => {
          setLoadingMembers(false);
        });

      if (group.target_steps && group.target_steps > 0) {
        setTargetMode('steps');
        setTargetSteps(String(group.target_steps));
      } else {
        setTargetMode('open');
        setTargetSteps('70000');
      }

      // تنظيف تواريخ البداية والنهاية من أي لواحق وقت زائدة
      const cleanStart = group.start_date ? cleanDateYMD(group.start_date) : formatDateYMD(new Date());
      const cleanEnd = group.end_date ? cleanDateYMD(group.end_date) : null;

      setStartDate(cleanStart);
      setEndDate(cleanEnd);

      if (!cleanEnd) {
        setDurationType('open');
      } else {
        const diff = calculateDaysBetween(cleanStart, cleanEnd);
        if (diff === 7) {
          setDurationType('7_days');
        } else if (diff === 14) {
          setDurationType('14_days');
        } else if (diff === 30) {
          setDurationType('30_days');
        } else {
          setDurationType('custom');
        }
      }
    }
  }, [group, visible]);

  const handleDurationPresetChange = (preset: DurationType) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setDurationType(preset);

    const base = startDate ? parseSafeDate(startDate) : new Date();

    if (preset === '7_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 7)));
    } else if (preset === '14_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 14)));
    } else if (preset === '30_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 30)));
    } else if (preset === 'open') {
      setEndDate(null);
    } else if (preset === 'custom') {
      if (!endDate) {
        setEndDate(formatDateYMD(addDaysToDate(base, 7)));
      }
    }
  };

  const handleStartDateChange = (newStart: string) => {
    const clean = cleanDateYMD(newStart);
    setStartDate(clean);

    if (durationType === 'open') return;

    const base = parseSafeDate(clean);
    if (durationType === '7_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 7)));
    } else if (durationType === '14_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 14)));
    } else if (durationType === '30_days') {
      setEndDate(formatDateYMD(addDaysToDate(base, 30)));
    }
  };

  const handleEndDateChange = (newEnd: string) => {
    const clean = cleanDateYMD(newEnd);
    setEndDate(clean);
    setDurationType('custom');
  };

  const daysCount = useMemo(() => {
    if (durationType === 'open' || !endDate) return null;
    return calculateDaysBetween(startDate, endDate);
  }, [durationType, startDate, endDate]);

  const durationSummaryText = useMemo(() => {
    const startFormatted = formatArabicDateDisplay(startDate);
    if (durationType === 'open' || !endDate) {
      return `يبدأ من ${startFormatted} (تحدي مفتوح ومستمر ♾️)`;
    }
    const endFormatted = formatArabicDateDisplay(endDate);
    const daysLabel = daysCount !== null
      ? ` (${daysCount} ${daysCount === 1 ? 'يوم' : daysCount === 2 ? 'يومان' : daysCount <= 10 ? 'أيام' : 'يوماً'})`
      : '';
    return `الفترة: من ${startFormatted} إلى ${endFormatted}${daysLabel}`;
  }, [durationType, startDate, endDate, daysCount]);

  const existingMemberIds = useMemo(() => {
    return existingMembers
      .map((m: any) => Number(m.person_id || m.person?.id || 0))
      .filter((id) => id > 0);
  }, [existingMembers]);

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

    const isAlreadySelected = newSelectedMembers.some((m) => m.id === personId);

    if (isAlreadySelected) {
      setNewSelectedMembers((prev) => prev.filter((m) => m.id !== personId));
    } else {
      setNewSelectedMembers((prev) => [
        ...prev,
        { id: personId, name: personName, avatar: personAvatar },
      ]);
    }
  };

  const handleRemoveSelected = (personId: number) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }
    setNewSelectedMembers((prev) => prev.filter((m) => m.id !== personId));
  };

  const handleDeleteGroupClick = () => {
    if (!group?.id) return;
    CustomAlert.alert(
      'حذف التحدي (سلة المحذوفات)',
      'هل أنت متأكد من رغبتك في حذف هذا التحدي؟ سيتم إخفاؤه ونقله لسلة المحذوفات مع إمكانية استرجاعه لاحقاً من لوحة التحكم.',
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'نعم، احذف التحدي',
          style: 'destructive',
          onPress: async () => {
            setIsDeleting(true);
            try {
              const res = await deleteWalkingGroup(group.id);
              if (res?.success) {
                if (onDelete) onDelete(group.id);
                onClose();
                setTimeout(() => {
                  CustomAlert.alert('تم الحذف', 'تم حذف التحدي بنجاح ونقله لسلة المحذوفات.');
                }, 350);
              } else {
                CustomAlert.alert('خطأ', res?.message || 'تعذر حذف التحدي');
              }
            } finally {
              setIsDeleting(false);
            }
          },
        },
      ]
    );
  };

  const handleSave = async () => {
    if (!group) return;
    Keyboard.dismiss();

    if (!name.trim()) {
      CustomAlert.alert('تنبيه', 'يرجى إدخال اسم التحدي أو المجموعة');
      return;
    }

    let parsedSteps: number | null = null;
    if (targetMode === 'steps') {
      parsedSteps = parseInt(targetSteps, 10);
      if (isNaN(parsedSteps) || parsedSteps <= 0) {
        CustomAlert.alert('تنبيه', 'يرجى إدخال هدف خطوات صحيح');
        return;
      }
    }

    setIsSubmitting(true);
    try {
      const payload: UpdateGroupPayload = {
        name: name.trim(),
        description: description.trim() || null,
        target_steps: parsedSteps,
        start_date: cleanDateYMD(startDate) || null,
        end_date: durationType === 'open' ? null : (cleanDateYMD(endDate) || null),
        member_person_ids: newSelectedMembers.map((m) => m.id),
      };

      const res = await updateWalkingGroup(group.id, payload);

      if (res && res.success && res.data) {
        let addedCount = 0;
        if (newSelectedMembers.length > 0) {
          const newIds = newSelectedMembers.map((m) => m.id);
          try {
            const inviteRes = await addGroupMembers(group.id, newIds);
            if (inviteRes && inviteRes.success) {
              addedCount = newIds.length;
            }
          } catch (invErr) {
            console.warn('[EditGroupModal] addGroupMembers error:', invErr);
          }
        }

        try {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch { }

        const successMsg = addedCount > 0
          ? `تم تحديث بيانات التحدي وإضافة ${addedCount} أعضاء جدد بنجاح 🎉`
          : (res.message || 'تم تحديث بيانات التحدي بنجاح.');

        let updatedData = res.data;
        // Fetch fresh details with full members array populated
        try {
          const freshDetail = await fetchWalkingGroupDetails(group.id);
          if (freshDetail) {
            updatedData = freshDetail as any;
          }
        } catch { }

        onSuccess(updatedData);
        onClose();

        setTimeout(() => {
          CustomAlert.alert('تم التحديث 🎉', successMsg);
        }, 350);
      } else {
        CustomAlert.alert('خطأ', res?.message || 'تعذر تحديث التحدي، يرجى المحاولة لاحقاً.');
      }
    } catch (err: any) {
      CustomAlert.alert('خطأ', err?.message || 'حدث خطأ غير متوقع.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!group) return null;

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.keyboardContainer}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 10 : 0}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {/* Header */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={() => {
                  Keyboard.dismiss();
                  onClose();
                }}
                style={styles.closeBtn}
                hitSlop={8}>
                <IconSymbol name="xmark" size={20} color={colors.icon} />
              </TouchableOpacity>

              <ThemedText style={styles.headerTitle}>تعديل التحدي</ThemedText>
              <View style={{ width: 36 }} />
            </View>

            <ScrollView
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="interactive"
              automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
              contentContainerStyle={styles.formContent}>
              {/* Group Name */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>اسم التحدي أو المجموعة *</ThemedText>
                <TextInput
                  value={name}
                  onChangeText={setName}
                  placeholder="اسم التحدي"
                  placeholderTextColor={isDark ? '#6B7280' : '#9CA3AF'}
                  style={[
                    styles.textInput,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                      borderColor: colors.border,
                      color: colors.text,
                    },
                  ]}
                />
              </View>

              {/* Group Description */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>الوصف أو الهدف التحفيزي</ThemedText>
                <TextInput
                  value={description}
                  onChangeText={setDescription}
                  placeholder="وصف مختصر للتحدي وشروطه..."
                  placeholderTextColor={isDark ? '#6B7280' : '#9CA3AF'}
                  multiline
                  numberOfLines={2}
                  style={[
                    styles.textInput,
                    styles.textArea,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                      borderColor: colors.border,
                      color: colors.text,
                    },
                  ]}
                />
              </View>

              {/* Target Mode Segment */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>نوع هدف التحدي</ThemedText>
                <View style={[styles.segmentWrap, { backgroundColor: isDark ? '#1F2937' : '#F3F4F6' }]}>
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
                        { backgroundColor: isDark ? '#374151' : '#FFFFFF' },
                      ],
                    ]}>
                    <ThemedText
                      style={[
                        styles.segmentText,
                        { color: targetMode === 'steps' ? silaTheme.primary : colors.icon },
                        targetMode === 'steps' && styles.segmentTextActive,
                      ]}>
                      هدف بعدد الخطوات
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
                        { backgroundColor: isDark ? '#374151' : '#FFFFFF' },
                      ],
                    ]}>
                    <ThemedText
                      style={[
                        styles.segmentText,
                        { color: targetMode === 'open' ? silaTheme.primary : colors.icon },
                        targetMode === 'open' && styles.segmentTextActive,
                      ]}>
                      صدارة مفتوحة (بدون سقف)
                    </ThemedText>
                  </TouchableOpacity>
                </View>
              </View>

              {/* Step Presets (if steps mode) */}
              {targetMode === 'steps' && (
                <View style={styles.inputGroup}>
                  <ThemedText style={styles.subLabel}>عدد الخطوات المستهدف</ThemedText>
                  <View style={styles.presetsGrid}>
                    {STEP_PRESETS.map((stepsVal) => {
                      const isSelected = targetSteps === String(stepsVal);
                      return (
                        <TouchableOpacity
                          key={stepsVal}
                          activeOpacity={0.8}
                          onPress={() => {
                            try {
                              Haptics.selectionAsync();
                            } catch { }
                            setTargetSteps(String(stepsVal));
                          }}
                          style={[
                            styles.presetPill,
                            {
                              backgroundColor: isSelected
                                ? isDark
                                  ? '#064E3B'
                                  : '#ECFDF5'
                                : isDark
                                  ? '#1F2937'
                                  : '#F9FAFB',
                              borderColor: isSelected ? silaTheme.primary : colors.border,
                            },
                          ]}>
                          <ThemedText
                            style={[
                              styles.presetText,
                              { color: isSelected ? silaTheme.primary : colors.text },
                            ]}>
                            {formatSteps(stepsVal)}
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>

                  <TextInput
                    value={targetSteps}
                    onChangeText={setTargetSteps}
                    keyboardType="number-pad"
                    placeholder="أو أدخل رقم خطوات مخصص"
                    placeholderTextColor={isDark ? '#6B7280' : '#9CA3AF'}
                    style={[
                      styles.textInput,
                      {
                        backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                        borderColor: colors.border,
                        color: colors.text,
                        marginTop: 6,
                      },
                    ]}
                  />
                </View>
              )}

              {/* Duration & Timeline Section */}
              <View style={styles.inputGroup}>
                <ThemedText style={styles.label}>مدة وتاريخ التحدي</ThemedText>

                {/* Duration Presets */}
                <View style={styles.durationPillsContainer}>
                  <View style={styles.durationRow}>
                    {DURATION_PRESETS.slice(0, 3).map((item) => {
                      const isSelected = durationType === item.key;
                      return (
                        <TouchableOpacity
                          key={item.key}
                          activeOpacity={0.8}
                          onPress={() => handleDurationPresetChange(item.key)}
                          style={[
                            styles.durationPill,
                            isSelected && styles.durationPillActive,
                            {
                              backgroundColor: isSelected
                                ? isDark
                                  ? '#064E3B'
                                  : '#ECFDF5'
                                : isDark
                                  ? '#1F2937'
                                  : '#F9FAFB',
                              borderColor: isSelected ? silaTheme.primary : colors.border,
                            },
                          ]}>
                          <ThemedText
                            style={[
                              styles.durationPillText,
                              { color: isSelected ? silaTheme.primary : colors.text },
                            ]}>
                            {item.label}
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>

                  <View style={styles.durationRow}>
                    {DURATION_PRESETS.slice(3).map((item) => {
                      const isSelected = durationType === item.key;
                      return (
                        <TouchableOpacity
                          key={item.key}
                          activeOpacity={0.8}
                          onPress={() => handleDurationPresetChange(item.key)}
                          style={[
                            styles.durationPill,
                            isSelected && styles.durationPillActive,
                            {
                              backgroundColor: isSelected
                                ? isDark
                                  ? '#064E3B'
                                  : '#ECFDF5'
                                : isDark
                                  ? '#1F2937'
                                  : '#F9FAFB',
                              borderColor: isSelected ? silaTheme.primary : colors.border,
                            },
                          ]}>
                          <ThemedText
                            style={[
                              styles.durationPillText,
                              { color: isSelected ? silaTheme.primary : colors.text },
                            ]}>
                            {item.label}
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
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
                  <IconSymbol name="calendar" size={16} color={silaTheme.primary} />
                  <ThemedText style={[styles.durationSummaryText, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                    {durationSummaryText}
                  </ThemedText>
                </View>

                {/* Date Pickers */}
                {durationType === 'open' ? (
                  <View style={styles.openDateBlock}>
                    <DatePickerField
                      label="تاريخ بداية التحدي"
                      value={startDate}
                      onChange={handleStartDateChange}
                      placeholder="اختر تاريخ البدء"
                      allowClear={false}
                    />

                    <View
                      style={[
                        styles.openDateNotice,
                        {
                          backgroundColor: isDark ? '#1E3A5F33' : '#EFF6FF',
                          borderColor: isDark ? '#3B82F655' : '#BFDBFE',
                        },
                      ]}>
                      <ThemedText style={{ fontSize: 16 }}>🌟</ThemedText>
                      <ThemedText style={[styles.openDateNoticeText, { color: isDark ? '#93C5FD' : '#1E40AF' }]}>
                        تحدي مفتوح ومستمر بدون تاريخ نهاية محدد. يستمر احتساب الخطوات وترتيب الصدارة بشكل دائم!
                      </ThemedText>
                    </View>
                  </View>
                ) : (
                  <View style={styles.datePickersRow}>
                    <View style={{ flex: 1 }}>
                      <DatePickerField
                        label="تاريخ البداية"
                        value={startDate}
                        onChange={handleStartDateChange}
                        placeholder="تاريخ البداية"
                        allowClear={false}
                      />
                    </View>

                    <View style={{ flex: 1 }}>
                      <DatePickerField
                        label="تاريخ النهاية"
                        value={endDate}
                        minimumDate={startDate ? parseSafeDate(startDate) : undefined}
                        onChange={handleEndDateChange}
                        placeholder="تاريخ النهاية"
                        allowClear={false}
                      />
                    </View>
                  </View>
                )}
              </View>

              {/* Challenge Members Section (عرض الأعضاء الحاليين + إضافة من نسيتهم) */}
              <View style={styles.inputGroup}>
                <View style={styles.sectionHeaderRow}>
                  <ThemedText style={styles.label}>👥 أعضاء التحدي والمشاركون</ThemedText>
                  <View style={[styles.memberCountBadge, { backgroundColor: isDark ? '#374151' : '#E5E7EB' }]}>
                    <ThemedText style={[styles.memberCountText, { color: colors.text }]}>
                      {loadingMembers
                        ? 'جاري التحميل...'
                        : `${existingMembers.length} ${existingMembers.length === 1 ? 'عضو حالي' : 'أعضاء حاليون'}`}
                    </ThemedText>
                  </View>
                </View>

                {/* شريط الأعضاء الحاليين */}
                {loadingMembers && existingMembers.length === 0 ? (
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 10 }}>
                    <ActivityIndicator size="small" color={silaTheme.primary} />
                    <ThemedText style={{ fontFamily: 'Tajawal-Regular', fontSize: 12, color: colors.icon }}>
                      جاري تحميل قائمة المشاركين في التحدي...
                    </ThemedText>
                  </View>
                ) : existingMembers.length > 0 ? (
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.existingMembersScroll}>
                    {existingMembers.map((m: any, idx: number) => {
                      const p = m.person || {};
                      const pName =
                        p.name ||
                        p.full_name ||
                        [p.first_name, p.last_name].filter(Boolean).join(' ') ||
                        m.name ||
                        'عضو';
                      const pAvatar = p.avatar || p.photo_url || m.avatar;
                      const isAdmin = m.role === 'admin';
                      return (
                        <View
                          key={m.id || m.person_id || idx}
                          style={[
                            styles.existingMemberPill,
                            {
                              backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                              borderColor: isAdmin ? silaTheme.primary : colors.border,
                            },
                          ]}>
                          {pAvatar ? (
                            <Image source={{ uri: pAvatar }} style={styles.miniAvatarImg} />
                          ) : (
                            <View
                              style={[
                                styles.miniAvatarPlaceholder,
                                { backgroundColor: isAdmin ? silaTheme.primary : '#3B82F6' },
                              ]}>
                              <ThemedText style={styles.miniAvatarText}>
                                {pName.slice(0, 1) || 'ع'}
                              </ThemedText>
                            </View>
                          )}
                          <ThemedText
                            numberOfLines={1}
                            style={[styles.existingMemberName, { color: colors.text }]}>
                            {pName}
                          </ThemedText>
                          {isAdmin && (
                            <ThemedText style={{ fontSize: 10 }}>⭐</ThemedText>
                          )}
                        </View>
                      );
                    })}
                  </ScrollView>
                ) : null}

                {/* الأعضاء الجدد المحددون للإضافة */}
                {newSelectedMembers.length > 0 && (
                  <View
                    style={[
                      styles.newMembersContainer,
                      {
                        backgroundColor: isDark ? '#064E3B22' : '#F0FDF4',
                        borderColor: isDark ? '#065F46' : '#BBF7D0',
                      },
                    ]}>
                    <View style={styles.newMembersHeaderRow}>
                      <ThemedText style={[styles.newMembersTitle, { color: silaTheme.primary }]}>
                        ✨ أعضاء جدد سيتم إضافتهم ({newSelectedMembers.length}):
                      </ThemedText>
                      <TouchableOpacity
                        hitSlop={8}
                        onPress={() => {
                          try {
                            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                          } catch { }
                          setNewSelectedMembers([]);
                        }}>
                        <ThemedText style={[styles.clearNewBtnText, { color: isDark ? '#F87171' : '#DC2626' }]}>
                          إلغاء التحديد
                        </ThemedText>
                      </TouchableOpacity>
                    </View>

                    <ScrollView
                      horizontal
                      showsHorizontalScrollIndicator={false}
                      contentContainerStyle={styles.newMembersScroll}>
                      {newSelectedMembers.map((member) => (
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
                              <ThemedText style={styles.chipInitialText}>{member.name.slice(0, 1)}</ThemedText>
                            </View>
                          )}
                          <ThemedText style={[styles.chipName, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                            {member.name}
                          </ThemedText>
                          <TouchableOpacity
                            onPress={() => handleRemoveSelected(member.id)}
                            style={styles.chipRemoveBtn}
                            hitSlop={8}>
                            <IconSymbol name="xmark.circle.fill" size={16} color={isDark ? '#34D399' : '#059669'} />
                          </TouchableOpacity>
                        </View>
                      ))}
                    </ScrollView>
                  </View>
                )}

                {/* زر فتح منتقي شجرة العائلة لإضافة الأعضاء */}
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
                      borderColor: newSelectedMembers.length > 0
                        ? silaTheme.primary
                        : isDark ? '#374151' : '#BBF7D0',
                    },
                  ]}>
                  <View style={[styles.pickerIconCircle, { backgroundColor: silaTheme.primary }]}>
                    <IconSymbol name="person.crop.circle.badge.plus" size={20} color="#FFFFFF" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <ThemedText style={styles.openPickerCardTitle}>
                      {newSelectedMembers.length > 0
                        ? `تم تحديد (${newSelectedMembers.length}) من أفراد العائلة للإضافة`
                        : '+ إضافة أعضاء من العائلة نسيتهم'}
                    </ThemedText>
                    <ThemedText style={[styles.openPickerCardSub, { color: colors.icon }]}>
                      {newSelectedMembers.length > 0
                        ? 'اضغط هنا لتعديل أو اختيار المزيد من أفراد العائلة'
                        : 'اضغط لاختيار ودعوة أي فرد من شجرة العائلة للمشاركة'}
                    </ThemedText>
                  </View>
                  <IconSymbol name="chevron.left" size={18} color={silaTheme.primary} />
                </TouchableOpacity>
              </View>

              {/* Submit Button */}
              <TouchableOpacity
                activeOpacity={0.85}
                disabled={isSubmitting}
                onPress={handleSave}
                style={[
                  styles.saveBtn,
                  { backgroundColor: silaTheme.primary, opacity: isSubmitting ? 0.7 : 1 },
                ]}>
                {isSubmitting ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <ThemedText style={styles.saveBtnText}>
                    {newSelectedMembers.length > 0
                      ? `حفظ التعديلات وإضافة (${newSelectedMembers.length}) أعضاء`
                      : 'حفظ التعديلات'}
                  </ThemedText>
                )}
              </TouchableOpacity>

              {/* Delete / Soft Delete Button for Creator / Admin */}
              <TouchableOpacity
                activeOpacity={0.85}
                disabled={isDeleting || isSubmitting}
                onPress={handleDeleteGroupClick}
                style={[
                  styles.deleteGroupBtn,
                  { backgroundColor: isDark ? 'rgba(239, 68, 68, 0.16)' : '#FEF2F2', borderColor: isDark ? '#EF444455' : '#FCA5A5' },
                ]}>
                {isDeleting ? (
                  <ActivityIndicator color="#EF4444" />
                ) : (
                  <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
                    <Ionicons name="trash-outline" size={16} color="#EF4444" />
                    <ThemedText style={styles.deleteGroupBtnText}>حذف التحدي (سلة المحذوفات)</ThemedText>
                  </View>
                )}
              </TouchableOpacity>
            </ScrollView>
          </View>
        </View>
      </KeyboardAvoidingView>

      {/* منتقي شجرة العائلة لإضافة الأعضاء */}
      <ChallengeInvitePickerModal
        visible={showMemberPicker}
        onClose={() => setShowMemberPicker(false)}
        selectedMembers={newSelectedMembers}
        existingMemberIds={existingMemberIds}
        onTogglePerson={handleTogglePerson}
        onRemovePerson={handleRemoveSelected}
        onClearAll={() => setNewSelectedMembers([])}
        onConfirm={() => setShowMemberPicker(false)}
        confirmLabel={
          newSelectedMembers.length > 0
            ? `تأكيد إضافة (${newSelectedMembers.length}) للتحدي`
            : 'إغلاق'
        }
        title="إضافة أعضاء للتحدي 👥"
      />
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
    width: '100%',
    maxHeight: '90%',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    paddingTop: 16,
    paddingHorizontal: 20,
    paddingBottom: 24,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#3F3F4640',
  },
  closeBtn: {
    padding: 6,
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'center',
  },
  formContent: {
    paddingTop: 16,
    paddingBottom: 16,
    gap: 16,
  },
  inputGroup: {
    gap: 6,
  },
  label: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,

  },
  subLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    color: '#6B7280',

  },
  textInput: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,

  },
  textArea: {
    minHeight: 65,
    textAlignVertical: 'top',
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
    textAlign: 'center',
  },
  segmentTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  presetsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  presetPill: {
    flex: 1,
    minWidth: '22%',
    paddingVertical: 8,
    paddingHorizontal: 6,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  presetText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  durationPillsContainer: {
    gap: 8,
  },
  durationRow: {
    flexDirection: 'row',
    gap: 8,
  },
  durationPill: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 6,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 42,
  },
  durationPillActive: {
    borderWidth: 1.5,
  },
  durationPillText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    textAlign: 'center',
  },
  durationSummaryStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
    marginTop: 8,
  },
  durationSummaryText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
    flex: 1,
  },
  datePickersRow: {
  flexDirection: 'row',
  gap: 10,
  marginTop: 8,
},
  openDateBlock: {
  gap: 6,
  marginTop: 8,
},
  openDateNotice: {
  flexDirection: 'row',
  alignItems: 'center',
  padding: 12,
  borderRadius: 14,
  borderWidth: 1,
  gap: 8,
},
  openDateNoticeText: {
  fontFamily: 'Tajawal-Regular',
  fontSize: 12,
  lineHeight: 18,
  flex: 1,

},
  saveBtn: {
  paddingVertical: 14,
  borderRadius: 16,
  alignItems: 'center',
  justifyContent: 'center',
  marginTop: 8,
  minHeight: 48,
},
  saveBtnText: {
  color: '#FFFFFF',
  fontFamily: 'Tajawal-Bold',
  fontSize: 15,
},
  deleteGroupBtn: {
    paddingVertical: 13,
    borderRadius: 16,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
    marginBottom: 8,
  },
  deleteGroupBtnText: {
    color: '#EF4444',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  memberCountBadge: {
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 10,
  },
  memberCountText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
  },
  existingMembersScroll: {
    gap: 8,
    paddingVertical: 4,
  },
  existingMemberPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: 12,
    borderWidth: 1,
    maxWidth: 160,
  },
  miniAvatarImg: {
    width: 22,
    height: 22,
    borderRadius: 11,
  },
  miniAvatarPlaceholder: {
    width: 22,
    height: 22,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  miniAvatarText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
  existingMemberName: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    maxWidth: 90,
  },
  newMembersContainer: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 10,
    gap: 8,
    marginTop: 4,
  },
  newMembersHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  newMembersTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
  },
  clearNewBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
  },
  newMembersScroll: {
    flexDirection: 'row',
    gap: 6,
    paddingVertical: 2,
  },
  memberChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
  },
  chipAvatar: {
    width: 20,
    height: 20,
    borderRadius: 10,
  },
  chipAvatarPlaceholder: {
    width: 20,
    height: 20,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipInitialText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
  chipName: {
    fontFamily: 'Tajawal-Medium',
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
    borderWidth: 1,
    gap: 12,
    marginTop: 4,
  },
  pickerIconCircle: {
    width: 38,
    height: 38,
    borderRadius: 12,
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
});
