import { InvitationPreviewModal } from '@/components/invitations/composer/InvitationPreviewModal';
import { Colors } from '@/constants/theme';
import { api } from '@/lib/api/alsorayiaClient';
import { resolveCreativeArtworkUrl } from '@/lib/api/invitations';
import { fireEvent, render, screen } from '@testing-library/react-native';

jest.mock('axios', () => ({ isAxiosError: jest.fn() }));
jest.mock('@/lib/api/alsorayiaClient', () => ({
  api: { defaults: { baseURL: 'http://192.168.43.249:8000/api' } },
  apiUpload: jest.fn(),
  WEB_BASE_URL: 'https://alsorayia.com',
}));
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('expo-image', () => {
  const { View } = jest.requireActual('react-native');
  return { Image: View };
});

const props = {
  visible: true,
  onClose: jest.fn(),
  colors: Colors.light,
  muted: '#475569',
  cardBg: '#FFF',
  previewCard: 'https://alsorayia.com/storage/notifications/cards/preview.jpg',
  previewCreative: { id: 1, image_url: 'https://alsorayia.com/storage/creative-templates/wedding.jpg' },
  previewBg: null,
  attachedImage: null,
  previewText: 'معاينة دعوة زفاف',
  fontFamily: 'Amiri-Regular',
};

test('loads generated artwork from the active backend and falls back if loading fails', () => {
  render(<InvitationPreviewModal {...props} />);
  const image = () => screen.getByLabelText('صورة معاينة الدعوة');

  expect(image().props.source.uri).toBe('http://192.168.43.249:8000/storage/notifications/cards/preview.jpg');
  fireEvent(image(), 'error');
  expect(image().props.source.uri).toBe('http://192.168.43.249:8000/storage/creative-templates/wedding.jpg');
  expect(screen.getByText('تعذّر تحميل الصورة الناتجة. هذه صورة القالب الأصلي.')).toBeOnTheScreen();

  fireEvent(image(), 'error');
  expect(screen.queryByLabelText('صورة معاينة الدعوة')).toBeNull();
  expect(screen.getByText('تعذّر تحميل صورة المعاينة.')).toBeOnTheScreen();
});

test('requests generated artwork through the active testing tunnel', () => {
  api.defaults.baseURL = 'https://test.ngrok-free.app/api';
  try {
    render(<InvitationPreviewModal {...props} />);
    expect(screen.getByLabelText('صورة معاينة الدعوة')).toHaveProp('source', {
      uri: 'https://test.ngrok-free.app/storage/notifications/cards/preview.jpg',
      headers: { 'ngrok-skip-browser-warning': 'true' },
    });
    expect(resolveCreativeArtworkUrl('https://alsorayia.com/storage/photos/member.jpg'))
      .toBe('https://alsorayia.com/storage/photos/member.jpg');
  } finally {
    api.defaults.baseURL = 'http://192.168.43.249:8000/api';
  }
});

test('uses the loaded aspect ratio to size a wide card', () => {
  render(<InvitationPreviewModal {...props} />);
  const image = screen.getByLabelText('صورة معاينة الدعوة');
  fireEvent(image, 'layout', { nativeEvent: { layout: { width: 300 } } });
  fireEvent(image, 'load', { source: { width: 900, height: 300 } });
  expect(image).toHaveStyle({ height: 100 });
});
