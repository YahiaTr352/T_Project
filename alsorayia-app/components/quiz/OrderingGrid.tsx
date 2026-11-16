import React from 'react';
import { StyleSheet, View, Pressable, Dimensions } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { Image } from 'expo-image';
import type { QuizChoice } from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

interface OrderingGridProps {
  choices: QuizChoice[];
  orderedIds: number[];
  onChange: (ids: number[]) => void;
  colors: any;
}

export default function OrderingGrid({
  choices,
  orderedIds,
  onChange,
  colors,
}: OrderingGridProps) {
  
  const handleSelectChoice = (choiceId: number) => {
    if (orderedIds.includes(choiceId)) {
      // If already ordered, remove it
      onChange(orderedIds.filter((id) => id !== choiceId));
    } else {
      // Add to the end of the order
      onChange([...orderedIds, choiceId]);
    }
  };

  const handleClearSlot = (slotIndex: number) => {
    const newOrderedIds = [...orderedIds];
    newOrderedIds.splice(slotIndex, 1);
    onChange(newOrderedIds);
  };

  return (
    <View style={styles.container}>
      {/* Target Slots: Displays the current arrangement */}
      <View style={styles.section}>
        <ThemedText style={[styles.sectionTitle, { color: colors.icon }]}>
          الترتيب الصحيح (اضغط الخيارات بالترتيب):
        </ThemedText>
        <View style={styles.slotsRow}>
          {choices.map((_, index) => {
            const placedChoiceId = orderedIds[index];
            const placedChoice = placedChoiceId
              ? choices.find((c) => c.id === placedChoiceId)
              : null;

            return (
              <Pressable
                key={`slot-${index}`}
                style={[
                  styles.slotBox,
                  placedChoice ? styles.slotFilled : styles.slotEmpty,
                  {
                    borderColor: placedChoice ? '#10b981' : colors.border,
                    backgroundColor: placedChoice ? colors.card : colors.background,
                  },
                ]}
                onPress={() => placedChoice && handleClearSlot(index)}
              >
                {placedChoice ? (
                  placedChoice.image_url ? (
                    <Image
                      source={{ uri: placedChoice.image_url }}
                      style={styles.slotImage}
                      contentFit="cover"
                    />
                  ) : (
                    <ThemedText style={styles.slotText} numberOfLines={2}>
                      {placedChoice.choice_text}
                    </ThemedText>
                  )
                ) : (
                  <ThemedText style={styles.slotNum}>{toEnglishDigits(index + 1)}</ThemedText>
                )}
              </Pressable>
            );
          })}
        </View>
      </View>

      {/* Choices Pool: Grid of choices to select from */}
      <View style={styles.section}>
        <ThemedText style={[styles.sectionTitle, { color: colors.icon }]}>
          الخيارات المتاحة:
        </ThemedText>
        <View style={styles.poolGrid}>
          {choices.map((choice) => {
            const isPlaced = orderedIds.includes(choice.id);
            const placementIndex = orderedIds.indexOf(choice.id);

            return (
              <Pressable
                key={choice.id}
                style={[
                  styles.poolCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: isPlaced ? '#10b981' : colors.border,
                    opacity: isPlaced ? 0.5 : 1,
                  },
                ]}
                onPress={() => handleSelectChoice(choice.id)}
              >
                {choice.image_url ? (
                  <Image
                    source={{ uri: choice.image_url }}
                    style={styles.poolImage}
                    contentFit="cover"
                  />
                ) : (
                  <View style={styles.poolTextContainer}>
                    <ThemedText style={[styles.poolText, { color: colors.text }]} numberOfLines={3}>
                      {choice.choice_text}
                    </ThemedText>
                  </View>
                )}

                {isPlaced && (
                  <View style={styles.badge}>
                    <ThemedText style={styles.badgeText}>
                      {toEnglishDigits(placementIndex + 1)}
                    </ThemedText>
                  </View>
                )}
              </Pressable>
            );
          })}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
    gap: 20,
  },
  section: {
    width: '100%',
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginBottom: 10,
  },
  slotsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 10,
    width: '100%',
    flexWrap: 'wrap',
  },
  slotBox: {
    width: (SCREEN_WIDTH - 80) / 4,
    height: (SCREEN_WIDTH - 80) / 4,
    maxHeight: 80,
    maxWidth: 80,
    borderRadius: 14,
    borderWidth: 2,
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  slotEmpty: {
    borderStyle: 'dashed',
    backgroundColor: '#f6fdf9',
  },
  slotFilled: {
    borderStyle: 'solid',
    backgroundColor: '#fff',
  },
  slotNum: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    color: '#6ee7b7',
  },
  slotText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
    textAlign: 'center',
    color: '#10b981',
    padding: 4,
  },
  slotImage: {
    width: '100%',
    height: '100%',
  },
  poolGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'center',
  },
  poolCard: {
    width: (SCREEN_WIDTH - 64) / 3,
    height: (SCREEN_WIDTH - 64) / 3,
    maxHeight: 100,
    maxWidth: 100,
    borderRadius: 16,
    borderWidth: 1.5,
    overflow: 'hidden',
    position: 'relative',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 1,
  },
  poolImage: {
    width: '100%',
    height: '100%',
  },
  poolTextContainer: {
    flex: 1,
    padding: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  poolText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    textAlign: 'center',
  },
  badge: {
    position: 'absolute',
    top: 6,
    right: 6,
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: '#10b981',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1.5,
    borderColor: '#fff',
  },
  badgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
    color: '#fff',
  },
});
