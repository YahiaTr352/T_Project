import { Image } from 'expo-image';
import React from 'react';
import { type ImageStyle, type StyleProp, type ViewStyle } from 'react-native';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';

interface AnonymousAvatarProps {
  /** النوع القادم من الباك (male/female) لاختيار الصورة الافتراضية المناسبة */
  gender?: string | null;
  /** رابط الصورة الرمزية القادم من الباك إند إن وجد */
  avatar?: string | null;
  size?: number;
  backgroundColor?: string;
  iconColor?: string;
  style?: StyleProp<ImageStyle>;
}

function resolveAvatarUri(raw?: string | null, gender?: string | null): string {
  const isFemale = gender === 'female';
  const defaultUrl = `${WEB_BASE_URL}/assets/img/${isFemale ? 'avatar-anonymous-female.png' : 'avatar-anonymous-male.png'}`;
  if (!raw || typeof raw !== 'string' || !raw.trim()) {
    return defaultUrl;
  }
  const u = raw.trim();
  if (/^https?:\/\//i.test(u)) {
    if (u.includes('localhost') || u.includes('127.0.0.1')) {
      return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, WEB_BASE_URL);
    }
    return u;
  }
  const base = WEB_BASE_URL.replace(/\/$/, '');
  return `${base}/${u.replace(/^\//, '')}`;
}

/**
 * يعرض الصورة الرمزية الرسمية للمتسابق المتخفي المعتمدة في موديل الباك إند
 */
export function AnonymousAvatar({
  gender,
  avatar,
  size = 40,
  style,
}: AnonymousAvatarProps) {
  const radius = size / 2;
  const uri = resolveAvatarUri(avatar, gender);

  return (
    <Image
      source={{ uri }}
      style={[{ width: size, height: size, borderRadius: radius }, style]}
      contentFit="cover"
      transition={200}
      cachePolicy="memory-disk"
    />
  );
}

