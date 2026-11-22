import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import type { CreativeCardField, CreativeInlineText, CreativeTextBox, CreativeTextFormat, CreativeTextLayout } from '@/lib/api/invitations';
import { SCALED_FLAG } from '@/lib/utils/patchGlobalText';
import { Image } from 'expo-image';
import { useEffect, useMemo, useState } from 'react';
import { Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, useWindowDimensions, View, type LayoutRectangle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

type Measure = 'x' | 'y' | 'width' | 'height' | 'fontSize' | 'offsetX' | 'offsetY';
const controls: { key: Measure; label: string; step: number }[] = [
  { key: 'x', label: 'الموقع من اليسار', step: 2 },
  { key: 'y', label: 'الموقع من الأعلى', step: 2 },
  { key: 'fontSize', label: 'حجم الخط', step: 0.5 },
  { key: 'width', label: 'عرض الحقل', step: 2 },
  { key: 'height', label: 'ارتفاع الحقل', step: 2 },
];
const inlineControls: typeof controls = [
  { key: 'offsetX', label: 'الإزاحة الأفقية', step: 2 },
  { key: 'offsetY', label: 'الإزاحة العمودية', step: 2 },
  { key: 'fontSize', label: 'حجم الخط', step: 0.5 },
];
const rtlTextAlign = Platform.OS === 'android' ? 'auto' : 'right';
const formats = [
  { key: 'bold', label: 'غامق' },
  { key: 'italic', label: 'مائل' },
  { key: 'underline', label: 'تحته خط' },
] as const;

function outsideFrame(box: CreativeTextBox) {
  return box.x < 0 || box.y < 0 || box.x > 100 || box.y > 100
    || box.x + box.width > 100.001 || box.y + box.height > 100.001;
}

function readMeasure(value: string) {
  const normalized = value.trim().replace(/[٠-٩۰-۹]/g, (digit) => String(digit.charCodeAt(0) % 16))
    .replace(/[٫,]/g, '.').replace('−', '-');
  return /^-?(?:\d+\.?\d*|\.\d+)$/.test(normalized) && Number.isFinite(Number(normalized))
    ? Number(normalized)
    : null;
}

export function CreativeTextEditor({ colors, artwork, field, initialLayout, onClose, onSave }: {
  colors: ThemeColors;
  artwork: { uri: string; width: number; height: number };
  field: CreativeCardField;
  initialLayout: CreativeTextLayout;
  onClose: () => void;
  onSave: (layout: CreativeTextLayout) => void;
}) {
  const insets = useSafeAreaInsets();
  const window = useWindowDimensions();
  const [box, setBox] = useState(initialLayout.message ?? field.box);
  const [selected, setSelected] = useState<string | null>(null);
  const [draft, setDraft] = useState<{ key: Measure; value: string } | null>(null);
  const [overflow, setOverflow] = useState<string | null>(null);
  const ratio = artwork.width > 0 && artwork.height > 0 ? artwork.width / artwork.height : 1;
  const width = Math.max(1, Math.min(window.width - 32, 520, 360 * ratio));
  const height = width / ratio;
  const segments = field.segments ?? [{ key: 'text_0', text: field.sample, label: 'نص الدعوة', variable: false }];
  const activePart = segments.find((segment) => segment.key === selected);
  const activeFormat = { ...box, offsetX: 0, offsetY: 0, ...box.inline?.[selected ?? ''] };
  const adjustedParts = Object.entries(box.inline ?? {});
  const hasOffsets = adjustedParts.some(([, part]) => part.offsetX || part.offsetY);
  const invalidOffset = adjustedParts.find(([, part]) => Math.abs(part.offsetX ?? 0) >= 100 || Math.abs(part.offsetY ?? 0) >= 100);
  const outside = outsideFrame(box);
  const invalidSize = box.width < 10 || box.height < 5 || box.fontSize < 2 || box.fontSize > 18
    || adjustedParts.some(([, part]) => part.fontSize !== undefined && (part.fontSize < 2 || part.fontSize > 18));
  const shiftedOutside = (invalidOffset ? segments.find((segment) => segment.key === invalidOffset[0])?.label : null)
    ?? (hasOffsets ? overflow : null);
  const issue = outside ? 'نص الدعوة خارج إطار الصورة. عدّل الموضع أو الأبعاد.'
    : shiftedOutside ? `العنصر «${shiftedOutside}» خارج إطار الصورة. عدّل إزاحته.`
    : draft && readMeasure(draft.value) === null ? 'أدخل رقمًا صالحًا.'
    : invalidSize ? 'الحد الأدنى للعرض 10% وللارتفاع 5%. حجم الخط من 2% إلى 18%.'
    : null;

  const toggleFormat = (key: keyof CreativeTextFormat) => setBox((current) => {
    if (!selected) return { ...current, [key]: !current[key] };
    const segment = current.inline?.[selected];
    return { ...current, inline: { ...current.inline, [selected]: { ...segment, [key]: !(segment?.[key] ?? current[key]) } } };
  });
  const select = (key: string | null) => {
    setDraft(null);
    setSelected(key);
  };
  const setMeasure = (key: Measure, value: number) => {
    const next = Number(value.toFixed(2));
    setBox((current) => selected
      ? { ...current, inline: { ...current.inline, [selected]: { ...current.inline?.[selected], [key]: next } } }
      : { ...current, [key]: next });
  };
  const adjust = (key: Measure, delta: number) => {
    setDraft(null);
    const value = activeFormat[key] + delta;
    if (Number.isFinite(value)) setMeasure(key, value);
  };

  return (
    <Modal visible animationType="slide" onRequestClose={onClose}>
      <View style={[styles.page, { backgroundColor: colors.background, paddingTop: insets.top + 12, paddingBottom: insets.bottom + 12 }]}>
        <View style={styles.header}>
          <ThemedText style={styles.title}>محرر القالب</ThemedText>
          <Pressable accessibilityRole="button" onPress={onClose} style={styles.touch}>
            <ThemedText style={{ color: colors.primary }}>إلغاء</ThemedText>
          </Pressable>
        </View>
        <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}>
          <ThemedText style={styles.rtl}>اضغط على عبارة أو متغير داخل النص لتنسيقه.</ThemedText>
          <View style={[styles.board, { width, height }]}>
            <Image source={{ uri: artwork.uri }} style={StyleSheet.absoluteFill} contentFit="contain" />
            {!outside && !invalidOffset && !invalidSize ? <TextBox segments={segments} box={box} width={width} height={height}
              hasOffsets={hasOffsets} onOverflow={setOverflow} /> : null}
          </View>
          <View style={[styles.controls, { borderColor: colors.border, backgroundColor: colors.card }]}>
            <ThemedText testID="creative-editable-invitation-text" style={styles.invitationText}>
              {'\u200F'}
              {segments.map((segment, index) => (
                <Text key={index} accessibilityRole={segment.text.trim() ? 'button' : undefined}
                  accessibilityLabel={segment.variable ? segment.label : segment.text.trim() || undefined}
                  accessibilityState={{ selected: selected === segment.key }}
                  onPress={segment.text.trim() ? () => select(segment.key) : undefined}
                  style={[styleFor({ ...box, ...box.inline?.[segment.key] }),
                    segment.variable && { backgroundColor: `${colors.primary}22` },
                    selected === segment.key && { backgroundColor: `${colors.primary}40` }]}>
                  {segment.text.replace(/\n/g, '\n\u200F')}
                </Text>
              ))}
            </ThemedText>
            <View style={styles.controlRow}>
              <ThemedText style={[styles.fieldLabel, styles.controlLabel]}>
                {activePart?.label ?? 'التنسيق العام'}
              </ThemedText>
              {selected ? <Pressable accessibilityRole="button" onPress={() => select(null)}
                style={styles.touch}>
                <ThemedText style={{ color: colors.primary }}>التنسيق العام</ThemedText>
              </Pressable> : null}
            </View>
            <View style={styles.fields}>
              {formats.map(({ key, label }) => (
                <Pressable key={key} accessibilityRole="checkbox" accessibilityLabel={label}
                  accessibilityState={{ checked: Boolean(activeFormat[key]) }}
                  onPress={() => toggleFormat(key)}
                  style={[styles.touch, { borderColor: activeFormat[key] ? colors.primary : colors.border,
                    backgroundColor: activeFormat[key] ? `${colors.primary}19` : colors.card }]}>
                  <ThemedText style={styles.rtl}>{label}</ThemedText>
                </Pressable>
              ))}
            </View>
          </View>
          <View style={[styles.controls, { borderColor: colors.border, backgroundColor: colors.card }]}>
            <ThemedText style={styles.fieldLabel}>{activePart ? `موضع «${activePart.label}» وحجمه` : 'موضع نص الدعوة وحجمه'}</ThemedText>
            {activePart ? <ThemedText style={styles.rtl}>القيم السالبة تحرّك الجزء لليسار أو للأعلى.</ThemedText> : null}
            {(selected ? inlineControls : controls).map(({ key, label, step }) => (
              <View key={key} style={styles.controlRow}>
                <ThemedText style={[styles.rtl, styles.controlLabel]}>{label}</ThemedText>
                <Pressable accessibilityRole="button" accessibilityLabel={`إنقاص ${label}`} onPress={() => adjust(key, -step)} style={styles.touch}>
                  <ThemedText>−</ThemedText>
                </Pressable>
                <TextInput accessibilityLabel={`${label} (%)`} inputMode="decimal" maxLength={12}
                  value={draft?.key === key ? draft.value : String(activeFormat[key])}
                  onChangeText={(value) => {
                    setDraft({ key, value });
                    const numeric = readMeasure(value);
                    if (numeric !== null) setMeasure(key, numeric);
                  }}
                  onBlur={() => setDraft((current) => current?.key === key ? null : current)}
                  style={[styles.value, { borderColor: colors.border, color: colors.text }]} />
                <Pressable accessibilityRole="button" accessibilityLabel={`زيادة ${label}`} onPress={() => adjust(key, step)} style={styles.touch}>
                  <ThemedText>+</ThemedText>
                </Pressable>
              </View>
            ))}
          </View>
          <View style={styles.fields}>
            <Pressable accessibilityRole="button" style={styles.touch}
              onPress={() => {
                setDraft(null);
                setSelected(null);
                setOverflow(null);
                setBox(field.box);
              }}>
              <ThemedText style={{ color: colors.primary }}>استعادة التوزيع الافتراضي</ThemedText>
            </Pressable>
          </View>
        </ScrollView>
        {issue ? (
          <View accessible accessibilityRole="alert" accessibilityLiveRegion="polite" style={styles.error}>
            <ThemedText style={[styles.rtl, { color: '#9B2424' }]}>{issue}</ThemedText>
          </View>
        ) : null}
        <Pressable accessibilityRole="button" disabled={Boolean(issue)} onPress={() => onSave({ message: box })}
          style={[styles.save, { backgroundColor: colors.primary, opacity: issue ? 0.5 : 1 }]}>
          <ThemedText style={styles.saveText}>حفظ التوزيع</ThemedText>
        </Pressable>
      </View>
    </Modal>
  );
}

function styleFor(format: CreativeTextFormat) {
  return {
    fontWeight: format.bold ? 'bold' : 'normal',
    fontStyle: format.italic ? 'italic' : 'normal',
    textDecorationLine: format.underline ? 'underline' : 'none',
  } as const;
}

function TextBox({ segments, box, width, height, hasOffsets, onOverflow }: {
  segments: NonNullable<CreativeCardField['segments']>; box: CreativeTextBox; width: number; height: number;
  hasOffsets: boolean; onOverflow: (label: string | null) => void;
}) {
  const boxHeight = Math.max(0, height * box.height / 100);
  const availableHeight = Math.max(1, boxHeight - 3);
  const lineFactor = 1.5;
  const fontSize = Math.max(1, Math.min(Math.min(width, height) * box.fontSize / 100, availableHeight / lineFactor));
  const lineHeight = fontSize * lineFactor;
  const textStyle = {
    [SCALED_FLAG]: true,
    fontSize, lineHeight,
    fontFamily: 'Amiri-Regular',
  } as const;

  return (
    <View pointerEvents="none" style={[styles.box, {
      start: width * box.x / 100, top: height * box.y / 100,
      width: Math.max(0, width * box.width / 100), height: boxHeight,
    }]}>
      {hasOffsets ? <PositionedText segments={segments} box={box} width={width} height={height}
        fontSize={fontSize} onOverflow={onOverflow} /> : (
        <Text testID="creative-preview-message" allowFontScaling={false} numberOfLines={Math.max(1, Math.floor(availableHeight / lineHeight))}
          adjustsFontSizeToFit minimumFontScale={0.5} style={[styles.sample, textStyle]}>
        {segments.map((segment, index) => {
          const local = box.inline?.[segment.key];
          const size = fontSize * (local?.fontSize ?? box.fontSize) / box.fontSize;
          const localSize = local?.fontSize === undefined ? undefined
            : { [SCALED_FLAG]: true, fontSize: size, lineHeight: size * lineFactor };
          return (
            <Text key={index} style={[styleFor({ ...box, ...local }), localSize]}>
              {segment.text}
            </Text>
          );
        })}
        </Text>
      )}
    </View>
  );
}

function PositionedText({ segments, box, width, height, fontSize, onOverflow }: {
  segments: NonNullable<CreativeCardField['segments']>;
  box: CreativeTextBox; width: number; height: number; fontSize: number;
  onOverflow: (label: string | null) => void;
}) {
  const [positions, setPositions] = useState<Record<string, LayoutRectangle>>({});
  const paragraphs = useMemo(() => {
    const rows: { id: string; text: string; segment: typeof segments[number] }[][] = [[]];
    segments.forEach((segment, index) => {
      segment.text.split(/(\n|[^\S\n]+)/).forEach((text, part) => {
        if (text === '\n') rows.push([]);
        else if (text) rows[rows.length - 1].push({ id: `${index}-${part}`, text, segment });
      });
    });
    return rows;
  }, [segments]);
  const measure = (id: string, layout: LayoutRectangle) => {
    setPositions((current) => {
      const old = current[id];
      return old && old.x === layout.x && old.y === layout.y && old.width === layout.width && old.height === layout.height
        ? current : { ...current, [id]: layout };
    });
  };
  const delta = (format?: CreativeInlineText) => ({
    x: width * (format?.offsetX ?? 0) / 100,
    y: height * (format?.offsetY ?? 0) / 100,
  });
  useEffect(() => {
    let outside: string | null = null;
    for (const [index, paragraph] of paragraphs.entries()) {
      const row = positions[`row-${index}`];
      if (!positions.flow || !row) continue;
      for (const { id, text, segment } of paragraph) {
        const word = positions[id];
        const format = box.inline?.[segment.key];
        if (!word || !text.trim() || (!format?.offsetX && !format?.offsetY)) continue;
        const x = width * (box.x + (format.offsetX ?? 0)) / 100 + word.x + row.x + positions.flow.x + 1;
        const y = height * (box.y + (format.offsetY ?? 0)) / 100 + word.y + row.y + positions.flow.y + 1;
        if (x < -0.5 || y < -0.5 || x + word.width > width + 0.5 || y + word.height > height + 0.5) {
          outside = segment.label;
          break;
        }
      }
      if (outside) break;
    }
    onOverflow(outside);
  }, [box, height, onOverflow, paragraphs, positions, width]);

  return (
    <View style={styles.flow} onLayout={(event) => measure('flow', event.nativeEvent.layout)} testID="creative-preview-message">
      {paragraphs.map((paragraph, index) => (
        <View key={index} onLayout={(event) => measure(`row-${index}`, event.nativeEvent.layout)}
          style={[styles.flowRow, { minHeight: fontSize * 1.5 }]}>
          {paragraph.map(({ id, text, segment }) => {
            const local = box.inline?.[segment.key];
            const size = fontSize * (local?.fontSize ?? box.fontSize) / box.fontSize;
            if (!text.trim()) return <View key={id} style={{ width: size * .25 }} />;
            const offset = delta(local);
            const textStyle = { [SCALED_FLAG]: true, fontSize: size, lineHeight: size * 1.5 };
            return (
              <Text key={id} testID={`creative-preview-part-${segment.key}`} allowFontScaling={false}
                onLayout={(event) => measure(id, event.nativeEvent.layout)}
                style={[styles.flowWord, textStyle, styleFor({ ...box, ...local }), {
                  transform: [{ translateX: offset.x }, { translateY: offset.y }],
                }]}>
                {text}
              </Text>
            );
          })}
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  page: { flex: 1, direction: 'rtl', paddingHorizontal: 16, gap: 8 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  title: { fontFamily: 'Tajawal-Bold', fontSize: 20, writingDirection: 'rtl' },
  rtl: { textAlign: 'auto', writingDirection: 'rtl', fontFamily: 'Tajawal-Medium' },
  content: { gap: 10, paddingBottom: 12 },
  board: { alignSelf: 'center', direction: 'ltr', overflow: 'hidden', backgroundColor: '#24282B' },
  box: { position: 'absolute', justifyContent: 'center', borderWidth: 1, borderColor: '#57E7B6', backgroundColor: '#0002' },
  sample: { width: '100%', direction: 'rtl', writingDirection: 'rtl', textAlign: rtlTextAlign, color: '#FFF', includeFontPadding: false },
  flow: { width: '100%', direction: 'rtl' },
  flowRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'baseline', direction: 'rtl' },
  flowWord: { color: '#FFF', fontFamily: 'Amiri-Regular', includeFontPadding: false },
  fields: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  invitationText: { direction: 'rtl', textAlign: rtlTextAlign, writingDirection: 'rtl', lineHeight: 26 },
  fieldLabel: { fontFamily: 'Tajawal-Bold', writingDirection: 'rtl' },
  controls: { padding: 10, borderWidth: 1, borderRadius: 12, gap: 6 },
  controlRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  controlLabel: { flex: 1 },
  value: { width: 58, minHeight: 44, borderWidth: 1, borderRadius: 10, paddingHorizontal: 4, textAlign: 'center', writingDirection: 'ltr' },
  touch: { minHeight: 44, minWidth: 44, borderRadius: 10, borderWidth: 1, borderColor: '#8885',
    justifyContent: 'center', alignItems: 'center', paddingHorizontal: 8 },
  save: { minHeight: 48, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  saveText: { color: '#FFF', fontFamily: 'Tajawal-Bold' },
  error: { backgroundColor: '#FFF1F1', borderColor: '#D74D4D', borderWidth: 1, borderRadius: 10, padding: 8 },
});
