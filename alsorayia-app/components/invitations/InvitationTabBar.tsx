import { ThemedText } from '@/components/themed-text';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, StyleSheet, View } from 'react-native';
import { cardShadowStyle, getInvitationPalette } from './invitationTheme';

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
  const isDark = useColorScheme() === 'dark';
  const palette = getInvitationPalette(isDark);

  const badgeFor = (key: InvitationTabKey) => {
    if (key === 'groups') return groupsCount;
    if (key === 'logs') return logsCount;
    return undefined;
  };

  return (
    <View
      accessibilityRole="tablist"
      style={[
        styles.container,
        cardShadowStyle(palette),
        {
          backgroundColor: palette.tabContainerBg,
          borderColor: palette.border,
        },
      ]}>
      {TABS.map((tab) => {
        const isActive = tab.key === active;
        const badge = badgeFor(tab.key);

        const content = (
          <>
            <View style={styles.tabIconRow}>
              {typeof badge === 'number' && badge > 0 ? (
                <View
                  style={[
                    styles.badgePill,
                    {
                      backgroundColor: isActive ? 'rgba(255,255,255,0.24)' : palette.pillBg,
                    },
                  ]}>
                  <ThemedText
                    style={[
                      styles.badgeText,
                      { color: isActive ? '#FFFFFF' : palette.pillText },
                    ]}>
                    {badge}
                  </ThemedText>
                </View>
              ) : null}
              <IconSymbol
                name={tab.icon}
                size={17}
                color={isActive ? '#FFFFFF' : palette.textSecondary}
              />
            </View>
            <ThemedText
              numberOfLines={1}
              style={[
                styles.tabLabel,
                {
                  color: isActive ? '#FFFFFF' : palette.textSecondary,
                  fontFamily: isActive ? 'Tajawal-Bold' : 'Tajawal-Medium',
                },
              ]}>
              {tab.label}
            </ThemedText>
          </>
        );

        return (
          <Pressable
            key={tab.key}
            accessibilityRole="tab"
            accessibilityLabel={tab.label}
            accessibilityState={{ selected: isActive }}
            onPress={() => onChange(tab.key)}
            style={({ pressed }) => [
              styles.tabPill,
              { opacity: pressed ? 0.85 : 1 },
            ]}>
            {isActive ? (
              <LinearGradient
                colors={palette.primaryGradient}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={styles.tabInnerActive}>
                {content}
              </LinearGradient>
            ) : (
              <View style={styles.tabInnerInactive}>{content}</View>
            )}
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
    gap: 4,
    padding: 5,
    borderRadius: 20,
    borderWidth: 1,
    marginTop: 2,
    marginBottom: 12,
  },
  tabPill: {
    flex: 1,
    minWidth: 0,
    borderRadius: 15,
    overflow: 'hidden',
  },
  tabInnerActive: {
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    paddingHorizontal: 4,
    paddingVertical: 6,
    borderRadius: 15,
  },
  tabInnerInactive: {
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    paddingHorizontal: 4,
    paddingVertical: 6,
    borderRadius: 15,
  },
  tabLabel: {
    fontSize: 11.5,
    lineHeight: 16,
    textAlign: 'center',
    writingDirection: 'rtl',
  },
  tabIconRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  badgePill: {
    paddingHorizontal: 6,
    height: 18,
    borderRadius: 9,
    minWidth: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    fontSize: 10.5,
    lineHeight: 14,
    fontFamily: 'Tajawal-Bold',
  },
});
