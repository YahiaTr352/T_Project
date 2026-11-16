/**
 * تصميم موحّد للتطبيق: ألوان الوضعين + رموز شاشة السِّلة/شجرة العائلة.
 */

import { Platform } from 'react-native';

/** رموز شاشة السِّلة (شجرة العائلة) — تُستورد كـ `silaTheme as T` حيث يلزم */
export const silaTheme = {
  primary: '#37a05c',
  primaryLight: '#4ade80',
  primaryDark: '#166534',
  darkGreen: '#145147',
  lightGreen: '#DCF2DD',
  mourning: '#1b1b1b',
  pink: '#ec4899',
  pinkDark: '#be185d',
  gold: '#d97706',
  goldLight: '#f59e0b',
  white: '#ffffff',
  offWhite: '#f8fafa',
  border: '#e2ebe7',
  textMuted: '#7a9490',
  textDark: '#1a2e2a',
  cardRadius: 20,
  cardW: 130,
  shadow: {
    shadowColor: '#145147',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 8,
  },
  shadowStrong: {
    shadowColor: '#145147',
    shadowOffset: { width: 0, height: 16 },
    shadowOpacity: 0.2,
    shadowRadius: 28,
    elevation: 16,
  },
} as const;

const tintColorLight = silaTheme.primary;
/** لون التبويب المحدد في الليلي — فاتح ليظهر على شريط داكن، منفصل عن primary */
const tintColorDark = '#E5E7EB';

export interface ThemeScheme {
  text: string;
  background: string;
  tint: string;
  icon: string;
  tabIconDefault: string;
  tabIconSelected: string;
  primary: string;
  card: string;
  border: string;
}

const lightTheme: ThemeScheme = {
  text: '#111827',
  background: '#FFFFFF',
  tint: tintColorLight,
  icon: '#687076',
  tabIconDefault: '#687076',
  tabIconSelected: tintColorLight,
  primary: tintColorLight,
  card: '#FFFFFF',
  border: '#E5E7EB',
};

const darkTheme: ThemeScheme = {
  text: '#FFFFFF',
  background: '#000000',
  tint: tintColorDark,
  icon: '#9BA1A6',
  tabIconDefault: '#9BA1A6',
  tabIconSelected: tintColorDark,
  primary: silaTheme.primary,
  card: '#1A1A1A',
  border: '#333333',
};

export const Colors: Record<string, ThemeScheme> & {
  light: ThemeScheme;
  dark: ThemeScheme;
} = {
  light: lightTheme,
  dark: darkTheme,
  unspecified: lightTheme,
};

export type ThemeColors = ThemeScheme;

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: 'system-ui',
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: 'ui-serif',
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: 'ui-rounded',
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    rounded: "'SF Pro Rounded', 'Hiragino Maru Gothic ProN', Meiryo, 'MS PGothic', sans-serif",
    mono: "SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace",
  },
});
