import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import type { CreativeDesignMode } from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, View } from 'react-native';

export const creativeModeLabels: Record<CreativeDesignMode, string> = {
  ready: 'صورة ثابتة',
  overlay: 'قالب قابل للتخصيص',
};

const options = [
  { mode: 'ready', icon: 'image-outline', description: 'تُرسل الصورة كما هي، وتظهر الرسالة معها.' },
  { mode: 'overlay', icon: 'create-outline', description: 'رتّب النص والمتغيرات التي تظهر داخل الصورة.' },
] as const;

export function CreativeModePicker({ colors, value, onChange, disabled }: {
  colors: ThemeColors;
  value: CreativeDesignMode | null;
  onChange: (value: CreativeDesignMode) => void;
  disabled?: boolean;
}) {
  return (
    <View style={styles.options}>
      {options.map(({ mode, icon, description }) => {
        const selected = value === mode;
        return (
          <Pressable key={mode} accessibilityRole="button" accessibilityLabel={creativeModeLabels[mode]}
            accessibilityState={{ selected, disabled }} disabled={disabled} onPress={() => onChange(mode)}
            style={[styles.option, { borderColor: selected ? colors.primary : colors.border,
              backgroundColor: selected ? `${colors.primary}16` : colors.card }]}>
            <Ionicons name={icon} size={20} color={colors.primary} />
            <View style={styles.copy}>
              <ThemedText style={styles.title}>{creativeModeLabels[mode]}</ThemedText>
              <ThemedText style={[styles.description, { color: colors.icon }]}>{description}</ThemedText>
            </View>
            {selected ? <Ionicons name="checkmark-circle" size={18} color={colors.primary} /> : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  options: { gap: 8, direction: 'rtl' },
  option: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 10, borderWidth: 1, borderRadius: 12 },
  copy: { flex: 1 },
  title: { fontFamily: 'Tajawal-Bold', fontSize: 14, writingDirection: 'rtl', textAlign: 'auto' },
  description: { fontFamily: 'Tajawal-Medium', fontSize: 13, lineHeight: 18, writingDirection: 'rtl', textAlign: 'auto' },
});
