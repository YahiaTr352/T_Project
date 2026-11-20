import NotificationsScreen from '@/app/(app)/notifications';
import { CustomAlert } from '@/components/ui/AlertModal';
import { api } from '@/lib/api/alsorayiaClient';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { StyleSheet } from 'react-native';

jest.mock('react-native/Libraries/Utilities/Platform', () => {
  const { default: platform } = jest.requireActual('react-native/Libraries/Utilities/Platform');
  return { default: { ...platform, OS: 'android' } };
});
jest.mock('@/lib/api/alsorayiaClient', () => ({
  api: { get: jest.fn(), put: jest.fn() },
  WEB_BASE_URL: 'http://127.0.0.1:8000',
}));
jest.mock('axios', () => ({ isAxiosError: jest.fn(() => false) }));
jest.mock('expo-router', () => {
  const router = { push: jest.fn(), back: jest.fn() };
  return { useLocalSearchParams: () => ({}), useRouter: () => router };
});
jest.mock('@/contexts/auth-context', () => ({
  useAuth: () => ({ hasIdentity: true, user: { id: 42, first_name: 'محمد' } }),
}));
jest.mock('@/lib/storage/appStorage', () => ({
  appStorage: { getItem: jest.fn().mockResolvedValue(null) },
}));
jest.mock('@/components/ui/AlertModal', () => ({ CustomAlert: { alert: jest.fn() } }));
jest.mock('@/components/headerBar', () => () => null);
jest.mock('@/components/quiz/MediaLightbox', () => () => null);
jest.mock('@/components/ui/skeleton', () => ({ ListSkeleton: () => null }));
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/themed-view', () => ({ ThemedView: jest.requireActual('react-native').View }));
jest.mock('@/components/ui/icon-symbol', () => ({ IconSymbol: () => null }));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-image', () => ({ Image: () => null }));
jest.mock('expo-haptics', () => ({
  ImpactFeedbackStyle: { Light: 'light' },
  impactAsync: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('expo-web-browser', () => ({}));

const get = jest.mocked(api.get);
const put = jest.mocked(api.put);
const alert = jest.mocked(CustomAlert.alert);
const baseNotification = {
  body: 'نتشرف بحضوركم',
  media_type: null,
  media_url: null,
  created_at: '2026-11-20T12:00:00Z',
  time_ago: '',
  read: true,
};

beforeEach(() => {
  jest.clearAllMocks();
  put.mockReset();
  get.mockResolvedValue({
    data: {
      data: [
        { ...baseNotification, id: 10, title: 'دعوة عشاء', invitation: { id: 10, response: null } },
        { ...baseNotification, id: 11, title: 'دعوة سابقة', invitation: { id: 11, response: 'accepted' } },
        { ...baseNotification, id: 12, title: 'إشعار عام', invitation: null },
      ],
    },
  });
});

test('right-aligns the invitation title and message on Android in both card states', async () => {
  const message = 'السلام عليكم ورحمة الله وبركاته،\nنتشرف بحضوركم.';
  get.mockResolvedValueOnce({
    data: {
      data: [{
        ...baseNotification,
        id: 10,
        title: 'دعوة عشاء',
        body: message,
        invitation: { id: 10, response: null },
      }],
    },
  });

  render(<NotificationsScreen />);
  const title = await screen.findByText('دعوة عشاء', {}, { timeout: 5000 });
  const rightAligned = {
    direction: 'ltr',
    textAlign: 'right',
    writingDirection: 'rtl',
  };
  expect(StyleSheet.flatten(title.props.style)).toMatchObject(rightAligned);
  expect(StyleSheet.flatten(screen.getByText(message).props.style)).toMatchObject(rightAligned);

  fireEvent.press(screen.getByText('عرض تفاصيل الإشعار كاملة ▾'), { stopPropagation: jest.fn() });

  expect(screen.getByText('إخفاء تفاصيل الإشعار ▴')).toBeOnTheScreen();
  const expandedMessage = screen.getByText(message);
  expect(expandedMessage.props.numberOfLines).toBeUndefined();
  expect(StyleSheet.flatten(expandedMessage.props.style)).toMatchObject(rightAligned);
});

test.each([
  ['accepted', 'قبول الدعوة', 'تأكيد القبول', 'تم القبول'],
  ['declined', 'رفض الدعوة', 'تأكيد الرفض', 'تم الرفض'],
] as const)('saves %s directly from a received invitation notification', async (response, action, confirm, status) => {
  let finishRequest: (() => void) | undefined;
  put.mockImplementationOnce(() => new Promise((resolve) => {
    finishRequest = () => resolve({ success: true, data: { invitation_response: response } });
  }));

  render(<NotificationsScreen />);
  const button = await screen.findByRole('button', { name: action }, { timeout: 5000 });
  expect(screen.getAllByRole('button', { name: 'قبول الدعوة' })).toHaveLength(1);
  expect(screen.getAllByRole('button', { name: 'رفض الدعوة' })).toHaveLength(1);
  expect(screen.getByText('إشعار عام')).toBeOnTheScreen();
  expect(screen.getByText('تم القبول')).toBeOnTheScreen();

  fireEvent.press(button, { stopPropagation: jest.fn() });
  expect(put).not.toHaveBeenCalled();
  expect(alert).toHaveBeenCalledWith(confirm, expect.any(String), expect.any(Array));

  const confirmPress = alert.mock.calls[0][2]?.[1].onPress;
  await act(async () => {
    confirmPress?.();
    confirmPress?.();
  });
  expect(put).toHaveBeenCalledTimes(1);
  expect(put).toHaveBeenCalledWith('/v1/invitations/logs/10/response', { response }, expect.anything());
  expect(screen.getByRole('button', { name: 'قبول الدعوة' })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'رفض الدعوة' })).toBeDisabled();

  await act(async () => finishRequest?.());
  await waitFor(() => expect(screen.getAllByText(status)).toHaveLength(response === 'accepted' ? 2 : 1));
  expect(screen.queryByRole('button', { name: 'قبول الدعوة' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'رفض الدعوة' })).toBeNull();
}, 15000);
