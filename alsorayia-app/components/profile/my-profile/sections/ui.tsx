import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { Text, TextInput, View } from 'react-native';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { styles } from '../styles';

export function SectionHeader({ icon, title, subtitle }: { icon: keyof typeof Ionicons.glyphMap; title: string; subtitle?: string }) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
    <View style={styles.sectionHeaderRow}>
      <View style={[styles.sectionIconBadge, { backgroundColor: isDark ? colors.primary + '25' : colors.primary + '15' }]}>
        <Ionicons name={icon} size={18} color={colors.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.sectionHeaderTitle, { color: colors.text }]}>{title}</Text>
        {subtitle ? <Text style={[styles.sectionHeaderSubtitle, { color: colors.icon }]}>{subtitle}</Text> : null}
      </View>
    </View>
  );
}

export function ModernInput({
  label,
  icon,
  value,
  onChangeText,
  placeholder,
  multiline,
  numberOfLines,
  keyboardType,
}: {
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  multiline?: boolean;
  numberOfLines?: number;
  keyboardType?: React.ComponentProps<typeof TextInput>['keyboardType'];
}) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
    <View style={styles.inputContainer}>
      <Text style={[styles.inputLabel, { color: colors.icon }]}>{label}</Text>
      <View
        style={[
          styles.inputWrapper,
          {
            backgroundColor: isDark ? '#111827' : '#F8FAFC',
            borderColor: isDark ? '#334155' : '#E2E8F0',
          },
          multiline && { alignItems: 'flex-start', minHeight: 90 },
        ]}>
        <Ionicons
          name={icon}
          size={18}
          color={colors.primary}
          style={[styles.inputIcon, multiline && { marginTop: 12 }]}
        />
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={isDark ? '#64748B' : '#94A3B8'}
          multiline={multiline}
          numberOfLines={numberOfLines}
          keyboardType={keyboardType}
          style={[
            styles.textInput,
            { color: colors.text },
            multiline && { textAlignVertical: 'top', paddingTop: 10, minHeight: 80 },
          ]}
        />
      </View>
    </View>
  );
}
