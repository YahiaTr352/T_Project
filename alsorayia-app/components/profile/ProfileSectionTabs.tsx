import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';

export type ProfileTabKey = 'basic' | 'contacts' | 'children' | 'marriages' | 'locations';

interface TabItem {
  key: ProfileTabKey;
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  activeIcon: keyof typeof Ionicons.glyphMap;
  badgeCount?: number;
}

interface ProfileSectionTabsProps {
  active: ProfileTabKey;
  onChange: (key: ProfileTabKey) => void;
  counts?: {
    contacts?: number;
    children?: number;
    marriages?: number;
    locations?: number;
  };
}

export function ProfileSectionTabs({ active, onChange, counts }: ProfileSectionTabsProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const tabs: TabItem[] = [
    { key: 'basic', label: 'بياناتي', icon: 'person-outline', activeIcon: 'person' },
    { key: 'contacts', label: 'التواصل', icon: 'call-outline', activeIcon: 'call', badgeCount: counts?.contacts },
    { key: 'children', label: 'أبنائي', icon: 'people-outline', activeIcon: 'people', badgeCount: counts?.children },
    { key: 'marriages', label: 'الزواج', icon: 'heart-outline', activeIcon: 'heart', badgeCount: counts?.marriages },
    { key: 'locations', label: 'أماكني', icon: 'location-outline', activeIcon: 'location', badgeCount: counts?.locations },
  ];

  return (
    <View style={styles.container}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled">
        {tabs.map((tab) => {
          const isActive = tab.key === active;
          const iconName = isActive ? tab.activeIcon : tab.icon;

          return (
            <Pressable
              key={tab.key}
              onPress={() => onChange(tab.key)}
              style={({ pressed }) => [
                styles.tabPill,
                {
                  backgroundColor: isActive
                    ? colors.primary
                    : isDark
                      ? '#1E293B'
                      : '#FFFFFF',
                  borderColor: isActive
                    ? colors.primary
                    : isDark
                      ? '#334155'
                      : '#E2E8F0',
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <Ionicons
                name={iconName}
                size={16}
                color={isActive ? '#FFFFFF' : isDark ? '#94A3B8' : colors.primary}
              />
              <ThemedText
                style={[
                  styles.tabLabel,
                  {
                    color: isActive ? '#FFFFFF' : isDark ? '#F1F5F9' : '#334155',
                    fontFamily: isActive ? 'Tajawal-Bold' : 'Tajawal-Medium',
                  },
                ]}>
                {tab.label}
              </ThemedText>
              {typeof tab.badgeCount === 'number' && tab.badgeCount > 0 ? (
                <View
                  style={[
                    styles.badgePill,
                    {
                      backgroundColor: isActive
                        ? 'rgba(255, 255, 255, 0.25)'
                        : isDark
                          ? colors.primary + '30'
                          : colors.primary + '15',
                    },
                  ]}>
                  <ThemedText
                    style={[
                      styles.badgeText,
                      { color: isActive ? '#FFFFFF' : colors.primary },
                    ]}>
                    {tab.badgeCount}
                  </ThemedText>
                </View>
              ) : null}
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 6,
  },
  scrollContent: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 2,
    paddingVertical: 4,
  },
  tabPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 20,
    borderWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 4,
    elevation: 1,
  },
  tabLabel: {
    fontSize: 13,
  },
  badgePill: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 10,
    minWidth: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
  },
});
