import { CreativeSpace } from '@/components/invitations/CreativeSpace';
import { CreativePickerModal } from '@/components/invitations/composer/CreativePickerModal';
import { PreparedArtworkEditor } from '@/components/invitations/composer/PreparedArtworkEditor';
import { InvitationTabBar } from '@/components/invitations/InvitationTabBar';
import { Colors } from '@/constants/theme';
import { fetchMyCreatives, previewCreativeUpload, uploadCreative } from '@/lib/api/invitations';
import { ImageViewerModal } from '@/components/ui/zoomable-image';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nManager, StyleSheet } from 'react-native';

jest.mock('axios', () => ({ isAxiosError: jest.fn() }));
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
jest.mock('@/lib/api/alsorayiaClient', () => ({
  api: { defaults: { baseURL: 'https://backend.test/api' } },
  apiUpload: jest.fn(),
  WEB_BASE_URL: 'https://alsorayia.com',
}));
jest.mock('@/lib/api/invitations', () => ({
  ...jest.requireActual('@/lib/api/invitations'),
  fetchMyCreatives: jest.fn(async () => ({ success: true, data: { templates: [] } })),
  previewCreativeUpload: jest.fn(),
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
  expect(screen.queryByRole('button', { name: 'صورة ثابتة' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'قالب قابل للتخصيص' })).toBeNull();
  fireEvent.press(screen.getByRole('button', { name: 'زفاف' }));
  fireEvent.press(screen.getByRole('button', { name: 'اختيار صورة التصميم' }));
  await screen.findByRole('button', { name: 'تغيير صورة التصميم' });

  const submitButton = () => screen.getByRole('button', { name: 'إرسال للمراجعة' });
  expect(submitButton()).toBeDisabled();
  expect(uploadCreative).not.toHaveBeenCalled();
  fireEvent.press(screen.getByRole('checkbox'));
  expect(screen.queryByRole('header', { name: 'محرّر المتغيّرات' })).toBeNull();
  expect(submitButton()).toBeEnabled();
  fireEvent.press(submitButton());

  await waitFor(() => expect(uploadCreative).toHaveBeenCalledWith(
    { uri: 'file:///design.png' },
    { name: '', invitationType: 'wedding', publicationConsent: true },
  ));
  expect(await screen.findByText('تم إرسال التصميم إلى الإدارة للمراجعة.')).toBeOnTheScreen();
});

test('opens the variable editor on request and submits the reviewed text and position', async () => {
  const proof = 'a'.repeat(64);
  const design = 'data:image/png;base64,design';
  jest.mocked(previewCreativeUpload).mockResolvedValueOnce({ success: true, data: { design, proof } });
  render(<CreativeSpace active types={types} />);
  const button = () => screen.getByRole('button', { name: 'فتح محرّر المتغيّرات' });
  expect(button()).toBeDisabled();
  fireEvent.press(screen.getByRole('button', { name: 'زفاف' }));
  fireEvent.press(screen.getByRole('button', { name: 'اختيار صورة التصميم' }));
  await screen.findByRole('button', { name: 'تغيير صورة التصميم' });
  expect(screen.getByTestId('creative-test-image')).toHaveProp('source', { uri: 'file:///design.png' });
  expect(screen.queryByRole('header', { name: 'محرّر المتغيّرات' })).toBeNull();
  fireEvent.press(button());
  fireEvent(screen.getByLabelText('صورة التصميم الأصلية'), 'load', { source: { width: 800, height: 600 } });
  const stage = screen.getByTestId('creative-upload-stage');
  const { width, height } = StyleSheet.flatten(stage.props.style);
  fireEvent.press(screen.getByRole('button', { name: 'إضافة حقل' }));
  fireEvent(stage, 'responderGrant', { nativeEvent: { locationX: .26 * width, locationY: .36 * height } });
  fireEvent(stage, 'responderRelease', { nativeEvent: { locationX: .69 * width, locationY: .5 * height } });
  expect(screen.getAllByLabelText('النص')).toHaveLength(1);
  expect(screen.getByPlaceholderText('اكتب اسم الحقل')).toBeOnTheScreen();
  expect(screen.getByPlaceholderText('اكتب النص هنا')).toBeOnTheScreen();
  const size = () => screen.getByText(/بكسل/);
  const originalSize = Number.parseInt(String(size().props.children[0]), 10);
  fireEvent.press(screen.getByRole('button', { name: 'تكبير حجم الخط' }));
  expect(screen.getByText(`${originalSize + 1} بكسل`)).toBeOnTheScreen();
  expect(screen.getByText('+')).toBeOnTheScreen();
  fireEvent.press(screen.getByRole('button', { name: 'تصغير حجم الخط' }));
  expect(screen.getByText(`${originalSize} بكسل`)).toBeOnTheScreen();
  expect(screen.getByText('−')).toBeOnTheScreen();
  expect(screen.queryByLabelText('نص بديل للمعاينة')).toBeNull();
  expect(screen.queryByLabelText('استبدال الكتابة المطبوعة داخل هذا الحقل')).toBeNull();
  expect(screen.getByText('اسم الحقل')).toHaveStyle({ writingDirection: 'rtl' });
  const save = () => screen.getByRole('button', { name: 'حفظ المتغيّرات' });
  expect(save()).toBeDisabled();
  fireEvent.changeText(screen.getByLabelText('اسم الحقل'), 'اسم المدعو');
  fireEvent.changeText(screen.getByLabelText('النص'), 'أحمد');
  fireEvent.press(screen.getByRole('button', { name: 'أميري' }));
  const colorButton = screen.getByRole('button', { name: 'اختيار لون الخط' });
  expect(colorButton).toHaveAccessibilityValue({ text: '#ffffff' });
  expect(screen.queryByLabelText('رمز لون الخط')).toBeNull();
  fireEvent.press(colorButton);
  const hue = screen.getByTestId('creative-color-hue');
  const plane = screen.getByTestId('creative-color-plane');
  fireEvent(hue, 'layout', { nativeEvent: { layout: { width: 120 } } });
  fireEvent(plane, 'layout', { nativeEvent: { layout: { width: 220 } } });
  fireEvent(hue, 'responderGrant', { nativeEvent: { locationX: 40 } });
  fireEvent(hue, 'responderRelease');
  fireEvent(plane, 'responderGrant', { nativeEvent: { locationX: 220, locationY: 74 } });
  fireEvent(plane, 'responderRelease');
  expect(screen.getByRole('button', { name: 'اختيار لون الخط' })).toHaveAccessibilityValue({ text: '#008000' });
  fireEvent.press(screen.getByRole('button', { name: 'معاينة التصميم' }));
  const preview = await screen.findByLabelText('معاينة التصميم مع النصوص');
  const fields = jest.mocked(previewCreativeUpload).mock.lastCall![1];
  expect(fields).toEqual([{
    id: 1, label: 'اسم المدعو', text: 'أحمد', x: expect.closeTo(26), y: expect.closeTo(36),
    width: expect.closeTo(43), height: expect.closeTo(14), fontFamily: 'amiri', fontSize: expect.any(Number),
    color: '#008000', bold: false, alignment: 'center',
  }]);
  expect(save()).toBeDisabled();
  fireEvent(preview, 'load');
  fireEvent.press(save());
  expect(screen.getByText('الحقول المحفوظة (1)')).toBeOnTheScreen();
  fireEvent.press(button());
  expect(screen.getByLabelText('النص')).toHaveDisplayValue('أحمد');
  fireEvent.press(screen.getByRole('button', { name: 'إلغاء' }));
  fireEvent.press(screen.getByRole('checkbox'));
  fireEvent.press(screen.getByRole('button', { name: 'إرسال للمراجعة' }));
  await waitFor(() => expect(uploadCreative).toHaveBeenLastCalledWith(
    { uri: 'file:///design.png' },
    { name: '', invitationType: 'wedding', publicationConsent: true, prepared: { fields, proof } },
  ));
  expect(await screen.findByText('تم إرسال التصميم إلى الإدارة للمراجعة.')).toBeOnTheScreen();
});

test('shows image templates and previews without requiring a mode selection', () => {
  const onSelect = jest.fn();
  const previewUrl = 'http://example.test/storage/notifications/cards/overlay-preview.jpg';
  render(<CreativePickerModal visible onClose={jest.fn()} colors={Colors.light}
      cardBg="#fff" border="#ccc" muted="#666" loading={false}
      creatives={[
        { id: 2, name: 'بطاقة قابلة للتحرير', design_mode: 'overlay', image_url: 'http://example.test/overlay.png', preview_image_url: previewUrl },
        { id: 3, name: 'قالب سابق' },
      ]}
      tempCreativeId={null} onSelect={onSelect} onConfirm={jest.fn()} />);
  expect(screen.queryByRole('button', { name: 'قالب قابل للتخصيص' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'صورة ثابتة' })).toBeNull();
  fireEvent.press(screen.getByRole('button', { name: 'بطاقة قابلة للتحرير' }));
  expect(onSelect).toHaveBeenCalledWith(2);
  expect(screen.getByText('قالب سابق')).toBeOnTheScreen();
  expect(screen.getByTestId('creative-test-image')).toHaveProp('source', { uri: previewUrl });
  fireEvent.press(screen.getByRole('button', { name: 'تكبير بطاقة قابلة للتحرير' }));
  expect(screen.UNSAFE_getByType(ImageViewerModal).props.images).toEqual([{ uri: previewUrl, title: 'بطاقة قابلة للتحرير' }]);
  expect(onSelect).toHaveBeenCalledTimes(1);
  fireEvent.press(screen.getByLabelText('إغلاق'));
  expect(screen.getByRole('button', { name: 'بطاقة قابلة للتحرير' })).toBeOnTheScreen();
  fireEvent(screen.getByTestId('creative-test-image'), 'error');
  expect(screen.getByTestId('creative-test-image')).toHaveProp('source', { uri: 'http://example.test/overlay.png' });
});

test.each([false, true])('keeps the creative form RTL when the device RTL flag is %s', async (deviceRtl) => {
  const rtlFlag = jest.replaceProperty(I18nManager, 'isRTL', deviceRtl);
  try {
    render(<CreativeSpace active types={types} />);
    await screen.findByText('لسا ما أرسلت أي تصميم.');
    expect(screen.getByTestId('creative-space')).toHaveStyle({ direction: 'rtl' });
    expect(screen.getByLabelText('اسم التصميم')).toHaveStyle({ textAlign: 'right', writingDirection: 'rtl' });
    expect(screen.getByRole('checkbox')).toHaveStyle({ flexDirection: 'row' });
    fireEvent.press(screen.getByRole('button', { name: 'اختيار صورة التصميم' }));
    await screen.findByRole('button', { name: 'تغيير صورة التصميم' });
    fireEvent.press(screen.getByRole('button', { name: 'فتح محرّر المتغيّرات' }));
    expect(screen.getByTestId('creative-upload-editor')).toHaveStyle({ direction: 'rtl' });
    expect(screen.getByRole('header', { name: 'محرّر المتغيّرات' })).toHaveStyle({ writingDirection: 'rtl' });
    expect(screen.getByTestId('creative-upload-stage')).toHaveStyle({ direction: 'ltr' });
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
  const previewPath = '/storage/notifications/cards/greeting-preview.jpg';
  const previewUrl = `https://backend.test${previewPath}`;
  jest.mocked(fetchMyCreatives).mockResolvedValueOnce({
    success: true,
    data: { templates: [
      { id: 1, name: 'بطاقة زفاف', image_url: 'http://192.168.1.5:8000/storage/creative-templates/wedding.png', status: 'pending' },
      { id: 2, name: 'بطاقة تهنئة', image_url: 'http://192.168.1.5:8000/storage/creative-templates/greeting.png', preview_image_url: previewPath, status: 'approved' },
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
  const images = () => screen.getAllByTestId('creative-test-image');
  fireEvent(images().find((image) => image.props.source.uri === previewUrl)!, 'error');
  expect(images().map((image) => image.props.source.uri)).toContain('https://backend.test/storage/creative-templates/greeting.png');
});

test('edits a prepared field and requires a rendered preview before applying', async () => {
  const onApply = jest.fn();
  const uri = 'data:image/jpeg;base64,prepared';
  const renderPreview = jest.fn()
    .mockRejectedValueOnce(new Error('النص لا يتّسع في الحقل'))
    .mockResolvedValueOnce(uri);
  render(<PreparedArtworkEditor imageUrl="/storage/creative-templates/card.jpg"
    fields={[{ key: 'creative_text_1', label: 'اسم المدعو', default_text: 'أحمد محمد', x: 20, y: 30, width: 50, height: 12 }]}
    values={{}} colors={Colors.light} background="#fff" border="#ccc"
    onClose={jest.fn()} onApply={onApply} renderPreview={renderPreview} />);

  fireEvent(screen.getByLabelText('صورة القالب'), 'load', { source: { width: 600, height: 400 } });
  expect(screen.getByRole('button', { name: 'تعديل اسم المدعو' })).toHaveStyle({ left: '20%', top: '30%' });
  const input = screen.getByLabelText('اسم المدعو');
  expect(input).toHaveDisplayValue('أحمد محمد');
  fireEvent.changeText(input, 'اسم أطول من المساحة المحجوزة للحقل');
  expect(await screen.findByText('النص لا يتّسع في الحقل')).toBeOnTheScreen();
  const apply = () => screen.getByRole('button', { name: 'تطبيق التعديلات' });
  expect(apply()).toBeDisabled();

  fireEvent.changeText(input, 'سارة');
  const rendered = await screen.findByLabelText('معاينة الكتابة المعدّلة');
  expect(renderPreview).toHaveBeenLastCalledWith({ creative_text_1: 'سارة' }, expect.any(AbortSignal));
  expect(apply()).toBeDisabled();
  fireEvent(rendered, 'load');
  fireEvent.press(apply());
  expect(onApply).toHaveBeenCalledWith({ creative_text_1: 'سارة' }, uri);
});
