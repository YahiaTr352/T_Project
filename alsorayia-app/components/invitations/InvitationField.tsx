import { ThemedText } from '@/components/themed-text';
import React from 'react';
import { StyleSheet, TextInput, View } from 'react-native';

interface InvitationFieldProps {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  multiline?: boolean;
  mutedColor: string;
  textColor: string;
  cardBg: string;
  borderColor: string;
  returnKeyType?: 'done' | 'next' | 'go' | 'search' | 'send';
  onSubmitEditing?: () => void;
  selection?: { start: number; end: number } | null;
  onSelectionChange?: (sel: { start: number; end: number }) => void;
  fontFamily?: string;
  hideLabel?: boolean;
}

export function InvitationField({
  label,
  value,
  onChangeText,
  placeholder,
  multiline,
  mutedColor,
  textColor,
  cardBg,
  borderColor,
  returnKeyType,
  onSubmitEditing,
  selection,
  onSelectionChange,
  fontFamily,
  hideLabel = false,
}: InvitationFieldProps) {
  return (
    <View style={styles.wrap}>
      {!hideLabel ? (
        <ThemedText style={[styles.label, { color: mutedColor }]}>{label}</ThemedText>
      ) : null}
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={mutedColor}
        multiline={multiline}
        textAlign="right"
        textAlignVertical={multiline ? 'top' : 'center'}
        returnKeyType={returnKeyType ?? (multiline ? undefined : 'done')}
        onSubmitEditing={onSubmitEditing}
        selection={selection ?? undefined}
        onSelectionChange={(e) => onSelectionChange?.(e.nativeEvent.selection)}
        style={[
          styles.input,
          multiline && styles.multiline,
          {
            color: textColor,
            backgroundColor: cardBg,
            borderColor,
            ...(fontFamily ? { fontFamily } : {}),
          },
        ]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 12,
  },
  label: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Medium',
    marginBottom: 6,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  input: {
    borderWidth: 1,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    fontFamily: 'Tajawal-Regular',
    writingDirection: 'rtl',
  },
  multiline: {
    minHeight: 122,
    paddingTop: 12,
  },
});
