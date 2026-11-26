import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import type { CreativeUploadField } from '@/lib/api/invitations';
import { Image } from 'expo-image';
import { useRef, useState } from 'react';
import {
  ActivityIndicator, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet,
  TextInput, View, useWindowDimensions, type GestureResponderEvent,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { CreativeColorPicker } from './CreativeColorPicker';

type Region = Pick<CreativeUploadField, 'x' | 'y' | 'width' | 'height'>;
type Point = Pick<Region, 'x' | 'y'>;
type Preview = { design: string; proof: string };
export type PreparedCreativeUpload = Preview & { fields: CreativeUploadField[] };

const maxFields = 12;
const fonts = [['tajawal', 'تجوال'], ['amiri', 'أميري']] as const;
const alignments = [['right', 'يمين'], ['center', 'وسط'], ['left', 'يسار']] as const;
// Android aligns its native paragraph to the start of the explicit RTL direction.
const arabicTextAlign = Platform.OS === 'android' ? 'auto' : 'right';
const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(max, value));

interface Props {
  imageUri: string;
  initial?: PreparedCreativeUpload;
  colors: ThemeColors;
  onClose: () => void;
  onApply: (prepared?: PreparedCreativeUpload) => void;
  renderPreview: (fields: CreativeUploadField[]) => Promise<Preview>;
}

export function CreativeUploadEditor({ imageUri, initial, colors, onClose, onApply, renderPreview }: Props) {
  const { width: screenWidth, height: screenHeight } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [dimensions, setDimensions] = useState<{ width: number; height: number }>();
  const [sourceError, setSourceError] = useState(false);
  const [fields, setFields] = useState(initial?.fields ?? []);
  const [selected, setSelected] = useState(initial?.fields[0]?.id);
  const [target, setTarget] = useState<'new' | number>();
  const [drawing, setDrawing] = useState<Region>();
  const [preview, setPreview] = useState(initial);
  const [showPreview, setShowPreview] = useState(false);
  const [choosingColor, setChoosingColor] = useState(false);
  const [busy, setBusy] = useState(false);
  const [imageLoading, setImageLoading] = useState(false);
  const [error, setError] = useState<string>();
  const start = useRef<Point | null>(null);
  const nextId = useRef(Math.max(0, ...fields.map((field) => field.id)) + 1);
  const current = fields.find((field) => field.id === selected);
  const ratio = dimensions ? dimensions.width / dimensions.height : null;
  const width = Math.min(540, screenWidth - 40, ratio ? screenHeight * .42 * ratio : Infinity);
  const height = ratio ? width / ratio : 150;
  const shortSide = dimensions ? Math.min(dimensions.width, dimensions.height) : 100;
  const interactionLocked = busy || imageLoading;
  const complete = fields.every((field) => field.label.trim() && field.text.trim() && /^#[a-f\d]{6}$/i.test(field.color));
  const canSave = !interactionLocked && target === undefined && (!fields.length || Boolean(preview));
  const markers = [...fields.filter((field) => !drawing || field.id !== target), ...(drawing ? [{ ...drawing, id: 'drawing', label: '' }] : [])];

  const changeFields = (updated: CreativeUploadField[]) => {
    setFields(updated);
    setPreview(undefined);
    setShowPreview(false);
    setError(undefined);
  };

  const update = (values: Partial<CreativeUploadField>) => {
    changeFields(fields.map((item) => item.id === selected ? { ...item, ...values } : item));
  };

  const point = ({ nativeEvent }: GestureResponderEvent): Point => ({
    x: clamp(nativeEvent.locationX / width * 100, 0, 100),
    y: clamp(nativeEvent.locationY / height * 100, 0, 100),
  });

  const region = (origin: Point, end: Point): Region => ({
    x: Math.min(origin.x, end.x), y: Math.min(origin.y, end.y),
    width: Math.abs(end.x - origin.x), height: Math.abs(end.y - origin.y),
  });

  const stopDrawing = () => { start.current = null; setDrawing(undefined); };

  const placeField = (event: GestureResponderEvent) => {
    if (!start.current || !dimensions || target === undefined) return;
    const origin = start.current;
    const dragged = region(origin, point(event));
    const size = target === 'new' ? { width: 36, height: 12 } : current ?? { width: 36, height: 12 };
    const box = dragged.width >= 1 && dragged.height >= 1 ? dragged : {
      x: clamp(origin.x - size.width / 2, 0, 100 - size.width), y: clamp(origin.y - size.height / 2, 0, 100 - size.height),
      width: size.width, height: size.height,
    };
    if (target === 'new') {
      const id = nextId.current++;
      changeFields([...fields, {
        ...box, id, label: '', text: '', fontFamily: 'tajawal', fontSize: clamp(box.height * dimensions.height / shortSide * .48, 1, 40),
        alignment: 'center', bold: false, color: '#ffffff',
      }]);
      setSelected(id);
    } else {
      changeFields(fields.map((field) => field.id === target ? { ...field, ...box } : field));
    }
    stopDrawing();
    setTarget(undefined);
  };

  const viewPreview = async () => {
    if (!fields.length || !complete || interactionLocked || target !== undefined) return;
    setError(undefined);
    setImageLoading(true);
    if (preview) {
      setShowPreview(true);
      return;
    }
    setBusy(true);
    try {
      const result = await renderPreview(fields);
      setPreview({ ...result, fields });
      setShowPreview(true);
    } catch (cause) {
      setImageLoading(false);
      setError(cause instanceof Error ? cause.message : 'تعذّر تجهيز معاينة التصميم.');
    } finally {
      setBusy(false);
    }
  };

  const chip = (label: string, onPress: () => void, active = false, disabled = false, key = label) => (
    <Pressable key={key} accessibilityRole="button" accessibilityState={{ selected: active, disabled }} disabled={disabled} onPress={onPress}
      style={[styles.chip, { borderColor: active ? colors.primary : colors.border, backgroundColor: active ? `${colors.primary}19` : colors.card, opacity: disabled ? .5 : 1 }]}>
      <ThemedText style={[styles.label, { color: active ? colors.primary : colors.text }]}>{label}</ThemedText>
    </Pressable>
  );

  const fontSizeButton = (field: CreativeUploadField, increase: boolean) => {
    const disabled = interactionLocked || (increase ? field.fontSize >= 40 : field.fontSize <= 1);
    return (
      <Pressable accessibilityRole="button" accessibilityLabel={increase ? 'تكبير حجم الخط' : 'تصغير حجم الخط'}
        accessibilityState={{ disabled }} disabled={disabled}
        onPress={() => update({ fontSize: clamp(field.fontSize + (increase ? 100 : -100) / shortSide, 1, 40) })}
        style={[styles.sizeButton, { borderColor: colors.border, opacity: disabled ? .5 : 1 }]}>
        <ThemedText style={[styles.sizeSymbol, { color: colors.text }]}>{increase ? '+' : '−'}</ThemedText>
      </Pressable>
    );
  };

  return (
    <Modal visible animationType="slide" onRequestClose={onClose}>
      <KeyboardAvoidingView testID="creative-upload-editor" style={[styles.page, { backgroundColor: colors.background, paddingTop: Math.max(10, insets.top) }]}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={[styles.header, { borderColor: colors.border }]}>
          <ThemedText accessibilityRole="header" style={styles.title}>محرّر المتغيّرات</ThemedText>
          <Pressable accessibilityRole="button" onPress={onClose} style={styles.action}>
            <ThemedText style={[styles.label, { color: colors.primary }]}>إلغاء</ThemedText>
          </Pressable>
        </View>
        <ScrollView style={styles.rtl} keyboardShouldPersistTaps="handled" scrollEnabled={!drawing && !choosingColor}
          contentContainerStyle={[styles.content, { paddingBottom: Math.max(24, insets.bottom + 12) }]}>
          <ThemedText style={[styles.text, { color: colors.icon }]}>حدّد مساحة فارغة للاسم أو التاريخ أو المكان، ثم اكتب النص واضبط تنسيقه.</ThemedText>
          <View style={styles.row}>
            {target === undefined
              ? chip('إضافة حقل', () => { setShowPreview(false); setTarget('new'); }, false, !dimensions || sourceError || fields.length >= maxFields || interactionLocked)
              : chip('إلغاء التحديد', () => { setDrawing(undefined); setTarget(undefined); }, true)}
            {showPreview
              ? chip('عرض أماكن الحقول', () => setShowPreview(false), false, interactionLocked)
              : chip('معاينة التصميم', () => void viewPreview(), false, !dimensions || !fields.length || !complete || target !== undefined || interactionLocked)}
          </View>
          {target !== undefined ? <ThemedText accessibilityLiveRegion="polite" style={styles.text}>اضغط على الموقع أو اسحب لتحديد مساحة الحقل.</ThemedText> : null}
          <View testID="creative-upload-stage" style={[styles.stage, { width, height, backgroundColor: colors.card }]}
            onStartShouldSetResponder={() => target !== undefined}
            onResponderGrant={(event) => { start.current = point(event); setDrawing(region(start.current, start.current)); }}
            onResponderMove={(event) => { if (start.current && target !== undefined) setDrawing(region(start.current, point(event))); }}
            onResponderRelease={placeField} onResponderTerminationRequest={() => false} onResponderTerminate={stopDrawing}>
            <View pointerEvents="none" style={StyleSheet.absoluteFill}>
              <Image source={{ uri: imageUri }} accessibilityLabel="صورة التصميم الأصلية" style={StyleSheet.absoluteFill} contentFit="contain"
                onLoad={({ source }) => {
                  if (source.width > 0 && source.height > 0) {
                    setDimensions({ width: source.width, height: source.height });
                    setSourceError(false);
                  }
                }} onError={() => setSourceError(true)} />
              {showPreview && preview ? <Image source={{ uri: preview.design }} accessibilityLabel="معاينة التصميم مع النصوص"
                style={StyleSheet.absoluteFill} contentFit="contain" cachePolicy="none" onLoad={() => setImageLoading(false)}
                onError={() => {
                  setImageLoading(false);
                  setPreview(undefined);
                  setShowPreview(false);
                  setError('تعذّر عرض المعاينة. جرّب مرة أخرى.');
                }} /> : null}
              {!showPreview ? markers.map((box) => (
                <View key={box.id} style={[styles.marker, {
                  start: `${box.x}%`, top: `${box.y}%`, width: `${box.width}%`, height: `${box.height}%`, borderColor: colors.primary,
                  backgroundColor: box.id === selected || box.id === 'drawing' ? `${colors.primary}33` : 'transparent',
                }]}>
                  {box.label ? <ThemedText numberOfLines={1} style={styles.markerLabel}>{box.label}</ThemedText> : null}
                </View>
              )) : null}
              {(!dimensions && !sourceError) || interactionLocked ? <ActivityIndicator color={colors.primary} style={styles.loader} /> : null}
            </View>
          </View>
          {sourceError || error ? <ThemedText accessibilityLiveRegion="polite" style={[styles.text, { color: '#DC5656' }]}>{error || 'تعذّر فتح صورة التصميم.'}</ThemedText> : null}
          {fields.length ? (
            <View style={styles.row}>
              {fields.map((field, index) => chip(field.label || `حقل ${index + 1}`, () => {
                setShowPreview(false);
                setSelected(field.id);
              }, selected === field.id, interactionLocked || target !== undefined, String(field.id)))}
            </View>
          ) : null}
          {current && !showPreview && target === undefined ? (
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <ThemedText style={styles.label}>اسم الحقل</ThemedText>
              <TextInput accessibilityLabel="اسم الحقل" value={current.label} onChangeText={(label) => update({ label })} maxLength={60} editable={!interactionLocked}
                placeholder="اكتب اسم الحقل" placeholderTextColor={colors.icon} style={[styles.input, { color: colors.text, borderColor: colors.border }]} />
              <ThemedText style={styles.label}>النص</ThemedText>
              <TextInput accessibilityLabel="النص" value={current.text} onChangeText={(text) => update({ text })} maxLength={255} editable={!interactionLocked} multiline
                placeholder="اكتب النص هنا" placeholderTextColor={colors.icon} style={[styles.input, styles.multiline, { color: colors.text, borderColor: colors.border }]} />
              <ThemedText style={styles.label}>الخط</ThemedText>
              <View style={styles.row}>{fonts.map(([fontFamily, label]) => chip(label, () => update({ fontFamily }), current.fontFamily === fontFamily, interactionLocked))}
                {chip('عريض', () => update({ bold: !current.bold }), current.bold, interactionLocked)}</View>
              <ThemedText style={styles.label}>حجم الخط</ThemedText>
              <View style={styles.row}>
                {fontSizeButton(current, false)}
                <ThemedText style={styles.text}>{Math.round(current.fontSize * shortSide / 100)} بكسل</ThemedText>
                {fontSizeButton(current, true)}
              </View>
              <ThemedText style={styles.label}>المحاذاة</ThemedText>
              <View style={styles.row}>{alignments.map(([alignment, label]) => chip(label, () => update({ alignment }), current.alignment === alignment, interactionLocked))}</View>
              <CreativeColorPicker key={current.id} color={current.color} colors={colors} disabled={interactionLocked}
                onChange={(color) => update({ color })} onInteractionChange={setChoosingColor} />
              <View style={styles.row}>
                {chip('تغيير الموضع', () => setTarget(current.id), false, interactionLocked || sourceError)}
                {chip('حذف الحقل', () => {
                  const remaining = fields.filter((field) => field.id !== current.id);
                  changeFields(remaining);
                  setSelected(remaining[0]?.id);
                }, false, interactionLocked)}
              </View>
            </View>
          ) : null}
          <Pressable accessibilityRole="button" accessibilityState={{ disabled: !canSave }} disabled={!canSave} onPress={() => onApply(preview)}
            style={[styles.save, { backgroundColor: colors.primary, opacity: canSave ? 1 : .55 }]}>
            <ThemedText style={[styles.label, { color: '#fff' }]}>حفظ المتغيّرات</ThemedText>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  page: { flex: 1, direction: 'rtl' },
  rtl: { direction: 'rtl' },
  header: { direction: 'rtl', flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, borderBottomWidth: 1 },
  title: { fontFamily: 'Tajawal-Bold', fontSize: 20, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  text: { fontFamily: 'Tajawal-Medium', fontSize: 14, lineHeight: 21, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  label: { fontFamily: 'Tajawal-Bold', fontSize: 15, textAlign: arabicTextAlign, writingDirection: 'rtl' },
  action: { padding: 12 },
  content: { direction: 'rtl', padding: 20, gap: 12, width: '100%', maxWidth: 680, alignSelf: 'center' },
  stage: { alignSelf: 'center', position: 'relative', overflow: 'hidden', borderRadius: 12, direction: 'ltr' },
  loader: { flex: 1 },
  marker: { position: 'absolute', borderWidth: 1.5 },
  markerLabel: { backgroundColor: '#145147', color: '#fff', fontFamily: 'Tajawal-Bold', fontSize: 11, textAlign: 'center', writingDirection: 'rtl' },
  row: { direction: 'rtl', flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  chip: { minHeight: 44, borderWidth: 1, borderRadius: 12, paddingHorizontal: 12, justifyContent: 'center' },
  sizeButton: { width: 44, height: 44, borderWidth: 1, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  sizeSymbol: { fontFamily: 'Tajawal-Bold', fontSize: 22, textAlign: 'center' },
  card: { direction: 'rtl', borderRadius: 20, borderWidth: 1, padding: 16, gap: 8 },
  input: { minHeight: 48, paddingHorizontal: 12, borderWidth: 1, borderRadius: 12, textAlign: 'right', writingDirection: 'rtl', fontFamily: 'Tajawal-Medium' },
  multiline: { paddingTop: 12, textAlignVertical: 'top' },
  save: { minHeight: 48, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
});
