import { Tabs } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { HapticTab } from '@/components/haptic-tab';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { FontAwesome } from '@expo/vector-icons';

/** الكيبورد على Android edge-to-edge يدخل ضمن insets.bottom — لا تستخدمه لارتفاع شريط التبويب */
const MAX_NAV_BOTTOM_INSET = 80;

function useStableTabBarBottom() {
  const insets = useSafeAreaInsets();
  const [stable, setStable] = useState(Math.min(insets.bottom, MAX_NAV_BOTTOM_INSET));

  useEffect(() => {
    if (insets.bottom <= MAX_NAV_BOTTOM_INSET) {
      setStable(insets.bottom);
    }
  }, [insets.bottom]);

  return stable;
}

export default function TabLayout() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const bottomInset = useStableTabBarBottom();

  return (
    <Tabs
      initialRouteName="index"
      screenOptions={{
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.tabIconDefault,
        headerShown: false,
        tabBarHideOnKeyboard: true,
        tabBarButton: HapticTab,
        tabBarStyle: {
          backgroundColor: colors.background,
          borderTopColor: colors.border,
          borderTopWidth: 1,
          height: 56 + bottomInset,
          paddingBottom: bottomInset > 0 ? bottomInset : 6,
          paddingTop: 6,
        },
        tabBarLabelStyle: {
          fontFamily: 'Tajawal-Medium',
          fontSize: 10,
          flexShrink: 1,
        },
        tabBarItemStyle: {
          minWidth: 0,
          paddingHorizontal: 2,
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'الرئيسية',
          tabBarIcon: ({ color }) => <IconSymbol size={22} name="house.fill" color={color} />,
        }}
      />
      <Tabs.Screen
        name="gallery"
        options={{
          title: 'المعرض',
          tabBarIcon: ({ color }) => <IconSymbol size={22} name="photo.fill" color={color} />,
        }}
      />
      <Tabs.Screen
        name="sila"
        options={{
          title: 'صلة',
          tabBarIcon: ({ color }) => <IconSymbol size={22} name="person.2.fill" color={color} />,
        }}
      />
      <Tabs.Screen
        name="ai"
        options={{
          title: 'الذكاء',
          tabBarIcon: ({ color }) => <IconSymbol size={22} name="sparkles" color={color} />,
        }}
      />
      <Tabs.Screen
        name="walking"
        options={{
          title: 'المشي',
          tabBarIcon: ({ color }) => <FontAwesome size={22} name="heartbeat" color={color} />,
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'المزيد',
          tabBarIcon: ({ color }) => <FontAwesome size={22} name="bars" color={color} />,
        }}
      />
    </Tabs>
  );
}
