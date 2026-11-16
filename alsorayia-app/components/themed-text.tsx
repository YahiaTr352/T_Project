import { StyleSheet, Text, type TextProps, type TextStyle } from 'react-native';
import React from 'react';

import { useThemeColor } from '@/hooks/use-theme-color';
import { MAX_FONT_SIZE_MULTIPLIER, scaleFont, scaleLineHeight } from '@/lib/utils/responsive';
import { SCALED_FLAG } from '@/lib/utils/patchGlobalText';

export type ThemedTextProps = TextProps & {
  lightColor?: string;
  darkColor?: string;
  type?: 'default' | 'title' | 'defaultSemiBold' | 'subtitle' | 'link';
  align?: 'auto' | 'left' | 'right' | 'center' | 'justify';
};

/**
 * نطاق اليونيكود الخاص بالأحرف العربية (الأساسية، الممتدة، التشكيل، والعرض)
 */
const ARABIC_CHAR_REGEX = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;

/**
 * نطاق الأحرف اللاتينية/الإنجليزية
 */
const LATIN_CHAR_REGEX = /[A-Za-z]/;

/**
 * استخراج عينة نصية من المحتوى لاكتشاف لغة النص برمجياً بدقة وأداء فائق
 */
function extractSampleText(children: React.ReactNode): string {
  if (typeof children === 'string') return children;
  if (typeof children === 'number') return children.toString();
  if (Array.isArray(children)) {
    for (const child of children) {
      const sample = extractSampleText(child);
      if (sample) return sample;
    }
  }
  return '';
}

/**
 * تحديد محاذاة واتجاه النص بذكاء بناءً على لغة المحتوى والتنسيقات الممررة
 */
function resolveTextDirectionAndAlignment(
  children: React.ReactNode,
  alignProp?: 'auto' | 'left' | 'right' | 'center' | 'justify',
  styleTextAlign?: TextStyle['textAlign'],
  styleWritingDirection?: TextStyle['writingDirection'],
): { textAlign: TextStyle['textAlign']; writingDirection: TextStyle['writingDirection'] } {
  // 1. الأولوية التامة للمحاذاة المحددة صراحة (عبر prop أو عبر style)
  const explicitAlign = alignProp && alignProp !== 'auto' ? alignProp : styleTextAlign;

  if (explicitAlign === 'center' || explicitAlign === 'justify') {
    return {
      textAlign: explicitAlign,
      writingDirection: styleWritingDirection ?? 'rtl',
    };
  }

  if (explicitAlign) {
    return {
      textAlign: explicitAlign,
      writingDirection: styleWritingDirection ?? (explicitAlign === 'left' ? 'ltr' : 'rtl'),
    };
  }

  // 2. فحص المحتوى النصي ذكياً (Smart BiDi Detection)
  const sample = extractSampleText(children);
  const hasArabic = ARABIC_CHAR_REGEX.test(sample);
  const hasLatin = LATIN_CHAR_REGEX.test(sample);

  // إذا كان المحتوى إنجليزياً/لاتينياً خالصاً (مثل الإيميلات والروابط) بدون أي حرف عربي
  if (hasLatin && !hasArabic) {
    return {
      textAlign: 'left',
      writingDirection: styleWritingDirection ?? 'ltr',
    };
  }

  // 3. القاعدة الافتراضية لتطبيق السريّع (عربي من اليمين RTL)
  return {
    // Keep 'auto' (undefined): under forced RTL, RN swaps an explicit 'right' to the visual left.
    textAlign: explicitAlign,
    writingDirection: styleWritingDirection ?? 'rtl',
  };
}

export function ThemedText({
  style,
  lightColor,
  darkColor,
  type = 'default',
  align,
  children,
  allowFontScaling = true,
  maxFontSizeMultiplier = MAX_FONT_SIZE_MULTIPLIER,
  ...rest
}: ThemedTextProps) {
  const color = useThemeColor({ light: lightColor, dark: darkColor }, 'text');
  const flattenedStyle = StyleSheet.flatten(style);

  const { textAlign, writingDirection } = resolveTextDirectionAndAlignment(
    children,
    align,
    flattenedStyle?.textAlign,
    flattenedStyle?.writingDirection,
  );

  // تحجيم ذكي وتلقائي لأي مقاس مخصص يُمرر عبر style في أي مكان بالتطبيق
  let dynamicStyleOverride: TextStyle | undefined;
  if (flattenedStyle?.fontSize && typeof flattenedStyle.fontSize === 'number') {
    const responsiveSize = scaleFont(flattenedStyle.fontSize);
    const responsiveLineHeight = typeof flattenedStyle.lineHeight === 'number'
      ? scaleFont(flattenedStyle.lineHeight)
      : scaleLineHeight(responsiveSize);

    dynamicStyleOverride = {
      fontSize: responsiveSize,
      lineHeight: responsiveLineHeight,
      [SCALED_FLAG]: true,
    } as any;
  }

  return (
    <Text
      style={[
        {
          color,
          textAlign,
          writingDirection,
        },
        type === 'default' ? styles.default : undefined,
        type === 'title' ? styles.title : undefined,
        type === 'defaultSemiBold' ? styles.defaultSemiBold : undefined,
        type === 'subtitle' ? styles.subtitle : undefined,
        type === 'link' ? styles.link : undefined,
        style,
        dynamicStyleOverride,
      ]}
      allowFontScaling={allowFontScaling}
      maxFontSizeMultiplier={maxFontSizeMultiplier}
      {...rest}>
      {children}
    </Text>
  );
}

const styles = StyleSheet.create({
  default: {
    fontSize: scaleFont(16),
    // العربية (ث، ش، أ) تحتاج مساحة علوية: مقاسات Tajawal على iOS تقصّ النقاط مع lineHeight ضيق
    lineHeight: scaleLineHeight(scaleFont(16), 27),
    fontFamily: 'Tajawal-Regular',
  },
  defaultSemiBold: {
    fontSize: scaleFont(16),
    lineHeight: scaleLineHeight(scaleFont(16), 27),
    fontFamily: 'Tajawal-Medium',
  },
  title: {
    fontSize: scaleFont(30),
    fontFamily: 'Tajawal-Bold',
    lineHeight: scaleLineHeight(scaleFont(30), 45),
  },
  subtitle: {
    fontSize: scaleFont(20),
    fontFamily: 'Tajawal-Bold',
    lineHeight: scaleLineHeight(scaleFont(20), 31),
  },
  link: {
    lineHeight: scaleLineHeight(scaleFont(16), 28),
    fontSize: scaleFont(16),
    color: '#0a7ea4',
  },
});

