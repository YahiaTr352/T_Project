// Fallback for using MaterialIcons on Android and web.

import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import { SymbolWeight } from 'expo-symbols';
import { ComponentProps } from 'react';
import { OpaqueColorValue, type StyleProp, type TextStyle } from 'react-native';

type IconMapping = Record<string, ComponentProps<typeof MaterialIcons>['name']>;
export type IconSymbolName = keyof typeof MAPPING;

/**
 * Add your SF Symbols to Material Icons mappings here.
 * - see Material Icons in the [Icons Directory](https://icons.expo.fyi).
 * - see SF Symbols in the [SF Symbols](https://developer.apple.com/sf-symbols/) app.
 */
const MAPPING = {
  'house.fill': 'home',
  'paperplane.fill': 'send',
  'chevron.left.forwardslash.chevron.right': 'code',
  'chevron.right': 'chevron-right',
  'calendar': 'calendar-today',
  'chart.bar.fill': 'bar-chart',
  'person.fill': 'person',
  'magnifyingglass': 'search',
  'clock.fill': 'access-time',
  'mappin.circle.fill': 'place',
  'person.2.fill': 'people',
  'calendar.badge.exclamationmark': 'event-busy',
  'pencil': 'edit',
  'chevron.left': 'chevron-left',
  'arrow.right.square.fill': 'logout',
  'star.fill': 'star',
  'plus.circle.fill': 'add-circle',
  'bell.fill': 'notifications',
  'gearshape.fill': 'settings',
  'questionmark.circle.fill': 'help',
  'info.circle.fill': 'info',
  'arrow.up.right': 'trending-up',
  'arrow.down.right': 'trending-down',
  'checkmark.circle.fill': 'check-circle',
  'xmark.circle.fill': 'cancel',
  'xmark': 'close',
  'play.fill': 'play-arrow',
  'speaker.wave.2.fill': 'volume-up',
  'doc.on.doc': 'content-copy',
  'arrow.down.to.bracket': 'download',
  'exclamationmark.triangle.fill': 'warning',
  // أيقونات إضافية للصفحة الرئيسية
  'book.fill': 'menu-book',
  'heart.fill': 'favorite',
  'figure.run': 'directions-run',
  'gift.fill': 'card-giftcard',
  'moon.stars.fill': 'nightlight-round',
  'graduationcap.fill': 'school',
  'info.circle': 'info-outline',
  'lock.fill': 'lock',
  'lock.shield.fill': 'verified-user',
  'checkmark': 'check',
  'arrow.left': 'arrow-back',
  'photo.fill': 'photo',
  'photo.stack.fill': 'photo-library',
  'folder.fill': 'folder',
  'doc.fill': 'description',
  'trash.fill': 'delete',
  'share.fill': 'share',
  'square.and.arrow.up': 'share',
  'bookmark.fill': 'bookmark',
  'eye.fill': 'visibility',
  'eye.slash.fill': 'visibility-off',
  'cart.fill': 'storefront',
  'link': 'link',
  'link.circle.fill': 'link',
  'play.circle.fill': 'play-circle-outline',
  'arrow.up.right.square.fill': 'open-in-new',
  'envelope.fill': 'mail',
  'camera.fill': 'camera-alt',
  'phone.fill': 'phone',
  'whatsapp': 'chat',
  'sparkles': 'auto-awesome',
  'face.smiling': 'face',
  'shield.fill': 'shield',
  'key.fill': 'vpn-key',
  'arrow.clockwise': 'refresh',
  'person.crop.circle.fill': 'account-circle',
  'person.crop.circle': 'account-circle',
  'person.crop.circle.badge.plus': 'person-add',
  'person.badge.plus': 'person-add',
  'person.add.fill': 'person-add',
  'plus': 'add',
  'chevron.down': 'keyboard-arrow-down',
  'keyboard.chevron.compact.down': 'keyboard-hide',
  'checkmark.shield.fill': 'security',
  'checkmark.seal.fill': 'verified',
  'list.number': 'format-list-numbered',
} as unknown as IconMapping;

/**
 * An icon component that uses native SF Symbols on iOS, and Material Icons on Android and web.
 * This ensures a consistent look across platforms, and optimal resource usage.
 * Icon `name`s are based on SF Symbols and require manual mapping to Material Icons.
 */
export function IconSymbol({
  name,
  size = 24,
  color,
  style,
}: {
  name: IconSymbolName;
  size?: number;
  color: string | OpaqueColorValue;
  style?: StyleProp<TextStyle>;
  weight?: SymbolWeight;
}) {
  const iconName = (MAPPING[name] || 'help-outline') as ComponentProps<typeof MaterialIcons>['name'];
  return <MaterialIcons color={color} size={size} name={iconName} style={style} />;
}
