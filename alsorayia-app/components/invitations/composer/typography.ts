import type { InvitationLog } from '@/lib/api/invitations';

export type InvitationFontFamily = 'tajawal' | 'amiri';
export type InvitationFontWeight = 'regular' | 'bold';

export function invitationFontName(family: InvitationFontFamily, weight: InvitationFontWeight) {
  return `${family === 'amiri' ? 'Amiri' : 'Tajawal'}-${weight === 'bold' ? 'Bold' : 'Regular'}`;
}
export function invitationLogFontName(log: InvitationLog | null, defaultWeight: InvitationFontWeight) {
  const family = log?.font_family ?? log?.targeting_filters?.font_family;
  const weight = log?.font_weight ?? log?.targeting_filters?.font_weight;
  return invitationFontName(family ?? 'tajawal', weight ?? defaultWeight);
}
