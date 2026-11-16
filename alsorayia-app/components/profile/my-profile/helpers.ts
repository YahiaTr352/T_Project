import { Ionicons } from '@expo/vector-icons';
import type { ContactType } from '@/lib/api/myProfile';

export const CONTACT_TYPES: { value: ContactType; label: string; icon: keyof typeof Ionicons.glyphMap; color: string }[] = [
  { value: 'whatsapp', label: 'واتساب', icon: 'logo-whatsapp', color: '#25D366' },
  { value: 'phone', label: 'هاتف', icon: 'call', color: '#3B82F6' },
  { value: 'email', label: 'بريد', icon: 'mail', color: '#EA4335' },
  { value: 'twitter', label: 'تويتر/X', icon: 'logo-twitter', color: '#1DA1F2' },
  { value: 'instagram', label: 'انستغرام', icon: 'logo-instagram', color: '#E4405F' },
  { value: 'facebook', label: 'فيسبوك', icon: 'logo-facebook', color: '#1877F2' },
  { value: 'telegram', label: 'تيليجرام', icon: 'paper-plane', color: '#2AABEE' },
  { value: 'linkedin', label: 'لينكدإن', icon: 'logo-linkedin', color: '#0A66C2' },
  { value: 'other', label: 'أخرى', icon: 'link', color: '#8B5CF6' },
];

export interface BasicFormState {
  first_name: string;
  last_name: string;
  birth_date: string;
  birth_place: string;
  occupation: string;
  biography: string;
  death_date: string;
  death_place: string;
  cemetery: string;
  cemetery_location: string;
  grave_number: string;
}

export interface ContactFormState {
  type: ContactType;
  value: string;
  label: string;
}

export interface ChildFormState {
  first_name: string;
  gender: 'male' | 'female';
  birth_date: string;
  parentChoiceId: number;
}

export interface MarriageFormState {
  spouseType: 'existing' | 'outside_family' | 'inside_family';
  spouseId: number;
  firstName: string;
  lastName: string;
  parentId: number;
  birthDate: string;
  photoUri: string | null;
  married_at: string;
  searchQuery: string;
}

export type MarriageEditsMap = Record<number, { married_at: string; divorced_at: string; is_divorced: boolean }>;

export interface LocationFormState {
  location_id: number;
  label: string;
  url: string;
}
