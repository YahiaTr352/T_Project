import { ThemedText } from '@/components/themed-text';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import React from 'react';
import {
  ActivityIndicator,
  Image,
  type ImageSourcePropType,
  Pressable,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { cardShadowStyle, type InvitationPalette } from './invitationTheme';

interface SectionTitleProps {
  title: string;
  palette: InvitationPalette;
  subtitle?: string;
  rightBadge?: React.ReactNode;
  style?: StyleProp<ViewStyle>;
}

/** عنوان قسم بنقطة خضراء مميزة ومحتوى جانبي اختياري */
export function InvitationSectionTitle({
  title,
  palette,
  subtitle,
  rightBadge,
  style,
}: SectionTitleProps) {
  return (
    <View style={[styles.sectionHeader, style]}>
      <View style={styles.sectionTitleGroup}>
        <View style={[styles.sectionDot, { backgroundColor: palette.primary }]} />
        <ThemedText style={[styles.sectionTitle, { color: palette.text }]}>{title}</ThemedText>
      </View>
      {rightBadge ? (
        rightBadge
      ) : subtitle ? (
        <ThemedText style={[styles.sectionSub, { color: palette.muted }]}>{subtitle}</ThemedText>
      ) : null}
    </View>
  );
}

interface HeroBannerProps {
  title: string;
  subtitle: string;
  image: ImageSourcePropType;
  palette: InvitationPalette;
  style?: StyleProp<ViewStyle>;
}

/** بانر تعريفي علوي مطابق لهوية صفحات المجموعات والسجل ومساحة الإبداع */
export function InvitationHeroBanner({
  title,
  subtitle,
  image,
  palette,
  style,
}: HeroBannerProps) {
  return (
    <View style={[styles.heroWrap, cardShadowStyle(palette), { borderColor: palette.border }, style]}>
      <Image source={image} style={StyleSheet.absoluteFill} resizeMode="cover" />
      <LinearGradient
        colors={palette.heroGradient}
        start={{ x: 1, y: 0.5 }}
        end={{ x: 0, y: 0.5 }}
        style={styles.heroOverlay}>
        <Text style={styles.heroTitle}>{title}</Text>
        <Text style={styles.heroSub}>{subtitle}</Text>
      </LinearGradient>
    </View>
  );
}

interface GradientButtonProps {
  label: string;
  onPress: () => void;
  palette: InvitationPalette;
  icon?: keyof typeof Ionicons.glyphMap;
  disabled?: boolean;
  loading?: boolean;
  accessibilityLabel?: string;
  style?: StyleProp<ViewStyle>;
}

/** زر رئيسي عريض متدرج الأخضر الزمردي */
export function InvitationGradientButton({
  label,
  onPress,
  palette,
  icon,
  disabled = false,
  loading = false,
  accessibilityLabel,
  style,
}: GradientButtonProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled, busy: loading }}
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.gradBtnWrap,
        cardShadowStyle(palette),
        { opacity: disabled ? 0.55 : pressed ? 0.88 : 1 },
        style,
      ]}>
      <LinearGradient
        colors={palette.primaryGradient}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.gradBtnInner}>
        {loading ? (
          <ActivityIndicator color="#FFFFFF" />
        ) : (
          <>
            {icon ? <Ionicons name={icon} size={19} color="#FFFFFF" /> : null}
            <Text style={styles.gradBtnText}>{label}</Text>
          </>
        )}
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  sectionHeader: {
    direction: 'rtl',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 6,
    marginBottom: 10,
    gap: 8,
  },
  sectionTitleGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  sectionDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    lineHeight: 24,
  },
  sectionSub: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
  },
  heroWrap: {
    height: 108,
    borderRadius: 20,
    overflow: 'hidden',
    borderWidth: 1,
    marginBottom: 14,
  },
  heroOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'flex-start',
    paddingHorizontal: 18,
    paddingVertical: 14,
    direction: 'rtl',
  },
  heroTitle: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    lineHeight: 28,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  heroSub: {
    color: 'rgba(236, 253, 245, 0.9)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    lineHeight: 20,
    marginTop: 2,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  gradBtnWrap: {
    borderRadius: 16,
    overflow: 'hidden',
  },
  gradBtnInner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 50,
    paddingHorizontal: 18,
    paddingVertical: 13,
  },
  gradBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
  },
});
