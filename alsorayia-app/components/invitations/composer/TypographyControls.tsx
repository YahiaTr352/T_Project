import type { ThemeColors } from '@/constants/theme';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { invitationFontName, type InvitationFontFamily, type InvitationFontWeight } from './typography';

const fontFamilies = [
  { value: 'tajawal', label: 'عادي' },
  { value: 'amiri', label: 'نسخ' },
] as const;
const fontWeights = [
  { value: 'regular', label: 'عادي' },
  { value: 'bold', label: 'عريض' },
] as const;

interface TypographyControlsProps {
  colors: ThemeColors;
  fontFamily: InvitationFontFamily;
  fontWeight: InvitationFontWeight;
  onFamilyChange: (family: InvitationFontFamily) => void;
  onWeightChange: (weight: InvitationFontWeight) => void;
}

export function TypographyControls({ colors, fontFamily, fontWeight, onFamilyChange, onWeightChange }: TypographyControlsProps) {
  const groups = [
    {
      label: 'نوع الخط',
      options: fontFamilies.map(({ value, label }) => ({
        label,
        selected: value === fontFamily,
        sampleFont: invitationFontName(value, fontWeight),
        onPress: () => onFamilyChange(value),
      })),
    },
    {
      label: 'سماكة الخط',
      options: fontWeights.map(({ value, label }) => ({
        label,
        selected: value === fontWeight,
        sampleFont: invitationFontName(fontFamily, value),
        onPress: () => onWeightChange(value),
      })),
    },
  ];

  return (
    <View style={[styles.container, { borderColor: colors.border, backgroundColor: colors.card }]}>
      {groups.map((group) => (
        <View key={group.label} style={styles.group}>
          <Text style={[styles.label, { color: colors.text }]}>{group.label}</Text>
          <View style={[styles.options, { borderColor: colors.border }]} accessibilityRole="radiogroup" accessibilityLabel={group.label}>
            {group.options.map(({ label, selected, sampleFont, onPress }) => (
              <Pressable
                key={label}
                accessibilityRole="radio"
                accessibilityLabel={`${group.label} ${label}`}
                accessibilityState={{ checked: selected }}
                onPress={onPress}
                style={[styles.option, selected && { backgroundColor: colors.primary + '18' }]}>
                <Text style={[styles.optionLabel, { color: selected ? colors.primary : colors.text, fontFamily: sampleFont }]}>
                  {label}
                </Text>
              </Pressable>
            ))}
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { direction: 'rtl', borderWidth: 1, borderRadius: 14, padding: 8, gap: 6, marginBottom: 10 },
  group: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  label: { minWidth: 80, fontFamily: 'Tajawal-Medium', fontSize: 13, textAlign: 'right' },
  options: { flex: 1, flexDirection: 'row', borderWidth: 1, borderRadius: 10 },
  option: { flex: 1, minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4, borderRadius: 9 },
  optionLabel: { fontSize: 14, textAlign: 'center' },
});
