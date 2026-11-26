import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useState } from 'react';
import { Pressable, StyleSheet, View, type GestureResponderEvent } from 'react-native';

type Hsv = { hue: number; saturation: number; value: number };
const hues = ['#ff0000', '#ffff00', '#00ff00', '#00ffff', '#0000ff', '#ff00ff', '#ff0000'] as const;
const clamp = (value: number) => Math.min(1, Math.max(0, value));

function toHex({ hue, saturation, value }: Hsv) {
  const channel = (offset: number) => {
    const position = (offset + hue * 6) % 6;
    const intensity = value * (1 - saturation * Math.max(0, Math.min(position, 4 - position, 1)));
    return Math.round(intensity * 255).toString(16).padStart(2, '0');
  };
  return `#${channel(5)}${channel(3)}${channel(1)}`;
}

function fromHex(color: string): Hsv {
  const red = parseInt(color.slice(1, 3), 16) / 255;
  const green = parseInt(color.slice(3, 5), 16) / 255;
  const blue = parseInt(color.slice(5, 7), 16) / 255;
  const value = Math.max(red, green, blue);
  const chroma = value - Math.min(red, green, blue);
  let hue = 0;
  if (chroma) {
    if (value === red) hue = (green - blue) / chroma;
    else if (value === green) hue = 2 + (blue - red) / chroma;
    else hue = 4 + (red - green) / chroma;
  }
  return { hue: (hue + 6) % 6 / 6, saturation: value ? chroma / value : 0, value };
}

interface Props {
  color: string;
  colors: ThemeColors;
  disabled: boolean;
  onChange: (color: string) => void;
  onInteractionChange: (active: boolean) => void;
}

export function CreativeColorPicker({ color, colors, disabled, onChange, onInteractionChange }: Props) {
  const [expanded, setExpanded] = useState(false);
  const [selection, setSelection] = useState(() => fromHex(color));
  const [planeWidth, setPlaneWidth] = useState(0);
  const [hueWidth, setHueWidth] = useState(0);
  const pureHue = toHex({ hue: selection.hue, saturation: 1, value: 1 });

  const choose = (next: Hsv) => {
    setSelection(next);
    const nextColor = toHex(next);
    if (nextColor !== color) onChange(nextColor);
  };

  const adjustPlane = ({ nativeEvent }: GestureResponderEvent) => {
    if (planeWidth > 0) choose({ ...selection, saturation: clamp(nativeEvent.locationX / planeWidth), value: clamp(1 - nativeEvent.locationY / styles.plane.height) });
  };

  const adjustHue = ({ nativeEvent }: GestureResponderEvent) => {
    if (hueWidth > 0) choose({ ...selection, hue: clamp(nativeEvent.locationX / hueWidth) });
  };

  return (
    <View style={styles.picker}>
      <Pressable accessibilityRole="button" accessibilityLabel="اختيار لون الخط" accessibilityState={{ expanded, disabled }}
        accessibilityValue={{ text: color }} disabled={disabled} onPress={() => setExpanded(!expanded)}
        style={[styles.trigger, { borderColor: expanded ? colors.primary : colors.border, backgroundColor: `${colors.primary}0D` }]}>
        <View style={[styles.swatch, { backgroundColor: color, borderColor: colors.border }]} />
        <ThemedText style={[styles.triggerLabel, { color: colors.text }]}>اختيار لون الخط</ThemedText>
        <Ionicons name={expanded ? 'chevron-up' : 'chevron-down'} size={18} color={colors.primary} />
      </Pressable>
      {expanded ? (
        <View style={[styles.panel, { borderColor: colors.border, backgroundColor: colors.card }]}>
          <View testID="creative-color-plane" accessible accessibilityRole="adjustable" accessibilityLabel="تشبّع اللون وسطوعه"
            accessibilityValue={{ text: `التشبّع ${Math.round(selection.saturation * 100)}٪، السطوع ${Math.round(selection.value * 100)}٪` }}
            accessibilityActions={[{ name: 'increment', label: 'زيادة التشبّع' }, { name: 'decrement', label: 'خفض التشبّع' },
              { name: 'brighter', label: 'زيادة السطوع' }, { name: 'darker', label: 'خفض السطوع' }]}
            onAccessibilityAction={({ nativeEvent: { actionName } }) => {
              if (disabled) return;
              if (actionName === 'increment' || actionName === 'decrement') {
                choose({ ...selection, saturation: clamp(selection.saturation + (actionName === 'increment' ? .05 : -.05)) });
              } else if (actionName === 'brighter' || actionName === 'darker') {
                choose({ ...selection, value: clamp(selection.value + (actionName === 'brighter' ? .05 : -.05)) });
              }
            }}
            onLayout={({ nativeEvent }) => setPlaneWidth(nativeEvent.layout.width)}
            onStartShouldSetResponder={() => !disabled}
            onResponderGrant={(event) => { onInteractionChange(true); adjustPlane(event); }} onResponderMove={adjustPlane}
            onResponderRelease={() => onInteractionChange(false)} onResponderTerminate={() => onInteractionChange(false)}
            onResponderTerminationRequest={() => false} style={[styles.plane, { backgroundColor: pureHue }]}>
            <View pointerEvents="none" style={StyleSheet.absoluteFill}>
              <LinearGradient colors={['#ffffff', '#ffffff00']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={StyleSheet.absoluteFill} />
              <LinearGradient colors={['#00000000', '#000000']} style={StyleSheet.absoluteFill} />
              <View style={[styles.planeThumb, { start: `${selection.saturation * 100}%`, top: `${(1 - selection.value) * 100}%`, backgroundColor: color }]} />
            </View>
          </View>
          <View testID="creative-color-hue" accessible accessibilityRole="adjustable" accessibilityLabel="درجة اللون"
            accessibilityValue={{ min: 0, now: Math.round(selection.hue * 360), max: 360 }}
            accessibilityActions={[{ name: 'increment' }, { name: 'decrement' }]}
            onAccessibilityAction={({ nativeEvent: { actionName } }) => {
              if (disabled) return;
              choose({ ...selection, hue: clamp(selection.hue + (actionName === 'increment' ? .03 : -.03)) });
            }} onLayout={({ nativeEvent }) => setHueWidth(nativeEvent.layout.width)}
            onStartShouldSetResponder={() => !disabled}
            onResponderGrant={(event) => { onInteractionChange(true); adjustHue(event); }} onResponderMove={adjustHue}
            onResponderRelease={() => onInteractionChange(false)} onResponderTerminate={() => onInteractionChange(false)}
            onResponderTerminationRequest={() => false} style={styles.hue}>
            <View pointerEvents="none" style={StyleSheet.absoluteFill}>
              <LinearGradient colors={hues} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.hueTrack} />
              <View style={[styles.hueThumb, { start: `${selection.hue * 100}%`, backgroundColor: pureHue }]} />
            </View>
          </View>
          <ThemedText style={[styles.colorCode, { color: colors.icon }]}>{color.toUpperCase()}</ThemedText>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  picker: { gap: 8, direction: 'rtl' },
  trigger: { paddingHorizontal: 12, minHeight: 48, borderRadius: 12, borderWidth: 1, flexDirection: 'row', alignItems: 'center', gap: 10 },
  triggerLabel: { fontFamily: 'Tajawal-Bold', fontSize: 15, flex: 1, writingDirection: 'rtl' },
  swatch: { width: 28, height: 28, borderWidth: 1, borderRadius: 8 },
  panel: { gap: 8, padding: 12, borderWidth: 1, borderRadius: 14 },
  plane: { height: 148, overflow: 'hidden', borderRadius: 10, direction: 'ltr' },
  planeThumb: { position: 'absolute', width: 20, height: 20, borderRadius: 10, borderColor: '#ffffff', borderWidth: 2, transform: [{ translateX: -10 }, { translateY: -10 }], shadowColor: '#000', shadowOpacity: .6, shadowRadius: 2, elevation: 3 },
  hue: { height: 44, direction: 'ltr', marginHorizontal: 10 },
  hueTrack: { position: 'absolute', start: 0, end: 0, top: 15, height: 14, borderRadius: 8 },
  hueThumb: { position: 'absolute', top: 10, width: 24, height: 24, borderRadius: 12, borderWidth: 2, borderColor: '#fff', transform: [{ translateX: -12 }], shadowColor: '#000', shadowOpacity: .6, shadowRadius: 2, elevation: 3 },
  colorCode: { fontSize: 13, fontVariant: ['tabular-nums'], textAlign: 'center', writingDirection: 'ltr' },
});
