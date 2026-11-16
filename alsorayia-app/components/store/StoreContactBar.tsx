import { ThemedText } from '@/components/themed-text';
import {
  buildWhatsappUrl,
  normalizeExternalUrl,
  type StoreContact,
} from '@/lib/api/store';
import { Ionicons } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import React, { useMemo } from 'react';
import { Linking, Pressable, StyleSheet, View } from 'react-native';
import { STORE_ACCENT } from './constants';

type ContactAction = {
  key: string;
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  color: string;
  onPress: () => void;
};

type Props = {
  contact: StoreContact;
  cardBg: string;
  borderColor: string;
};

function openUrl(url: string) {
  WebBrowser.openBrowserAsync(url).catch(() => {});
}

function openTel(phone: string) {
  const digits = phone.replace(/\s/g, '');
  Linking.openURL(`tel:${digits}`).catch(() => {});
}

function openMail(email: string) {
  Linking.openURL(`mailto:${email.trim()}`).catch(() => {});
}

export function StoreContactBar({ contact, cardBg, borderColor }: Props) {
  const actions = useMemo((): ContactAction[] => {
    const list: ContactAction[] = [];

    if (contact.phone?.trim()) {
      const phone = contact.phone.trim();
      list.push({
        key: 'phone',
        label: 'اتصال',
        icon: 'call',
        color: '#3B82F6',
        onPress: () => openTel(phone),
      });
    }

    if (contact.whatsapp?.trim()) {
      list.push({
        key: 'whatsapp',
        label: 'واتساب',
        icon: 'logo-whatsapp',
        color: '#25D366',
        onPress: () => openUrl(buildWhatsappUrl(contact.whatsapp!.trim())),
      });
    }

    if (contact.email?.trim()) {
      list.push({
        key: 'email',
        label: 'بريد',
        icon: 'mail',
        color: '#6366F1',
        onPress: () => openMail(contact.email!.trim()),
      });
    }

    const instagram = normalizeExternalUrl(contact.instagram);
    if (instagram) {
      list.push({
        key: 'instagram',
        label: 'إنستغرام',
        icon: 'logo-instagram',
        color: '#E1306C',
        onPress: () => openUrl(instagram),
      });
    }

    const facebook = normalizeExternalUrl(contact.facebook);
    if (facebook) {
      list.push({
        key: 'facebook',
        label: 'فيسبوك',
        icon: 'logo-facebook',
        color: '#1877F2',
        onPress: () => openUrl(facebook),
      });
    }

    const website = normalizeExternalUrl(contact.website_url);
    if (website) {
      list.push({
        key: 'website',
        label: 'الموقع',
        icon: 'globe',
        color: STORE_ACCENT,
        onPress: () => openUrl(website),
      });
    }

    const location = normalizeExternalUrl(contact.location_url);
    if (location) {
      list.push({
        key: 'location',
        label: 'الموقع',
        icon: 'location',
        color: '#EA4335',
        onPress: () => openUrl(location),
      });
    }

    return list;
  }, [contact]);

  if (actions.length === 0) return null;

  return (
    <View style={styles.wrap}>
      <ThemedText type="defaultSemiBold" style={styles.heading}>
        تواصل مع البائع
      </ThemedText>
      <View style={styles.row}>
        {actions.map((a) => (
          <Pressable
            key={a.key}
            onPress={a.onPress}
            style={({ pressed }) => [
              styles.btn,
              { backgroundColor: cardBg, borderColor },
              pressed && { opacity: 0.88 },
            ]}>
            <View style={[styles.iconWrap, { backgroundColor: a.color + '18' }]}>
              <Ionicons name={a.icon} size={20} color={a.color} />
            </View>
            <ThemedText style={styles.btnLabel}>{a.label}</ThemedText>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 12,
  },
  heading: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  btn: {
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 14,
    paddingVertical: 10,
    paddingHorizontal: 12,
    minWidth: 76,
    gap: 6,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
});
