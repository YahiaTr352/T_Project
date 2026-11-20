import { GroupDetailsModal } from '@/components/walking/GroupDetailsModal';
import type { LeaderboardData, WalkingGroupDetail } from '@/lib/api/walkingApi';
import { fireEvent, render, screen } from '@testing-library/react-native';

jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/ui/icon-symbol', () => ({ IconSymbol: () => null }));
jest.mock('@/components/ui/AlertModal', () => ({ CustomAlert: { alert: jest.fn() } }));
jest.mock('@/contexts/auth-context', () => ({ useAuth: () => ({ user: null }) }));
jest.mock('@/lib/api/familyTreeApi', () => ({}));
jest.mock('@/lib/api/walkingApi', () => ({}));
jest.mock('@/components/walking/AnimatedCounter', () => ({ AnimatedCounter: () => null }));
jest.mock('@/components/walking/AnonymousAvatar', () => ({ AnonymousAvatar: () => null }));
jest.mock('@/components/walking/ChallengeInvitePickerModal', () => ({ ChallengeInvitePickerModal: () => null }));
jest.mock('@/components/walking/ChallengePrivacySettingsModal', () => ({ ChallengePrivacySettingsModal: () => null }));
jest.mock('@/components/walking/EditGroupModal', () => ({ EditGroupModal: () => null }));
jest.mock('@/components/walking/group-details/GroupActionsSheet', () => ({ GroupActionsSheet: () => null }));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-haptics', () => ({}));
jest.mock('expo-image', () => ({ Image: () => null }));
jest.mock('expo-linear-gradient', () => ({ LinearGradient: jest.requireActual('react-native').View }));
jest.mock('expo-router', () => {
  const router = { push: jest.fn() };
  return { useRouter: () => router };
});

const group: WalkingGroupDetail = {
  id: 1,
  name: 'تحدي المشي',
  description: null,
  cover_image: null,
  target_steps: null,
  start_date: null,
  end_date: null,
  is_public: true,
  status: 'active',
  invite_code: 'TEST',
  member_count: 2,
  created_at: '2026-11-19T12:00:00Z',
  members: [
    { id: 1, person_id: 1, person: { id: 1, name: 'أحمد', avatar: null } },
    { id: 2, person_id: 2, person: { id: 2, name: 'محمد', avatar: null } },
  ].map((member) => ({ ...member, role: 'member', status: 'accepted', joined_at: '2026-11-19T12:00:00Z' })),
};

function renderChallenge(steps: number) {
  const leaderboard: LeaderboardData = {
    period: 'today',
    top: group.members.map((member, index) => ({
      rank: index + 1,
      person_id: member.person_id,
      membership_id: member.id,
      total_steps: index === 0 ? steps : 0,
      person: member.person!,
    })),
    viewer_rank: null,
  };

  return render(
    <GroupDetailsModal
      visible
      group={group}
      leaderboard={leaderboard}
      onClose={jest.fn()}
      onInviteMember={jest.fn()}
      onLeaveGroup={jest.fn()}
    />
  );
}

test('excludes zero-step members from step standings while retaining them in points and members', () => {
  renderChallenge(30);
  expect(screen.getByText('محمد')).toBeOnTheScreen();

  fireEvent.press(screen.getByText('الخطوات'));
  expect(screen.getByText('أحمد')).toBeOnTheScreen();
  expect(screen.queryByText('محمد')).toBeNull();

  fireEvent.press(screen.getByText('إجمالي النقاط'));
  expect(screen.getByText('محمد')).toBeOnTheScreen();

  fireEvent.press(screen.getByText(/الأعضاء \(2\)/));
  expect(screen.getByText('محمد')).toBeOnTheScreen();
});

test('shows the empty standings message when nobody has steps in the period', () => {
  renderChallenge(0);

  fireEvent.press(screen.getByText('الخطوات'));

  expect(screen.getByText('لم يسجّل أي مشارك خطوات في الفترة المحددة')).toBeOnTheScreen();
  expect(screen.queryByText('أحمد')).toBeNull();
  expect(screen.queryByText('محمد')).toBeNull();
});
