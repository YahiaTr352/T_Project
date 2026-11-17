import { LogsTab } from '@/components/invitations/LogsTab';
import { Colors } from '@/constants/theme';
import { api } from '@/lib/api/alsorayiaClient';
import type { InvitationLog } from '@/lib/api/invitations';
import { act, fireEvent, render, screen } from '@testing-library/react-native';

jest.mock('@/lib/api/alsorayiaClient', () => ({
  api: { put: jest.fn() },
  WEB_BASE_URL: 'http://127.0.0.1:8000',
}));
jest.mock('axios', () => ({ isAxiosError: jest.fn(() => false) }));
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/invitations/PersonAvatar', () => ({ PersonAvatar: () => null }));
jest.mock('@/components/ui/icon-symbol', () => ({ IconSymbol: () => null }));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-image', () => ({ Image: () => null }));

const invitation = {
  id: 10,
  title: 'دعوة عشاء',
  body: 'نتشرف بحضوركم',
  channels: ['whatsapp'],
  status: 'sent',
  created_at: '2026-11-18T12:00:00Z',
} satisfies InvitationLog;

function renderLogs(log: InvitationLog, incoming: boolean, onItemUpdated = jest.fn()) {
  render(
    <LogsTab
      incoming={incoming}
      items={[log]}
      loading={false}
      refreshing={false}
      loadingMore={false}
      error={null}
      hasMore={false}
      onRefresh={jest.fn()}
      onLoadMore={jest.fn()}
      onOpenDetail={async () => ({ ok: true as const, notification: log })}
      onItemUpdated={onItemUpdated}
      colors={Colors.light}
      isDark={false}
    />
  );
}

test.each([
  ['accepted', 'قبول الدعوة', 'تأكيد القبول', 'قبلت الدعوة'],
  ['declined', 'رفض الدعوة', 'تأكيد الرفض', 'رفضت الدعوة'],
] as const)('a recipient confirms %s once from the in-app details', async (response, action, confirm, status) => {
  const put = jest.mocked(api.put);
  put.mockClear();
  let completeSave: (() => void) | undefined;
  put.mockImplementationOnce(() => new Promise((resolve) => {
    completeSave = () => resolve({ success: true, data: { invitation_response: response } });
  }));
  const onItemUpdated = jest.fn();
  renderLogs({ ...invitation, invitation_response: null }, true, onItemUpdated);
  expect(screen.getByText('عرض الدعوة والرد')).toBeOnTheScreen();
  fireEvent.press(screen.getByRole('button', { name: `عرض الدعوة والرد، ${invitation.title}` }));
  expect(put).not.toHaveBeenCalled();

  fireEvent.press(await screen.findByRole('button', { name: action }));
  expect(screen.getByText('بعد التأكيد، لا يمكنك تغيير ردك.')).toBeOnTheScreen();
  expect(put).not.toHaveBeenCalled();
  fireEvent.press(screen.getByRole('button', { name: 'إلغاء' }));
  expect(screen.queryByRole('button', { name: confirm })).toBeNull();
  expect(put).not.toHaveBeenCalled();

  fireEvent.press(screen.getByRole('button', { name: action }));
  fireEvent.press(screen.getByRole('button', { name: confirm }));
  expect(screen.getByText('جارٍ حفظ ردك…')).toBeOnTheScreen();
  expect(screen.getByRole('button', { name: action, busy: true })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'رفض الدعوة' })).toBeDisabled();

  await act(async () => completeSave?.());
  await screen.findByText(status);
  expect(put).toHaveBeenCalledTimes(1);
  expect(put).toHaveBeenCalledWith('/v1/invitations/logs/10/response', { response }, expect.anything());
  expect(onItemUpdated).toHaveBeenLastCalledWith(expect.objectContaining({ invitation_response: response }));
  expect(screen.queryByRole('button', { name: 'قبول الدعوة' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'رفض الدعوة' })).toBeNull();
});

test('an answered invitation offers a details action and shows the saved response', async () => {
  renderLogs({ ...invitation, invitation_response: 'accepted' }, true);

  expect(screen.queryByText('عرض الدعوة والرد')).toBeNull();
  expect(screen.getByText('عرض التفاصيل')).toBeOnTheScreen();
  fireEvent.press(screen.getByRole('button', { name: `عرض التفاصيل، ${invitation.title}` }));

  expect(await screen.findByText('قبلت الدعوة')).toBeOnTheScreen();
  expect(screen.queryByRole('button', { name: 'رفض الدعوة' })).toBeNull();
});

test('the sender sees response totals and phone-only recipients are excluded', async () => {
  renderLogs({
    ...invitation,
    recipients: [
      { id: 1, person_id: 21, invitation_response: 'accepted' },
      { id: 2, person_id: 22, invitation_response: 'accepted' },
      { id: 3, person_id: 23, invitation_response: 'declined' },
      { id: 4, person_id: 24, invitation_response: null },
      { id: 5, person_id: null, recipient_name: 'ضيف تجريبي', invitation_response: null },
    ],
  }, false);

  expect(screen.queryByText('عرض الدعوة والرد')).toBeNull();
  expect(screen.getByText('عرض التفاصيل')).toBeOnTheScreen();
  fireEvent.press(screen.getByRole('button', { name: `عرض التفاصيل، ${invitation.title}` }));
  expect(await screen.findByLabelText('2 موافق')).toBeOnTheScreen();
  expect(screen.getByLabelText('1 معتذر')).toBeOnTheScreen();
  expect(screen.getByLabelText('1 بانتظار الرد')).toBeOnTheScreen();
  expect(screen.getByText('خارج التطبيق')).toBeOnTheScreen();
  expect(screen.queryByRole('button', { name: 'قبول الدعوة' })).toBeNull();
});
