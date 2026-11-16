import { ThemedText } from '@/components/themed-text';
import type { ThemeColors } from '@/constants/theme';
import type { HomeDynamicSectionItem } from '@/lib/api/homePage';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import React, { useEffect, useMemo, useState } from 'react';
import { StyleSheet, TouchableOpacity, View } from 'react-native';

interface CountdownSectionItemProps {
  item: HomeDynamicSectionItem;
  colors: ThemeColors;
  contentWidth: number;
}

interface TimeRemaining {
  days: string;
  hours: string;
  minutes: string;
  seconds: string;
  isExpired: boolean;
}

function padZero(num: number): string {
  const val = Math.max(0, Math.floor(num));
  return val < 10 ? `0${val}` : `${val}`;
}

function calculateTimeLeft(targetDateStr?: string | null): TimeRemaining {
  if (!targetDateStr) {
    return { days: '00', hours: '00', minutes: '00', seconds: '00', isExpired: false };
  }

  const targetTime = new Date(targetDateStr).getTime();
  if (isNaN(targetTime)) {
    return { days: '00', hours: '00', minutes: '00', seconds: '00', isExpired: false };
  }

  const now = Date.now();
  const diff = targetTime - now;

  if (diff <= 0) {
    return { days: '00', hours: '00', minutes: '00', seconds: '00', isExpired: true };
  }

  const totalSeconds = Math.floor(diff / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return {
    days: padZero(days),
    hours: padZero(hours),
    minutes: padZero(minutes),
    seconds: padZero(seconds),
    isExpired: false,
  };
}

export function CountdownSectionItem({ item, colors, contentWidth }: CountdownSectionItemProps) {
  const content = typeof item.content === 'object' && item.content !== null ? item.content : {};
  const settings = typeof item.settings === 'object' && item.settings !== null ? item.settings : {};

  const targetDate = content.target_date;
  const title = content.title;
  const description = content.description;
  const badgeText = content.badge_text;
  const buttonText = content.button_text;
  const buttonUrl = item.button_url_absolute || content.button_url;
  const expiredMessage = content.expired_message || 'بدأت الفعالية!';
  const imageUrl = item.image_url;

  const layoutVariant = settings.layout_variant || 'overlay';
  const themeStyle = settings.theme_style || 'glass';
  const showDays = settings.show_days !== false;
  const showHours = settings.show_hours !== false;
  const showMinutes = settings.show_minutes !== false;
  const showSeconds = settings.show_seconds !== false;
  const autoHide = settings.auto_hide_on_expire === true;

  const [time, setTime] = useState<TimeRemaining>(() => calculateTimeLeft(targetDate));

  useEffect(() => {
    if (!targetDate) return;

    // Initial sync
    setTime(calculateTimeLeft(targetDate));

    const timer = setInterval(() => {
      const remaining = calculateTimeLeft(targetDate);
      setTime(remaining);
      if (remaining.isExpired) {
        clearInterval(timer);
      }
    }, 1000);

    return () => clearInterval(timer);
  }, [targetDate]);

  const handlePressButton = () => {
    if (buttonUrl) {
      WebBrowser.openBrowserAsync(buttonUrl).catch(() => {});
    }
  };

  // If auto-hide is turned on and countdown expired, render nothing
  if (autoHide && time.isExpired) {
    return null;
  }

  const isOverlay = layoutVariant === 'overlay';
  const isMinimal = layoutVariant === 'minimal';
  const isSideBySide = layoutVariant === 'side_by_side';

  // Unit box styling depending on themeStyle
  const unitBoxStyle = useMemo(() => {
    if (themeStyle === 'solid') {
      return {
        backgroundColor: '#0f172a',
        borderColor: '#334155',
        borderWidth: 1,
      };
    }
    if (themeStyle === 'gradient') {
      return {
        backgroundColor: colors.primary,
        borderColor: 'rgba(255,255,255,0.2)',
        borderWidth: 1,
      };
    }
    // Default: glass
    return {
      backgroundColor: isOverlay ? 'rgba(0, 0, 0, 0.35)' : '#0f172a',
      borderColor: isOverlay ? 'rgba(255, 255, 255, 0.35)' : '#1e293b',
      borderWidth: 1,
    };
  }, [themeStyle, isOverlay, colors]);

  const digitColor = useMemo(() => {
    if (themeStyle === 'solid') return '#f59e0b';
    return '#ffffff';
  }, [themeStyle]);

  const labelColor = useMemo(() => {
    if (themeStyle === 'solid') return '#fde68a';
    return '#ffffff'; // Pure bright white for crystal-clear readability against green
  }, [themeStyle]);

  return (
    <View style={[styles.container, { width: contentWidth }]}>
      {isOverlay ? (
        <View style={styles.overlayWrapper}>
          {imageUrl ? (
            <>
              <Image source={{ uri: imageUrl }} style={StyleSheet.absoluteFill} contentFit="cover" />
              <LinearGradient
                colors={['rgba(15,23,42,0.4)', 'rgba(15,23,42,0.85)', 'rgba(15,23,42,0.96)']}
                style={StyleSheet.absoluteFill}
              />
            </>
          ) : (
            <LinearGradient
              colors={['#145147', '#1c6d5f', '#0f3d36']}
              style={StyleSheet.absoluteFill}
            />
          )}

          <View style={styles.overlayContent}>
            {badgeText ? (
              <View style={styles.badgeWrap}>
                <ThemedText style={styles.badgeText}>{badgeText}</ThemedText>
              </View>
            ) : null}

            {title ? (
              <ThemedText style={styles.overlayTitle}>{title}</ThemedText>
            ) : null}

            {description ? (
              <ThemedText style={styles.overlayDesc}>{description}</ThemedText>
            ) : null}

            {/* Countdown Digits */}
            {!time.isExpired ? (
              <View style={styles.digitsRow}>
                {showDays ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.days}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>يوم</ThemedText>
                  </View>
                ) : null}

                {showHours ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.hours}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>ساعة</ThemedText>
                  </View>
                ) : null}

                {showMinutes ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.minutes}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>دقيقة</ThemedText>
                  </View>
                ) : null}

                {showSeconds ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: '#f59e0b' }]}>{time.seconds}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>ثانية</ThemedText>
                  </View>
                ) : null}
              </View>
            ) : (
              <View style={styles.expiredBanner}>
                <Ionicons name="flag" size={18} color="#fef08a" />
                <ThemedText style={styles.expiredBannerText}>{expiredMessage}</ThemedText>
              </View>
            )}

            {/* CTA Button */}
            {buttonText && buttonUrl ? (
              <TouchableOpacity
                style={styles.ctaButton}
                activeOpacity={0.88}
                onPress={handlePressButton}
              >
                <ThemedText style={styles.ctaButtonText}>{buttonText}</ThemedText>
                <Ionicons name="arrow-back" size={14} color="#0f172a" />
              </TouchableOpacity>
            ) : null}
          </View>
        </View>
      ) : (
        // Standard Card / Minimal / Side-by-side
        <View style={[styles.cardContainer, { backgroundColor: colors.card, borderColor: colors.border }]}>
          {imageUrl && !isMinimal ? (
            <Image
              source={{ uri: imageUrl }}
              style={isSideBySide ? styles.sideImage : styles.stackedImage}
              contentFit="cover"
            />
          ) : null}

          <View style={styles.cardContent}>
            {badgeText ? (
              <View style={[styles.badgeWrapCard, { backgroundColor: colors.background }]}>
                <ThemedText style={[styles.badgeTextCard, { color: colors.primary }]}>{badgeText}</ThemedText>
              </View>
            ) : null}

            {title ? (
              <ThemedText style={[styles.cardTitle, { color: colors.text }]}>{title}</ThemedText>
            ) : null}

            {description ? (
              <ThemedText style={[styles.cardDesc, { color: colors.icon }]}>{description}</ThemedText>
            ) : null}

            {!time.isExpired ? (
              <View style={styles.digitsRow}>
                {showDays ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.days}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>يوم</ThemedText>
                  </View>
                ) : null}

                {showHours ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.hours}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>ساعة</ThemedText>
                  </View>
                ) : null}

                {showMinutes ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: digitColor }]}>{time.minutes}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>دقيقة</ThemedText>
                  </View>
                ) : null}

                {showSeconds ? (
                  <View style={[styles.unitCard, unitBoxStyle]}>
                    <ThemedText style={[styles.digitNum, { color: '#f59e0b' }]}>{time.seconds}</ThemedText>
                    <ThemedText style={[styles.digitLabel, { color: labelColor }]}>ثانية</ThemedText>
                  </View>
                ) : null}
              </View>
            ) : (
              <View style={[styles.expiredBanner, { backgroundColor: colors.background }]}>
                <Ionicons name="flag" size={16} color={colors.primary} />
                <ThemedText style={[styles.expiredBannerText, { color: colors.text }]}>{expiredMessage}</ThemedText>
              </View>
            )}

            {buttonText && buttonUrl ? (
              <TouchableOpacity
                style={[styles.ctaButtonPrimary, { backgroundColor: colors.primary }]}
                activeOpacity={0.88}
                onPress={handlePressButton}
              >
                <ThemedText style={styles.ctaButtonPrimaryText}>{buttonText}</ThemedText>
                <Ionicons name="open-outline" size={15} color="#ffffff" />
              </TouchableOpacity>
            ) : null}
          </View>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 6,
  },
  overlayWrapper: {
    borderRadius: 24,
    overflow: 'hidden',
    position: 'relative',
    minHeight: 220,
    justifyContent: 'center',
    paddingVertical: 24,
    paddingHorizontal: 16,
  },
  overlayContent: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  badgeWrap: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
    backgroundColor: 'rgba(245, 158, 11, 0.25)',
    borderWidth: 1,
    borderColor: 'rgba(245, 158, 11, 0.45)',
    marginBottom: 4,
  },
  badgeText: {
    color: '#fde68a',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  overlayTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    color: '#ffffff',
    textAlign: 'center',
  },
  overlayDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    color: 'rgba(255, 255, 255, 0.88)',
    textAlign: 'center',
    lineHeight: 19,
    paddingHorizontal: 12,
  },
  digitsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginVertical: 12,
  },
  unitCard: {
    borderRadius: 16,
    paddingVertical: 10,
    paddingHorizontal: 12,
    minWidth: 62,
    alignItems: 'center',
    justifyContent: 'center',
  },
  digitNum: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    lineHeight: 26,
  },
  digitLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginTop: 3,
    color: '#ffffff',
    textAlign: 'center',
  },
  expiredBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 14,
    marginVertical: 8,
  },
  expiredBannerText: {
    color: '#fef08a',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  ctaButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ffffff',
    paddingVertical: 10,
    paddingHorizontal: 22,
    borderRadius: 14,
    marginTop: 6,
  },
  ctaButtonText: {
    color: '#0f172a',
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  cardContainer: {
    borderRadius: 20,
    borderWidth: 1,
    overflow: 'hidden',
  },
  stackedImage: {
    width: '100%',
    height: 160,
  },
  sideImage: {
    width: '100%',
    height: 180,
  },
  cardContent: {
    padding: 16,
    alignItems: 'center',
    gap: 6,
  },
  badgeWrapCard: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
    marginBottom: 4,
  },
  badgeTextCard: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  cardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'center',
  },
  cardDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'center',
    lineHeight: 18,
  },
  ctaButtonPrimary: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 22,
    borderRadius: 14,
    marginTop: 6,
  },
  ctaButtonPrimaryText: {
    color: '#ffffff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
});
