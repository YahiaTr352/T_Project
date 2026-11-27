import type { Ionicons } from '@expo/vector-icons';
import type { ImageSourcePropType } from 'react-native';

export interface InvitationPalette {
  isDark: boolean;
  bgGradient: readonly [string, string, string];
  surface: string;
  surfaceAlt: string;
  surfaceSoft: string;
  tabContainerBg: string;
  headerBtnBg: string;
  headerBtnBorder: string;
  border: string;
  borderStrong: string;
  text: string;
  textSecondary: string;
  muted: string;
  primary: string;
  primaryDark: string;
  primaryGradient: readonly [string, string];
  heroGradient: readonly [string, string, string];
  cardOverlayGradient: readonly [string, string, string];
  toolbarBg: string;
  toolbarActive: string;
  pillBg: string;
  pillText: string;
  pillBorder: string;
  successBg: string;
  successText: string;
  successBorder: string;
  warningBg: string;
  warningText: string;
  warningBorder: string;
  dangerBg: string;
  dangerText: string;
  dangerBorder: string;
  infoBg: string;
  infoText: string;
  infoBorder: string;
  neutralBg: string;
  neutralText: string;
  neutralBorder: string;
  overlay: string;
  shadowColor: string;
}

const LIGHT_PALETTE: InvitationPalette = {
  isDark: false,
  bgGradient: ['#E5F4EB', '#F3FAF6', '#F8FCFA'],
  surface: '#FFFFFF',
  surfaceAlt: '#F3F7F5',
  surfaceSoft: '#EAF7EF',
  tabContainerBg: '#FFFFFF',
  headerBtnBg: '#F1F8F4',
  headerBtnBorder: '#DAEBE1',
  border: '#DFEBE4',
  borderStrong: '#B5DFC7',
  text: '#0F241C',
  textSecondary: '#445C52',
  muted: '#6E857B',
  primary: '#19844A',
  primaryDark: '#0B4D31',
  primaryGradient: ['#218C4F', '#0B4D31'],
  heroGradient: ['rgba(9,39,27,0.92)', 'rgba(13,56,39,0.72)', 'rgba(17,74,51,0.34)'],
  cardOverlayGradient: ['rgba(10,36,24,0)', 'rgba(10,36,24,0.32)', 'rgba(9,31,21,0.88)'],
  toolbarBg: '#0E482E',
  toolbarActive: '#1E8D52',
  pillBg: '#DCF3E5',
  pillText: '#0E663D',
  pillBorder: '#BCE3CC',
  successBg: '#DCFCE7',
  successText: '#15803D',
  successBorder: '#BBF7D0',
  warningBg: '#FEF3C7',
  warningText: '#B45309',
  warningBorder: '#FDE68A',
  dangerBg: '#FFF1F2',
  dangerText: '#BE123C',
  dangerBorder: '#FECDD3',
  infoBg: '#E0F2FE',
  infoText: '#0369A1',
  infoBorder: '#BAE6FD',
  neutralBg: '#F1F5F9',
  neutralText: '#475569',
  neutralBorder: '#E2E8F0',
  overlay: 'rgba(9, 24, 18, 0.56)',
  shadowColor: '#0E4E34',
};

const DARK_PALETTE: InvitationPalette = {
  isDark: true,
  bgGradient: ['#06130E', '#0B1D16', '#081510'],
  surface: '#11251E',
  surfaceAlt: '#0B1914',
  surfaceSoft: '#153428',
  tabContainerBg: '#0E2019',
  headerBtnBg: '#142B22',
  headerBtnBorder: '#234739',
  border: '#1F3F33',
  borderStrong: '#2A634C',
  text: '#ECFDF5',
  textSecondary: '#B5CEC3',
  muted: '#7EA092',
  primary: '#26A45F',
  primaryDark: '#125E3D',
  primaryGradient: ['#26A45F', '#105A3B'],
  heroGradient: ['rgba(6,24,17,0.94)', 'rgba(9,39,27,0.78)', 'rgba(12,52,36,0.45)'],
  cardOverlayGradient: ['rgba(6,20,14,0)', 'rgba(6,20,14,0.42)', 'rgba(5,17,12,0.92)'],
  toolbarBg: '#092B1D',
  toolbarActive: '#239858',
  pillBg: 'rgba(52, 211, 153, 0.16)',
  pillText: '#6EE7B7',
  pillBorder: 'rgba(52, 211, 153, 0.32)',
  successBg: 'rgba(16, 185, 129, 0.16)',
  successText: '#6EE7B7',
  successBorder: 'rgba(52, 211, 153, 0.30)',
  warningBg: 'rgba(245, 158, 11, 0.16)',
  warningText: '#FCD34D',
  warningBorder: 'rgba(251, 191, 36, 0.32)',
  dangerBg: 'rgba(239, 68, 68, 0.14)',
  dangerText: '#FCA5A5',
  dangerBorder: 'rgba(248, 113, 113, 0.32)',
  infoBg: 'rgba(56, 189, 248, 0.16)',
  infoText: '#7DD3FC',
  infoBorder: 'rgba(56, 189, 248, 0.30)',
  neutralBg: 'rgba(148, 163, 184, 0.14)',
  neutralText: '#CBD5E1',
  neutralBorder: 'rgba(148, 163, 184, 0.25)',
  overlay: 'rgba(2, 10, 7, 0.78)',
  shadowColor: '#000000',
};

export function getInvitationPalette(isDark: boolean): InvitationPalette {
  return isDark ? DARK_PALETTE : LIGHT_PALETTE;
}

export const INVITATION_IMAGES = {
  event_invitation: require('@/assets/images/invitations/event-invitation.jpg') as ImageSourcePropType,
  wedding: require('@/assets/images/invitations/wedding.jpg') as ImageSourcePropType,
  reminder: require('@/assets/images/invitations/reminder.jpg') as ImageSourcePropType,
  greeting: require('@/assets/images/invitations/greeting.jpg') as ImageSourcePropType,
  familyLogo: require('@/assets/images/family-logo.png') as ImageSourcePropType,
};

export interface TemplateVisualMeta {
  isVisualCard: boolean;
  order: number;
  image?: ImageSourcePropType;
  icon: keyof typeof Ionicons.glyphMap;
  dotColor: string;
}

const TEMPLATE_VISUALS: Record<string, TemplateVisualMeta> = {
  event_invitation: {
    isVisualCard: true,
    order: 0,
    image: INVITATION_IMAGES.event_invitation,
    icon: 'calendar-outline',
    dotColor: '#19844A',
  },
  wedding: {
    isVisualCard: true,
    order: 1,
    image: INVITATION_IMAGES.wedding,
    icon: 'heart-outline',
    dotColor: '#19844A',
  },
  reminder: {
    isVisualCard: true,
    order: 2,
    image: INVITATION_IMAGES.reminder,
    icon: 'notifications-outline',
    dotColor: '#19844A',
  },
  greeting: {
    isVisualCard: true,
    order: 3,
    image: INVITATION_IMAGES.greeting,
    icon: 'sparkles-outline',
    dotColor: '#19844A',
  },
  announcement: {
    isVisualCard: false,
    order: 0,
    icon: 'megaphone-outline',
    dotColor: '#19844A',
  },
  general: {
    isVisualCard: false,
    order: 1,
    icon: 'chatbubble-ellipses-outline',
    dotColor: '#3B82F6',
  },
  condolences: {
    isVisualCard: false,
    order: 2,
    icon: 'moon-outline',
    dotColor: '#64748B',
  },
  urgent_alert: {
    isVisualCard: false,
    order: 3,
    icon: 'alert-circle-outline',
    dotColor: '#EF4444',
  },
};

export function getTemplateVisualMeta(key?: string | null): TemplateVisualMeta {
  if (key && TEMPLATE_VISUALS[key]) return TEMPLATE_VISUALS[key];
  return {
    isVisualCard: false,
    order: 99,
    icon: 'document-text-outline',
    dotColor: '#19844A',
  };
}

export function inferThumbnailFromLog(title?: string | null, body?: string | null): ImageSourcePropType {
  const haystack = `${title ?? ''} ${body ?? ''}`;
  if (/زفاف|عريس|زواج/.test(haystack)) return INVITATION_IMAGES.wedding;
  if (/تذكير|موعد|اللقاء/.test(haystack)) return INVITATION_IMAGES.reminder;
  if (/تهنئة|تخرج|مباركة|نجاح/.test(haystack)) return INVITATION_IMAGES.greeting;
  return INVITATION_IMAGES.event_invitation;
}

export function getVarFieldIcon(key: string): keyof typeof Ionicons.glyphMap {
  switch (key) {
    case 'date':
      return 'calendar-outline';
    case 'time':
      return 'time-outline';
    case 'location':
      return 'location-outline';
    case 'event_name':
    case 'occasion':
      return 'sparkles-outline';
    case 'hosts':
    case 'groom':
    case 'deceased_name':
      return 'person-outline';
    case 'announcement_title':
    case 'topic':
    case 'urgent_matter':
      return 'document-text-outline';
    case 'fund_name':
    case 'amount':
      return 'cash-outline';
    default:
      return 'create-outline';
  }
}

export function cardShadowStyle(palette: InvitationPalette) {
  return {
    shadowColor: palette.shadowColor,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: palette.isDark ? 0.28 : 0.06,
    shadowRadius: 14,
    elevation: palette.isDark ? 4 : 3,
  };
}
