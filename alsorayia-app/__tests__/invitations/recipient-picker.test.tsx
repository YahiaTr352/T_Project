import { RecipientPickerModal } from '@/components/invitations/RecipientPickerModal';
import { Colors } from '@/constants/theme';
import { fetchInvitationTreePicker, searchInvitationPersons, type InvitationTreeNode } from '@/lib/api/invitations';
import { fireEvent, render, screen } from '@testing-library/react-native';

jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 0, bottom: 0 }) }));
jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/invitations/PersonAvatar', () => ({ PersonAvatar: () => null }));
jest.mock('@/lib/api/invitations', () => ({
  fetchInvitationTreePicker: jest.fn(),
  searchInvitationPersons: jest.fn(),
  resolveInvitationPhotoUrl: (url: string | null) => url,
}));

test('hides the sender from the tree and search without hiding their descendants', async () => {
  const member: InvitationTreeNode = {
    id: 2, first_name: 'عضو آخر', photo_url: null, gender: 'male', has_whatsapp: true,
    selectable: true, is_deceased: false, whatsapp_descendant_count: 0, expandable: false, children: [],
  };
  const sender: InvitationTreeNode = {
    ...member, id: 1, first_name: 'العضو المرسل', children: [member], expandable: true, whatsapp_descendant_count: 1,
  };
  jest.mocked(fetchInvitationTreePicker).mockResolvedValue({ success: true, data: { tree: [sender] } });
  jest.mocked(searchInvitationPersons).mockResolvedValue({ success: true, data: { persons: [sender, member] } });
  const onConfirm = jest.fn();

  render(<RecipientPickerModal visible onClose={jest.fn()} onConfirm={onConfirm} initialSelected={[sender]}
    excludedPersonId={sender.id} colors={Colors.light} isDark={false} />);

  expect(await screen.findByText(member.first_name)).toBeOnTheScreen();
  expect(screen.queryByText(sender.first_name)).toBeNull();
  fireEvent.press(screen.getByText('📋 البحث والقائمة'));
  fireEvent.changeText(screen.getByPlaceholderText('ابحث بالاسم...'), 'عضو');
  expect(await screen.findByText(member.first_name)).toBeOnTheScreen();
  expect(screen.queryByText(sender.first_name)).toBeNull();
  fireEvent.press(screen.getByText(member.first_name));
  fireEvent.press(screen.getByText('✅ تأكيد واختيار (1) أفراد'));
  expect(onConfirm).toHaveBeenCalledWith([{ id: member.id, first_name: member.first_name, photo_url: null }]);
});
