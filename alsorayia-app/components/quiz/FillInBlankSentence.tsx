import React from 'react';
import { StyleSheet, View, Pressable } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { QuizChoice } from '@/lib/api/quiz';

interface FillInBlankSentenceProps {
  questionText: string;
  choices: QuizChoice[];
  selectedId: number | null;
  onChange: (id: number | null) => void;
  colors: any;
}

export default function FillInBlankSentence({
  questionText,
  choices,
  selectedId,
  onChange,
  colors,
}: FillInBlankSentenceProps) {
  // Split the sentence by the standard '___' blank indicator
  const parts = questionText.split('___');
  const beforeText = parts[0] || '';
  const afterText = parts[1] || '';

  const selectedChoice = choices.find((c) => c.id === selectedId);

  const handleSelectChip = (choiceId: number) => {
    onChange(choiceId);
  };

  const handleClear = () => {
    onChange(null);
  };

  return (
    <View style={styles.container}>
      {/* The Sentence with Drop Zone */}
      <View style={[styles.sentenceWrapper, { borderColor: colors.text === '#FFFFFF' ? colors.primary + '33' : '#d1fae5', backgroundColor: colors.background }]}>
        <View style={styles.sentenceRow}>
          {beforeText.trim() !== '' && (
            <ThemedText style={[styles.sentenceText, { color: colors.text }]}>
              {beforeText}
            </ThemedText>
          )}

          {/* Interactive Drop Zone */}
          <Pressable
            style={[
              styles.dropZone,
              selectedChoice ? styles.dropZoneActive : styles.dropZoneEmpty,
              {
                borderColor: selectedChoice ? '#10b981' : '#34d399',
              },
            ]}
            onPress={selectedChoice ? handleClear : undefined}
          >
            {selectedChoice ? (
              <ThemedText style={styles.dropZoneText}>
                {selectedChoice.choice_text}
              </ThemedText>
            ) : (
              <ThemedText style={styles.placeholderText}>
                اضغط كلمة
              </ThemedText>
            )}
          </Pressable>

          {afterText.trim() !== '' && (
            <ThemedText style={[styles.sentenceText, { color: colors.text }]}>
              {afterText}
            </ThemedText>
          )}
        </View>
      </View>

      {/* Word Chips Options */}
      <View style={styles.chipsContainer}>
        {choices.map((choice) => {
          const isSelected = selectedId === choice.id;

          return (
            <Pressable
              key={choice.id}
              style={[
                styles.chip,
                {
                  backgroundColor: isSelected ? 'rgba(37, 99, 235, 0.1)' : colors.card,
                  borderColor: isSelected ? '#bfdbfe' : colors.border,
                  opacity: isSelected ? 0.4 : 1,
                },
              ]}
              onPress={() => !isSelected && handleSelectChip(choice.id)}
              disabled={isSelected}
            >
              <ThemedText style={[styles.chipText, { color: '#2563eb' }]}>
                {choice.choice_text}
              </ThemedText>
            </Pressable>
          );
        })}
      </View>

      <ThemedText style={[styles.hint, { color: colors.icon }]}>
        اختر الكلمة المناسبة لإتمام الجملة بالضغط عليها
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
    alignItems: 'center',
    paddingVertical: 4,
  },
  sentenceWrapper: {
    width: '100%',
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    marginBottom: 20,
  },
  sentenceRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  sentenceText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    lineHeight: 24,
  },
  dropZone: {
    minWidth: 100,
    height: 38,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1.5,
    justifyContent: 'center',
    alignItems: 'center',
  },
  dropZoneEmpty: {
    borderStyle: 'dashed',
    backgroundColor: '#fff',
  },
  dropZoneActive: {
    borderStyle: 'solid',
    backgroundColor: '#d1fae5',
  },
  dropZoneText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#047857',
  },
  placeholderText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    color: '#9ca3af',
  },
  chipsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: 10,
    marginBottom: 16,
  },
  chip: {
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 20,
    borderWidth: 1,
  },
  chipText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  hint: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    textAlign: 'center',
  },
});
