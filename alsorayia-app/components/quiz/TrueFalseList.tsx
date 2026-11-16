import React from 'react';
import { StyleSheet, View, Pressable } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { QuizChoice } from '@/lib/api/quiz';

interface TrueFalseListProps {
  choices: QuizChoice[];
  value: Record<number, boolean>;
  onChange: (value: Record<number, boolean>) => void;
  colors: any;
}

export default function TrueFalseList({
  choices,
  value,
  onChange,
  colors,
}: TrueFalseListProps) {
  const handleToggle = (choiceId: number, status: boolean) => {
    onChange({
      ...value,
      [choiceId]: status,
    });
  };

  return (
    <View style={styles.container}>
      {choices.map((choice) => {
        const currentVal = value[choice.id];

        return (
          <View key={choice.id} style={[styles.row, { borderColor: colors.border }]}>
            <ThemedText style={[styles.choiceText, { color: colors.text }]}>
              {choice.choice_text}
            </ThemedText>

            <View style={[styles.toggleContainer, { backgroundColor: colors.background }]}>
              {/* True Button */}
              <Pressable
                style={[
                  styles.toggleBtn,
                  styles.trueBtn,
                  currentVal === true && styles.trueActiveBtn,
                ]}
                onPress={() => handleToggle(choice.id, true)}
              >
                <ThemedText
                  style={[
                    styles.toggleBtnText,
                    currentVal === true ? styles.activeText : { color: '#6b7280' },
                  ]}
                >
                  صح
                </ThemedText>
              </Pressable>

              {/* False Button */}
              <Pressable
                style={[
                  styles.toggleBtn,
                  styles.falseBtn,
                  currentVal === false && styles.falseActiveBtn,
                ]}
                onPress={() => handleToggle(choice.id, false)}
              >
                <ThemedText
                  style={[
                    styles.toggleBtnText,
                    currentVal === false ? styles.activeText : { color: '#6b7280' },
                  ]}
                >
                  خطأ
                </ThemedText>
              </Pressable>
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
    gap: 12,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderBottomWidth: 1,
  },
  choiceText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    flex: 1,
    paddingLeft: 10,
  },
  toggleContainer: {
    flexDirection: 'row',
    borderRadius: 10,
    padding: 3,
    width: 110,
  },
  toggleBtn: {
    flex: 1,
    paddingVertical: 6,
    alignItems: 'center',
    borderRadius: 8,
  },
  toggleBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  trueBtn: {},
  falseBtn: {},
  trueActiveBtn: {
    backgroundColor: '#10b981', // green for true
  },
  falseActiveBtn: {
    backgroundColor: '#ef4444', // red for false
  },
  activeText: {
    color: '#fff',
  },
});
