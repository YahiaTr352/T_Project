import { Ionicons } from '@expo/vector-icons';
import { StyleProp, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyProfilePayload } from '@/lib/api/myProfile';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { SectionHeader } from './ui';
import { styles } from '../styles';

export function ParentsCard({ data, cardStyle }: {
  data: MyProfilePayload;
  cardStyle: StyleProp<ViewStyle>;
}) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
          <View style={cardStyle}>
            <SectionHeader
              icon="git-network-outline"
              title="النسب والوالدان"
              subtitle="للعرض التوثيقي فقط من شجرة العائلة"
            />
            <View style={styles.parentRow}>
              <View style={[styles.parentBox, { borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: isDark ? '#1E293B' : '#F8FAFC' }]}>
                <View style={styles.parentHeader}>
                  <Text style={styles.parentLabel}>الأب</Text>
                  <Ionicons name="lock-closed" size={12} color={colors.primary} />
                </View>
                <Text style={[styles.parentValue, { color: colors.text }]}>{data.parents.father?.full_name ?? '—'}</Text>
              </View>

              <View style={[styles.parentBox, { borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: isDark ? '#1E293B' : '#F8FAFC' }]}>
                <View style={styles.parentHeader}>
                  <Text style={styles.parentLabel}>الأم</Text>
                  <Ionicons name="lock-closed" size={12} color={colors.primary} />
                </View>
                <Text style={[styles.parentValue, { color: colors.text }]}>{data.parents.mother?.full_name ?? '—'}</Text>
              </View>
            </View>
          </View>
  );
}

export function PrivacyBanner({ setShowPrivacyModal }: {
  setShowPrivacyModal: (value: boolean | ((prev: boolean) => boolean)) => void;
}) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
            <TouchableOpacity
              activeOpacity={0.82}
              onPress={() => setShowPrivacyModal(true)}
              style={[
                styles.privacyHeroBanner,
                {
                  backgroundColor: isDark ? colors.card : '#FFFFFF',
                  borderColor: isDark ? '#26382E' : '#E2E8F0',
                },
              ]}>
              <View style={styles.privacyBannerInner}>
                <View
                  style={[
                    styles.privacyShieldWrap,
                    { backgroundColor: isDark ? 'rgba(5, 150, 105, 0.16)' : '#ECFDF5' },
                  ]}>
                  <Ionicons name="shield-checkmark" size={23} color={isDark ? '#34D399' : '#059669'} />
                </View>

                <View style={styles.privacyTextCol}>
                  <View style={styles.privacyTitleRow}>
                    <Text style={[styles.privacyBannerTitle, { color: colors.text }]}>
                      إعدادات الخصوصية والظهور
                    </Text>
                    <View
                      style={[
                        styles.privacyBadgeActive,
                        { backgroundColor: isDark ? 'rgba(5, 150, 105, 0.22)' : '#D1FAE5' },
                      ]}>
                      <Text
                        style={[
                          styles.privacyBadgeActiveText,
                          { color: isDark ? '#34D399' : '#047857' },
                        ]}>
                        درع الأمان
                      </Text>
                    </View>
                  </View>
                  <Text style={[styles.privacyBannerSubtitle, { color: colors.icon }]}>
                    تحكم في ظهور صورتك، ميلادك، وبيانات التواصل
                  </Text>
                </View>

                <View
                  style={[
                    styles.privacyActionPill,
                    {
                      backgroundColor: isDark ? 'rgba(5, 150, 105, 0.14)' : '#F0FDF4',
                      borderColor: isDark ? 'rgba(5, 150, 105, 0.3)' : '#BBF7D0',
                    },
                  ]}>
                  <Text
                    style={[
                      styles.privacyActionPillText,
                      { color: isDark ? '#34D399' : '#059669' },
                    ]}>
                    تعديل
                  </Text>
                  <Ionicons
                    name="chevron-back"
                    size={13}
                    color={isDark ? '#34D399' : '#059669'}
                  />
                </View>
              </View>
            </TouchableOpacity>
  );
}
