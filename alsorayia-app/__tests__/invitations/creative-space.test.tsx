import { CreativeSpace } from '@/components/invitations/CreativeSpace';
import { CreativePickerModal } from '@/components/invitations/composer/CreativePickerModal';
import { InvitationTabBar } from '@/components/invitations/InvitationTabBar';
import { Colors } from '@/constants/theme';
import { fetchMyCreatives, uploadCreative } from '@/lib/api/invitations';
import { ImageViewerModal } from '@/components/ui/zoomable-image';
import type { CreativeDesignMode, CreativeTemplate } from '@/lib/api/invitations';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { useState } from 'react';
import { I18nManager } from 'react-native';

jest.mock('expo-router', () => ({
  useFocusEffect: (callback: () => void) => jest.requireActual('react').useEffect(callback, [callback]),
}));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 0, bottom: 0 }) }));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-image', () => ({
  Image: (props: object) => jest.requireActual('react').createElement(jest.requireActual('react-native').View, {
    ...props, testID: 'creative-test-image',
  }),
}));
jest.mock('@/components/ui/icon-symbol', () => ({ IconSymbol: () => null }));
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/ui/AlertModal', () => ({ CustomAlert: { alert: jest.fn() } }));
jest.mock('expo-image-picker', () => ({
  requestMediaLibraryPermissionsAsync: async () => ({ granted: true }),
  launchImageLibraryAsync: async () => ({ canceled: false, assets: [{ uri: 'file:///design.png' }] }),
}));
jest.mock('@/lib/api/invitations', () => ({
  fetchMyCreatives: jest.fn(async () => ({ success: true, data: { templates: [] } })),
  creativePreviewImage: ({ preview_image_url, image_url }: CreativeTemplate) => {
    const uri = preview_image_url || image_url;
    return uri ? { uri } : null;
  },
  uploadCreative: jest.fn(async () => ({ success: true })),
}));

const types = {
  templates: [{ key: 'wedding', name: 'زفاف', title: 'دعوة زفاف', body: '' }],
  loading: false,
  error: null,
  identityRequired: false,
  profileIncomplete: false,
  underageBlocked: false,
  blocked: false,
  reload: jest.fn(),
};

test('requires explicit consent before submitting the selected artwork and invitation type', async () => {
  render(<CreativeSpace active types={types} />);
  fireEvent.press(screen.getByRole('button', { name: 'صورة ثابتة' }));
  fireEvent.press(screen.getByRole('button', { name: 'زفاف' }));
  fireEvent.press(screen.getByRole('button', { name: 'اختيار صورة التصميم' }));
  await screen.findByRole('button', { name: 'تغيير صورة التصميم' });

  const submitButton = () => screen.getByRole('button', { name: 'إرسال للمراجعة' });
  expect(submitButton()).toBeDisabled();
  expect(uploadCreative).not.toHaveBeenCalled();
  fireEvent.press(screen.getByRole('checkbox'));
  expect(screen.queryByText('محرر القالب')).toBeNull();
  expect(submitButton()).toBeEnabled();
  fireEvent.press(submitButton());

  await waitFor(() => expect(uploadCreative).toHaveBeenCalledWith(
    { uri: 'file:///design.png' },
    { name: '', invitationType: 'wedding', publicationConsent: true, designMode: 'ready' },
  ));
  expect(await screen.findByText('تم إرسال التصميم إلى الإدارة للمراجعة.')).toBeOnTheScreen();
});

test('formats variables and phrases within the same invitation text block', async () => {
  const box = { x: 8, y: 8, width: 84, height: 48, fontSize: 8 };
  const segments = [
    { key: 'text_0', text: 'تقام المناسبة في ', label: 'عبارة من نص الدعوة', variable: false },
    { key: 'date', text: '{date}', label: 'التاريخ', variable: true },
  ];
  render(<CreativeSpace active types={{ ...types, templates: [{
    ...types.templates[0], overlay_fields: [
      { key: 'message', label: 'نص الدعوة', sample: 'تقام المناسبة في {date}', box, segments },
    ],
  }] }} />);
  fireEvent.press(screen.getByRole('button', { name: 'قالب قابل للتخصيص' }));
  fireEvent.press(screen.getByRole('button', { name: 'زفاف' }));
  fireEvent.press(screen.getByRole('button', { name: 'اختيار صورة التصميم' }));
  await screen.findByText('محرر القالب');
  expect(screen.getByTestId('creative-preview-message')).toHaveTextContent('تقام المناسبة في {date}');
  expect(screen.getByTestId('creative-editable-invitation-text')).toHaveStyle({ direction: 'rtl', writingDirection: 'rtl' });
  expect(screen.queryByText('عنوان الدعوة')).toBeNull();
  expect(screen.queryByText('المتغيرات')).toBeNull();
  expect(screen.queryByRole('button', { name: 'كتابة ثابتة' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'ضبط الموضع بالأرقام' })).toBeNull();
  fireEvent.press(screen.getByRole('button', { name: 'زيادة حجم الخط' }));
  fireEvent.press(screen.getByLabelText('التاريخ'));
  fireEvent.press(screen.getByRole('checkbox', { name: 'غامق' }));
  fireEvent.press(screen.getByRole('checkbox', { name: 'مائل' }));
  expect(screen.getByLabelText('التاريخ')).toHaveStyle({ fontStyle: 'italic' });
  fireEvent.press(screen.getByRole('checkbox', { name: 'مائل' }));
  expect(screen.getByLabelText('التاريخ')).toHaveStyle({ fontStyle: 'normal' });
  fireEvent.press(screen.getByRole('checkbox', { name: 'مائل' }));
  fireEvent.press(screen.getByRole('checkbox', { name: 'تحته خط' }));
  expect(screen.getByLabelText('التاريخ')).toHaveStyle({ textDecorationLine: 'underline' });
  expect(screen.queryByLabelText('الموقع من الأعلى (%)')).toBeNull();
  fireEvent.changeText(screen.getByLabelText('الإزاحة الأفقية (%)'), '4');
  fireEvent.changeText(screen.getByLabelText('الإزاحة العمودية (%)'), '-3');
  fireEvent.changeText(screen.getByLabelText('حجم الخط (%)'), '11');
  expect(screen.getByTestId('creative-preview-part-date')).toBeOnTheScreen();
  fireEvent.press(screen.getByLabelText('تقام المناسبة في'));
  fireEvent.press(screen.getByRole('checkbox', { name: 'تحته خط' }));
  fireEvent.press(screen.getByRole('button', { name: 'التنسيق العام' }));
  const position = screen.getByLabelText('الموقع من الأعلى (%)');
  fireEvent.changeText(position, '92');
  expect(screen.getByRole('alert')).toHaveTextContent('نص الدعوة خارج إطار الصورة. عدّل الموضع أو الأبعاد.');
  expect(screen.getByRole('button', { name: 'حفظ التوزيع' })).toBeDisabled();
  fireEvent.changeText(position, '٣٧');
  expect(screen.queryByRole('alert')).toBeNull();
  fireEvent.press(screen.getByRole('button', { name: 'حفظ التوزيع' }));
  fireEvent.press(screen.getByRole('checkbox'));
  fireEvent.press(screen.getByRole('button', { name: 'إرسال للمراجعة' }));
  await waitFor(() => expect(uploadCreative).toHaveBeenLastCalledWith(
    { uri: 'file:///design.png' },
    { name: '', invitationType: 'wedding', publicationConsent: true, designMode: 'overlay', textLayout: {
      message: { ...box, y: 37, fontSize: 8.5, inline: {
        date: { bold: true, italic: true, underline: true, offsetX: 4, offsetY: -3, fontSize: 11 }, text_0: { underline: true },
      } },
    } },
  ));
});

test('shows only the chosen kind of invitation image template', () => {
  const onSelect = jest.fn();
  const previewUrl = 'http://example.test/storage/notifications/cards/overlay-preview.jpg';
  function Picker() {
    const [mode, setMode] = useState<CreativeDesignMode | null>(null);
    return <CreativePickerModal visible onClose={jest.fn()} colors={Colors.light}
      cardBg="#fff" border="#ccc" muted="#666" loading={false} mode={mode} onModeChange={setMode}
      creatives={[
        { id: 1, name: 'بطاقة جاهزة', design_mode: 'ready' },
        { id: 2, name: 'بطاقة قابلة للتحرير', design_mode: 'overlay', image_url: 'http://example.test/overlay.png', preview_image_url: previewUrl },
        { id: 3, name: 'قالب سابق' },
      ]}
      tempCreativeId={null} onSelect={onSelect} onConfirm={jest.fn()} />;
  }

  render(<Picker />);
  expect(screen.queryByText('بطاقة جاهزة')).toBeNull();
  fireEvent.press(screen.getByRole('button', { name: 'صورة ثابتة' }));
  fireEvent.press(screen.getByRole('button', { name: 'بطاقة جاهزة' }));
  expect(onSelect).toHaveBeenCalledWith(1);
  expect(screen.queryByText('بطاقة قابلة للتحرير')).toBeNull();

  fireEvent.press(screen.getByRole('button', { name: 'قالب قابل للتخصيص' }));
  expect(screen.queryByText('بطاقة جاهزة')).toBeNull();
  expect(screen.getByText('بطاقة قابلة للتحرير')).toBeOnTheScreen();
  expect(screen.getByText('قالب سابق')).toBeOnTheScreen();
  expect(screen.getByTestId('creative-test-image')).toHaveProp('source', { uri: previewUrl });
  fireEvent.press(screen.getByRole('button', { name: 'تكبير بطاقة قابلة للتحرير' }));
  expect(screen.UNSAFE_getByType(ImageViewerModal).props.images).toEqual([{ uri: previewUrl, title: 'بطاقة قابلة للتحرير' }]);
  expect(onSelect).toHaveBeenCalledTimes(1);
  fireEvent.press(screen.getByLabelText('إغلاق'));
  expect(screen.getByRole('button', { name: 'بطاقة قابلة للتحرير' })).toBeOnTheScreen();
});

test.each([false, true])('keeps the creative form RTL when the device RTL flag is %s', async (deviceRtl) => {
  const rtlFlag = jest.replaceProperty(I18nManager, 'isRTL', deviceRtl);
  try {
    render(<CreativeSpace active types={types} />);
    await screen.findByText('لسا ما أرسلت أي تصميم.');
    expect(screen.getByTestId('creative-space')).toHaveStyle({ direction: 'rtl' });
    expect(screen.getByLabelText('اسم التصميم')).toHaveStyle({ textAlign: 'right', writingDirection: 'rtl' });
    expect(screen.getByRole('checkbox')).toHaveStyle({ flexDirection: 'row' });
  } finally {
    rtlFlag.restore();
  }
});

test('exposes Creative Space as the fourth invitation tab', () => {
  const onChange = jest.fn();
  const { rerender } = render(<InvitationTabBar active="compose" onChange={onChange} />);
  const creativeTab = screen.getByRole('tab', { name: 'مساحة إبداع' });
  expect(screen.getAllByRole('tab')).toHaveLength(4);
  expect(screen.getAllByRole('tab')[3]).toBe(creativeTab);
  fireEvent.press(creativeTab);
  expect(onChange).toHaveBeenCalledWith('creative');
  rerender(<InvitationTabBar active="creative" onChange={onChange} />);
  expect(screen.getByRole('tab', { name: 'مساحة إبداع', selected: true })).toBeOnTheScreen();
});

test('shows submitted designs without a delete action or a scroll indicator', async () => {
  jest.mocked(fetchMyCreatives).mockResolvedValueOnce({
    success: true,
    data: { templates: [{ id: 1, name: 'بطاقة زفاف', invitation_type: 'wedding', status: 'pending' }] },
  });

  render(<CreativeSpace active types={types} />);

  expect(await screen.findByText('بطاقة زفاف')).toBeOnTheScreen();
  expect(screen.queryByRole('button', { name: 'معاينة بطاقة زفاف' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'حذف التصميم' })).toBeNull();
  expect(screen.getByTestId('creative-space')).toHaveProp('showsVerticalScrollIndicator', false);
});

test('opens and closes the larger preview for the selected design', async () => {
  const previewUrl = 'http://192.168.1.5:8000/storage/notifications/cards/greeting-preview.jpg';
  jest.mocked(fetchMyCreatives).mockResolvedValueOnce({
    success: true,
    data: { templates: [
      { id: 1, name: 'بطاقة زفاف', image_url: 'http://192.168.1.5:8000/storage/creative-templates/wedding.png', status: 'pending' },
      { id: 2, name: 'بطاقة تهنئة', image_url: 'http://192.168.1.5:8000/storage/creative-templates/greeting.png', preview_image_url: previewUrl, status: 'approved' },
    ] },
  });
  render(<CreativeSpace active types={types} />);

  fireEvent.press(await screen.findByRole('button', { name: 'معاينة بطاقة تهنئة' }));
  expect(screen.getByLabelText('إغلاق')).toBeOnTheScreen();
  expect(screen.UNSAFE_getByType(ImageViewerModal).props.images).toEqual([{ uri: previewUrl, title: 'بطاقة تهنئة' }]);
  expect(screen.getAllByText('بطاقة تهنئة')).toHaveLength(2);
  expect(screen.getAllByText('بطاقة زفاف')).toHaveLength(1);
  fireEvent.press(screen.getByLabelText('إغلاق'));
  expect(screen.queryByLabelText('إغلاق')).toBeNull();
});
