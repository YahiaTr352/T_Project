import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';

export type InvitationTabKey = 'compose' | 'groups' | 'logs';

interface TabItem {
  key: InvitationTabKey;
  label: string;
  icon: IconSymbolName;
}

const TABS: TabItem[] = [
  { key: 'compose', label: 'دعوة جديدة', icon: 'paperplane.fill' },
  { key: 'groups', label: 'المجموعات', icon: 'person.2.fill' },
  { key: 'logs', label: 'السجل', icon: 'clock.fill' },
];

interface InvitationTabBarProps {
  active: InvitationTabKey;
  onChange: (key: InvitationTabKey) => void;
  groupsCount?: number;
  logsCount?: number;
}

export function InvitationTabBar({
  active,
  onChange,
  groupsCount,
  logsCount,
}: InvitationTabBarProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const badgeFor = (key: InvitationTabKey) => {
    if (key === 'groups') return groupsCount;
    if (key === 'logs') return logsCount;
    return undefined;
  };

  return (
    <View style={styles.container}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled">
        {TABS.map((tab) => {
          const isActive = tab.key === active;
          const badge = badgeFor(tab.key);

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
              <IconSymbol
                name={tab.icon}
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
              {typeof badge === 'number' && badge > 0 ? (
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
                    {badge}
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
