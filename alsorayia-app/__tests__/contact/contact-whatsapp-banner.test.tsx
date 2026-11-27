import ContactScreen from '@/app/(app)/contact';
import { useAuth } from '@/contexts/auth-context';
import { render, screen } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  useRouter: () => ({ back: jest.fn() }),
}));
jest.mock('@/contexts/auth-context', () => ({
  useAuth: jest.fn(),
}));
jest.mock('@/components/headerBar', () => () => null);
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/themed-view', () => ({ ThemedView: jest.requireActual('react-native').View }));
jest.mock('@/components/ui/AlertModal', () => ({ CustomAlert: { alert: jest.fn() } }));
jest.mock('@/lib/api/contactMessageApi', () => ({
  sendContactMessageApi: jest.fn(),
}));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-audio', () => ({
  RecordingPresets: { HIGH_QUALITY: {} },
  requestRecordingPermissionsAsync: jest.fn(),
  setAudioModeAsync: jest.fn(),
  useAudioPlayer: () => ({ play: jest.fn(), pause: jest.fn() }),
  useAudioPlayerStatus: () => ({ playing: false }),
  useAudioRecorder: () => ({}),
}));
jest.mock('expo-haptics', () => ({
  ImpactFeedbackStyle: { Light: 'light', Medium: 'medium', Heavy: 'heavy' },
  NotificationFeedbackType: { Success: 'success' },
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('expo-image-picker', () => ({}));
jest.mock('expo-linear-gradient', () => ({
  LinearGradient: ({ children }: { children: any }) => children,
}));

const mockedUseAuth = jest.mocked(useAuth);

function buildAuthState(overrides: Record<string, unknown>) {
  return {
    isAuthenticated: true,
    isLoading: false,
    hasIdentity: true,
    authenticate: jest.fn(),
    lock: jest.fn(),
    lastAuthTime: null,
    logout: jest.fn(),
    refreshIdentityProfile: jest.fn().mockResolvedValue(true),
    user: {
      id: 1919,
      first_name: 'تجريب',
      last_name: 'السريّع',
      full_name: 'تجريب يحيى محمد حمد البراهيم',
      phone: '+963981922672',
      ...overrides,
    },
  } as any;
}

beforeEach(() => {
  jest.clearAllMocks();
});

test('shows WhatsApp registration notice only when the member has a registered WhatsApp number', () => {
  mockedUseAuth.mockReturnValue(
    buildAuthState({
      has_whatsapp: true,
      whatsapp_number: '+963981922672',
    })
  );

  render(<ContactScreen />);

  expect(
    screen.getByText('سيتم توثيق مشاركتك واقتراحك برقمك المسجل (+963981922672) لدى إدارة العائلة.')
  ).toBeOnTheScreen();
  expect(
    screen.queryByText(/سيصلك رد الإدارة عبر إشعارات التطبيق/)
  ).toBeNull();
  expect(screen.getByText('أو إرسال المحتوى مباشرة عبر واتساب')).toBeOnTheScreen();
});

test('shows app notifications reply notice when the member has no registered WhatsApp number', () => {
  mockedUseAuth.mockReturnValue(
    buildAuthState({
      has_whatsapp: false,
      whatsapp_number: null,
    })
  );

  render(<ContactScreen />);

  expect(
    screen.getByText('سيتم توثيق مشاركتك واقتراحك بحسابك المسجل لدى إدارة العائلة، وسيصلك رد الإدارة عبر إشعارات التطبيق.')
  ).toBeOnTheScreen();
  expect(
    screen.queryByText(/برقمك المسجل/)
  ).toBeNull();
  expect(screen.getByText('أو إرسال المحتوى مباشرة عبر واتساب')).toBeOnTheScreen();
});
