import type { ThemeColors } from '@/constants/theme';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { invitationFontName, type InvitationFontFamily, type InvitationFontWeight } from './typography';

interface TypographyControlsProps {
  colors: ThemeColors;
  fontFamily: InvitationFontFamily;
  fontWeight: InvitationFontWeight;
  onFamilyChange: (family: InvitationFontFamily) => void;
  onWeightChange: (weight: InvitationFontWeight) => void;
  hasDesign: boolean;
}

export function TypographyControls({ colors, fontFamily, fontWeight, onFamilyChange, onWeightChange, hasDesign }: TypographyControlsProps) {
  const groups = [
    { label: 'نوع الخط', options: [{ value: 'tajawal', label: 'تجوال' }, { value: 'amiri', label: 'أميري' }] },
    { label: 'سماكة الخط', options: [{ value: 'regular', label: 'عادي' }, { value: 'bold', label: 'عريض' }] },
  ];

  return (
    <View style={[styles.container, { borderColor: colors.border }]}>
      {groups.map((group, index) => (
        <View key={group.label} style={styles.group}>
          <Text style={[styles.label, { color: colors.text }]}>{group.label}</Text>
          <View style={styles.options} accessibilityRole="radiogroup">
            {group.options.map((option) => {
              const selected = option.value === (index === 0 ? fontFamily : fontWeight);
              return (
                <Pressable
                  key={option.value}
                  accessibilityRole="radio"
                  accessibilityLabel={`${group.label}: ${option.label}`}
                  accessibilityState={{ checked: selected }}
                  onPress={() => index === 0 ? onFamilyChange(option.value as InvitationFontFamily) : onWeightChange(option.value as InvitationFontWeight)}
                  style={[styles.option, { borderColor: selected ? colors.primary : colors.border, backgroundColor: selected ? colors.primary : colors.card }]}>
                  <Text style={{ color: selected ? '#FFFFFF' : colors.text, fontSize: 16, fontFamily: invitationFontName(index === 0 ? option.value as InvitationFontFamily : fontFamily, index === 1 ? option.value as InvitationFontWeight : fontWeight) }}>
                    {option.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>
      ))}
      <Text style={[styles.hint, { color: colors.icon }]}>
        {hasDesign ? 'يُطبّق الخط على العنوان والنص داخل بطاقة الدعوة.' : 'اختر قالبًا من مساحة إبداع لإرسال بطاقة بالخط المختار. رسائل واتساب النصية تستخدم خط واتساب.'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { borderWidth: 1, borderRadius: 16, padding: 12, gap: 12, marginBottom: 12 },
  group: { gap: 8 },
  label: { fontFamily: 'Tajawal-Medium', fontSize: 13 },
  options: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  option: { minWidth: 86, minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 8, borderWidth: 1, borderRadius: 14 },
  hint: { fontFamily: 'Tajawal-Regular', fontSize: 12, lineHeight: 20 },
});
