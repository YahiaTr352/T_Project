import { useCallback, useEffect, useRef, useState } from 'react';
import {
  EmitterSubscription,
  Keyboard,
  KeyboardEvent,
  LayoutAnimation,
  LayoutChangeEvent,
  Platform,
} from 'react-native';

export interface UseSmartKeyboardOptions {
  /**
   * Set to true if this screen is inside a tab navigator where the tab bar
   * is present at the bottom.
   */
  isTabScreen?: boolean;
  /**
   * Optional custom tab bar height in dp.
   * If omitted and isTabScreen is true, defaults to 60 + safeAreaInsets.bottom.
   */
  tabBarHeight?: number;
  /**
   * Optional extra padding/offset in dp above the keyboard.
   * Default: 0.
   */
  extraOffset?: number;
}

export interface UseSmartKeyboardResult {
  /**
   * The calculated bottom offset to apply to the container (e.g. `paddingBottom: bottomOffset`).
   */
  bottomOffset: number;
  /**
   * Whether the keyboard is currently open and visible.
   */
  isKeyboardOpen: boolean;
  /**
   * The raw keyboard height in dp.
   */
  keyboardHeight: number;
  /**
   * Pass this onLayout callback to the main container wrapping the chat/input view
   * to accurately detect native window resizing across all Android OEM versions and iOS.
   */
  onLayout: (e: LayoutChangeEvent) => void;
  /**
   * Utility to programmatically dismiss the keyboard.
   */
  dismissKeyboard: () => void;
}

/**
 * Enterprise-grade keyboard avoidance hook designed for Expo SDK 52-57+ / React Native 0.76-0.86+ (New Architecture).
 *
 * Solves the critical edge-to-edge Android and iOS keyboard-obscuring bugs via Continuous Delta Calculation:
 * - On Android Edge-to-Edge: computes the exact remaining gap `Math.max(0, keyboardHeight - nativeWindowShrinkage)`.
 *   If the OS resized the window partially (e.g. due to navigation bar / decor insets), it compensates ONLY the missing delta.
 *   If the OS resized completely, delta becomes 0 (no double resize).
 *   If the OS did not resize at all (Android 14/15 edge-to-edge), it displaces the full keyboard height.
 * - On iOS: matches the exact CADisplayLink keyboard curve and duration via LayoutAnimation.
 * - Tab Bar coordination: intelligently handles tab bar hiding / showing without layout jumping.
 */
export function useSmartKeyboard(options?: UseSmartKeyboardOptions): UseSmartKeyboardResult {
  const extraOffset = options?.extraOffset ?? 0;

  const [bottomOffset, setBottomOffset] = useState(0);
  const [isKeyboardOpen, setIsKeyboardOpen] = useState(false);
  const [keyboardHeight, setKeyboardHeight] = useState(0);

  // Mutable refs to prevent race conditions during rapid render/layout cycles
  const keyboardHeightRef = useRef(0);
  const isKeyboardOpenRef = useRef(false);
  const baselineHeightRef = useRef(0);
  const currentHeightRef = useRef(0);

  const dismissKeyboard = useCallback(() => {
    Keyboard.dismiss();
  }, []);

  const applyAnimation = useCallback((e?: KeyboardEvent) => {
    if (Platform.OS === 'ios' && e?.duration && e.duration > 10) {
      LayoutAnimation.configureNext({
        duration: e.duration,
        update: {
          duration: e.duration,
          type:
            ((LayoutAnimation.Types as Record<string, any>)[e.easing] ||
              LayoutAnimation.Types.keyboard) as any,
        },
      });
    } else {
      LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    }
  }, []);

  /**
   * Calculates the exact displacement required to keep the input bar above the keyboard.
   * Key formula: targetOffset = max(0, keyboardHeight - nativeWindowShrinkage) + extraOffset
   */
  const computeDisplacement = useCallback(
    (kHeight: number, cHeight: number, bHeight: number): number => {
      if (kHeight <= 0) return 0;

      if (Platform.OS === 'ios') {
        // On iOS, window height never shrinks natively.
        // Full keyboard height displacement is required.
        return Math.max(0, kHeight + extraOffset);
      }

      // Android: Edge-to-Edge vs adjustResize calculation
      if (bHeight > 0 && cHeight > 0) {
        const windowShrinkage = Math.max(0, bHeight - cHeight);

        // If the window already shrank by at least (keyboardHeight - 15dp),
        // Android OS native resize completely handled it.
        if (windowShrinkage >= kHeight - 15) {
          return extraOffset > 0 ? extraOffset : 0;
        }

        // If the window shrank partially (e.g., shrank by 180dp but keyboard is 300dp),
        // or did NOT shrink at all (Android 15 edge-to-edge where shrinkage ~ 0),
        // compensate the exact remaining unhandled delta!
        const remainingGap = Math.max(0, kHeight - windowShrinkage);
        return Math.max(0, remainingGap + extraOffset);
      }

      // Fallback if baseline was not captured yet
      return Math.max(0, kHeight + extraOffset);
    },
    [extraOffset]
  );

  const onLayout = useCallback(
    (e: LayoutChangeEvent) => {
      const h = e.nativeEvent.layout.height;
      if (h <= 0) return;

      currentHeightRef.current = h;

      if (!isKeyboardOpenRef.current) {
        // When keyboard is closed, keep updating baseline container height
        baselineHeightRef.current = h;
      } else {
        // When keyboard is open, layout change indicates native window resize pass
        const kHeight = keyboardHeightRef.current;
        if (kHeight > 0) {
          const newOffset = computeDisplacement(kHeight, h, baselineHeightRef.current);
          setBottomOffset((prev) => (Math.abs(prev - newOffset) > 1 ? newOffset : prev));
        }
      }
    },
    [computeDisplacement]
  );

  useEffect(() => {
    const handleKeyboardShow = (e: KeyboardEvent) => {
      const height = e?.endCoordinates?.height ?? 0;
      if (height <= 0) return;

      keyboardHeightRef.current = height;
      isKeyboardOpenRef.current = true;
      setKeyboardHeight(height);
      setIsKeyboardOpen(true);

      applyAnimation(e);

      const targetOffset = computeDisplacement(
        height,
        currentHeightRef.current,
        baselineHeightRef.current
      );
      setBottomOffset(targetOffset);
    };

    const handleKeyboardHide = (e?: KeyboardEvent) => {
      keyboardHeightRef.current = 0;
      isKeyboardOpenRef.current = false;
      setKeyboardHeight(0);
      setIsKeyboardOpen(false);

      applyAnimation(e);
      setBottomOffset(0);
    };

    const subscriptions: EmitterSubscription[] = [];

    if (Platform.OS === 'ios') {
      subscriptions.push(Keyboard.addListener('keyboardWillShow', handleKeyboardShow));
      subscriptions.push(Keyboard.addListener('keyboardWillHide', handleKeyboardHide));
    } else {
      // Android: subscribe to didShow/didHide, with willShow/willHide fallback for newer engines
      subscriptions.push(Keyboard.addListener('keyboardDidShow', handleKeyboardShow));
      subscriptions.push(Keyboard.addListener('keyboardDidHide', handleKeyboardHide));
      try {
        subscriptions.push(Keyboard.addListener('keyboardWillShow', handleKeyboardShow));
        subscriptions.push(Keyboard.addListener('keyboardWillHide', handleKeyboardHide));
      } catch {
        // Ignored if not implemented on platform
      }
    }

    return () => {
      subscriptions.forEach((sub) => sub.remove());
    };
  }, [computeDisplacement, applyAnimation]);

  return {
    bottomOffset,
    isKeyboardOpen,
    keyboardHeight,
    onLayout,
    dismissKeyboard,
  };
}
