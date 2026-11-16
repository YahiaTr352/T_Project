import { ThemedText } from '@/components/themed-text';
import { DatePickerModal, formatArabicDateDisplay } from '@/components/ui/DatePickerModal';
import { Colors, silaTheme } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useState } from 'react';
import {
  Pressable,
  StyleProp,
  StyleSheet,
  TouchableOpacity,
  useColorScheme,
  View,
  ViewStyle,
} from 'react-native';

export interface DatePickerFieldProps {
  label: string;
  value?: string | null; // Expected format: YYYY-MM-DD
  onChange: (date: string) => void;
  onClear?: () => void;
  placeholder?: string;
  icon?: keyof typeof Ionicons.glyphMap;
  title?: string;
  minimumDate?: Date | string | null;
  maximumDate?: Date | string | null;
  allowClear?: boolean;
  disabled?: boolean;
  containerStyle?: StyleProp<ViewStyle>;
  error?: string | null;
}

export function DatePickerField({
  label,
  value,
  onChange,
  onClear,
  placeholder = 'اختر التاريخ (YYYY-MM-DD)',
  icon = 'calendar-outline',
  title,
  minimumDate,
  maximumDate,
  allowClear = true,
  disabled = false,
  containerStyle,
  error,
}: DatePickerFieldProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[colorScheme ?? 'light'];
  const [modalVisible, setModalVisible] = useState(false);

  // تطهير التاريخ لضمان عدم احتوائه على وقت زائد إطلاقاً
  const cleanValue =
    typeof value === 'string'
      ? value.trim().split(/[ T]/)[0]
      : (value as unknown) instanceof Date
      ? `${(value as unknown as Date).getFullYear()}-${String((value as unknown as Date).getMonth() + 1).padStart(2, '0')}-${String((value as unknown as Date).getDate()).padStart(2, '0')}`
      : '';
  const hasValue = Boolean(cleanValue && cleanValue.length > 0);
  const formattedArabic = hasValue ? formatArabicDateDisplay(cleanValue) : '';

  const handleOpenPicker = () => {
    if (disabled) return;
    try {
      Haptics.selectionAsync();
    } catch { }
    setModalVisible(true);
  };

  const handleSelectDate = (dateString: string) => {
    const clean = dateString ? dateString.trim().split(/[ T]/)[0] : '';
    onChange(clean);
  };

  const handleClearDate = () => {
    try {
      Haptics.selectionAsync();
    } catch { }
    if (onClear) {
      onClear();
    } else {
      onChange('');
    }
  };

  return (
    <View style={[styles.fieldContainer, containerStyle]}>
      {label ? (
        <ThemedText style={[styles.inputLabel, { color: colors.icon }]}>
          {label}
        </ThemedText>
      ) : null}

      <Pressable
        onPress={handleOpenPicker}
        disabled={disabled}
        android_ripple={{ color: isDark ? '#334155' : '#E2E8F0', borderless: false }}
        style={({ pressed }) => [
          styles.inputWrapper,
          {
            backgroundColor: isDark ? '#1F2937' : '#F8FAFC',
            borderColor: error
              ? '#EF4444'
              : pressed
                ? silaTheme.primary
                : isDark
                  ? '#334155'
                  : '#E2E8F0',
            opacity: disabled ? 0.6 : 1,
          },
        ]}>
        <Ionicons
          name={icon}
          size={18}
          color={hasValue ? silaTheme.primary : colors.icon}
          style={styles.inputIcon}
        />

        <View style={styles.textContainer}>
          {hasValue ? (
            <ThemedText numberOfLines={1} style={[styles.primaryDateText, { color: colors.text }]}>
              {formattedArabic || cleanValue}
            </ThemedText>
          ) : (
            <ThemedText numberOfLines={1} style={[styles.placeholderText, { color: isDark ? '#64748B' : '#94A3B8' }]}>
              {placeholder}
            </ThemedText>
          )}
        </View>

        {hasValue && allowClear && !disabled ? (
          <TouchableOpacity
            onPress={handleClearDate}
            hitSlop={8}
            style={styles.clearIconBtn}>
            <Ionicons name="close-circle" size={18} color={isDark ? '#9CA3AF' : '#94A3B8'} />
          </TouchableOpacity>
        ) : (
          <Ionicons
            name="chevron-down"
            size={16}
            color={colors.icon}
            style={styles.chevronIcon}
          />
        )}
      </Pressable>

      {error ? (
        <ThemedText style={styles.errorText}>{error}</ThemedText>
      ) : null}

      <DatePickerModal
        visible={modalVisible}
        onClose={() => setModalVisible(false)}
        onSelect={handleSelectDate}
        onClear={handleClearDate}
        value={cleanValue}
        title={title || label || 'اختيار التاريخ'}
        minimumDate={minimumDate}
        maximumDate={maximumDate}
        allowClear={allowClear}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  fieldContainer: {
    gap: 6,
    marginBottom: 4,
  },
  inputLabel: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',

  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 14,
    paddingHorizontal: 12,
    minHeight: 48,
    gap: 8,
  },
  inputIcon: {},
  textContainer: {
    flex: 1,
    justifyContent: 'center',
    paddingVertical: 8,
  },
  valueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'nowrap',
  },
  primaryDateText: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Bold',
    fontWeight: '700',

  },
  arabicBadge: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 8,
    flexShrink: 1,
  },
  arabicBadgeText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Medium',

  },
  placeholderText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',

  },
  clearIconBtn: {
    padding: 4,
  },
  chevronIcon: {
    opacity: 0.7,
  },
  errorText: {
    fontSize: 11,
    color: '#EF4444',
    fontFamily: 'Tajawal-Regular',

    marginTop: 2,
  },
});
