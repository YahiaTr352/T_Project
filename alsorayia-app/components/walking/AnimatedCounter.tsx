import React, { useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, type TextStyle, type StyleProp } from 'react-native';
import { formatSteps } from '@/lib/walking/formatters';

interface AnimatedCounterProps {
  value: number;
  duration?: number;
  style?: StyleProp<TextStyle>;
  formatter?: (val: number) => string;
}

/**
 * AnimatedCounter: عداد رقمي مرن وسلس (Fluid Spring / EaseOut)
 * يضمن أداء 60/120fps بدون تهنيج أو تسريب للذاكرة، مع تنسيق الأرقام العربية تلقائياً.
 */
export function AnimatedCounter({
  value,
  duration = 600,
  style,
  formatter = formatSteps,
}: AnimatedCounterProps) {
  const [displayValue, setDisplayValue] = useState<number>(value);
  const startValRef = useRef<number>(value);
  const targetValRef = useRef<number>(value);
  const startTimeRef = useRef<number | null>(null);
  const rafIdRef = useRef<number | null>(null);

  useEffect(() => {
    // إذا لم تتغير القيمة، لا داعي لإطلاق الحركة
    if (value === targetValRef.current && displayValue === value) return;

    startValRef.current = displayValue;
    targetValRef.current = value;
    startTimeRef.current = null;

    if (rafIdRef.current != null) {
      cancelAnimationFrame(rafIdRef.current);
      rafIdRef.current = null;
    }

    // إذا كان الفرق طفيفاً جداً أو الرسوم المتحركة غير مطلوبة، حدّث فوراً
    if (Math.abs(value - startValRef.current) === 0) {
      setDisplayValue(value);
      return;
    }

    const animate = (timestamp: number) => {
      if (startTimeRef.current === null) {
        startTimeRef.current = timestamp;
      }

      const elapsed = timestamp - startTimeRef.current;
      const progress = Math.min(1, elapsed / duration);

      // دالة تسارع مرنة (Cubic Ease-Out)
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(startValRef.current + (targetValRef.current - startValRef.current) * easeOut);

      setDisplayValue(current);

      if (progress < 1) {
        rafIdRef.current = requestAnimationFrame(animate);
      } else {
        setDisplayValue(targetValRef.current);
        rafIdRef.current = null;
      }
    };

    rafIdRef.current = requestAnimationFrame(animate);

    return () => {
      if (rafIdRef.current != null) {
        cancelAnimationFrame(rafIdRef.current);
        rafIdRef.current = null;
      }
    };
  }, [value, duration]);

  return (
    <Text style={[styles.defaultText, style]}>
      {formatter(displayValue)}
    </Text>
  );
}

const styles = StyleSheet.create({
  defaultText: {
    fontFamily: 'Tajawal-Bold',
    includeFontPadding: false,
    textAlignVertical: 'center',
  },
});
