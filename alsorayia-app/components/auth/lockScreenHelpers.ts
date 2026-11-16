import { Colors } from '@/constants/theme';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as Haptics from 'expo-haptics';
import { Platform } from 'react-native';

export const OTP_LENGTH = 6;

/** تحويل الأرقام العربية/الفارسية إلى 0-9 واستبعاد أي رموز أخرى. */
export function normalizeOtpInput(raw: string): string {
  const western = raw
    .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660))
    .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06f0));
  return western.replace(/\D/g, '');
}

export type ColorPalette = typeof Colors.light;
export type AuthTab = 'face' | 'whatsapp' | 'passcode';

// ==================== ميزات المنصة العائلية عند الدخول ====================

export interface FamilyFeature {
  id: string;
  icon: keyof typeof MaterialIcons.glyphMap;
  title: string;
  badge: string;
  desc: string;
  gradient: readonly [string, string];
}

export const FAMILY_FEATURES: FamilyFeature[] = [
  {
    id: '1',
    icon: 'bolt',
    title: 'تخطي كلمة السر مستقبلاً',
    badge: 'دخول فوري',
    desc: 'توثيق حسابك مرّة واحدة يمنحك دخولاً تلقائياً وآمناً في كل مرّة تفتح التطبيق.',
    gradient: ['#10B981', '#059669'],
  },
  {
    id: '2',
    icon: 'account-tree',
    title: 'مكانك في شجرة العائلة',
    badge: 'تعرف تلقائي',
    desc: 'الربط الآلي مع سجلك العائلي لمعرفة تسلسل النسب وتحديث بيانات أسرتك.',
    gradient: ['#37a05c', '#166534'],
  },
  {
    id: '3',
    icon: 'notifications-active',
    title: 'إشعارات عائلية مخصصة',
    badge: 'تنبيهات خـاصة',
    desc: 'وصول مباشر لأخبار الفعاليّات واللقاءات والمناسبات الخاصة بفرعك العائلي.',
    gradient: ['#0D9488', '#0F766E'],
  },
  {
    id: '4',
    icon: 'emoji-events',
    title: 'المسابقات والجوائز',
    badge: 'مشاركات وجوائز',
    desc: 'المشاركة الفورية في مسابقات القرآن الكريم وتكريم المتفوقين والأنشطة.',
    gradient: ['#D97706', '#B45309'],
  },
  {
    id: '5',
    icon: 'mark-chat-unread',
    title: 'صندوق التهاني والمواساة',
    badge: 'تواصل عائلي',
    desc: 'مشاركة التبريكات في أفراح العائلة والواجبات مباشرة مع أفراد الأسرة.',
    gradient: ['#2563EB', '#1D4ED8'],
  },
];

// ---------------- التفاعل مع التغذية الراجعة والمؤثرات ----------------

export const triggerHaptic = async (type: 'light' | 'success' | 'error' = 'light') => {
  if (Platform.OS === 'web') return;
  try {
    if (type === 'success') {
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } else if (type === 'error') {
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
    } else {
      await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    }
  } catch { }
};
