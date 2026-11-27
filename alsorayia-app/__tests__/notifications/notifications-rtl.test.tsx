import NotificationsScreen from '@/app/(app)/notifications';
import { api } from '@/lib/api/alsorayiaClient';
import { render, screen } from '@testing-library/react-native';
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
  return {
    useLocalSearchParams: () => ({}),
    useRouter: () => router,
  };
});
jest.mock('@/contexts/auth-context', () => ({
  useAuth: () => ({ hasIdentity: true, user: { id: 1919, first_name: 'تجريب' } }),
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

test('forces RTL layout, right-start unread border, and Android-safe right text alignment for standard notifications', async () => {
  const mixedBody = 'تمت الموافقة على التصميم wedding-phrases-preview.png واصبح متاحا كقالب للدعوات.';
  get.mockResolvedValue({
    data: {
      data: [
        {
          id: 1365,
          title: 'تم اعتماد تصميمك',
          body: mixedBody,
          media_type: null,
          media_url: null,
          created_at: '2026-11-27T03:26:57+03:00',
          time_ago: 'منذ 20 ساعة',
          read: false,
          invitation: null,
        },
      ],
    },
  });

  const view = render(<NotificationsScreen />);
  const title = await screen.findByText('تم اعتماد تصميمك', {}, { timeout: 5000 });
  const body = screen.getByText(mixedBody);
  const expandBtn = screen.getByText('عرض تفاصيل الإشعار كاملة ▾');

  const expectedRtlText = {
    direction: 'ltr',
    textAlign: 'right',
    writingDirection: 'rtl',
  };

  expect(StyleSheet.flatten(title.props.style)).toMatchObject(expectedRtlText);
  expect(StyleSheet.flatten(body.props.style)).toMatchObject(expectedRtlText);
  expect(StyleSheet.flatten(expandBtn.props.style)).toMatchObject(expectedRtlText);

  const rootStyle = StyleSheet.flatten(view.root.props.style);
  expect(rootStyle).toMatchObject({ direction: 'rtl' });

  let cardNode = body.parent;
  while (cardNode && StyleSheet.flatten(cardNode.props?.style)?.borderRadius !== 18) {
    cardNode = cardNode.parent;
  }
  const cardStyle = StyleSheet.flatten(cardNode?.props.style);
  expect(cardStyle).toMatchObject({
    direction: 'rtl',
    overflow: 'hidden',
    borderStartWidth: 4,
  });
  expect(cardStyle?.borderRightWidth).toBeUndefined();
});
