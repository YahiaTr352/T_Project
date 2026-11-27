import { AnimatedBubbles } from '@/components/animated-bubbles';
import { CreativeSpace } from '@/components/invitations/CreativeSpace';
import { GroupsTab } from '@/components/invitations/GroupsTab';
import { InvitationComposer } from '@/components/invitations/InvitationComposer';
import { InvitationGradientButton, InvitationHeroBanner } from '@/components/invitations/InvitationPrimitives';
import { InvitationTabBar, type InvitationTabKey } from '@/components/invitations/InvitationTabBar';
import { LogsTab } from '@/components/invitations/LogsTab';
import {
  cardShadowStyle,
  getInvitationPalette,
  INVITATION_IMAGES,
} from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, type ThemeColors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { useInvitationGroups } from '@/hooks/use-invitation-groups';
import { useInvitationLogs } from '@/hooks/use-invitation-logs';
import { useInvitationTemplates } from '@/hooks/use-invitation-templates';
import type { InvitationLogDirection } from '@/lib/api/invitations';
import {
  getInvitationEligibility,
  missingFieldsLabel,
} from '@/lib/utils/invitationEligibility';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function InvitesScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const colors: ThemeColors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const palette = getInvitationPalette(isDark);
  const { hasIdentity, user, lock } = useAuth();
  const [tab, setTab] = useState<InvitationTabKey>('compose');
  const [creativeOpened, setCreativeOpened] = useState(false);
  const [logDirection, setLogDirection] = useState<InvitationLogDirection>('outgoing');

  // الأهلية المحلية: هوية (وجه/واتساب) + عمر 20+ + بيانات أساسية (الاسم + الميلاد)
  const eligibility = getInvitationEligibility(hasIdentity, user);

  const templates = useInvitationTemplates(hasIdentity);
  const groups = useInvitationGroups(hasIdentity);
  const logs = useInvitationLogs(hasIdentity);
  const incomingLogs = useInvitationLogs(hasIdentity && tab === 'logs' && logDirection === 'incoming', 'incoming');
  const activeLogs = logDirection === 'incoming' ? incomingLogs : logs;

  const serverIdentityBlocked =
    templates.identityRequired || groups.identityRequired || logs.identityRequired;
  const serverIncompleteBlocked =
    (templates as any).profileIncomplete ||
    (groups as any).profileIncomplete ||
    (logs as any).profileIncomplete;
  const serverUnderageBlocked =
    (templates as any).underageBlocked ||
    (groups as any).underageBlocked ||
    (logs as any).underageBlocked;

  const serverBlockMessage =
    templates.error || groups.error || logs.error || null;

  const showUnder20 =
    eligibility.reason === 'under20' || serverUnderageBlocked;
  const showIdentity =
    !showUnder20 &&
    (eligibility.reason === 'identity' || serverIdentityBlocked);
  const showIncomplete =
    !showUnder20 &&
    !showIdentity &&
    (eligibility.reason === 'incomplete' || serverIncompleteBlocked);

  const missingLabel = missingFieldsLabel(
    eligibility.reason === 'incomplete' ? eligibility.missing : []
  );

  const handleSent = () => {
    void logs.refresh();
    void groups.refresh();
    setLogDirection('outgoing');
    setTab('logs');
  };

  const selectTab = (next: InvitationTabKey) => {
    if (next === tab) return;
    Keyboard.dismiss();
    if (next === 'creative') setCreativeOpened(true);
    setTab(next);
  };

  const composing = tab === 'compose';
  const showingCreative = tab === 'creative';

  return (
    <LinearGradient colors={palette.bgGradient} style={styles.container}>
      <AnimatedBubbles bubbleCount={5} minSize={48} maxSize={120} />

      {/* الهيدر الموحد للدعوات */}
      <View style={[styles.header, { paddingTop: Math.max(insets.top + 6, 14) }]}>
        <View style={styles.headerStart}>
          <Pressable
            onPress={() => router.back()}
            accessibilityRole="button"
            accessibilityLabel="رجوع"
            style={({ pressed }) => [
              styles.backBtn,
              cardShadowStyle(palette),
              {
                backgroundColor: palette.headerBtnBg,
                borderColor: palette.headerBtnBorder,
                opacity: pressed ? 0.78 : 1,
              },
            ]}>
            <IconSymbol name="chevron.right" size={20} color={palette.primaryDark === '#0B4D31' && isDark ? '#ECFDF5' : palette.primary} />
          </Pressable>
          <ThemedText style={[styles.headerTitle, { color: palette.text }]}>الدعوات</ThemedText>
        </View>

        <View style={[styles.logoRing, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: isDark ? '#2A634C' : '#C9A962' }]}>
          <Image source={INVITATION_IMAGES.familyLogo} style={styles.logoImg} contentFit="cover" />
        </View>
      </View>

      {showUnder20 ? (
        <View style={styles.centered}>
          <View style={[styles.gateCard, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <View style={[styles.gateIconWrap, { backgroundColor: palette.warningBg, borderColor: palette.warningBorder }]}>
              <IconSymbol name="exclamationmark.triangle.fill" size={38} color={palette.warningText} />
            </View>
            <ThemedText style={[styles.gateTitle, { color: palette.text }]}>غير متاح لعمرك</ThemedText>
            <ThemedText style={[styles.gateText, { color: palette.muted }]}>
              ميزة إرسال الدعوات متاحة فقط لمن بلغ 20 سنة فأكثر.
            </ThemedText>
          </View>
        </View>
      ) : showIdentity ? (
        <View style={styles.centered}>
          <View style={[styles.gateCard, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <View style={[styles.gateIconWrap, { backgroundColor: palette.surfaceSoft, borderColor: palette.borderStrong }]}>
              <IconSymbol name="lock.shield.fill" size={38} color={palette.primary} />
            </View>
            <ThemedText style={[styles.gateTitle, { color: palette.text }]}>توثيق الهوية مطلوب</ThemedText>
            <ThemedText style={[styles.gateText, { color: palette.muted }]}>
              مجموعات الدعوات والإرسال متاحة بعد تسجيل الدخول ببصمة الوجه أو واتساب.
              {'\n'}الدخول برمز الموقع فقط لا يتيح إرسال الدعوات.
            </ThemedText>
            <InvitationGradientButton
              label="توثيق الهوية الآن"
              icon="lock-closed-outline"
              onPress={() => void lock()}
              palette={palette}
              style={{ width: '100%' }}
            />
          </View>
        </View>
      ) : showIncomplete ? (
        <View style={styles.centered}>
          <View style={[styles.gateCard, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <View style={[styles.gateIconWrap, { backgroundColor: palette.warningBg, borderColor: palette.warningBorder }]}>
              <IconSymbol name="person.crop.circle.badge.exclamationmark" size={38} color={palette.warningText} />
            </View>
            <ThemedText style={[styles.gateTitle, { color: palette.text }]}>أكمل بياناتك الأساسية أولاً</ThemedText>
            <ThemedText style={[styles.gateText, { color: palette.muted }]}>
              {missingLabel
                ? `لا يمكن إرسال الدعوات لأن بياناتك الأساسية ناقصة: ${missingLabel}.`
                : serverBlockMessage ?? 'بياناتك الأساسية غير مكتملة.'}
              {'\n'}المطلوب فقط: الاسم + تاريخ الميلاد (البيانات الفرعية مثل السيرة والوظيفة ليست شرطاً).
            </ThemedText>
            <InvitationGradientButton
              label="إكمال البيانات الآن"
              icon="person-outline"
              onPress={() => router.push('/(app)/my-profile' as any)}
              palette={palette}
              style={{ width: '100%' }}
            />
          </View>
        </View>
      ) : (
        <View style={styles.body}>
          <InvitationTabBar
            active={tab}
            onChange={selectTab}
            groupsCount={groups.groups.length}
          />

          {composing || showingCreative ? (
            <KeyboardAvoidingView
              style={[styles.flex, !composing && styles.hidden]}
              enabled={composing}
              behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
              keyboardVerticalOffset={Platform.OS === 'ios' ? 92 : 0}
            >
              <InvitationComposer
                templates={templates.templates}
                templatesLoading={templates.loading}
                groups={groups.groups}
                senderId={user?.id}
                colors={colors}
                isDark={isDark}
                onSent={handleSent}
              />
            </KeyboardAvoidingView>
          ) : null}

          {creativeOpened ? (
            <KeyboardAvoidingView
              style={[styles.flex, !showingCreative && styles.hidden]}
              enabled={showingCreative}
              behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
              keyboardVerticalOffset={Platform.OS === 'ios' ? 92 : 0}
            >
              <CreativeSpace active={showingCreative} types={templates} />
            </KeyboardAvoidingView>
          ) : null}

          {tab === 'groups' ? (
            <GroupsTab
              groups={groups.groups}
              loading={groups.loading}
              refreshing={groups.refreshing}
              saving={groups.saving}
              error={groups.error}
              onRefresh={() => void groups.refresh()}
              onCreate={groups.createGroup}
              onUpdate={groups.updateGroup}
              onDelete={groups.deleteGroup}
              onDetach={groups.detachPerson}
              colors={colors}
              isDark={isDark}
            />
          ) : null}

          {tab === 'logs' ? (
            <View style={styles.flex}>
              <InvitationHeroBanner
                title="سجل الدعوات"
                subtitle={
                  logDirection === 'incoming'
                    ? 'الدعوات الواردة إليك من أفراد العائلة'
                    : 'متابعة الدعوات الصادرة وحالة التسليم والردود'
                }
                image={INVITATION_IMAGES.event_invitation}
                palette={palette}
              />

              <View
                style={[
                  styles.logSwitchWrap,
                  cardShadowStyle(palette),
                  { backgroundColor: palette.surface, borderColor: palette.border },
                ]}>
                {([
                  { key: 'outgoing', label: 'الصادر', icon: 'paper-plane-outline' },
                  { key: 'incoming', label: 'الوارد', icon: 'time-outline' },
                ] as const).map(({ key, label, icon }) => {
                  const selected = key === logDirection;
                  return (
                    <Pressable
                      key={key}
                      accessibilityRole="tab"
                      accessibilityState={{ selected }}
                      onPress={() => setLogDirection(key)}
                      style={[
                        styles.logSwitchItem,
                        selected && [
                          styles.logSwitchItemActive,
                          { backgroundColor: palette.isDark ? palette.surfaceSoft : '#F3F8F5', borderColor: palette.borderStrong },
                        ],
                      ]}>
                      <Ionicons
                        name={icon}
                        size={16}
                        color={selected ? palette.primary : palette.muted}
                      />
                      <Text
                        style={[
                          styles.logSwitchLabel,
                          {
                            color: selected ? palette.text : palette.muted,
                            fontFamily: selected ? 'Tajawal-Bold' : 'Tajawal-Medium',
                          },
                        ]}>
                        {label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>

              <LogsTab
                key={logDirection}
                incoming={logDirection === 'incoming'}
                items={activeLogs.items}
                loading={activeLogs.loading}
                refreshing={activeLogs.refreshing}
                loadingMore={activeLogs.loadingMore}
                error={activeLogs.error}
                hasMore={activeLogs.hasMore}
                onRefresh={activeLogs.refresh}
                onLoadMore={activeLogs.loadMore}
                onOpenDetail={activeLogs.fetchDetail}
                onItemUpdated={activeLogs.updateItem}
                colors={colors}
                isDark={isDark}
              />
            </View>
          ) : null}
        </View>
      )}
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    direction: 'rtl',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingBottom: 10,
  },
  headerStart: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    lineHeight: 30,
  },
  logoRing: {
    width: 42,
    height: 42,
    borderRadius: 21,
    borderWidth: 2,
    padding: 2,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoImg: {
    width: '100%',
    height: '100%',
    borderRadius: 19,
  },
  body: {
    flex: 1,
    paddingHorizontal: 16,
  },
  flex: {
    flex: 1,
  },
  hidden: {
    display: 'none',
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  gateCard: {
    width: '100%',
    maxWidth: 420,
    borderRadius: 24,
    borderWidth: 1,
    padding: 24,
    alignItems: 'center',
  },
  gateIconWrap: {
    width: 82,
    height: 82,
    borderRadius: 41,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  gateTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    textAlign: 'center',
    marginBottom: 8,
  },
  gateText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 20,
  },
  logSwitchWrap: {
    flexDirection: 'row',
    borderRadius: 18,
    borderWidth: 1,
    padding: 4,
    marginBottom: 12,
    gap: 4,
  },
  logSwitchItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  logSwitchItemActive: {
    borderWidth: 1,
  },
  logSwitchLabel: {
    fontSize: 14,
    lineHeight: 20,
  },
});
