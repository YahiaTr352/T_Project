export type InvitationFontFamily = 'tajawal' | 'amiri';
export type InvitationFontWeight = 'regular' | 'bold';

export function invitationFontName(family: InvitationFontFamily, weight: InvitationFontWeight) {
  return `${family === 'amiri' ? 'Amiri' : 'Tajawal'}-${weight === 'bold' ? 'Bold' : 'Regular'}`;
}
