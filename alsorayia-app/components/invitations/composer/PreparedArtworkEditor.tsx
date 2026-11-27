import { InvitationGradientButton } from '@/components/invitations/InvitationPrimitives';
import { getInvitationPalette } from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import { creativeArtworkSource, resolveCreativeArtworkUrl, type CreativeTextField } from '@/lib/api/invitations';
import { Image } from 'expo-image';
import { useEffect, useRef, useState } from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  useColorScheme,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface Props {
  imageUrl: string;
  fields: CreativeTextField[];
  values: Record<string, string>;
  colors: ThemeColors;
  background: string;
  border: string;
  renderPreview: (values: Record<string, string>, signal: AbortSignal) => Promise<string>;
  onApply: (values: Record<string, string>, image?: string) => void;
  onClose: () => void;
}

export function PreparedArtworkEditor({ imageUrl, fields, values, renderPreview, onApply, onClose }: Props) {
  const window = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const isDark = useColorScheme() === 'dark';
  const palette = getInvitationPalette(isDark);
  const [draft, setDraft] = useState(() => Object.fromEntries(fields.map((field) => [
    field.key, values[field.key] ?? field.default_text,
  ])));
  const [ratio, setRatio] = useState<number>();
  const [sourceError, setSourceError] = useState(false);
  const [frame, setFrame] = useState<{ key: string; uri: string }>();
  const [loadedKey, setLoadedKey] = useState<string>();
  const [error, setError] = useState<{ key: string; message: string }>();
  const [retry, setRetry] = useState(0);
  const [focusedField, setFocusedField] = useState<string>();
  const inputs = useRef(new Map<string, TextInput>());
  const previewKey = fields.some((field) => draft[field.key] !== field.default_text) ? JSON.stringify(draft) : '';
  const activeFrame = frame?.key === previewKey ? frame : undefined;
  const activeError = error?.key === previewKey ? error.message : undefined;
  const canApply = !previewKey || Boolean(activeFrame && loadedKey === previewKey && !activeError);
  const width = Math.min(540, window.width - 40, ratio ? window.height * 0.45 * ratio : Infinity);

  useEffect(() => {
    if (!previewKey) return;
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        const uri = await renderPreview(JSON.parse(previewKey), controller.signal);
        if (!controller.signal.aborted) {
          setError(undefined);
          setFrame({ key: previewKey, uri });
        }
      } catch (cause) {
        if (!controller.signal.aborted) {
          setError({ key: previewKey, message: cause instanceof Error ? cause.message : 'تعذّر تجهيز المعاينة.' });
        }
      }
    }, 450);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [previewKey, renderPreview, retry]);

  return (
    <Modal visible animationType="slide" onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={{ flex: 1, backgroundColor: palette.bgGradient[0], paddingTop: Math.max(10, insets.top) }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <View style={[styles.header, { borderColor: palette.border, backgroundColor: palette.surface }]}>
          <ThemedText accessibilityRole="header" style={[styles.headerTitle, { color: palette.text }]}>
            تعديل كتابة القالب
          </ThemedText>
          <Pressable
            accessibilityRole="button"
            onPress={onClose}
            style={[styles.cancelBtn, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
            <ThemedText style={[styles.cancelText, { color: palette.primary }]}>إلغاء</ThemedText>
          </Pressable>
        </View>
        <ScrollView
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={[styles.content, { paddingBottom: Math.max(28, insets.bottom + 12) }]}>
          <ThemedText style={[styles.rtl, { color: palette.textSecondary }]}>
            اضغط على النص في الصورة أو اختر الحقل أدناه.
          </ThemedText>
          <View style={[styles.stage, { width, height: ratio ? width / ratio : 150 }]}>
            <Image
              source={creativeArtworkSource(resolveCreativeArtworkUrl(imageUrl))}
              style={StyleSheet.absoluteFill}
              contentFit="contain"
              accessibilityLabel="صورة القالب"
              onLoad={({ source }) => {
                if (source.width > 0 && source.height > 0) setRatio(source.width / source.height);
                setSourceError(false);
              }}
              onError={() => setSourceError(true)}
            />
            {activeFrame ? (
              <View pointerEvents="none" style={StyleSheet.absoluteFill}>
                <Image
                  key={activeFrame.key}
                  source={{ uri: activeFrame.uri }}
                  style={StyleSheet.absoluteFill}
                  contentFit="fill"
                  accessibilityLabel="معاينة الكتابة المعدّلة"
                  cachePolicy="none"
                  onLoad={() => setLoadedKey(activeFrame.key)}
                  onError={() => setError({ key: activeFrame.key, message: 'تعذّر عرض المعاينة.' })}
                />
              </View>
            ) : null}
            {ratio ? fields.map((field) => field.x != null && field.y != null && field.width != null && field.height != null ? (
              <Pressable
                key={field.key}
                accessibilityRole="button"
                accessibilityLabel={`تعديل ${field.label}`}
                onPress={() => inputs.current.get(field.key)?.focus()}
                style={[styles.hotspot, {
                  left: `${field.x}%`,
                  top: `${field.y}%`,
                  width: `${field.width}%`,
                  height: `${field.height}%`,
                  borderColor: palette.primary,
                  backgroundColor: focusedField === field.key ? `${palette.primary}33` : 'transparent',
                }]}
              />
            ) : null) : null}
          </View>
          {sourceError ? (
            <ThemedText accessibilityLiveRegion="polite" style={[styles.rtl, { color: palette.dangerText }]}>
              تعذّر فتح صورة القالب.
            </ThemedText>
          ) : null}
          {fields.map((field) => (
            <View
              key={field.key}
              style={[
                styles.field,
                { width: Math.min(540, window.width - 40), backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              <ThemedText style={[styles.rtl, styles.fieldLabel, { color: palette.muted }]}>{field.label}</ThemedText>
              <TextInput
                ref={(input) => { if (input) inputs.current.set(field.key, input); else inputs.current.delete(field.key); }}
                accessibilityLabel={field.label}
                maxLength={255}
                multiline
                value={draft[field.key]}
                onFocus={() => setFocusedField(field.key)}
                onChangeText={(text) => setDraft((current) => ({ ...current, [field.key]: text }))}
                style={[
                  styles.rtl,
                  styles.input,
                  { color: palette.text, backgroundColor: palette.surfaceAlt, borderColor: palette.border },
                ]}
              />
            </View>
          ))}
          {activeError || !canApply ? (
            <ThemedText
              accessibilityLiveRegion="polite"
              style={[styles.rtl, { color: activeError ? palette.dangerText : palette.muted }]}>
              {activeError || 'جارٍ تجهيز المعاينة…'}
            </ThemedText>
          ) : null}
          {activeError ? (
            <Pressable
              accessibilityRole="button"
              onPress={() => {
                setError(undefined);
                setFrame(undefined);
                setLoadedKey(undefined);
                setRetry((attempt) => attempt + 1);
              }}
              style={[styles.retryBtn, { backgroundColor: palette.pillBg, borderColor: palette.pillBorder }]}>
              <ThemedText style={{ color: palette.pillText, fontFamily: 'Tajawal-Bold' }}>إعادة المعاينة</ThemedText>
            </Pressable>
          ) : null}
          <InvitationGradientButton
            label="تطبيق التعديلات"
            disabled={!canApply}
            onPress={() => onApply(draft, activeFrame?.uri)}
            palette={palette}
            style={{ width: Math.min(540, window.width - 40) }}
          />
        </ScrollView>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row-reverse',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderBottomWidth: 1,
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
  },
  cancelBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 12,
    borderWidth: 1,
  },
  cancelText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  content: { alignItems: 'center', padding: 18, gap: 12 },
  stage: { position: 'relative', direction: 'ltr', borderRadius: 14, overflow: 'hidden' },
  hotspot: { position: 'absolute', borderWidth: 1.5 },
  field: { gap: 6, borderWidth: 1, borderRadius: 18, padding: 12 },
  fieldLabel: { fontFamily: 'Tajawal-Bold', fontSize: 13 },
  retryBtn: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12, borderWidth: 1 },
  input: { borderWidth: 1, borderRadius: 12, padding: 10, minHeight: 48, fontSize: 15, fontFamily: 'Tajawal-Medium' },
  rtl: { writingDirection: 'rtl', textAlign: 'right' },
});
