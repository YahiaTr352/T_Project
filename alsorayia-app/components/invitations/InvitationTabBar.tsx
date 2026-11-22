import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { Pressable, StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';

export type InvitationTabKey = 'compose' | 'groups' | 'logs' | 'creative';

interface TabItem {
  key: InvitationTabKey;
  label: string;
  icon: IconSymbolName;
}

const TABS: TabItem[] = [
  { key: 'compose', label: 'دعوة جديدة', icon: 'paperplane.fill' },
  { key: 'groups', label: 'المجموعات', icon: 'person.2.fill' },
  { key: 'logs', label: 'السجل', icon: 'clock.fill' },
  { key: 'creative', label: 'مساحة إبداع', icon: 'paintpalette.fill' },
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
    <View accessibilityRole="tablist" style={styles.container}>
      {TABS.map((tab) => {
        const isActive = tab.key === active;
        const badge = badgeFor(tab.key);

        return (
          <Pressable
            key={tab.key}
            accessibilityRole="tab"
            accessibilityLabel={tab.label}
            accessibilityState={{ selected: isActive }}
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
            <View style={styles.tabIconRow}>
              <IconSymbol
                name={tab.icon}
                size={16}
                color={isActive ? '#FFFFFF' : isDark ? '#94A3B8' : colors.primary}
              />
              {typeof badge === 'number' && badge > 0 ? (
                <View style={[styles.badgePill, { backgroundColor: isActive ? '#FFFFFF40' : `${colors.primary}20` }]}>
                  <ThemedText style={[styles.badgeText, { color: isActive ? '#FFFFFF' : colors.primary }]}>{badge}</ThemedText>
                </View>
              ) : null}
            </View>
            <ThemedText
              numberOfLines={2}
              style={[
                styles.tabLabel,
                {
                  color: isActive ? '#FFFFFF' : isDark ? '#F1F5F9' : '#334155',
                  fontFamily: isActive ? 'Tajawal-Bold' : 'Tajawal-Medium',
                },
              ]}>
              {tab.label}
            </ThemedText>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    direction: 'rtl',
    flexDirection: 'row',
    gap: 6,
    marginVertical: 6,
  },
  tabPill: {
    flex: 1,
    minWidth: 0,
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    paddingHorizontal: 3,
    paddingVertical: 6,
    borderRadius: 14,
    borderWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 4,
    elevation: 1,
  },
  tabLabel: {
    fontSize: 12,
    lineHeight: 16,
    textAlign: 'center',
    writingDirection: 'rtl',
  },
  tabIconRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  badgePill: {
    paddingHorizontal: 4,
    borderRadius: 10,
    minWidth: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    fontSize: 10,
    lineHeight: 14,
    fontFamily: 'Tajawal-Bold',
  },
});
