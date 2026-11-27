import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Ionicons } from '@expo/vector-icons';
import { Pressable } from 'react-native';
import { composerStyles as styles } from './composerStyles';

export function ChannelToggle({
  label,
  kind,
  color,
  active,
  onPress,
  cardBg,
  border,
  textColor,
}: {
  label: string;
  kind: 'whatsapp' | 'push';
  color: string;
  active: boolean;
  onPress: () => void;
  cardBg: string;
  border: string;
  textColor: string;
}) {
  const iconColor = active ? color : textColor;
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.channelBtn,
        {
          backgroundColor: active ? color + '18' : cardBg,
          borderColor: active ? color : border,
          borderWidth: active ? 1.5 : 1,
          opacity: pressed ? 0.85 : 1,
        },
      ]}>
      {kind === 'whatsapp' ? (
        <Ionicons name="logo-whatsapp" size={18} color={iconColor} />
      ) : (
        <IconSymbol name="bell.fill" size={18} color={iconColor} />
      )}
      <ThemedText style={[styles.modeText, { color: active ? color : textColor }]}>
        {label}
      </ThemedText>
    </Pressable>
  );
}
