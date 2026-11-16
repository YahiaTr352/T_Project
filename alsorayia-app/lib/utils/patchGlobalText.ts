import React from 'react';
import * as ReactNative from 'react-native';
import { MAX_FONT_SIZE_MULTIPLIER, scaleFont, scaleLineHeight } from './responsive';

/**
 * الرمز الداخلي لتفادي التحجيم المزدوج بين ThemedText و PatchedText
 */
export const SCALED_FLAG = '__alsorayia_scaled__';

let isInitialized = false;

/**
 * تهيئة عامة على مستوى الـ Runtime لـ React Native Text & TextInput:
 * 1. فرض سقف Dynamic Type (maxFontSizeMultiplier = 1.15) لمنع انفجار النصوص عند تكبير خط النظام في iOS/Android.
 * 2. تطبيق التحجيم المتجاوب (scaleFont) على أي fontSize ممرر صراحة في الـ style لأي مكون Text عادي لم يُستخدم معه ThemedText.
 */
export function initGlobalTextScaling(): void {
  if (isInitialized) return;
  isInitialized = true;

  // تجاوز بيئة الويب وبيئة Node SSR أثناء التصدير الثابت (Static Export / EAS Update)
  if (ReactNative.Platform.OS === 'web' || typeof window === 'undefined') {
    return;
  }

  const RN = ReactNative as any;

  // ==================== 1. اعتراض مكون Text ====================
  try {
    const OriginalText = RN.Text;
    if (OriginalText) {
      const PatchedText = React.forwardRef<any, ReactNative.TextProps>((props, ref) => {
        const {
          style,
          maxFontSizeMultiplier = MAX_FONT_SIZE_MULTIPLIER,
          allowFontScaling = true,
          ...rest
        } = props;

        let processedStyle = style;
        if (style) {
          const flat = ReactNative.StyleSheet.flatten(style) as any;
          if (flat && typeof flat.fontSize === 'number' && !flat[SCALED_FLAG]) {
            const scaledSize = scaleFont(flat.fontSize);
            const scaledHeight = typeof flat.lineHeight === 'number'
              ? scaleFont(flat.lineHeight)
              : scaleLineHeight(scaledSize);

            processedStyle = [
              style,
              {
                fontSize: scaledSize,
                lineHeight: scaledHeight,
                [SCALED_FLAG]: true,
              } as any,
            ];
          }
        }

        return React.createElement(OriginalText, {
          ...rest,
          ref,
          style: processedStyle,
          allowFontScaling,
          maxFontSizeMultiplier,
        });
      });

      PatchedText.displayName = 'PatchedText';

      Object.assign(PatchedText, OriginalText);

      try {
        Object.defineProperty(RN, 'Text', {
          configurable: true,
          enumerable: true,
          get: () => PatchedText,
        });
      } catch {
        // إذا كان الكائن محمياً أو غير قابل لإعادة التعريف، يتم التجاوز بأمان
      }
    }
  } catch {
    // حماية إضافية ضد أي بيئة تمنع الوصول لكائن Text
  }

  // ==================== 2. اعتراض مكون TextInput ====================
  try {
    const OriginalTextInput = RN.TextInput;
    if (OriginalTextInput) {
      const PatchedTextInput = React.forwardRef<any, ReactNative.TextInputProps>((props, ref) => {
        const {
          style,
          maxFontSizeMultiplier = MAX_FONT_SIZE_MULTIPLIER,
          allowFontScaling = true,
          ...rest
        } = props;

        let processedStyle = style;
        if (style) {
          const flat = ReactNative.StyleSheet.flatten(style) as any;
          if (flat && typeof flat.fontSize === 'number' && !flat[SCALED_FLAG]) {
            const scaledSize = scaleFont(flat.fontSize);
            processedStyle = [
              style,
              {
                fontSize: scaledSize,
                [SCALED_FLAG]: true,
              } as any,
            ];
          }
        }

        return React.createElement(OriginalTextInput, {
          ...rest,
          ref,
          style: processedStyle,
          allowFontScaling,
          maxFontSizeMultiplier,
        });
      });

      PatchedTextInput.displayName = 'PatchedTextInput';

      Object.assign(PatchedTextInput, OriginalTextInput);

      try {
        Object.defineProperty(RN, 'TextInput', {
          configurable: true,
          enumerable: true,
          get: () => PatchedTextInput,
        });
      } catch {
        // إذا كان الكائن محمياً أو غير قابل لإعادة التعريف، يتم التجاوز بأمان
      }
    }
  } catch {
    // حماية إضافية ضد أي بيئة تمنع الوصول لكائن TextInput
  }
}

// تنفيذ التهيئة فور استيراد الملف تلقائياً
initGlobalTextScaling();
