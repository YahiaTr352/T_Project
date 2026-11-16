import { Ionicons } from '@expo/vector-icons';
import { Pressable, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { LIST_INITIAL } from '../helpers';
import { styles } from '../styles';

interface ShowMoreBtnProps {
  visibleCount: number;
  total: number;
  onMore: () => void;
  onCollapse?: () => void;
  isDark: boolean;
  label?: string;
}

export function ShowMoreBtn({
  visibleCount,
  total,
  onMore,
  onCollapse,
  isDark,
  label = 'عرض المزيد',
}: ShowMoreBtnProps) {
  if (total <= LIST_INITIAL && visibleCount >= total) {
    return null;
  }

  const hasMore = visibleCount < total;
  const canCollapse = onCollapse != null && visibleCount > LIST_INITIAL;

  if (!hasMore && !canCollapse) {
    return null;
  }

  return (
    <View style={styles.showMoreWrapper}>
      {hasMore ? (
        <Pressable
          onPress={onMore}
          style={({ pressed }) => [
            styles.showMoreBtn,
            {
              backgroundColor: isDark ? 'rgba(34, 197, 94, 0.12)' : '#f0fdf4',
              borderColor: isDark ? 'rgba(34, 197, 94, 0.3)' : '#86efac',
              opacity: pressed ? 0.8 : 1,
            },
          ]}>
          <Ionicons name="chevron-down-circle" size={17} color={isDark ? '#4ade80' : '#16a34a'} />
          <ThemedText style={[styles.showMoreBtnText, { color: isDark ? '#4ade80' : '#16a34a' }]}>
            {label} ({visibleCount} من {total})
          </ThemedText>
        </Pressable>
      ) : null}

      {canCollapse ? (
        <Pressable
          onPress={onCollapse}
          style={({ pressed }) => [
            styles.collapseBtn,
            {
              backgroundColor: isDark ? '#1a221b' : '#f9fafb',
              borderColor: isDark ? '#273429' : '#e5e7eb',
              opacity: pressed ? 0.8 : 1,
            },
          ]}>
          <Ionicons name="chevron-up" size={15} color={isDark ? '#9ca3af' : '#6b7280'} />
          <ThemedText style={[styles.collapseBtnText, { color: isDark ? '#9ca3af' : '#6b7280' }]}>
            طي القائمة
          </ThemedText>
        </Pressable>
      ) : null}
    </View>
  );
}
