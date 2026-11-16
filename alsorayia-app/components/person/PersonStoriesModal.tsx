import { fetchPersonStories, type PersonStoryItem } from '@/lib/api/familyTreeApi';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  useWindowDimensions,
  View
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface PersonStoriesModalProps {
  visible: boolean;
  onClose: () => void;
  personId: number;
  personName?: string;
  colors: any;
  initialCount?: number;
}

export const PersonStoriesModal: React.FC<PersonStoriesModalProps> = ({
  visible,
  onClose,
  personId,
  personName,
  colors,
  initialCount = 0,
}) => {
  const insets = useSafeAreaInsets();
  const { height: windowHeight } = useWindowDimensions();

  const [stories, setStories] = useState<PersonStoryItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [expandedStoryId, setExpandedStoryId] = useState<number | null>(null);

  const loadStories = useCallback(async () => {
    if (!personId) return;
    setLoading(true);
    setError(false);
    try {
      const res = await fetchPersonStories(personId);
      if (res.success && res.stories) {
        setStories(res.stories);
      } else {
        setStories([]);
      }
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  }, [personId]);

  useEffect(() => {
    if (visible) {
      loadStories();
    }
  }, [visible, loadStories]);

  const handleClose = () => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onClose();
  };

  const toggleExpand = (id: number) => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setExpandedStoryId((prev) => (prev === id ? null : id));
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={handleClose}
    >
      <View style={styles.overlay}>
        <View
          style={[
            styles.container,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              paddingBottom: Math.max(insets.bottom, 16),
              maxHeight: windowHeight * 0.88,
            },
          ]}
        >
          {/* مقبض السحب للأعلى */}
          <View style={styles.dragHandleWrap}>
            <View style={[styles.dragHandle, { backgroundColor: colors.border }]} />
          </View>

          {/* رأس المودال */}
          <View style={styles.header}>
            <TouchableOpacity
              onPress={handleClose}
              style={[styles.closeBtn, { backgroundColor: colors.primary + '15' }]}
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>

            <View style={styles.headerTitleWrap}>
              <Text style={[styles.headerTitle, { color: colors.text }]}>
                قصص {personName ? `«${personName}»` : ''}
              </Text>
              <Text style={[styles.headerSubtitle, { color: colors.icon }]}>
                {stories.length > 0 ? `${stories.length} قصة` : `${initialCount} قصة`}
              </Text>
            </View>

            <View style={{ width: 36 }} />
          </View>

          {/* محتوى القصص */}
          {loading ? (
            <View style={styles.loaderWrap}>
              <ActivityIndicator size="large" color={colors.primary} />
              <Text style={[styles.loadingText, { color: colors.icon }]}>جاري تحميل القصص...</Text>
            </View>
          ) : error ? (
            <View style={styles.emptyWrap}>
              <Ionicons name="alert-circle-outline" size={54} color="#e74c3c" />
              <Text style={[styles.emptyText, { color: colors.text }]}>تعذر جلب القصص</Text>
              <TouchableOpacity
                style={[styles.retryBtn, { backgroundColor: colors.primary }]}
                onPress={loadStories}
              >
                <Text style={styles.retryText}>إعادة المحاولة</Text>
              </TouchableOpacity>
            </View>
          ) : stories.length === 0 ? (
            <View style={styles.emptyWrap}>
              <Ionicons name="book-outline" size={54} color={colors.icon} style={{ opacity: 0.6 }} />
              <Text style={[styles.emptyText, { color: colors.text }]}>لا توجد قصص مسجلة لهذا العضو حالياً</Text>
            </View>
          ) : (
            <ScrollView
              showsVerticalScrollIndicator={false}
              contentContainerStyle={styles.scrollContent}
            >
              {stories.map((story) => {
                const isExpanded = expandedStoryId === story.id;
                return (
                  <TouchableOpacity
                    key={story.id}
                    style={[
                      styles.storyCard,
                      {
                        backgroundColor: colors.background,
                        borderColor: colors.border,
                      },
                    ]}
                    activeOpacity={0.85}
                    onPress={() => toggleExpand(story.id)}
                  >
                    <View style={styles.storyTopRow}>
                      <View style={[styles.bookIconBadge, { backgroundColor: colors.primary + '15' }]}>
                        <Ionicons name="book" size={16} color={colors.primary} />
                      </View>
                      <Text style={[styles.storyTitle, { color: colors.text }]} numberOfLines={2}>
                        {story.title}
                      </Text>
                    </View>

                    {/* الرواة إن وجدوا */}
                    {story.narrators && story.narrators.length > 0 && (
                      <View style={styles.narratorsRow}>
                        <Text style={[styles.narratorsLabel, { color: colors.icon }]}>الرواة:</Text>
                        <View style={styles.narratorsList}>
                          {story.narrators.map((n: { id: number; name: string }) => (
                            <View
                              key={n.id}
                              style={[styles.narratorChip, { backgroundColor: colors.primary + '10' }]}
                            >
                              <Ionicons name="mic-outline" size={11} color={colors.primary} />
                              <Text style={[styles.narratorName, { color: colors.primary }]}>{n.name}</Text>
                            </View>
                          ))}
                        </View>
                      </View>
                    )}

                    {/* محتوى القصة */}
                    <Text
                      style={[styles.storyContent, { color: colors.text }]}
                      numberOfLines={isExpanded ? undefined : 3}
                    >
                      {story.content}
                    </Text>

                    {/* زر التوسيع / الإغلاق */}
                    <View style={styles.cardFooter}>
                      <Text style={[styles.expandHint, { color: colors.primary }]}>
                        {isExpanded ? 'عرض أقل ↑' : 'قراءة المزيد ↓'}
                      </Text>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'flex-end',
  },
  container: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingTop: 10,
    paddingHorizontal: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -6 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  dragHandleWrap: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  dragHandle: {
    width: 44,
    height: 4.5,
    borderRadius: 3,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(150,150,150,0.15)',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    alignItems: 'center',
    flex: 1,
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  headerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  scrollContent: {
    paddingVertical: 14,
    gap: 12,
  },
  storyCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    gap: 8,
  },
  storyTopRow: {
    flexDirection: 'row-reverse',
    alignItems: 'center',
    gap: 10,
  },
  bookIconBadge: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  storyTitle: {
    flex: 1,
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',

    lineHeight: 22,
  },
  narratorsRow: {
    flexDirection: 'row-reverse',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
  },
  narratorsLabel: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
  },
  narratorsList: {
    flexDirection: 'row-reverse',
    flexWrap: 'wrap',
    gap: 6,
  },
  narratorChip: {
    flexDirection: 'row-reverse',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  narratorName: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  storyContent: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',

    lineHeight: 22,
    marginTop: 4,
  },
  cardFooter: {
    alignItems: 'flex-start',
    marginTop: 4,
  },
  expandHint: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  loaderWrap: {
    paddingVertical: 60,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
  },
  emptyWrap: {
    paddingVertical: 60,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  emptyText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 20,
    marginTop: 6,
  },
  retryText: {
    color: '#fff',
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },
});
