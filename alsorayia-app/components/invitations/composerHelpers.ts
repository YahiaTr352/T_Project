import type { ThemeColors } from '@/constants/theme';
import type {
  InvitationGroup,
  InvitationTemplate,
} from '@/lib/api/invitations';

export const WHATSAPP_GREEN = '#25D366';
export const PUSH_BLUE = '#3B82F6';

export interface InvitationComposerProps {
  templates: InvitationTemplate[];
  templatesLoading: boolean;
  groups: InvitationGroup[];
  senderId?: number;
  colors: ThemeColors;
  isDark: boolean;
  onSent?: () => void;
}
